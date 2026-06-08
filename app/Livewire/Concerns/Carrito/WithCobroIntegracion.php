<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Caja;
use App\Models\FormaPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\Sucursal;
use App\Services\IntegracionesPago\CobroIntegracionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Maquinaria de cobro por integración (QR presencial — Fase 5), compartida por
 * TODOS los flujos que cobran: NuevaVenta y NuevoPedidoMostrador (vía
 * WithPagosDesglose) y el listado de Pedidos Mostrador (pagos planificados).
 *
 * Es el ÚNICO lugar donde vive el cobro por QR: iniciar el cobro contra el
 * gateway, renderizar el QR, pollear el estado, cancelar y asociar la
 * transacción al cobrable. Cualquier cambio a la lógica de cobro por
 * integración debe hacerse acá para que impacte en todos los puntos de cobro.
 *
 * Desacople del host: el cobro nace SIN cobrable (modelo "cobro primero,
 * cobrable después"). Al aprobarse, este concern invoca el hook
 * `alConfirmarCobroIntegracion()` que cada host implementa para materializar lo
 * suyo (crear la venta, cobrar el pedido, materializar el pago planificado) y
 * luego asociar la transacción vía `asociarCobroIntegracionAlCobrable()`.
 *
 * Requiere del host: nada obligatorio. Los datos del cobro (forma_pago_id,
 * monto, sucursal_id, caja_id, moneda_id) se pasan explícitamente a
 * `iniciarCobroIntegracion()`, así el concern no depende de props del host.
 */
trait WithCobroIntegracion
{
    /** @var bool Modal "esperando pago" visible (QR mostrado, polling activo) */
    public bool $mostrarModalEsperandoPago = false;

    /** @var int|null Transacción de cobro en curso (IntegracionPagoTransaccion) */
    public ?int $cobroIntegracionTransaccionId = null;

    /** @var string|null Trama EMVCo del QR a renderizar en el front */
    public ?string $cobroIntegracionQrData = null;

    /** @var string|null SVG del QR ya renderizado (se genera una vez al iniciar el cobro) */
    public ?string $cobroIntegracionQrSvg = null;

    /**
     * @var string|null URL de la imagen del QR impreso del POS (modo estático).
     *                  En estático no hay trama EMVCo para renderizar: se muestra
     *                  directamente la imagen del QR físico de la caja.
     */
    public ?string $cobroIntegracionQrImagenUrl = null;

    /**
     * @var string|null Modo del cobro en curso (qr_dinamico|qr_estatico|qr_libre).
     *                  El modal lo usa para adaptar la UI (qr_libre = imagen +
     *                  confirmación manual primaria, sin detección automática).
     */
    public ?string $cobroIntegracionModo = null;

    /** @var float Monto del cobro por integración en curso */
    public float $cobroIntegracionMonto = 0;

    /** @var int|null Epoch (segundos) en que expira el cobro, para el countdown */
    public ?int $cobroIntegracionExpiraTs = null;

    /**
     * @var bool Pago por integración ya confirmado. Lo lee el enganche del flujo
     *           de cobro para proceder a materializar el cobrable.
     */
    public bool $cobroIntegracionConfirmado = false;

    /**
     * Si la caja del puesto tiene habilitada la pantalla orientada al cliente
     * (segundo monitor). Lo consumen el botón de conexión y el modal de cobro
     * para decidir si mandar el QR al monitor del cliente. La caja se resuelve
     * con `cajaIdParaPantallaCliente()` (overridable por el host).
     */
    #[Computed]
    public function usaPantallaClienteActiva(): bool
    {
        $cajaId = $this->cajaIdParaPantallaCliente();

        return (bool) (Caja::find($cajaId)?->usa_pantalla_cliente ?? false);
    }

    /**
     * Personalización de la 2da pantalla (config de la sucursal de la caja del
     * puesto) lista para enviar por BroadcastChannel: incluye los valores de
     * `getConfigPantallaCliente()` más el logo (URL absoluta) y el nombre a
     * mostrar. La consume `_boton-pantalla-cliente.blade.php` para inyectarla en
     * el host JS. Devuelve [] si no hay caja/sucursal resoluble.
     */
    #[Computed]
    public function configPantallaCliente(): array
    {
        $caja = Caja::find($this->cajaIdParaPantallaCliente());
        $sucursal = $caja?->sucursal_id ? Sucursal::find($caja->sucursal_id) : null;

        if (! $sucursal) {
            return [];
        }

        return array_merge($sucursal->getConfigPantallaCliente(), [
            'logo_url' => $sucursal->logoPantallaClienteUrl(),
            'nombre' => $sucursal->nombrePantallaCliente(),
        ]);
    }

    /**
     * Caja física del puesto del cajero para la pantalla cliente. Default: la
     * caja activa de la sesión. Hosts con una caja seleccionada propia (p. ej.
     * NuevaVenta/NuevoPedidoMostrador) lo overridean.
     */
    protected function cajaIdParaPantallaCliente(): ?int
    {
        return caja_activa();
    }

    /**
     * Si el usuario puede confirmar manualmente un cobro pendiente (RF-12). Lo
     * consume el modal de espera para mostrar/ocultar el fallback "el cliente
     * pagó" cuando el sistema no detecta el pago automáticamente.
     */
    #[Computed]
    public function puedeConfirmarManual(): bool
    {
        return $this->tienePermisoConfirmarManual();
    }

    /**
     * Lógica del permiso de confirmación manual, usable desde acciones (no solo
     * desde la computed, que requiere el ciclo de vida de Livewire montado).
     */
    protected function tienePermisoConfirmarManual(): bool
    {
        return Auth::user()?->hasPermissionTo('integraciones_pago.confirmar_manual') ?? false;
    }

    /**
     * Inicia el cobro QR: resuelve la integración de la forma de pago y la
     * config de la sucursal, pide el QR al gateway vía CobroIntegracionService y
     * abre el modal de espera. La transacción nace sin cobrable (se asocia al
     * confirmar). Todos los datos se pasan explícitos en `$datos`:
     *  - forma_pago_id (int, requerido)
     *  - monto (float) | monto_final (float, fallback)
     *  - sucursal_id (int, requerido)
     *  - caja_id (int|null)
     *  - moneda_id (int|null)
     */
    public function iniciarCobroIntegracion(array $datos): void
    {
        $formaPago = FormaPago::find($datos['forma_pago_id'] ?? null);
        $integracion = $formaPago?->integracionPrincipal();

        if (! $integracion) {
            $this->dispatch('toast-error', message: __('La forma de pago no tiene una integración asignada'));

            return;
        }

        $sucursalId = $datos['sucursal_id'] ?? null;
        $modo = $integracion->pivot->modo_default ?? 'qr_dinamico';
        $esQrLibre = $modo === IntegracionPagoTransaccion::MODO_QR_LIBRE;

        $config = IntegracionPagoSucursal::query()
            ->where('integracion_pago_id', $integracion->id)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->first();

        // qr_libre no empuja nada a MP: no exige Access Token cargado, solo que la
        // integración exista/activa en la sucursal. Los demás modos sí requieren
        // credenciales configuradas.
        if (! $config || (! $esQrLibre && ! $config->estaConfigurada())) {
            $this->dispatch('toast-error', message: __('La integración de pago no está configurada en esta sucursal'));

            return;
        }

        // qr_libre: la imagen del QR "Cobrar" vive en el pivote (config_qr_libre).
        // Se pasa al gateway vía metadata; sin imagen no se puede cobrar.
        $metadata = null;
        if ($esQrLibre) {
            $imagenPath = data_get(json_decode($integracion->pivot->config_qr_libre ?? 'null', true), 'imagen_path');

            if (empty($imagenPath)) {
                $this->dispatch('toast-error', message: __('Configurá la imagen del QR de Mercado Pago en la forma de pago antes de cobrar con QR de monto libre.'));

                return;
            }

            // URL root-relativa derivada del path (portable entre hosts/puertos).
            $metadata = ['qr_libre_imagen_url' => \App\Services\IntegracionesPago\ImagenQrLibreService::urlPublica($imagenPath)];
        }

        try {
            $transaccion = app(CobroIntegracionService::class)->iniciarCobro($config, [
                'forma_pago_id' => $formaPago->id,
                'sucursal_id' => $sucursalId,
                'caja_id' => $datos['caja_id'] ?? caja_activa(),
                'usuario_iniciador_id' => Auth::id(),
                'modo_usado' => $modo,
                'monto' => (float) ($datos['monto'] ?? $datos['monto_final'] ?? 0),
                'moneda_id' => $datos['moneda_id'] ?? null,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('toast-error', message: $e->getMessage());

            return;
        }

        $this->cobroIntegracionTransaccionId = $transaccion->id;
        $this->cobroIntegracionModo = $transaccion->modo_usado;
        $this->cobroIntegracionQrData = $transaccion->qr_data;
        $this->cobroIntegracionQrSvg = $this->renderizarQrSvg($transaccion->qr_data);
        // Modo estático/qr_libre: sin trama EMVCo; se muestra la imagen del QR
        // (impreso del POS en estático, "Cobrar" subido en qr_libre).
        $this->cobroIntegracionQrImagenUrl = $transaccion->metadata['qr_image_url'] ?? null;
        $this->cobroIntegracionMonto = (float) $transaccion->monto;
        $this->cobroIntegracionExpiraTs = $transaccion->expira_en?->timestamp;
        $this->cobroIntegracionConfirmado = false;
        $this->mostrarModalEsperandoPago = true;
    }

    /**
     * Renderiza la trama EMVCo del QR como SVG (PHP puro, sin imagick/gd).
     * Se genera una vez al iniciar el cobro y se guarda en una prop para
     * sobrevivir a los morphs de Livewire del wire:poll sin reconstruirse.
     */
    protected function renderizarQrSvg(?string $qrData): ?string
    {
        if (empty($qrData)) {
            return null;
        }

        $svg = QrCode::format('svg')
            ->size(240)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($qrData);

        // Quitar el prólogo XML (la declaración inicial de tipo xml): al embeber
        // el SVG inline en HTML puede provocar quirks de render en algunos navegadores.
        return trim(preg_replace('/^<\?xml.*?\?>\s*/s', '', (string) $svg));
    }

    /**
     * Polling del estado del cobro (wire:poll desde el modal de espera).
     * Consulta al proveedor; al aprobar marca la transacción y delega en el host
     * (alConfirmarCobroIntegracion) para que materialice lo suyo. Reverb
     * reemplazará este polling en Fase 6.
     */
    public function pollearCobroIntegracion(): void
    {
        if (! $this->cobroIntegracionTransaccionId) {
            return;
        }

        $transaccion = IntegracionPagoTransaccion::find($this->cobroIntegracionTransaccionId);
        if (! $transaccion) {
            $this->resetCobroIntegracion();

            return;
        }

        // Reaccionar primero al estado LOCAL de la transacción: el webhook (Fase 6)
        // la confirma server-side y el job de expiración (Fase 8) la vence — en
        // ambos casos el estado ya quedó resuelto en la DB y no hace falta (ni
        // conviene) re-consultar al proveedor.
        if ($transaccion->estaConfirmada()) {
            $this->cobroIntegracionConfirmado = true;
            $this->mostrarModalEsperandoPago = false;

            // Guard de reentrada: si la transacción ya tiene cobrable, otro flujo
            // (otra pestaña, webhook + este polling casi simultáneos) ya materializó
            // la venta/pedido. NO volver a materializar para no duplicar.
            if ($transaccion->cobrable_id !== null) {
                $this->resetCobroIntegracion();

                return;
            }

            $this->alConfirmarCobroIntegracion();

            return;
        }

        if ($transaccion->estaEnEstadoTerminal()) {
            // expirado / cancelado / fallido / sin_match.
            $this->dispatch('toast-error', message: __('El pago no se completó (:estado)', ['estado' => $transaccion->estado]));
            $this->resetCobroIntegracion();
            $this->dispatch('cobro-integracion-no-confirmado');
            $this->alCancelarCobroIntegracion();

            return;
        }

        // qr_libre: no hay order en MP que consultar. La confirmación es siempre
        // manual; mientras siga pendiente, el poll no tiene nada que hacer (los
        // estados terminal/confirmado ya se resolvieron arriba con el estado local).
        if ($transaccion->modo_usado === IntegracionPagoTransaccion::MODO_QR_LIBRE) {
            return;
        }

        $service = app(CobroIntegracionService::class);

        try {
            $estado = $service->consultarEstado($transaccion);
        } catch (\Throwable $e) {
            // Error transitorio de red: seguir esperando, no abortar el cobro.
            return;
        }

        if ($estado === 'aprobado') {
            // Registrar la confirmación en el momento del pago (aún sin cobrable:
            // el cobrable se materializa a continuación y se asocia ahí).
            $service->confirmarCobro($transaccion);
            $this->cobroIntegracionConfirmado = true;
            $this->mostrarModalEsperandoPago = false;

            // Delegar en el host la materialización del cobrable (crear venta,
            // cobrar pedido, materializar pago planificado). El host asocia la
            // transacción al cobrable vía asociarCobroIntegracionAlCobrable().
            $this->alConfirmarCobroIntegracion();

            return;
        }

        if (in_array($estado, ['cancelado', 'expirado', 'fallido'], true)) {
            $this->dispatch('toast-error', message: __('El pago no se completó (:estado)', ['estado' => $estado]));
            $this->resetCobroIntegracion();
            $this->dispatch('cobro-integracion-no-confirmado');
            $this->alCancelarCobroIntegracion();
        }
        // 'pendiente' → seguir esperando (no-op).
    }

    /**
     * Confirma manualmente el cobro en curso (RF-12): fallback cuando el sistema
     * no detectó el pago automáticamente y el cajero —con permiso— verificó que
     * el cliente pagó. Marca la transacción `confirmado_manual` (auditado con el
     * usuario) y delega en el host la materialización del cobrable, igual que el
     * camino automático.
     */
    public function confirmarCobroIntegracionManual(): void
    {
        if (! $this->cobroIntegracionTransaccionId) {
            return;
        }

        if (! $this->tienePermisoConfirmarManual()) {
            $this->dispatch('toast-error', message: __('No tenés permiso para confirmar pagos manualmente'));

            return;
        }

        $transaccion = IntegracionPagoTransaccion::find($this->cobroIntegracionTransaccionId);
        if (! $transaccion) {
            $this->resetCobroIntegracion();

            return;
        }

        app(CobroIntegracionService::class)->confirmarManual($transaccion, Auth::id());

        $this->cobroIntegracionConfirmado = true;
        $this->mostrarModalEsperandoPago = false;
        $this->alConfirmarCobroIntegracion();
    }

    /**
     * Cancela el cobro en curso (botón del modal): avisa al proveedor, marca
     * la transacción y cierra el modal. No se materializa ningún cobrable.
     */
    public function cancelarCobroIntegracion(): void
    {
        if ($this->cobroIntegracionTransaccionId) {
            $transaccion = IntegracionPagoTransaccion::find($this->cobroIntegracionTransaccionId);
            if ($transaccion) {
                app(CobroIntegracionService::class)->cancelarCobro($transaccion);
            }
        }

        $this->resetCobroIntegracion();
        $this->dispatch('cobro-integracion-no-confirmado');
        $this->alCancelarCobroIntegracion();
    }

    /**
     * Limpia el estado del cobro por integración.
     */
    protected function resetCobroIntegracion(): void
    {
        $this->mostrarModalEsperandoPago = false;
        $this->cobroIntegracionTransaccionId = null;
        $this->cobroIntegracionModo = null;
        $this->cobroIntegracionQrData = null;
        $this->cobroIntegracionQrSvg = null;
        $this->cobroIntegracionQrImagenUrl = null;
        $this->cobroIntegracionMonto = 0;
        $this->cobroIntegracionExpiraTs = null;
        $this->cobroIntegracionConfirmado = false;
    }

    /**
     * Asocia la transacción de cobro QR confirmada al cobrable (Venta o
     * PedidoMostrador). Lo llaman los hosts después de persistir/materializar el
     * cobrable. No-op si no hubo cobro por integración.
     */
    protected function asociarCobroIntegracionAlCobrable(Model $cobrable): void
    {
        if (! $this->cobroIntegracionConfirmado || ! $this->cobroIntegracionTransaccionId) {
            return;
        }

        $transaccion = IntegracionPagoTransaccion::find($this->cobroIntegracionTransaccionId);
        if ($transaccion) {
            app(CobroIntegracionService::class)->asociarCobrable($transaccion, $cobrable);
        }
    }

    /**
     * Hook que el host implementa para materializar su cobrable cuando el pago
     * QR se aprueba. Default no-op (el host puede no necesitarlo). Se invoca con
     * `cobroIntegracionConfirmado=true` y el modal de espera ya cerrado.
     */
    protected function alConfirmarCobroIntegracion(): void
    {
        // Cada host overridea: NuevaVenta/NuevoPedidoMostrador →
        // verificarPuntoVentaYProcesar(); PedidosMostrador → materializar el
        // pago planificado.
    }

    /**
     * Hook que el host implementa para reaccionar cuando el cobro QR se cancela
     * o expira (no se materializó nada). Default no-op. Se invoca de forma
     * sincrónica tras resetear el estado, así el host puede reabrir su modal
     * para reintentar/editar sin depender del round-trip del evento.
     */
    protected function alCancelarCobroIntegracion(): void
    {
        // NuevoPedidoMostrador → reabrir el desglose; PedidosMostrador →
        // reabrir "Cobrar pendiente".
    }
}

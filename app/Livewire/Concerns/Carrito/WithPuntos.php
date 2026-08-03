<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Cliente;
use App\Models\FormaPago;
use App\Models\FormaPagoSucursal;
use App\Models\VentaPago;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;

/**
 * Programa de puntos en NuevaVenta.
 *
 * Encapsula:
 * - Carga de saldo de puntos al seleccionar cliente.
 * - Canje de puntos como pago (monto $ desde saldo).
 * - Canje de articulos pagados con puntos (RF-25).
 * - Calculos auxiliares: valor del punto, puntos libres, maximo canjeable real,
 *   puntos por precio, puntos usados en articulos.
 * - Acumulacion de puntos post-venta (por venta confirmada).
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->items                  (WithCarritoItems)
 * - $this->resultado              (NuevaVenta)
 * - $this->clienteSeleccionado    (WithBusquedaClientes)
 * - $this->puntosService          (NuevaVenta — inyectado en boot())
 * - $this->sucursalId             (SucursalAware)
 * - $this->calcularVenta()        (NuevaVenta — ira a WithCalculoVenta)
 */
trait WithPuntos
{
    // =========================================
    // PROPIEDADES DE CANJE DE PUNTOS
    // =========================================

    /** @var bool Si el programa de puntos está activo para esta venta */
    public bool $puntosDisponibles = false;

    /** @var int Saldo de puntos del cliente seleccionado */
    public int $puntosSaldoCliente = 0;

    /** @var bool Si hay un canje de puntos como pago activo */
    public bool $canjePuntosActivo = false;

    /** @var float|null Monto $ que el cliente quiere pagar con puntos */
    public ?float $canjePuntosMonto = null;

    /** @var int Puntos que se consumirán con el canje */
    public int $canjePuntosUnidades = 0;

    /** @var float Valor máximo canjeable en $ según saldo */
    public float $canjePuntosMaximo = 0;

    /** @var int Mínimo de puntos para habilitar canje */
    public int $puntosMinimoCanje = 0;

    /** @var float|null Input temporal en el modal */
    public ?float $canjePuntosInputMonto = null;

    // =========================================
    // CARGA DE SALDO Y CANJE COMO PAGO
    // =========================================

    /**
     * Carga saldo de puntos al seleccionar un cliente (RF-23).
     */
    protected function cargarSaldoPuntosCliente(?Cliente $cliente = null): void
    {
        $this->puntosDisponibles = false;
        $this->puntosSaldoCliente = 0;
        $this->canjePuntosMaximo = 0;
        $this->puntosMinimoCanje = 0;

        if (! $cliente || ! $cliente->programa_puntos_activo) {
            return;
        }

        if (! $this->puntosService->isProgramaActivo($this->sucursalId)) {
            return;
        }

        $config = $this->puntosService->getConfiguracion();
        if (! $config) {
            return;
        }

        $sucursalIdParaSaldo = $config->esPorSucursal() ? $this->sucursalId : null;
        $saldo = $this->puntosService->obtenerSaldo($cliente->id, $sucursalIdParaSaldo);

        $this->puntosSaldoCliente = $saldo;
        $this->puntosMinimoCanje = $config->minimo_canje;
        $this->puntosDisponibles = $saldo >= $config->minimo_canje;

        // Calcular máximo canjeable en $
        if ($this->puntosDisponibles && $config->valor_punto_canje > 0) {
            $this->canjePuntosMaximo = round($saldo * (float) $config->valor_punto_canje, 2);
        }
    }

    /**
     * Aplica canje de puntos como pago (RF-24).
     */
    public function aplicarCanjePuntos(): void
    {
        $monto = $this->canjePuntosInputMonto;

        if ($monto === null || $monto <= 0) {
            $this->dispatch('toast-error', message: __('Ingrese un monto mayor a cero'));

            return;
        }

        $monto = (float) $monto;

        if (! $this->clienteSeleccionado || ! $this->puntosDisponibles) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes'));

            return;
        }

        // Calcular máximo real (descontando artículos canjeados)
        $maximoCanjeable = $this->canjePuntosMaximoReal;

        // RF-T58: con la restricción activa, el canje-pago solo cubre los
        // renglones habilitados (lo no habilitado se paga con plata).
        $topeElegible = $this->montoElegibleCanjePago();
        if ($topeElegible !== null) {
            $maximoCanjeable = min($maximoCanjeable, $topeElegible);
        }

        if ($maximoCanjeable <= 0) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes'));

            return;
        }

        // Limitar al máximo canjeable con puntos libres
        if ($monto > $maximoCanjeable) {
            $monto = $maximoCanjeable;
        }

        // Limitar al total de la venta
        $totalVenta = $this->resultado['total_final'] ?? 0;
        if ($monto > $totalVenta) {
            $monto = $totalVenta;
        }

        $config = $this->puntosService->getConfiguracion();
        if (! $config || $config->valor_punto_canje <= 0) {
            $this->dispatch('toast-error', message: __('Programa de puntos no configurado'));

            return;
        }

        $puntosNecesarios = (int) ceil($monto / (float) $config->valor_punto_canje);
        $puntosLibres = max(0, $this->puntosSaldoCliente - $this->calcularPuntosUsadosEnArticulos());

        if ($puntosNecesarios > $puntosLibres) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes').". {$puntosNecesarios} pts necesarios, {$puntosLibres} pts disponibles");

            return;
        }

        $this->canjePuntosActivo = true;
        $this->canjePuntosMonto = round($monto, 2);
        $this->canjePuntosUnidades = $puntosNecesarios;

        $this->calcularVenta();

        $this->dispatch('toast-success', message: __('Canje de puntos aplicado').": \${$this->canjePuntosMonto} ({$puntosNecesarios} pts)");
    }

    /**
     * Quita el canje de puntos.
     */
    public function quitarCanjePuntos(): void
    {
        $this->canjePuntosActivo = false;
        $this->canjePuntosMonto = null;
        $this->canjePuntosUnidades = 0;
        $this->canjePuntosInputMonto = null;

        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Canje de puntos eliminado'));
    }

    // =========================================
    // CANJE DE ARTICULOS POR PUNTOS (RF-25)
    // =========================================

    /**
     * Canjea un artículo del carrito por puntos (RF-10, RF-25).
     * El artículo se marca como pagado_con_puntos y se descuenta del total.
     */
    public function canjearArticuloConPuntos(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        if (! $this->clienteSeleccionado || ! $this->puntosDisponibles) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes'));

            return;
        }

        $item = $this->items[$index];

        // RF-T58: con la restricción del programa activa, solo los artículos
        // habilitados (canje_tienda de esta sucursal) se canjean.
        if (! $this->articuloCanjeableConPuntos($item)) {
            $this->dispatch('toast-error', message: __('Este artículo no es canjeable con puntos'));

            return;
        }

        // RF-T59: costo según la matriz (fijo/derivado × modo de opcionales).
        $costo = $this->costoCanjeItem($item);
        if ($costo === null) {
            $this->dispatch('toast-error', message: __('Configuración de puntos incompleta'));

            return;
        }

        $cantidad = (float) ($item['cantidad'] ?? 1);
        $puntosTotal = (int) ($costo['puntos'] * $cantidad);

        // Verificar saldo disponible (descontando artículos canjeados + canje como descuento)
        $puntosYaUsados = $this->calcularPuntosUsadosEnArticulos() + $this->canjePuntosUnidades;
        $puntosLibres = $this->puntosSaldoCliente - $puntosYaUsados;

        if ($puntosTotal > $puntosLibres) {
            $this->dispatch('toast-error', message: __('Puntos insuficientes').". {$puntosTotal} pts necesarios, {$puntosLibres} pts disponibles");

            return;
        }

        $this->items[$index]['pagado_con_puntos'] = true;
        $this->calcularVenta();

        $this->dispatch('toast-success', message: __('Canjeado con puntos').": {$item['nombre']} ({$puntosTotal} pts)");
    }

    /**
     * Quita el canje por puntos de un artículo del carrito.
     */
    public function quitarCanjeArticulo(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['pagado_con_puntos'] = false;
        $this->calcularVenta();
        $this->dispatch('toast-info', message: __('Canje de artículo eliminado'));
    }

    // =========================================
    // COMPUTED Y AUXILIARES
    // =========================================

    /**
     * Obtiene el valor de 1 punto en $ desde la configuración (no serializado por Livewire).
     */
    #[Computed]
    public function valorPuntoCanje(): float
    {
        $config = $this->puntosService->getConfiguracion();

        return $config ? (float) $config->valor_punto_canje : 0;
    }

    /**
     * Puntos libres del cliente (saldo - artículos canjeados - canje como descuento).
     */
    #[Computed]
    public function puntosLibres(): int
    {
        return max(0, $this->puntosSaldoCliente - $this->calcularPuntosUsadosEnArticulos() - $this->canjePuntosUnidades);
    }

    /**
     * Máximo canjeable en $ considerando puntos ya usados en artículos.
     */
    #[Computed]
    public function canjePuntosMaximoReal(): float
    {
        $puntosLibres = max(0, $this->puntosSaldoCliente - $this->calcularPuntosUsadosEnArticulos());
        $valorPunto = $this->valorPuntoCanje;

        return $valorPunto > 0 ? round($puntosLibres * $valorPunto, 2) : 0;
    }

    /**
     * Calcula puntos necesarios para canjear un artículo desde su precio.
     */
    protected function calcularPuntosCanjePorPrecio(float $precio): int
    {
        $valorPunto = $this->valorPuntoCanje;
        if ($valorPunto <= 0) {
            return 0;
        }

        return (int) ceil($precio / $valorPunto);
    }

    /**
     * Calcula los puntos totales usados en artículos canjeados del carrito
     * (costo por la matriz RF-T59, ya no siempre derivado del precio).
     */
    protected function calcularPuntosUsadosEnArticulos(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            if ($item['pagado_con_puntos'] ?? false) {
                $costo = $this->costoCanjeItem($item);
                $total += (int) ((($costo['puntos'] ?? 0)) * (float) ($item['cantidad'] ?? 1));
            }
        }

        return $total;
    }

    /**
     * RF-T59: costo POR UNIDAD del canje de un item del carrito según la
     * matriz costo (fijo/derivado) × modo de opcionales del artículo. El
     * `precio` del item INCLUYE los opcionales — acá se desagrega.
     *
     * @return array{puntos: int, monto_canjeado: float}|null null = no resoluble (derivar sin valor de canje)
     */
    protected function costoCanjeItem(array $item): ?array
    {
        $precioOpcionales = (float) ($item['precio_opcionales'] ?? 0);

        return $this->puntosService->costoCanjeArticulo(
            (int) ($item['puntos_canje'] ?? 0) ?: null,
            $item['canje_opcionales'] ?? null,
            (float) ($item['precio'] ?? 0) - $precioOpcionales,
            $precioOpcionales,
            $this->valorPuntoCanje ?: null,
        );
    }

    /**
     * RF-T58: elegibilidad del item para canje. Sin la restricción del
     * programa todo se canjea (comportamiento histórico); con ella, solo
     * artículos con canje_tienda prendido en ESTA sucursal (conceptos nunca).
     */
    protected function articuloCanjeableConPuntos(array $item): bool
    {
        if (! $this->puntosService->restringeCanjeArticulos()) {
            return true;
        }

        if (($item['es_concepto'] ?? false) || empty($item['articulo_id'])) {
            return false;
        }

        return in_array((int) $item['articulo_id'], $this->idsArticulosCanjeables(), true);
    }

    /** @var list<int>|null Cache por request de los IDs canjeables de la sucursal (RF-T58). */
    protected ?array $idsCanjeablesCache = null;

    /** @return list<int> */
    protected function idsArticulosCanjeables(): array
    {
        return $this->idsCanjeablesCache ??= DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('sucursal_id', (int) $this->sucursalId)
            ->where('canje_tienda', true)
            ->pluck('articulo_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * RF-T58: tope en $ del canje-pago cuando la restricción está activa —
     * suma de los renglones ELEGIBLES no canjeados (los no habilitados y el
     * envío se pagan con plata). Null = sin restricción (tope = el total,
     * como siempre).
     */
    protected function montoElegibleCanjePago(): ?float
    {
        if (! $this->puntosService->restringeCanjeArticulos()) {
            return null;
        }

        $total = 0.0;
        foreach ($this->items as $item) {
            if (($item['pagado_con_puntos'] ?? false) || ! $this->articuloCanjeableConPuntos($item)) {
                continue;
            }
            $total += (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
        }

        return round($total, 2);
    }

    // =========================================
    // ACUMULACION POST-VENTA
    // =========================================

    /**
     * Acumula puntos de fidelización después de completar la venta (RF-05).
     * Se ejecuta post-commit — si falla, la venta ya se creó.
     */
    protected function acumularPuntosPostVenta($venta): void
    {
        if (! $venta->cliente_id) {
            return;
        }

        try {
            // Obtener los pagos reales de la venta con su multiplicador (sucursal > genérico)
            $sucursalId = $venta->sucursal_id;
            $pagos = VentaPago::where('venta_id', $venta->id)
                ->get()
                ->map(function ($pago) use ($sucursalId) {
                    $fp = FormaPago::find($pago->forma_pago_id);
                    $multiplicador = (float) ($fp->multiplicador_puntos ?? 1.00);

                    // Override por sucursal si existe
                    if ($sucursalId && $fp) {
                        $fpSucursal = FormaPagoSucursal::where('forma_pago_id', $fp->id)
                            ->where('sucursal_id', $sucursalId)
                            ->first();
                        if ($fpSucursal && $fpSucursal->multiplicador_puntos !== null) {
                            $multiplicador = (float) $fpSucursal->multiplicador_puntos;
                        }
                    }

                    return [
                        'monto_final' => (float) $pago->monto_final,
                        'es_pago_puntos' => (bool) $pago->es_pago_puntos,
                        'es_cuenta_corriente' => (bool) $pago->es_cuenta_corriente,
                        'multiplicador_puntos' => $multiplicador,
                    ];
                });

            $this->puntosService->acumularPuntosPorVenta($venta, $pagos, Auth::id());
        } catch (Exception $e) {
            Log::warning('Error al acumular puntos post-venta', [
                'venta_id' => $venta->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

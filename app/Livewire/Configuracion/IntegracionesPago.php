<?php

namespace App\Livewire\Configuracion;

use App\Models\Caja;
use App\Models\Comercio;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\Localidad;
use App\Models\Sucursal;
use App\Services\CatalogoCache;
use App\Services\IntegracionesPago\IntegracionPagoSucursalService;
use App\Services\IntegracionesPago\SincronizacionMercadoPagoService;
use App\Traits\ManejaDomicilio;
use App\Traits\SucursalAware;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Pantalla de gestión de integraciones de pago para la sucursal activa.
 *
 * Es SucursalAware: cada cajero/encargado configura su propia sucursal.
 * La gestión multi-sucursal (todas las sucursales en un solo lugar) se hará
 * desde un componente futuro en el panel de Manager.
 *
 * Permiso requerido: `func.integraciones_pago.administrar`.
 */
#[Lazy]
#[Layout('layouts.app')]
class IntegracionesPago extends Component
{
    use ManejaDomicilio;
    use SucursalAware;

    // ==================== Modal ====================

    public bool $mostrarModal = false;

    public bool $editMode = false;

    public ?int $configId = null;

    public ?int $integracionPagoId = null;

    public ?int $sucursalId = null;

    // ==================== Campos del formulario ====================

    public string $modo = IntegracionPagoSucursal::MODO_TEST;

    public ?string $access_token_produccion = null;

    public ?string $access_token_test = null;

    public ?string $public_key_produccion = null;

    public ?string $public_key_test = null;

    public ?string $user_id_externo = null;

    public ?string $webhook_secret = null;

    public int $timeout_segundos = 300;

    public bool $activo = true;

    // ==================== Modal: dirección de sucursal ====================
    // Usa el trait ManejaDomicilio (mismo picker de Google Maps que Sucursales):
    // provincia (ISO) → localidad (catálogo) → mapa. Ver domicilio-form.blade.php.

    public bool $mostrarModalDireccion = false;

    // ==================== Terminales Point ====================

    /** @var array<int, array<string, mixed>> Terminales devueltas por MP al buscar. */
    public array $terminalesDisponibles = [];

    /** @var array<int, string> Terminal elegida por caja (cajaId => terminal_id). */
    public array $terminalSeleccionado = [];

    // ==================== Lifecycle ====================

    public function mount(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            abort(403, __('No tiene permiso para administrar integraciones de pago'));
        }
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="0" :columns="3" :rows="4" />
        HTML;
    }

    // ==================== Validación ====================

    protected function rules(): array
    {
        return [
            'modo' => 'required|in:test,produccion',
            'access_token_produccion' => 'nullable|string|max:500',
            'access_token_test' => 'nullable|string|max:500',
            'public_key_produccion' => 'nullable|string|max:255',
            'public_key_test' => 'nullable|string|max:255',
            'user_id_externo' => 'nullable|string|max:100',
            'webhook_secret' => 'nullable|string|max:500',
            'timeout_segundos' => 'required|integer|min:30|max:3600',
            'activo' => 'boolean',
        ];
    }

    protected function messages(): array
    {
        return [
            'modo.required' => __('Seleccione el modo (test o producción)'),
            'timeout_segundos.required' => __('Ingrese el timeout en segundos'),
            'timeout_segundos.min' => __('El timeout mínimo es 30 segundos'),
            'timeout_segundos.max' => __('El timeout máximo es 3600 segundos'),
        ];
    }

    // ==================== Acciones ====================

    public function abrirConfig(int $integracionPagoId): void
    {
        $this->resetForm();
        $this->resetValidation();

        $this->integracionPagoId = $integracionPagoId;
        $this->sucursalId = $this->sucursalActual();

        $existente = IntegracionPagoSucursal::where('integracion_pago_id', $integracionPagoId)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        if ($existente) {
            $this->editMode = true;
            $this->configId = $existente->id;
            $this->modo = $existente->modo;
            $this->access_token_produccion = $existente->access_token_produccion;
            $this->access_token_test = $existente->access_token_test;
            $this->public_key_produccion = $existente->public_key_produccion;
            $this->public_key_test = $existente->public_key_test;
            $this->user_id_externo = $existente->user_id_externo;
            $this->webhook_secret = $existente->webhook_secret;
            $this->timeout_segundos = $existente->timeout_segundos;
            $this->activo = $existente->activo;
        } else {
            $this->editMode = false;
        }

        $this->mostrarModal = true;
    }

    public function guardar(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        $this->validate();

        // Validación condicional: el access_token del modo activo es obligatorio.
        if ($this->modo === IntegracionPagoSucursal::MODO_PRODUCCION && empty($this->access_token_produccion)) {
            $this->addError('access_token_produccion', __('El Access Token de producción es obligatorio cuando el modo es producción'));

            return;
        }

        if ($this->modo === IntegracionPagoSucursal::MODO_TEST && empty($this->access_token_test)) {
            $this->addError('access_token_test', __('El Access Token de test es obligatorio cuando el modo es test'));

            return;
        }

        $data = [
            'integracion_pago_id' => $this->integracionPagoId,
            'sucursal_id' => $this->sucursalId,
            'modo' => $this->modo,
            'access_token_produccion' => $this->access_token_produccion,
            'access_token_test' => $this->access_token_test,
            'public_key_produccion' => $this->public_key_produccion,
            'public_key_test' => $this->public_key_test,
            'user_id_externo' => $this->user_id_externo,
            'webhook_secret' => $this->webhook_secret,
            'timeout_segundos' => $this->timeout_segundos,
            'activo' => $this->activo,
        ];

        try {
            if ($this->editMode && $this->configId) {
                $config = IntegracionPagoSucursal::findOrFail($this->configId);
                IntegracionPagoSucursalService::actualizar($config, $data);
                $this->dispatch('notify', message: __('Configuración actualizada correctamente'), type: 'success');
            } else {
                IntegracionPagoSucursalService::crear($data);
                $this->dispatch('notify', message: __('Configuración creada correctamente'), type: 'success');
            }

            $this->cerrarModal();
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: __('Error al guardar: ').$e->getMessage(), type: 'error');
        }
    }

    public function probarConexion(int $configId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::with('integracion')->findOrFail($configId);
            $gateway = $config->integracion->getGatewayInstance();
            $info = $gateway->probarConexion($config);

            $nickname = $info['nickname'] ?? $info['id'] ?? __('cuenta');
            $this->dispatch(
                'notify',
                message: __('Conexión OK con Mercado Pago').' — '.$nickname.' ('.ucfirst($config->modo).')',
                type: 'success'
            );
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function eliminar(int $configId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::findOrFail($configId);
            IntegracionPagoSucursalService::eliminar($config);

            $this->dispatch('notify', message: __('Configuración eliminada correctamente'), type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: __('Error al eliminar: ').$e->getMessage(), type: 'error');
        }
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    // ==================== Dirección + coordenadas de la sucursal ====================

    public function abrirModalDireccion(): void
    {
        $sucursal = $this->sucursalActivaModel();
        if (! $sucursal) {
            return;
        }

        $this->resetValidation();
        $this->setDomicilioDesde([
            'provincia' => $sucursal->provincia,
            'localidad_id' => $sucursal->localidad_id,
            'direccion' => $sucursal->direccion,
            'latitud' => $sucursal->latitud,
            'longitud' => $sucursal->longitud,
        ]);
        $this->mostrarModalDireccion = true;
    }

    public function guardarDireccion(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        // Mercado Pago exige dirección, localidad, provincia y coordenadas para
        // registrar la sucursal (store), así que reforzamos las reglas del trait
        // (que de base permiten localidad/coords vacías) haciéndolas obligatorias.
        $this->validate(array_merge(
            $this->reglasDomicilio(direccionRequerida: true),
            [
                'domLocalidadId' => 'required|integer',
                'domLatitud' => 'required|numeric|between:-90,90',
                'domLongitud' => 'required|numeric|between:-180,180',
            ]
        ), [
            'domProvincia.required' => __('Seleccione la provincia'),
            'domLocalidadId.required' => __('Seleccione la localidad'),
            'domDireccion.required' => __('Ingrese la dirección de la sucursal'),
            'domLatitud.required' => __('Ubique la sucursal en el mapa para obtener las coordenadas'),
            'domLongitud.required' => __('Ubique la sucursal en el mapa para obtener las coordenadas'),
        ]);

        $sucursal = $this->sucursalActivaModel();
        if (! $sucursal) {
            return;
        }

        $datos = $this->datosDomicilio();

        // Mercado Pago arma `city_name` desde el string `localidad`; lo poblamos
        // con el nombre de la localidad del catálogo para mantener el sync.
        $sucursal->update([
            'direccion' => $datos['direccion'],
            'provincia' => $datos['provincia'],
            'localidad_id' => $datos['localidad_id'],
            'localidad' => Localidad::find($datos['localidad_id'])?->nombre,
            'latitud' => $datos['latitud'],
            'longitud' => $datos['longitud'],
        ]);

        // La sucursal activa se lee de CatalogoCache; invalidar para reflejar el cambio.
        CatalogoCache::clear();

        $this->mostrarModalDireccion = false;
        $this->dispatch('notify', message: __('Dirección y coordenadas guardadas'), type: 'success');
    }

    public function cerrarModalDireccion(): void
    {
        $this->mostrarModalDireccion = false;
        $this->resetDomicilio();
        $this->resetValidation();
    }

    // ==================== Sincronización con Mercado Pago ====================

    public function sincronizarSucursal(int $configId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::with('integracion')->findOrFail($configId);
            $sucursal = $this->sucursalActivaModel();
            $comercioId = (int) session('comercio_activo_id');

            if (! $sucursal) {
                $this->dispatch('notify', message: __('No hay sucursal activa'), type: 'error');

                return;
            }

            SincronizacionMercadoPagoService::sincronizarSucursal($config, $sucursal, $comercioId);

            $this->dispatch('notify', message: __('Sucursal sincronizada con Mercado Pago'), type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function sincronizarCaja(int $configId, int $cajaId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::with('integracion')->findOrFail($configId);
            $sucursal = $this->sucursalActivaModel();
            $caja = Caja::where('sucursal_id', $sucursal?->id)->findOrFail($cajaId);
            $comercioId = (int) session('comercio_activo_id');
            $comercio = Comercio::find($comercioId);

            SincronizacionMercadoPagoService::sincronizarCaja(
                $config,
                $caja,
                $sucursal,
                $comercio?->rubro,
                $comercioId
            );

            $this->dispatch('notify', message: __('Caja sincronizada con Mercado Pago'), type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    // ==================== Terminales Point ====================

    public function buscarTerminales(int $configId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::with('integracion')->findOrFail($configId);
            $this->terminalesDisponibles = SincronizacionMercadoPagoService::listarTerminales($config);

            if (empty($this->terminalesDisponibles)) {
                $this->dispatch('notify', message: __('No se encontraron terminales Point en la cuenta'), type: 'info');
            }
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function vincularTerminal(int $configId, int $cajaId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        $terminalId = $this->terminalSeleccionado[$cajaId] ?? null;
        if (empty($terminalId)) {
            $this->dispatch('notify', message: __('Elegí una terminal para vincular'), type: 'error');

            return;
        }

        try {
            $config = IntegracionPagoSucursal::with('integracion')->findOrFail($configId);
            $sucursal = $this->sucursalActivaModel();
            $caja = Caja::where('sucursal_id', $sucursal?->id)->findOrFail($cajaId);

            SincronizacionMercadoPagoService::vincularTerminalCaja($config, $caja, $terminalId);

            $this->dispatch('notify', message: __('Terminal vinculada a la caja. Reiniciá el Point (con internet) para que tome el modo integrado.'), type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function desvincularTerminal(int $cajaId): void
    {
        if (! auth()->user()?->hasPermissionTo('func.integraciones_pago.administrar')) {
            $this->dispatch('notify', message: __('No tiene permiso para administrar integraciones de pago'), type: 'error');

            return;
        }

        try {
            $sucursal = $this->sucursalActivaModel();
            $caja = Caja::where('sucursal_id', $sucursal?->id)->findOrFail($cajaId);

            SincronizacionMercadoPagoService::desvincularTerminalCaja($caja);
            unset($this->terminalSeleccionado[$cajaId]);

            $this->dispatch('notify', message: __('Terminal desvinculada de la caja'), type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    private function resetForm(): void
    {
        $this->editMode = false;
        $this->configId = null;
        $this->integracionPagoId = null;
        $this->sucursalId = null;
        $this->modo = IntegracionPagoSucursal::MODO_TEST;
        $this->access_token_produccion = null;
        $this->access_token_test = null;
        $this->public_key_produccion = null;
        $this->public_key_test = null;
        $this->user_id_externo = null;
        $this->webhook_secret = null;
        $this->timeout_segundos = 300;
        $this->activo = true;
    }

    // ==================== Render ====================

    public function render()
    {
        $integraciones = IntegracionPago::activas()->orderBy('orden')->get();
        $sucursalActiva = $this->sucursalActivaModel();

        // Mapa de configs de la sucursal activa indexado por integracion_id.
        $configs = $sucursalActiva
            ? IntegracionPagoSucursal::where('sucursal_id', $sucursalActiva->id)->get()->keyBy('integracion_pago_id')
            : collect();

        $cajas = $sucursalActiva
            ? Caja::where('sucursal_id', $sucursalActiva->id)->where('activo', true)->orderBy('numero')->get()
            : collect();

        $comercio = Comercio::find(session('comercio_activo_id'));

        return view('livewire.configuracion.integraciones-pago', [
            'integraciones' => $integraciones,
            'sucursalActiva' => $sucursalActiva,
            'configs' => $configs,
            'cajas' => $cajas,
            'comercio' => $comercio,
        ]);
    }
}

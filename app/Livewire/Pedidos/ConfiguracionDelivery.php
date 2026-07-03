<?php

namespace App\Livewire\Pedidos;

use App\Models\Categoria;
use App\Models\DeliveryZona;
use App\Models\Sucursal;
use App\Traits\ManejaDomicilio;
use App\Traits\SucursalAware;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Configuración de Delivery de la sucursal (RF-05) + ABM de zonas de entrega.
 *
 * Edita `sucursales.usa_delivery` + el JSON `config_delivery` (keys CORE de
 * Sucursal::CONFIG_DELIVERY_DEFAULTS — franjas/programados/Routes API son
 * Fase 8 y no se exponen acá) y las `delivery_zonas` (v1 radio: centro con
 * el picker de Maps + km, costo propio que pisa el cálculo por km, rangos
 * horarios y prioridad de match).
 *
 * Requiere permiso `func.pedidos_delivery.config` para guardar.
 */
#[Layout('layouts.app')]
#[Lazy]
class ConfiguracionDelivery extends Component
{
    use ManejaDomicilio, SucursalAware;

    // ==================== CONFIG SUCURSAL (RF-05) ====================

    public bool $usaDelivery = false;

    public bool $georreferenciarPedidos = false;

    public string $radioEntregaKm = '';

    public string $costoEnvioBase = '0';

    public string $costoPorKmExtra = '0';

    public string $kmIncluidosEnBase = '0';

    public string $conceptoCategoriaEnvioId = '';

    public bool $exigirRepartidor = true;

    public bool $takeawayHabilitado = true;

    public string $aceptacionPedidosExternos = 'manual';

    public bool $imprimirComandaAlAceptar = false;

    public string $timeoutAceptacionMin = '';

    /** @var array<int, bool> día (1=lunes .. 7=domingo) => laboral */
    public array $diasLaborales = [];

    /** @var array<int, array{dias: array<int,bool>, desde: string, hasta: string}> */
    public array $horariosAtencion = [];

    /** @var array<int, string> fechas Y-m-d */
    public array $feriados = [];

    public string $nuevoFeriado = '';

    public string $modoPromesa = 'manual';

    public string $demoraBaseMin = '15';

    public string $demoraMinPorKm = '4';

    public string $botonesDemora = '0, 10, 15, 20, 25, 30, 35, 40, 45, 50, 60, 90';

    // ==================== ZONAS ====================

    public bool $showZonaModal = false;

    public ?int $zonaId = null;

    public string $zonaNombre = '';

    public string $zonaRadioKm = '';

    public string $zonaCostoEnvio = '';

    public string $zonaOrden = '0';

    public bool $zonaActivo = true;

    /** @var array<int, array{dias: array<int,bool>, desde: string, hasta: string}> */
    public array $zonaRangos = [];

    public bool $showEliminarZonaModal = false;

    public ?int $zonaAEliminar = null;

    public ?string $zonaNombreAEliminar = null;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-form :tabs="0" :fields="10" />
        HTML;
    }

    public function mount(): void
    {
        $this->cargarConfig();
    }

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->cerrarZonaModal();
        $this->showEliminarZonaModal = false;
        $this->cargarConfig();
    }

    // ==================== CARGA / GUARDADO CONFIG ====================

    protected function cargarConfig(): void
    {
        $sucursal = Sucursal::find($this->sucursalActual());
        if (! $sucursal) {
            return;
        }

        $config = $sucursal->getConfigDelivery();

        $this->usaDelivery = (bool) $sucursal->usa_delivery;
        $this->georreferenciarPedidos = (bool) $config['georreferenciar_pedidos'];
        $this->radioEntregaKm = $config['radio_entrega_km'] !== null ? (string) $config['radio_entrega_km'] : '';
        $this->costoEnvioBase = (string) $config['costo_envio_base'];
        $this->costoPorKmExtra = (string) $config['costo_por_km_extra'];
        $this->kmIncluidosEnBase = (string) $config['km_incluidos_en_base'];
        $this->conceptoCategoriaEnvioId = $config['concepto_categoria_envio_id'] !== null ? (string) $config['concepto_categoria_envio_id'] : '';
        $this->exigirRepartidor = (bool) $config['exigir_repartidor'];
        $this->takeawayHabilitado = (bool) $config['takeaway_habilitado'];
        $this->aceptacionPedidosExternos = (string) $config['aceptacion_pedidos_externos'];
        $this->imprimirComandaAlAceptar = (bool) $config['imprimir_comanda_al_aceptar'];
        $this->timeoutAceptacionMin = $config['timeout_aceptacion_min'] !== null ? (string) $config['timeout_aceptacion_min'] : '';

        $this->diasLaborales = [];
        foreach (range(1, 7) as $dia) {
            $this->diasLaborales[$dia] = in_array($dia, $config['dias_laborales'] ?? range(1, 7));
        }

        $this->horariosAtencion = collect($config['horarios_atencion'] ?? [])
            ->map(fn ($r) => $this->rangoAForm($r))
            ->values()
            ->toArray();

        $this->feriados = array_values($config['feriados'] ?? []);
        $this->modoPromesa = (string) $config['modo_promesa'] === 'automatica' ? 'automatica' : 'manual';
        $this->demoraBaseMin = (string) $config['demora_base_min'];
        $this->demoraMinPorKm = (string) $config['demora_min_por_km'];
        $this->botonesDemora = implode(', ', $config['botones_demora'] ?? []);
    }

    public function guardarConfig(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar el delivery'));

            return;
        }

        $sucursal = Sucursal::find($this->sucursalActual());
        if (! $sucursal) {
            return;
        }

        $botones = collect(explode(',', $this->botonesDemora))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v >= 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        // Merge sobre lo guardado: las keys de Fase 8 (franjas, programados,
        // usar_maps_para_demora) se preservan tal cual. (cast 'array')
        $guardada = is_array($sucursal->config_delivery) ? $sucursal->config_delivery : [];

        $config = array_merge($guardada, [
            'georreferenciar_pedidos' => $this->georreferenciarPedidos,
            'radio_entrega_km' => $this->radioEntregaKm !== '' ? round((float) $this->radioEntregaKm, 2) : null,
            'costo_envio_base' => round((float) $this->costoEnvioBase, 2),
            'costo_por_km_extra' => round((float) $this->costoPorKmExtra, 2),
            'km_incluidos_en_base' => round((float) $this->kmIncluidosEnBase, 2),
            'concepto_categoria_envio_id' => $this->conceptoCategoriaEnvioId !== '' ? (int) $this->conceptoCategoriaEnvioId : null,
            'exigir_repartidor' => $this->exigirRepartidor,
            'takeaway_habilitado' => $this->takeawayHabilitado,
            'aceptacion_pedidos_externos' => in_array($this->aceptacionPedidosExternos, ['manual', 'automatica'], true)
                ? $this->aceptacionPedidosExternos
                : 'manual',
            'imprimir_comanda_al_aceptar' => $this->imprimirComandaAlAceptar,
            'timeout_aceptacion_min' => $this->timeoutAceptacionMin !== '' ? (int) $this->timeoutAceptacionMin : null,
            'dias_laborales' => array_keys(array_filter($this->diasLaborales)),
            'horarios_atencion' => $this->rangosDesdeForm($this->horariosAtencion) ?: null,
            'feriados' => array_values($this->feriados),
            'modo_promesa' => $this->modoPromesa === 'automatica' ? 'automatica' : 'manual',
            'demora_base_min' => max(0, (int) $this->demoraBaseMin),
            'demora_min_por_km' => max(0, (float) $this->demoraMinPorKm),
            'botones_demora' => $botones ?: Sucursal::CONFIG_DELIVERY_DEFAULTS['botones_demora'],
        ]);

        try {
            $sucursal->update([
                'usa_delivery' => $this->usaDelivery,
                'config_delivery' => $config,
            ]);

            $this->dispatch('toast-success', message: __('Configuración de delivery guardada'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== HORARIOS (repeater compartido) ====================

    public function agregarHorario(): void
    {
        $this->horariosAtencion[] = $this->rangoAForm([]);
    }

    public function quitarHorario(int $index): void
    {
        unset($this->horariosAtencion[$index]);
        $this->horariosAtencion = array_values($this->horariosAtencion);
    }

    public function agregarFeriado(): void
    {
        $fecha = trim($this->nuevoFeriado);
        if ($fecha === '' || in_array($fecha, $this->feriados, true)) {
            return;
        }
        $this->feriados[] = $fecha;
        sort($this->feriados);
        $this->nuevoFeriado = '';
    }

    public function quitarFeriado(int $index): void
    {
        unset($this->feriados[$index]);
        $this->feriados = array_values($this->feriados);
    }

    /**
     * Rango persistido {dias:[1..7], desde, hasta} → filas del form con los
     * días como checkboxes.
     */
    protected function rangoAForm(array $rango): array
    {
        $dias = [];
        foreach (range(1, 7) as $dia) {
            $dias[$dia] = in_array($dia, $rango['dias'] ?? range(1, 7));
        }

        return [
            'dias' => $dias,
            'desde' => $rango['desde'] ?? '19:00',
            'hasta' => $rango['hasta'] ?? '23:30',
        ];
    }

    /**
     * Filas del form → formato persistido. Filas sin días u horas se descartan.
     *
     * @return list<array{dias: list<int>, desde: string, hasta: string}>
     */
    protected function rangosDesdeForm(array $rangos): array
    {
        return collect($rangos)
            ->map(fn ($r) => [
                'dias' => array_keys(array_filter($r['dias'] ?? [])),
                'desde' => $r['desde'] ?? '',
                'hasta' => $r['hasta'] ?? '',
            ])
            ->filter(fn ($r) => ! empty($r['dias']) && $r['desde'] !== '' && $r['hasta'] !== '')
            ->values()
            ->all();
    }

    // ==================== ZONAS (ABM) ====================

    public function abrirCrearZona(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar el delivery'));

            return;
        }

        $this->resetZonaForm();

        // Centro inicial: la sucursal (si está georreferenciada) para que el
        // picker arranque cerca.
        $sucursal = Sucursal::find($this->sucursalActual());
        if ($sucursal) {
            $this->domicilioDefaultDesdeSucursal($sucursal);
            if ($sucursal->latitud && $sucursal->longitud) {
                $this->domLatitud = (string) $sucursal->latitud;
                $this->domLongitud = (string) $sucursal->longitud;
            }
        }

        $this->showZonaModal = true;
    }

    public function abrirEditarZona(int $id): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar el delivery'));

            return;
        }

        $zona = DeliveryZona::find($id);
        if (! $zona || (int) $zona->sucursal_id !== (int) $this->sucursalActual()) {
            $this->dispatch('toast-error', message: __('Zona no encontrada'));

            return;
        }

        $this->resetZonaForm();
        $this->zonaId = $zona->id;
        $this->zonaNombre = $zona->nombre;
        $this->zonaRadioKm = (string) $zona->radio_km;
        $this->zonaCostoEnvio = (string) $zona->costo_envio;
        $this->zonaOrden = (string) $zona->orden;
        $this->zonaActivo = (bool) $zona->activo;
        $this->zonaRangos = collect($zona->rangos_horarios ?? [])
            ->map(fn ($r) => $this->rangoAForm((array) $r))
            ->values()
            ->toArray();

        $this->domLatitud = (string) $zona->centro_lat;
        $this->domLongitud = (string) $zona->centro_lng;
        $sucursal = Sucursal::find($this->sucursalActual());
        if ($sucursal) {
            $this->domicilioDefaultDesdeSucursal($sucursal);
        }

        $this->showZonaModal = true;
    }

    public function guardarZona(): void
    {
        $this->validate([
            'zonaNombre' => 'required|string|max:100',
            'zonaRadioKm' => 'required|numeric|min:0.1',
            'zonaCostoEnvio' => 'required|numeric|min:0',
        ], [
            'zonaNombre.required' => __('Ingresá el nombre de la zona'),
            'zonaRadioKm.required' => __('Ingresá el radio en km'),
            'zonaCostoEnvio.required' => __('Ingresá el costo de envío de la zona'),
        ]);

        if ($this->domLatitud === null || $this->domLatitud === '' || $this->domLongitud === null || $this->domLongitud === '') {
            $this->dispatch('toast-error', message: __('Ubicá el centro de la zona en el mapa (o cargá las coordenadas a mano)'));

            return;
        }

        try {
            $datos = [
                'sucursal_id' => (int) $this->sucursalActual(),
                'nombre' => trim($this->zonaNombre),
                'centro_lat' => (float) $this->domLatitud,
                'centro_lng' => (float) $this->domLongitud,
                'radio_km' => round((float) $this->zonaRadioKm, 2),
                'costo_envio' => round((float) $this->zonaCostoEnvio, 2),
                'rangos_horarios' => $this->rangosDesdeForm($this->zonaRangos) ?: null,
                'orden' => (int) $this->zonaOrden,
                'activo' => $this->zonaActivo,
            ];

            if ($this->zonaId) {
                $zona = DeliveryZona::findOrFail($this->zonaId);
                $zona->update($datos);
                $this->dispatch('toast-success', message: __('Zona actualizada'));
            } else {
                DeliveryZona::create($datos);
                $this->dispatch('toast-success', message: __('Zona creada'));
            }

            $this->cerrarZonaModal();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function agregarZonaRango(): void
    {
        $this->zonaRangos[] = $this->rangoAForm([]);
    }

    public function quitarZonaRango(int $index): void
    {
        unset($this->zonaRangos[$index]);
        $this->zonaRangos = array_values($this->zonaRangos);
    }

    public function confirmarEliminarZona(int $id): void
    {
        $zona = DeliveryZona::find($id);
        if (! $zona || (int) $zona->sucursal_id !== (int) $this->sucursalActual()) {
            return;
        }

        $this->zonaAEliminar = $zona->id;
        $this->zonaNombreAEliminar = $zona->nombre;
        $this->showEliminarZonaModal = true;
    }

    public function eliminarZona(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            return;
        }

        $zona = DeliveryZona::find($this->zonaAEliminar);
        if ($zona && (int) $zona->sucursal_id === (int) $this->sucursalActual()) {
            // Los pedidos históricos conservan zona_id (FK SET NULL en BD si
            // aplica); la zona simplemente deja de matchear cotizaciones.
            $zona->delete();
            $this->dispatch('toast-success', message: __('Zona eliminada'));
        }

        $this->showEliminarZonaModal = false;
        $this->zonaAEliminar = null;
        $this->zonaNombreAEliminar = null;
    }

    public function cerrarEliminarZona(): void
    {
        $this->showEliminarZonaModal = false;
        $this->zonaAEliminar = null;
        $this->zonaNombreAEliminar = null;
    }

    public function cerrarZonaModal(): void
    {
        $this->showZonaModal = false;
        $this->resetZonaForm();
    }

    protected function resetZonaForm(): void
    {
        $this->zonaId = null;
        $this->zonaNombre = '';
        $this->zonaRadioKm = '';
        $this->zonaCostoEnvio = '';
        $this->zonaOrden = '0';
        $this->zonaActivo = true;
        $this->zonaRangos = [];
        $this->resetDomicilio();
        $this->resetValidation();
    }

    // ==================== RENDER ====================

    public function render()
    {
        $sucursalId = (int) $this->sucursalActual();

        return view('livewire.pedidos.configuracion-delivery', [
            'zonas' => $sucursalId
                ? DeliveryZona::where('sucursal_id', $sucursalId)->orderBy('orden')->orderBy('nombre')->get()
                : collect(),
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'diasSemana' => [1 => __('Lu'), 2 => __('Ma'), 3 => __('Mi'), 4 => __('Ju'), 5 => __('Vi'), 6 => __('Sá'), 7 => __('Do')],
        ]);
    }
}

<?php

namespace App\Livewire\Pedidos;

use App\Models\DeliveryZona;
use App\Models\Sucursal;
use App\Traits\SucursalAware;
use Exception;
use Livewire\Component;

/**
 * Configuración de envío y alcance del delivery (RF-05/RF-06) — TODO lo que
 * usa Google Maps vive acá: georreferenciación, radio general + costos por
 * km, y el ABM de zonas por POLÍGONO con el mapa único (zonas-mapa.js).
 *
 * Sub-componente EMBEBIDO de ConfiguracionDelivery: el padre lo monta A
 * DEMANDA (botón "Configurar envío y zonas"), así el SDK de Maps no se carga
 * en cada visita a la página de configuración.
 *
 * Guarda SOLO sus keys de config_delivery (merge sobre lo persistido): el
 * guardado del padre y el de este componente no se pisan entre sí.
 */
class ConfiguracionDeliveryEnvio extends Component
{
    use SucursalAware;

    // ==================== CONFIG DE ENVÍO ====================

    public bool $georreferenciarPedidos = false;

    public string $radioEntregaKm = '';

    public string $costoEnvioBase = '0';

    public string $costoPorKmExtra = '0';

    public string $kmIncluidosEnBase = '0';

    // ==================== ZONAS ====================

    public bool $showZonaModal = false;

    public ?int $zonaId = null;

    public string $zonaNombre = '';

    public string $zonaCostoEnvio = '';

    public bool $zonaActivo = true;

    /**
     * Vértices del polígono dibujado en el mapa ([{lat, lng}, ...]).
     * Lo empuja zonas-mapa.js con $wire.set deferred en cada cambio.
     *
     * @var array<int, array{lat: float, lng: float}>
     */
    public array $zonaPoligono = [];

    /**
     * Franjas de COSTO de la zona: el costo default aplica siempre y estas
     * franjas lo pisan por día/hora (más caro de noche, etc.).
     *
     * @var array<int, array{dias: array<int,bool>, desde: string, hasta: string, costo: string}>
     */
    public array $zonaRangos = [];

    public bool $showEliminarZonaModal = false;

    public ?int $zonaAEliminar = null;

    public ?string $zonaNombreAEliminar = null;

    public function mount(): void
    {
        $this->cargarConfig();
    }

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->cerrarZonaModal();
        $this->showEliminarZonaModal = false;
        $this->cargarConfig();
        $this->dispatchZonasActualizadas();
    }

    // ==================== CARGA / GUARDADO ====================

    protected function cargarConfig(): void
    {
        $sucursal = Sucursal::find($this->sucursalActual());
        if (! $sucursal) {
            return;
        }

        $config = $sucursal->getConfigDelivery();

        $this->georreferenciarPedidos = (bool) $config['georreferenciar_pedidos'];
        $this->radioEntregaKm = $config['radio_entrega_km'] !== null ? (string) $config['radio_entrega_km'] : '';
        $this->costoEnvioBase = (string) $config['costo_envio_base'];
        $this->costoPorKmExtra = (string) $config['costo_por_km_extra'];
        $this->kmIncluidosEnBase = (string) $config['km_incluidos_en_base'];
    }

    public function guardarEnvio(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar el delivery'));

            return;
        }

        $sucursal = Sucursal::find($this->sucursalActual());
        if (! $sucursal) {
            return;
        }

        // Merge parcial: SOLO las keys de envío — el resto (promesa,
        // aceptación, calendario, etc.) lo administra el componente padre.
        $guardada = is_array($sucursal->config_delivery) ? $sucursal->config_delivery : [];

        $config = array_merge($guardada, [
            'georreferenciar_pedidos' => $this->georreferenciarPedidos,
            'radio_entrega_km' => $this->radioEntregaKm !== '' ? round((float) $this->radioEntregaKm, 2) : null,
            'costo_envio_base' => round((float) $this->costoEnvioBase, 2),
            'costo_por_km_extra' => round((float) $this->costoPorKmExtra, 2),
            'km_incluidos_en_base' => round((float) $this->kmIncluidosEnBase, 2),
        ]);

        try {
            $sucursal->update(['config_delivery' => $config]);
            $this->dispatch('toast-success', message: __('Configuración de envío guardada'));
            $this->dispatchZonasActualizadas();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /** El círculo del radio general del mapa se refresca al cambiar el valor. */
    public function updatedRadioEntregaKm(): void
    {
        $this->dispatchZonasActualizadas();
    }

    // ==================== ZONAS (ABM, polígonos) ====================

    public function abrirCrearZona(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para configurar el delivery'));

            return;
        }

        $this->resetZonaForm();
        $this->showZonaModal = true;
        $this->dispatch('zona-dibujo-iniciar', poligono: [], zonaId: null);
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
        $this->zonaCostoEnvio = (string) $zona->costo_envio;
        $this->zonaActivo = (bool) $zona->activo;
        $this->zonaPoligono = is_array($zona->poligono) ? $zona->poligono : [];
        $this->zonaRangos = collect($zona->rangos_horarios ?? [])
            ->map(fn ($r) => $this->zonaRangoAForm((array) $r))
            ->values()
            ->toArray();

        $this->showZonaModal = true;
        $this->dispatch('zona-dibujo-iniciar', poligono: $this->zonaPoligono, zonaId: $zona->id);
    }

    public function guardarZona(): void
    {
        $this->validate([
            'zonaNombre' => 'required|string|max:100',
            'zonaCostoEnvio' => 'required|numeric|min:0',
        ], [
            'zonaNombre.required' => __('Ingresá el nombre de la zona'),
            'zonaCostoEnvio.required' => __('Ingresá el costo de envío default de la zona'),
        ]);

        $poligono = $this->poligonoNormalizado();
        if (count($poligono) < 3) {
            $this->dispatch('toast-error', message: __('Dibujá la zona en el mapa: marcá al menos 3 puntos haciendo click'));

            return;
        }

        // Centroide del polígono: referencia para centrar el mapa (el match
        // es 100% por polígono).
        $centroLat = round(array_sum(array_column($poligono, 'lat')) / count($poligono), 7);
        $centroLng = round(array_sum(array_column($poligono, 'lng')) / count($poligono), 7);

        try {
            $datos = [
                'sucursal_id' => (int) $this->sucursalActual(),
                'nombre' => trim($this->zonaNombre),
                'centro_lat' => $centroLat,
                'centro_lng' => $centroLng,
                'radio_km' => 0,
                'poligono' => $poligono,
                'costo_envio' => round((float) $this->zonaCostoEnvio, 2),
                'rangos_horarios' => $this->zonaRangosDesdeForm($this->zonaRangos) ?: null,
                'activo' => $this->zonaActivo,
            ];

            if ($this->zonaId) {
                $zona = DeliveryZona::findOrFail($this->zonaId);
                unset($datos['radio_km']); // legacy: no pisar hasta que deje de usarse
                $zona->update($datos);
                $this->dispatch('toast-success', message: __('Zona actualizada'));
            } else {
                // Última de la lista: el drag & drop define la prioridad.
                $datos['orden'] = (int) DeliveryZona::porSucursal((int) $this->sucursalActual())->max('orden') + 1;
                DeliveryZona::create($datos);
                $this->dispatch('toast-success', message: __('Zona creada'));
            }

            $this->cerrarZonaModal();
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Orden nuevo de las zonas tras el drag & drop de la lista (SortableJS):
     * la posición en la lista ES la prioridad de match.
     *
     * @param  array<int>  $ids
     */
    public function reordenarZonas(array $ids): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.config')) {
            return;
        }

        $sucursalId = (int) $this->sucursalActual();
        foreach (array_values($ids) as $orden => $id) {
            DeliveryZona::where('sucursal_id', $sucursalId)->where('id', (int) $id)->update(['orden' => $orden]);
        }

        $this->dispatchZonasActualizadas();
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
        $this->dispatchZonasActualizadas();
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
        $this->dispatch('zona-dibujo-fin');
        $this->dispatchZonasActualizadas();
    }

    protected function resetZonaForm(): void
    {
        $this->zonaId = null;
        $this->zonaNombre = '';
        $this->zonaCostoEnvio = '';
        $this->zonaActivo = true;
        $this->zonaPoligono = [];
        $this->zonaRangos = [];
        $this->resetValidation();
    }

    /** Vértices saneados del dibujo ({lat,lng} numéricos). */
    protected function poligonoNormalizado(): array
    {
        return collect($this->zonaPoligono)
            ->map(fn ($v) => [
                'lat' => round((float) ($v['lat'] ?? 0), 7),
                'lng' => round((float) ($v['lng'] ?? 0), 7),
            ])
            ->filter(fn ($v) => $v['lat'] !== 0.0 || $v['lng'] !== 0.0)
            ->values()
            ->all();
    }

    // ==================== FRANJAS DE COSTO (form) ====================

    public function agregarZonaRango(): void
    {
        $this->zonaRangos[] = $this->zonaRangoAForm([]);
    }

    public function quitarZonaRango(int $index): void
    {
        unset($this->zonaRangos[$index]);
        $this->zonaRangos = array_values($this->zonaRangos);
    }

    /**
     * Franja de costo persistida {dias, desde, hasta, costo} → fila del form
     * con los días como checkboxes.
     */
    protected function zonaRangoAForm(array $rango): array
    {
        $dias = [];
        foreach (range(1, 7) as $dia) {
            $dias[$dia] = in_array($dia, $rango['dias'] ?? range(1, 7));
        }

        return [
            'dias' => $dias,
            'desde' => $rango['desde'] ?? '19:00',
            'hasta' => $rango['hasta'] ?? '23:30',
            'costo' => isset($rango['costo']) ? (string) $rango['costo'] : '',
        ];
    }

    /**
     * Filas del form → franjas de costo persistidas. Sin costo la fila no
     * aporta nada (el default ya cubre ese horario): se descarta.
     *
     * @return list<array{dias: list<int>, desde: string, hasta: string, costo: float}>
     */
    protected function zonaRangosDesdeForm(array $rangos): array
    {
        return collect($rangos)
            ->map(fn ($r) => [
                'dias' => array_map('intval', array_keys(array_filter($r['dias'] ?? []))),
                'desde' => $r['desde'] ?? '',
                'hasta' => $r['hasta'] ?? '',
                'costo' => ($r['costo'] ?? '') !== '' ? round((float) $r['costo'], 2) : null,
            ])
            ->filter(fn ($r) => ! empty($r['dias']) && $r['desde'] !== '' && $r['hasta'] !== '' && $r['costo'] !== null)
            ->values()
            ->all();
    }

    // ==================== MAPA ====================

    /** Payload del mapa siempre visible (todas las zonas + radio general). */
    protected function dispatchZonasActualizadas(): void
    {
        $this->dispatch('zonas-actualizadas', ...$this->zonasMapaPayload());
    }

    /**
     * @return array{zonas: array, radioKm: float|null, centro: array{lat: float, lng: float}|null}
     */
    protected function zonasMapaPayload(): array
    {
        $sucursal = Sucursal::find($this->sucursalActual());

        return [
            'zonas' => DeliveryZona::porSucursal((int) $this->sucursalActual())
                ->ordenadas()
                ->get()
                ->map(fn (DeliveryZona $z) => [
                    'id' => $z->id,
                    'nombre' => $z->nombre,
                    'poligono' => is_array($z->poligono) ? $z->poligono : [],
                    'activo' => (bool) $z->activo,
                ])
                ->all(),
            'radioKm' => $this->radioEntregaKm !== '' ? (float) $this->radioEntregaKm : null,
            'centro' => $sucursal && $sucursal->latitud && $sucursal->longitud
                ? ['lat' => (float) $sucursal->latitud, 'lng' => (float) $sucursal->longitud]
                : null,
        ];
    }

    // ==================== RENDER ====================

    public function render()
    {
        $sucursalId = (int) $this->sucursalActual();

        return view('livewire.pedidos.configuracion-delivery-envio', [
            'zonas' => $sucursalId
                ? DeliveryZona::where('sucursal_id', $sucursalId)->ordenadas()->get()
                : collect(),
            'zonasMapa' => $sucursalId ? $this->zonasMapaPayload() : ['zonas' => [], 'radioKm' => null, 'centro' => null],
            'diasSemana' => [1 => __('Lu'), 2 => __('Ma'), 3 => __('Mi'), 4 => __('Ju'), 5 => __('Vi'), 6 => __('Sá'), 7 => __('Do')],
        ]);
    }
}

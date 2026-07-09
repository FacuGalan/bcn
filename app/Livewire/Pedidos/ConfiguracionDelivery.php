<?php

namespace App\Livewire\Pedidos;

use App\Models\Categoria;
use App\Models\Sucursal;
use App\Traits\SucursalAware;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Configuración de Delivery de la sucursal (RF-05).
 *
 * Edita `sucursales.usa_delivery` + el JSON `config_delivery` (keys CORE de
 * Sucursal::CONFIG_DELIVERY_DEFAULTS — franjas/programados/Routes API son
 * Fase 8 y no se exponen acá). TODO lo que usa Google Maps (georreferenciar,
 * radio/costos por km y el ABM de zonas polígono) vive en el sub-componente
 * ConfiguracionDeliveryEnvio, montado A DEMANDA con `showEnvioZonas` para no
 * cargar el SDK de Maps en cada visita.
 *
 * Requiere permiso `func.pedidos_delivery.config` para guardar.
 */
#[Layout('layouts.app')]
#[Lazy]
class ConfiguracionDelivery extends Component
{
    use SucursalAware;

    // ==================== CONFIG SUCURSAL (RF-05) ====================

    public bool $usaDelivery = false;

    /**
     * Monta a demanda el sub-componente de envío/zonas (Google Maps): el SDK
     * de Maps no se carga hasta que el usuario abre esa sección.
     */
    public bool $showEnvioZonas = false;

    public string $conceptoCategoriaEnvioId = '';

    public bool $exigirRepartidor = true;

    /**
     * OFF: la columna "Listo" se oculta del kanban y de preparación se pasa
     * directo a en camino (delivery) / entregado (take-away). Aunque esté ON,
     * "listo" nunca bloquea: se puede despachar desde preparación igual.
     */
    public bool $usaEstadoListo = true;

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

    /**
     * Horarios de entrega del modo franjas, definidos a mano (RF-15).
     *
     * @var array<int, array{hora: string, dias: array<int,bool>, delivery: bool, take_away: bool}>
     */
    public array $franjas = [];

    public bool $aceptaLoAntesPosible = true;

    /**
     * Key `conversion_automatica_al_entregar` del JSON config_delivery —
     * PROPIA de delivery (rev9). Mostrador sigue usando su columna
     * pedido_conversion_automatica_al_entregar desde su propia config.
     */
    public bool $convertirVentaAlEntregar = false;

    /**
     * Numeración display PROPIA de delivery (rev9): keys del JSON
     * config_delivery + contador pedido_delivery_display_* en sucursales.
     * Mostrador conserva sus columnas y su UI en el modal de sucursal.
     */
    public bool $usaNumeracionDisplay = true;

    public string $numeracionDisplayModo = 'diario';

    /** Horas de reset del modo diario, CSV (ej: "6" o "6, 18"). */
    public string $numeracionDisplayHoras = '6';

    /**
     * Alertas de pedidos demorados (columnas de sucursales, compartidas con
     * mostrador). Minutos; 0 = deshabilitada.
     */
    public string $alertaAmarillaMin = '15';

    public string $alertaRojaMin = '30';

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
        $this->cargarConfig();
    }

    /** Botón "Configurar envío y zonas": monta el sub-componente con Maps. */
    public function toggleEnvioZonas(): void
    {
        $this->showEnvioZonas = ! $this->showEnvioZonas;
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
        $this->convertirVentaAlEntregar = (bool) ($config['conversion_automatica_al_entregar'] ?? false);
        $this->usaNumeracionDisplay = (bool) ($config['usa_numeracion_display'] ?? true);
        $modoNumeracion = (string) ($config['numeracion_display_modo'] ?? 'diario');
        $this->numeracionDisplayModo = in_array($modoNumeracion, ['diario', 'manual'], true) ? $modoNumeracion : 'diario';
        $this->numeracionDisplayHoras = implode(', ', (array) ($config['numeracion_display_horas'] ?? [6]));
        $this->alertaAmarillaMin = (string) ($sucursal->pedido_alerta_amarilla_min ?? 15);
        $this->alertaRojaMin = (string) ($sucursal->pedido_alerta_roja_min ?? 30);
        $this->conceptoCategoriaEnvioId = $config['concepto_categoria_envio_id'] !== null ? (string) $config['concepto_categoria_envio_id'] : '';
        $this->exigirRepartidor = (bool) $config['exigir_repartidor'];
        $this->usaEstadoListo = (bool) ($config['usa_estado_listo'] ?? true);
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
        $modo = (string) $config['modo_promesa'];
        $this->modoPromesa = in_array($modo, ['automatica', 'franjas'], true) ? $modo : 'manual';
        $this->demoraBaseMin = (string) $config['demora_base_min'];
        $this->demoraMinPorKm = (string) $config['demora_min_por_km'];
        $this->botonesDemora = implode(', ', $config['botones_demora'] ?? []);
        $this->franjas = collect($config['franjas'] ?? [])
            ->map(fn ($f) => $this->franjaAForm((array) $f))
            ->values()
            ->toArray();
        $this->aceptaLoAntesPosible = (bool) ($config['acepta_lo_antes_posible'] ?? true);
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

        // Merge sobre lo guardado: las keys de Fase 8 (programados,
        // usar_maps_para_demora) se preservan tal cual. (cast 'array')
        $guardada = is_array($sucursal->config_delivery) ? $sucursal->config_delivery : [];

        // Las keys de envío/alcance (georreferenciar, radio, costos por km)
        // las guarda el sub-componente ConfiguracionDeliveryEnvio: el merge
        // sobre lo persistido las preserva.
        $horasReset = collect(explode(',', $this->numeracionDisplayHoras))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v >= 0 && $v <= 23)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $config = array_merge($guardada, [
            'concepto_categoria_envio_id' => $this->conceptoCategoriaEnvioId !== '' ? (int) $this->conceptoCategoriaEnvioId : null,
            'exigir_repartidor' => $this->exigirRepartidor,
            'usa_estado_listo' => $this->usaEstadoListo,
            'conversion_automatica_al_entregar' => $this->convertirVentaAlEntregar,
            'usa_numeracion_display' => $this->usaNumeracionDisplay,
            'numeracion_display_modo' => in_array($this->numeracionDisplayModo, ['diario', 'manual'], true) ? $this->numeracionDisplayModo : 'diario',
            'numeracion_display_horas' => $horasReset ?: [6],
            'takeaway_habilitado' => $this->takeawayHabilitado,
            'aceptacion_pedidos_externos' => in_array($this->aceptacionPedidosExternos, ['manual', 'automatica'], true)
                ? $this->aceptacionPedidosExternos
                : 'manual',
            'imprimir_comanda_al_aceptar' => $this->imprimirComandaAlAceptar,
            'timeout_aceptacion_min' => $this->timeoutAceptacionMin !== '' ? (int) $this->timeoutAceptacionMin : null,
            'dias_laborales' => array_keys(array_filter($this->diasLaborales)),
            'horarios_atencion' => $this->rangosDesdeForm($this->horariosAtencion) ?: null,
            'feriados' => array_values($this->feriados),
            'modo_promesa' => in_array($this->modoPromesa, ['automatica', 'franjas'], true) ? $this->modoPromesa : 'manual',
            'demora_base_min' => max(0, (int) $this->demoraBaseMin),
            'demora_min_por_km' => max(0, (float) $this->demoraMinPorKm),
            'botones_demora' => $botones ?: Sucursal::CONFIG_DELIVERY_DEFAULTS['botones_demora'],
            'franjas' => $this->franjasDesdeForm($this->franjas),
            'acepta_lo_antes_posible' => $this->aceptaLoAntesPosible,
        ]);

        try {
            $sucursal->update([
                'usa_delivery' => $this->usaDelivery,
                'pedido_alerta_amarilla_min' => max(0, (int) $this->alertaAmarillaMin),
                'pedido_alerta_roja_min' => max(0, (int) $this->alertaRojaMin),
                'config_delivery' => $config,
            ]);

            $this->dispatch('toast-success', message: __('Configuración de delivery guardada'));
        } catch (Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Reinicia a 0 el contador display PROPIO de delivery (con permiso).
     */
    public function reiniciarNumeracionDisplay(\App\Services\Pedidos\PedidoDeliveryService $service): void
    {
        if (! auth()->user()?->hasPermissionTo('func.pedidos_delivery.resetear_numeracion')) {
            $this->dispatch('toast-error', message: __('No tenés permiso para reiniciar la numeración'));

            return;
        }

        $service->reiniciarNumeracionDisplay((int) $this->sucursalActual(), (int) auth()->id());
        $this->dispatch('toast-success', message: __('Numeración de pedidos delivery reiniciada'));
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

    // ==================== FRANJAS (horarios de entrega a mano, RF-15) ====================

    public function agregarFranja(): void
    {
        $this->franjas[] = $this->franjaAForm([]);
    }

    public function quitarFranja(int $index): void
    {
        unset($this->franjas[$index]);
        $this->franjas = array_values($this->franjas);
    }

    /**
     * Franja persistida {hora, dias:[1..7], delivery, take_away} → fila del
     * form con los días como checkboxes (mismo patrón que los horarios).
     */
    protected function franjaAForm(array $franja): array
    {
        $dias = [];
        foreach (range(1, 7) as $dia) {
            $dias[$dia] = in_array($dia, $franja['dias'] ?? range(1, 7));
        }

        return [
            'hora' => $franja['hora'] ?? '20:00',
            'dias' => $dias,
            'delivery' => (bool) ($franja['delivery'] ?? true),
            'take_away' => (bool) ($franja['take_away'] ?? true),
        ];
    }

    /**
     * Filas del form → formato persistido, ordenado por hora. Se descartan las
     * filas sin hora, sin días o que no sirven a ningún tipo.
     *
     * @return list<array{hora: string, dias: list<int>, delivery: bool, take_away: bool}>
     */
    protected function franjasDesdeForm(array $franjas): array
    {
        return collect($franjas)
            ->map(fn ($f) => [
                'hora' => trim((string) ($f['hora'] ?? '')),
                'dias' => array_map('intval', array_keys(array_filter($f['dias'] ?? []))),
                'delivery' => (bool) ($f['delivery'] ?? true),
                'take_away' => (bool) ($f['take_away'] ?? true),
            ])
            ->filter(fn ($f) => $f['hora'] !== '' && ! empty($f['dias']) && ($f['delivery'] || $f['take_away']))
            ->sortBy('hora')
            ->values()
            ->all();
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

    // ==================== RENDER ====================

    public function render()
    {
        return view('livewire.pedidos.configuracion-delivery', [
            'categorias' => Categoria::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'diasSemana' => [1 => __('Lu'), 2 => __('Ma'), 3 => __('Mi'), 4 => __('Ju'), 5 => __('Vi'), 6 => __('Sá'), 7 => __('Do')],
        ]);
    }
}

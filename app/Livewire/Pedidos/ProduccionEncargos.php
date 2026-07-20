<?php

namespace App\Livewire\Pedidos;

use App\Models\PedidoDelivery;
use App\Traits\SucursalAware;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Reporte de PRODUCCIÓN de encargos (RF-T16): qué hay que preparar en los
 * próximos días según los pedidos programados ACEPTADOS (los "por aceptar"
 * no cuentan — todavía no son un compromiso). Agrupa por DÍA → ARTÍCULO con
 * cantidades totales y drill-down a los pedidos que lo componen.
 *
 * Patrón ReportesCompras/ReportesTesoreria: página propia lazy, rango de
 * fechas, imprimible con el print del navegador. Se llega desde la solapa
 * Encargos del panel de pedidos.
 */
#[Layout('layouts.app')]
#[Lazy]
class ProduccionEncargos extends Component
{
    use SucursalAware;

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    /** Clave "fecha|articulo_id" del renglón expandido (drill-down). */
    public ?string $renglonExpandido = null;

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table />
        HTML;
    }

    public function mount(): void
    {
        $this->fechaDesde = today()->toDateString();
        $this->fechaHasta = today()->addDays(7)->toDateString();
    }

    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        $this->renglonExpandido = null;
    }

    public function toggleRenglon(string $clave): void
    {
        $this->renglonExpandido = $this->renglonExpandido === $clave ? null : $clave;
    }

    /**
     * Encargos activos del rango agrupados por día → artículo.
     *
     * @return array<string, array<int, array{articulo: string, unidad: string|null, cantidad: float, pedidos: list<array>}>>
     */
    protected function armarReporte(): array
    {
        $sucursalId = (int) $this->sucursalActual();
        if (! $sucursalId || $this->fechaDesde === '' || $this->fechaHasta === '') {
            return [];
        }

        $pedidos = PedidoDelivery::with(['detalles.articulo:id,nombre,unidad_medida', 'cliente:id,nombre'])
            ->where('sucursal_id', $sucursalId)
            ->activos()
            ->where('estado_pedido', '!=', PedidoDelivery::ESTADO_BORRADOR)
            ->whereNotNull('programado_para')
            ->whereDate('programado_para', '>=', $this->fechaDesde)
            ->whereDate('programado_para', '<=', $this->fechaHasta)
            ->orderBy('programado_para')
            ->get();

        $reporte = [];

        foreach ($pedidos as $pedido) {
            $dia = $pedido->programado_para->toDateString();

            foreach ($pedido->detalles as $detalle) {
                $articuloId = (int) $detalle->articulo_id;

                if (! isset($reporte[$dia][$articuloId])) {
                    $reporte[$dia][$articuloId] = [
                        'articulo' => $detalle->articulo?->nombre ?? $detalle->descripcion ?? __('Artículo'),
                        'unidad' => $detalle->articulo?->unidad_medida,
                        'cantidad' => 0.0,
                        'pedidos' => [],
                    ];
                }

                $reporte[$dia][$articuloId]['cantidad'] += (float) $detalle->cantidad;
                $reporte[$dia][$articuloId]['pedidos'][] = [
                    'id' => (int) $pedido->id,
                    'numero' => $pedido->numero,
                    'hora' => $pedido->programado_para->format('H:i'),
                    'cliente' => $pedido->cliente?->nombre ?? $pedido->nombre_cliente_temporal ?? __('Sin nombre'),
                    'cantidad' => (float) $detalle->cantidad,
                    'tipo' => $pedido->tipo,
                ];
            }
        }

        // Artículos de cada día ordenados por cantidad DESC (lo más pesado
        // de producir primero).
        foreach ($reporte as $dia => $articulos) {
            uasort($articulos, fn ($a, $b) => $b['cantidad'] <=> $a['cantidad']);
            $reporte[$dia] = $articulos;
        }

        ksort($reporte);

        return $reporte;
    }

    public function render()
    {
        return view('livewire.pedidos.produccion-encargos', [
            'reporte' => $this->armarReporte(),
        ]);
    }
}

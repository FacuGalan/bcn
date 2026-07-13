<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Traits\SucursalAware;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Reportes de compras (RF-22, patrón ReportesTesoreria).
 *
 * "Compras por cuenta": período + sucursal activa → total por cuenta de
 * compra (completadas − NC), con "Sin clasificar" como categoría propia
 * (para ir saneando) y drill-down a las compras. Cortes secundarios: por
 * proveedor y por mes. Acceso vía menú (permiso menu.reportes-compras).
 */
#[Layout('layouts.app')]
#[Lazy]
class ReportesCompras extends Component
{
    use SucursalAware;

    /** cuenta | proveedor | mes */
    public string $tipoReporte = 'cuenta';

    public string $fechaDesde = '';

    public string $fechaHasta = '';

    /** @var array<int, array> filas del reporte generado */
    public array $datosReporte = [];

    public array $resumen = [];

    /** Clave del grupo expandido (drill-down a las compras) */
    public ?string $grupoExpandido = null;

    /** @var array<int, array> compras del grupo expandido */
    public array $comprasDetalle = [];

    public function mount(): void
    {
        $this->fechaDesde = now()->startOfMonth()->toDateString();
        $this->fechaHasta = now()->toDateString();
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="3" :columns="4" :rows="8" />
        HTML;
    }

    protected function onSucursalChanged($sucursalId, $sucursalNombre): void
    {
        $this->datosReporte = [];
        $this->resumen = [];
        $this->grupoExpandido = null;
        $this->comprasDetalle = [];
    }

    public function updatedTipoReporte(): void
    {
        $this->datosReporte = [];
        $this->resumen = [];
        $this->grupoExpandido = null;
        $this->comprasDetalle = [];
    }

    public function generarReporte(): void
    {
        $this->grupoExpandido = null;
        $this->comprasDetalle = [];

        $compras = $this->comprasDelPeriodo();

        // Resumen del período: las NC restan (RF-22).
        $totalCompras = (float) $compras->reject(fn ($c) => $c->esNotaCredito())->sum('total');
        $totalNc = (float) $compras->filter(fn ($c) => $c->esNotaCredito())->sum('total');

        $this->resumen = [
            'comprobantes' => $compras->count(),
            'compras' => round($totalCompras, 2),
            'notas_credito' => round($totalNc, 2),
            'neto' => round($totalCompras - $totalNc, 2),
        ];

        $agrupadas = $compras->groupBy(fn ($c) => $this->claveGrupo($c));

        $this->datosReporte = $agrupadas
            ->map(function ($grupo, $clave) {
                $compras = (float) $grupo->reject(fn ($c) => $c->esNotaCredito())->sum('total');
                $nc = (float) $grupo->filter(fn ($c) => $c->esNotaCredito())->sum('total');

                return [
                    'clave' => (string) $clave,
                    'etiqueta' => $this->etiquetaGrupo($grupo->first()),
                    'comprobantes' => $grupo->count(),
                    'compras' => round($compras, 2),
                    'notas_credito' => round($nc, 2),
                    'neto' => round($compras - $nc, 2),
                ];
            })
            ->sortByDesc('neto')
            ->values()
            ->all();
    }

    /** Drill-down: las compras del grupo clickeado (RF-22). */
    public function expandirGrupo(string $clave): void
    {
        if ($this->grupoExpandido === $clave) {
            $this->grupoExpandido = null;
            $this->comprasDetalle = [];

            return;
        }

        $this->grupoExpandido = $clave;

        $this->comprasDetalle = $this->comprasDelPeriodo()
            ->filter(fn ($c) => $this->claveGrupo($c) === $clave)
            ->map(fn ($c) => [
                'id' => $c->id,
                'numero' => $c->numero_comprobante,
                'numero_proveedor' => $c->numero_comprobante_proveedor,
                'proveedor' => $c->proveedor?->nombre ?? '—',
                'cuenta' => $c->cuentaCompra?->nombre,
                'fecha' => $c->fecha?->format('d/m/Y'),
                'tipo' => $c->tipo_comprobante,
                'es_nc' => $c->esNotaCredito(),
                'total' => (float) $c->total,
            ])
            ->sortByDesc('id')
            ->values()
            ->all();
    }

    private function comprasDelPeriodo()
    {
        return Compra::with(['proveedor:id,nombre', 'cuentaCompra:id,nombre'])
            ->where('sucursal_id', $this->sucursalActual())
            ->completadas()
            ->when($this->fechaDesde, fn ($q) => $q->whereDate('fecha', '>=', $this->fechaDesde))
            ->when($this->fechaHasta, fn ($q) => $q->whereDate('fecha', '<=', $this->fechaHasta))
            ->get();
    }

    private function claveGrupo(Compra $compra): string
    {
        return match ($this->tipoReporte) {
            'proveedor' => 'prov-'.($compra->proveedor_id ?? 0),
            'mes' => $compra->fecha?->format('Y-m') ?? '—',
            default => 'cuenta-'.($compra->cuenta_compra_id ?? 0),
        };
    }

    private function etiquetaGrupo(Compra $compra): string
    {
        return match ($this->tipoReporte) {
            'proveedor' => $compra->proveedor?->nombre ?? __('Sin proveedor'),
            'mes' => $compra->fecha?->translatedFormat('F Y') ?? '—',
            default => $compra->cuentaCompra?->nombre ?? __('Sin clasificar'),
        };
    }

    public function render()
    {
        return view('livewire.compras.reportes-compras');
    }
}

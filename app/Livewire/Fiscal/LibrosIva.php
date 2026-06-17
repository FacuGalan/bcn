<?php

namespace App\Livewire\Fiscal;

use App\Models\Cuit;
use App\Services\Fiscal\PosicionFiscalService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Libros (subdiarios) de IVA ventas y compras (sistema-impositivo Fase 7,
 * RF-09): listado por comprobante/compra del CUIT en el período, exportable.
 *
 * Global (filtro por CUIT, no sucursal-aware). Permiso: `func.fiscal.libros`.
 *
 * Ref: .claude/specs/sistema-impositivo.md (Fase 7, RF-09).
 */
#[Lazy]
#[Layout('layouts.app')]
class LibrosIva extends Component
{
    public ?int $cuitId = null;

    public string $periodo = '';

    /** ventas | compras */
    public string $tab = 'ventas';

    public function mount(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.libros')) {
            abort(403, __('No tiene permiso para ver los libros de IVA'));
        }

        $this->periodo = now()->format('Y-m');
        $this->cuitId = Cuit::activos()->orderBy('razon_social')->value('id');
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="6" :rows="6" />
        HTML;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['ventas', 'compras'], true) ? $tab : 'ventas';
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.libros')) {
            $this->dispatch('notify', message: __('No tiene permiso para ver los libros de IVA'), type: 'error');

            return null;
        }

        $cuit = $this->cuitId ? Cuit::find($this->cuitId) : null;

        if (! $cuit) {
            $this->dispatch('notify', message: __('Seleccioná un CUIT'), type: 'error');

            return null;
        }

        $service = app(PosicionFiscalService::class);

        return $this->tab === 'compras'
            ? $this->exportarCompras($service, $cuit)
            : $this->exportarVentas($service, $cuit);
    }

    private function exportarVentas(PosicionFiscalService $service, Cuit $cuit): StreamedResponse
    {
        $comprobantes = $service->libroIvaVentas($cuit, $this->periodo);
        $totales = $service->totalesLibroVentas($comprobantes);

        $filename = 'libro_iva_ventas_'.$cuit->numero_cuit.'_'.$this->periodo.'.csv';

        $callback = function () use ($comprobantes, $totales) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                __('Fecha'),
                __('Comprobante'),
                __('Número'),
                __('Receptor'),
                __('Neto gravado'),
                __('No gravado'),
                __('Exento'),
                __('IVA'),
                __('Tributos'),
                __('Total'),
            ], ';');

            foreach ($comprobantes as $c) {
                fputcsv($file, [
                    optional($c->fecha_emision)->format('d/m/Y'),
                    $c->tipo_legible,
                    $c->numero_formateado,
                    $c->receptor_nombre,
                    number_format((float) $c->neto_gravado, 2, ',', ''),
                    number_format((float) $c->neto_no_gravado, 2, ',', ''),
                    number_format((float) $c->neto_exento, 2, ',', ''),
                    number_format((float) $c->iva_total, 2, ',', ''),
                    number_format((float) $c->tributos, 2, ',', ''),
                    number_format((float) $c->total, 2, ',', ''),
                ], ';');
            }

            fputcsv($file, [
                __('Totales'), '', '', '',
                number_format($totales['neto_gravado'], 2, ',', ''),
                number_format($totales['neto_no_gravado'], 2, ',', ''),
                number_format($totales['neto_exento'], 2, ',', ''),
                number_format($totales['iva'], 2, ',', ''),
                number_format($totales['tributos'], 2, ',', ''),
                number_format($totales['total'], 2, ',', ''),
            ], ';');

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function exportarCompras(PosicionFiscalService $service, Cuit $cuit): StreamedResponse
    {
        $lineas = $service->libroIvaCompras($cuit, $this->periodo);

        $filename = 'libro_iva_compras_'.$cuit->numero_cuit.'_'.$this->periodo.'.csv';

        $callback = function () use ($lineas) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($file, [
                __('Fecha'),
                __('Compra'),
                __('Crédito fiscal'),
                __('Percepciones'),
                __('Retenciones'),
            ], ';');

            foreach ($lineas as $linea) {
                fputcsv($file, [
                    $linea['fecha'] ? \Carbon\Carbon::parse($linea['fecha'])->format('d/m/Y') : '',
                    '#'.$linea['origen_id'],
                    number_format($linea['credito_fiscal'], 2, ',', ''),
                    number_format($linea['percepciones'], 2, ',', ''),
                    number_format($linea['retenciones'], 2, ',', ''),
                ], ';');
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function render()
    {
        $cuits = Cuit::activos()->orderBy('razon_social')->get(['id', 'razon_social', 'numero_cuit']);

        $service = app(PosicionFiscalService::class);
        $cuit = $this->cuitId ? $cuits->firstWhere('id', $this->cuitId) : null;

        $comprobantes = collect();
        $totalesVentas = null;
        $compras = collect();

        if ($cuit && $this->periodo !== '') {
            if ($this->tab === 'ventas') {
                $comprobantes = $service->libroIvaVentas($cuit, $this->periodo);
                $totalesVentas = $service->totalesLibroVentas($comprobantes);
            } else {
                $compras = $service->libroIvaCompras($cuit, $this->periodo);
            }
        }

        return view('livewire.fiscal.libros-iva', [
            'cuits' => $cuits,
            'comprobantes' => $comprobantes,
            'totalesVentas' => $totalesVentas,
            'compras' => $compras,
        ]);
    }
}

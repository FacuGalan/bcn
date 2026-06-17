<?php

namespace App\Livewire\Fiscal;

use App\Models\Cuit;
use App\Services\Fiscal\PosicionFiscalService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Posición fiscal (sistema-impositivo Fase 7, RF-09): IVA e IIBB por CUIT y
 * período, de solo lectura sobre el ledger fiscal. Exportable a CSV.
 *
 * Global (filtro por CUIT, no sucursal-aware). Permiso: `func.fiscal.posicion`.
 *
 * Ref: .claude/specs/sistema-impositivo.md (Fase 7, RF-09).
 */
#[Lazy]
#[Layout('layouts.app')]
class PosicionFiscal extends Component
{
    public ?int $cuitId = null;

    public string $periodo = '';

    public function mount(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.posicion')) {
            abort(403, __('No tiene permiso para ver la posición fiscal'));
        }

        $this->periodo = now()->format('Y-m');
        $this->cuitId = Cuit::activos()->orderBy('razon_social')->value('id');
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-dashboard />
        HTML;
    }

    public function exportarCsv(): ?StreamedResponse
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.posicion')) {
            $this->dispatch('notify', message: __('No tiene permiso para ver la posición fiscal'), type: 'error');

            return null;
        }

        $cuit = $this->cuitId ? Cuit::find($this->cuitId) : null;

        if (! $cuit) {
            $this->dispatch('notify', message: __('Seleccioná un CUIT'), type: 'error');

            return null;
        }

        $service = app(PosicionFiscalService::class);
        $iva = $service->posicionIva($cuit, $this->periodo);
        $iibb = $service->posicionIibb($cuit, $this->periodo);

        $filename = 'posicion_fiscal_'.$cuit->numero_cuit.'_'.$this->periodo.'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $callback = function () use ($iva, $iibb, $cuit) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            fputcsv($file, [__('Posición fiscal'), $cuit->razon_social, __('Período'), $this->periodo], ';');
            fputcsv($file, [], ';');

            // Posición IVA.
            fputcsv($file, [__('Posición IVA')], ';');
            fputcsv($file, [__('Débito fiscal'), number_format($iva['debito_fiscal'], 2, ',', '')], ';');
            fputcsv($file, [__('Crédito fiscal'), number_format($iva['credito_fiscal'], 2, ',', '')], ';');
            fputcsv($file, [__('Saldo técnico'), number_format($iva['saldo_tecnico'], 2, ',', '')], ';');
            fputcsv($file, [__('Percepciones de IVA sufridas'), number_format($iva['percepciones_iva_sufridas'], 2, ',', '')], ';');
            fputcsv($file, [__('Retenciones de IVA sufridas'), number_format($iva['retenciones_iva_sufridas'], 2, ',', '')], ';');
            fputcsv($file, [__('IVA a pagar'), number_format($iva['a_pagar'], 2, ',', '')], ';');
            fputcsv($file, [__('Saldo a favor'), number_format($iva['saldo_a_favor'], 2, ',', '')], ';');
            fputcsv($file, [], ';');

            // Posición IIBB por jurisdicción.
            fputcsv($file, [__('Posición IIBB por jurisdicción')], ';');
            fputcsv($file, [
                __('Jurisdicción'),
                __('Base imponible'),
                __('Percepciones sufridas'),
                __('Retenciones sufridas'),
                __('A cuenta'),
            ], ';');

            foreach ($iibb as $j) {
                fputcsv($file, [
                    $j['jurisdiccion_nombre'],
                    number_format($j['base_imponible'], 2, ',', ''),
                    number_format($j['percepciones_sufridas'], 2, ',', ''),
                    number_format($j['retenciones_sufridas'], 2, ',', ''),
                    number_format($j['a_cuenta'], 2, ',', ''),
                ], ';');
            }

            fclose($file);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }

    public function render()
    {
        $cuits = Cuit::activos()->orderBy('razon_social')->get(['id', 'razon_social', 'numero_cuit']);

        $service = app(PosicionFiscalService::class);
        $cuit = $this->cuitId ? $cuits->firstWhere('id', $this->cuitId) : null;

        $posicionIva = null;
        $posicionIibb = [];

        if ($cuit && $this->periodo !== '') {
            $posicionIva = $service->posicionIva($cuit, $this->periodo);
            $posicionIibb = $service->posicionIibb($cuit, $this->periodo);
        }

        return view('livewire.fiscal.posicion-fiscal', [
            'cuits' => $cuits,
            'posicionIva' => $posicionIva,
            'posicionIibb' => $posicionIibb,
        ]);
    }
}

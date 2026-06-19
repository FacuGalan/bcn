<?php

namespace App\Livewire\Fiscal;

use App\Models\Cuit;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Services\Fiscal\ImpuestoService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Movimientos fiscales (sistema-impositivo RF-08): listado del ledger fiscal por
 * CUIT/período con alta manual y anulación por contraasiento. Cubre los impuestos
 * que no llegan por un origen automático (retenciones que nos hacen clientes,
 * percepciones sufridas fuera de MP/compras, ajustes).
 *
 * Global (filtro por CUIT, no sucursal-aware). Permiso: `func.fiscal.movimientos`.
 * El alta manual permite percepción/retención/tributo en ambos sentidos
 * (sufrido/aplicado); débito/crédito fiscal NO se cargan a mano (se generan solos
 * desde comprobantes/compras → se evita doble conteo).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-08, Fase 6 / Pantalla 2).
 */
#[Lazy]
#[Layout('layouts.app')]
class MovimientosFiscales extends Component
{
    use WithPagination;

    /** Naturalezas habilitadas para el alta manual (sin débito/crédito fiscal). */
    public const NATURALEZAS_MANUALES = [
        MovimientoFiscal::NATURALEZA_PERCEPCION,
        MovimientoFiscal::NATURALEZA_RETENCION,
        MovimientoFiscal::NATURALEZA_TRIBUTO,
    ];

    // ==================== Filtros ====================
    #[Url]
    public ?int $cuitId = null;

    #[Url]
    public string $periodo = '';

    #[Url]
    public string $filtroSentido = '';

    #[Url]
    public string $filtroNaturaleza = '';

    public bool $incluirAnulados = false;

    // ==================== Modal alta ====================
    public bool $mostrarModalAlta = false;

    public ?int $formCuitId = null;

    public ?int $formImpuestoId = null;

    public string $formSentido = MovimientoFiscal::SENTIDO_SUFRIDO;

    public string $formNaturaleza = MovimientoFiscal::NATURALEZA_RETENCION;

    public string $formFecha = '';

    public ?string $formBaseImponible = null;

    public ?string $formAlicuota = null;

    public ?string $formMonto = null;

    public ?string $formCertificadoNumero = null;

    public ?string $formObservaciones = null;

    // ==================== Modal anulación ====================
    public bool $mostrarModalAnulacion = false;

    public ?int $anularMovimientoId = null;

    public string $motivoAnulacion = '';

    public function mount(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.movimientos')) {
            abort(403, __('No tiene permiso para gestionar movimientos fiscales'));
        }

        $this->periodo = now()->format('Y-m');
        $this->cuitId = Cuit::activos()->orderBy('razon_social')->value('id');
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="4" :columns="7" :rows="6" />
        HTML;
    }

    public function updatingCuitId(): void
    {
        $this->resetPage();
    }

    public function updatingPeriodo(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroSentido(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroNaturaleza(): void
    {
        $this->resetPage();
    }

    public function updatingIncluirAnulados(): void
    {
        $this->resetPage();
    }

    // ==================== Alta manual ====================
    public function abrirModalAlta(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.movimientos')) {
            $this->dispatch('notify', message: __('No tiene permiso para gestionar movimientos fiscales'), type: 'error');

            return;
        }

        $this->resetValidation();
        $this->formCuitId = $this->cuitId;
        $this->formImpuestoId = null;
        $this->formSentido = MovimientoFiscal::SENTIDO_SUFRIDO;
        $this->formNaturaleza = MovimientoFiscal::NATURALEZA_RETENCION;
        $this->formFecha = now()->format('Y-m-d');
        $this->formBaseImponible = null;
        $this->formAlicuota = null;
        $this->formMonto = null;
        $this->formCertificadoNumero = null;
        $this->formObservaciones = null;
        $this->mostrarModalAlta = true;
    }

    /** Al elegir un impuesto, prefijar la naturaleza desde su default si es manual. */
    public function updatedFormImpuestoId($value): void
    {
        $impuesto = $value ? Impuesto::find($value) : null;
        if ($impuesto && in_array($impuesto->naturaleza_default, self::NATURALEZAS_MANUALES, true)) {
            $this->formNaturaleza = $impuesto->naturaleza_default;
        }
    }

    public function updatedFormBaseImponible(): void
    {
        $this->recalcularMontoDesdeBase();
    }

    public function updatedFormAlicuota(): void
    {
        $this->recalcularMontoDesdeBase();
    }

    /** Si hay base y alícuota, sugerir el monto = base × alícuota / 100 (editable). */
    private function recalcularMontoDesdeBase(): void
    {
        if (is_numeric($this->formBaseImponible) && is_numeric($this->formAlicuota)) {
            $this->formMonto = (string) round((float) $this->formBaseImponible * (float) $this->formAlicuota / 100, 2);
        }
    }

    public function registrarMovimiento(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.movimientos')) {
            $this->dispatch('notify', message: __('No tiene permiso para gestionar movimientos fiscales'), type: 'error');

            return;
        }

        $datos = $this->validate([
            'formCuitId' => ['required', 'integer'],
            'formImpuestoId' => ['required', 'integer'],
            'formSentido' => ['required', 'in:'.MovimientoFiscal::SENTIDO_SUFRIDO.','.MovimientoFiscal::SENTIDO_APLICADO],
            'formNaturaleza' => ['required', 'in:'.implode(',', self::NATURALEZAS_MANUALES)],
            'formFecha' => ['required', 'date'],
            'formMonto' => ['required', 'numeric', 'gt:0'],
            'formBaseImponible' => ['nullable', 'numeric', 'min:0'],
            'formAlicuota' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'formCertificadoNumero' => ['nullable', 'string', 'max:50'],
            'formObservaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(ImpuestoService::class)->registrarMovimientoFiscal([
                'cuit_id' => $datos['formCuitId'],
                'impuesto_id' => $datos['formImpuestoId'],
                'sentido' => $datos['formSentido'],
                'naturaleza' => $datos['formNaturaleza'],
                'fecha' => $datos['formFecha'],
                'monto' => $datos['formMonto'],
                'base_imponible' => $datos['formBaseImponible'] !== null ? (float) $datos['formBaseImponible'] : null,
                'alicuota' => $datos['formAlicuota'] !== null ? (float) $datos['formAlicuota'] : null,
                'certificado_numero' => $datos['formCertificadoNumero'] ?: null,
                'observaciones' => $datos['formObservaciones'] ?: null,
                'usuario_id' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->mostrarModalAlta = false;
        $this->dispatch('notify', message: __('Movimiento fiscal registrado'), type: 'success');
    }

    public function cerrarModalAlta(): void
    {
        $this->mostrarModalAlta = false;
        $this->resetValidation();
    }

    // ==================== Anulación ====================
    public function abrirModalAnulacion(int $movimientoId): void
    {
        $this->anularMovimientoId = $movimientoId;
        $this->motivoAnulacion = '';
        $this->mostrarModalAnulacion = true;
    }

    public function confirmarAnulacion(): void
    {
        if (! auth()->user()?->hasPermissionTo('func.fiscal.movimientos')) {
            $this->dispatch('notify', message: __('No tiene permiso para gestionar movimientos fiscales'), type: 'error');

            return;
        }

        $movimiento = $this->anularMovimientoId ? MovimientoFiscal::find($this->anularMovimientoId) : null;

        if (! $movimiento) {
            $this->dispatch('notify', message: __('Movimiento fiscal no encontrado'), type: 'error');
            $this->mostrarModalAnulacion = false;

            return;
        }

        try {
            app(ImpuestoService::class)->anularMovimientoFiscal(
                $movimiento,
                (int) auth()->id(),
                $this->motivoAnulacion ?: null,
            );
        } catch (\Throwable $e) {
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->mostrarModalAnulacion = false;
        $this->anularMovimientoId = null;
        $this->dispatch('notify', message: __('Movimiento fiscal anulado'), type: 'success');
    }

    public function cerrarModalAnulacion(): void
    {
        $this->mostrarModalAnulacion = false;
        $this->anularMovimientoId = null;
    }

    public function render()
    {
        $cuits = Cuit::activos()->orderBy('razon_social')->get(['id', 'razon_social', 'numero_cuit']);
        $impuestos = Impuesto::activos()->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'tipo', 'naturaleza_default']);

        $movimientos = MovimientoFiscal::query()
            ->with(['impuesto:id,codigo,nombre', 'cuit:id,razon_social,numero_cuit'])
            ->when($this->cuitId, fn ($q) => $q->where('cuit_id', $this->cuitId))
            ->when($this->periodo !== '', fn ($q) => $q->where('periodo_fiscal', $this->periodo))
            ->when($this->filtroSentido !== '', fn ($q) => $q->where('sentido', $this->filtroSentido))
            ->when($this->filtroNaturaleza !== '', fn ($q) => $q->where('naturaleza', $this->filtroNaturaleza))
            ->when(! $this->incluirAnulados, fn ($q) => $q->where('estado', MovimientoFiscal::ESTADO_ACTIVO))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.fiscal.movimientos-fiscales', [
            'cuits' => $cuits,
            'impuestos' => $impuestos,
            'movimientos' => $movimientos,
            'naturalezasManuales' => self::NATURALEZAS_MANUALES,
        ]);
    }
}

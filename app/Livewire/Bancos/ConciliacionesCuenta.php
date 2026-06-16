<?php

namespace App\Livewire\Bancos;

use App\Models\ConciliacionCuenta;
use App\Models\ConciliacionFila;
use App\Models\CuentaEmpresa;
use App\Services\IntegracionesPago\ConciliacionCuentaService;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Conciliaciones de CuentaEmpresa contra el proveedor de pago (Paso 3 de
 * integraciones-pago): listado de corridas + detalle de revisión con
 * aplicar/descartar.
 *
 * Las cuentas son globales del comercio (NO SucursalAware). Mientras una
 * corrida está `generando`, el detalle se refresca con wire:poll hasta que el
 * comando del scheduler la deja pendiente_revision.
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 4, RF-06).
 */
#[Layout('layouts.app')]
#[Lazy]
class ConciliacionesCuenta extends Component
{
    use WithPagination;

    // Filtros del listado.
    #[Url]
    public string $filtroCuenta = '';

    public string $filtroEstado = '';

    // Detalle de una corrida. El filtro arranca en 'novedades' (pseudo-valor
    // que agrupa coincide + solo_proveedor + solo_sistema): lo prioritario a
    // revisar, dejando afuera el ruido de ya_registrado.
    public ?int $detalleId = null;

    public string $filtroClasificacion = self::FILTRO_NOVEDADES;

    public const FILTRO_NOVEDADES = 'novedades';

    private const CLASIFICACIONES_NOVEDADES = [
        ConciliacionFila::CLASIFICACION_MATCHEADO,
        ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR,
        ConciliacionFila::CLASIFICACION_SOLO_SISTEMA,
    ];

    // Modal nueva conciliación.
    public bool $showModalNueva = false;

    public string $nuevaCuentaId = '';

    public string $nuevaDesde = '';

    public string $nuevaHasta = '';

    // Confirmaciones aplicar / descartar.
    public bool $showConfirmAplicar = false;

    public string $saldoFinalProveedor = '';

    public bool $showConfirmDescartar = false;

    public function updatedFiltroCuenta()
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado()
    {
        $this->resetPage();
    }

    // ==================== Nueva conciliación ====================

    public function abrirNueva(): void
    {
        $this->nuevaCuentaId = $this->filtroCuenta !== '' ? $this->filtroCuenta : '';
        $this->nuevaDesde = now()->subDays(7)->toDateString();
        $this->nuevaHasta = now()->toDateString();
        $this->showModalNueva = true;
    }

    public function crearCorrida(): void
    {
        $this->validate([
            'nuevaCuentaId' => 'required',
            'nuevaDesde' => 'required|date',
            'nuevaHasta' => 'required|date|after_or_equal:nuevaDesde',
        ], [], [
            'nuevaCuentaId' => __('Cuenta'),
            'nuevaDesde' => __('Desde'),
            'nuevaHasta' => __('Hasta'),
        ]);

        $cuenta = CuentaEmpresa::findOrFail((int) $this->nuevaCuentaId);

        try {
            $corrida = app(ConciliacionCuentaService::class)->crearCorrida(
                $cuenta,
                Carbon::parse($this->nuevaDesde),
                Carbon::parse($this->nuevaHasta),
                auth()->id(),
            );
        } catch (\RuntimeException $e) {
            $this->dispatch('toast-error', message: $e->getMessage());

            return;
        }

        $this->showModalNueva = false;
        $this->detalleId = $corrida->id;
        $this->dispatch('toast-success', message: __('Conciliación iniciada: se está pidiendo el reporte al proveedor'));
    }

    public function cancelarNueva(): void
    {
        $this->showModalNueva = false;
    }

    // ==================== Detalle ====================

    public function verDetalle(int $id): void
    {
        $this->detalleId = $id;
        $this->filtroClasificacion = self::FILTRO_NOVEDADES;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
        $this->showConfirmAplicar = false;
        $this->showConfirmDescartar = false;
    }

    /**
     * Alterna generar_movimiento ↔ ignorar en una fila propuesta (solo
     * mientras la corrida está en revisión).
     */
    public function toggleAccionFila(int $filaId): void
    {
        $corrida = $this->detalle;

        if (! $corrida || ! $corrida->esEditable()) {
            return;
        }

        $fila = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->find($filaId);

        if (! $fila || ! $fila->esPropuesta()) {
            return;
        }

        $fila->update([
            'accion' => $fila->accion === ConciliacionFila::ACCION_GENERAR_MOVIMIENTO
                ? ConciliacionFila::ACCION_IGNORAR
                : ConciliacionFila::ACCION_GENERAR_MOVIMIENTO,
        ]);

        app(ConciliacionCuentaService::class)->recalcularTotales($corrida);
    }

    // ==================== Aplicar / Descartar ====================

    public function confirmarAplicar(): void
    {
        if (! $this->puedeAplicar) {
            return;
        }

        $this->saldoFinalProveedor = '';
        $this->showConfirmAplicar = true;
    }

    public function aplicar(): void
    {
        $corrida = $this->detalle;

        if (! $corrida || ! $this->puedeAplicar) {
            return;
        }

        $saldoFinal = null;
        if ($this->esPrimeraConciliacion && trim($this->saldoFinalProveedor) !== '') {
            $saldoFinal = $this->parsearMonto($this->saldoFinalProveedor);
        }

        try {
            app(ConciliacionCuentaService::class)->aplicar($corrida, auth()->id(), $saldoFinal);
        } catch (\RuntimeException $e) {
            $this->dispatch('toast-error', message: $e->getMessage());

            return;
        }

        $this->showConfirmAplicar = false;
        $this->dispatch('toast-success', message: __('Conciliación aplicada: los ajustes se registraron en la cuenta'));
    }

    public function confirmarDescartar(): void
    {
        if (! $this->puedeAplicar) {
            return;
        }

        $this->showConfirmDescartar = true;
    }

    public function descartar(): void
    {
        $corrida = $this->detalle;

        if (! $corrida || ! $this->puedeAplicar) {
            return;
        }

        try {
            app(ConciliacionCuentaService::class)->descartar($corrida, auth()->id());
        } catch (\RuntimeException $e) {
            $this->dispatch('toast-error', message: $e->getMessage());

            return;
        }

        $this->showConfirmDescartar = false;
        $this->dispatch('toast-success', message: __('Conciliación descartada'));
    }

    /**
     * Tolera formato argentino ("23.607.226,75") y de máquina ("23607226.75").
     */
    private function parsearMonto(string $valor): float
    {
        $valor = trim(str_replace(' ', '', $valor));

        if (str_contains($valor, ',')) {
            // Con coma decimal, los puntos son separadores de miles.
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif (substr_count($valor, '.') > 1) {
            // Varios puntos sin coma: son miles ("23.607.226").
            $valor = str_replace('.', '', $valor);
        }

        return (float) $valor;
    }

    // ==================== Computed ====================

    public function getPuedeAplicarProperty(): bool
    {
        return auth()->user()?->hasPermissionTo('func.conciliaciones.aplicar') ?? false;
    }

    public function getDetalleProperty(): ?ConciliacionCuenta
    {
        if ($this->detalleId === null) {
            return null;
        }

        return ConciliacionCuenta::with('cuentaEmpresa')->find($this->detalleId);
    }

    /**
     * ¿La corrida del detalle es la primera de su cuenta? Habilita el campo
     * de ajuste inicial (RF-07).
     */
    public function getEsPrimeraConciliacionProperty(): bool
    {
        $corrida = $this->detalle;

        if (! $corrida) {
            return false;
        }

        return ! ConciliacionCuenta::deCuenta($corrida->cuenta_empresa_id)
            ->where('id', '!=', $corrida->id)
            ->where('estado', ConciliacionCuenta::ESTADO_APLICADA)
            ->exists();
    }

    public function getCuentasConciliablesProperty()
    {
        return CuentaEmpresa::activas()
            ->whereNotNull('identificador_externo')
            ->orderBy('nombre')
            ->get();
    }

    public function placeholder()
    {
        return <<<'HTML'
        <x-skeleton.page-table :statCards="0" :filterCount="2" :columns="6" :rows="6" />
        HTML;
    }

    public function render()
    {
        $detalle = $this->detalle;
        $filas = collect();
        $soloSistemaRecientes = 0;
        $avisoCuentaSinCuit = false;

        if ($detalle) {
            $filas = ConciliacionFila::with('impuesto')
                ->where('conciliacion_cuenta_id', $detalle->id)
                ->when($this->filtroClasificacion === self::FILTRO_NOVEDADES, fn ($q) => $q->whereIn('clasificacion', self::CLASIFICACIONES_NOVEDADES))
                ->when($this->filtroClasificacion !== '' && $this->filtroClasificacion !== self::FILTRO_NOVEDADES, fn ($q) => $q->where('clasificacion', $this->filtroClasificacion))
                ->orderByRaw("FIELD(clasificacion, 'solo_proveedor', 'matcheado', 'ya_registrado', 'solo_sistema')")
                ->orderBy('fecha')
                ->get();

            // Aviso fiscal (RF-07): la cuenta no tiene CUIT asignado pero el
            // reporte trae impuestos identificados → no se generará el ledger
            // fiscal de esos impuestos hasta asignarle un CUIT a la cuenta.
            if ($detalle->cuentaEmpresa && $detalle->cuentaEmpresa->cuit_id === null) {
                $avisoCuentaSinCuit = ConciliacionFila::where('conciliacion_cuenta_id', $detalle->id)
                    ->whereNotNull('impuesto_id')
                    ->exists();
            }

            // Hint del lag: cobros del sistema sin contraparte en el reporte
            // pero muy recientes — el pipeline de reportes del proveedor corre
            // con horas de demora, casi seguro se concilian en la próxima corrida.
            if ($detalle->esEditable()) {
                $soloSistemaRecientes = ConciliacionFila::where('conciliacion_cuenta_id', $detalle->id)
                    ->where('clasificacion', ConciliacionFila::CLASIFICACION_SOLO_SISTEMA)
                    ->where('fecha', '>=', now()->subDay())
                    ->count();
            }
        }

        $corridas = ConciliacionCuenta::with('cuentaEmpresa')
            ->when($this->filtroCuenta !== '', fn ($q) => $q->where('cuenta_empresa_id', (int) $this->filtroCuenta))
            ->when($this->filtroEstado !== '', fn ($q) => $q->where('estado', $this->filtroEstado))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.bancos.conciliaciones-cuenta', [
            'corridas' => $corridas,
            'detalle' => $detalle,
            'filas' => $filas,
            'soloSistemaRecientes' => $soloSistemaRecientes,
            'avisoCuentaSinCuit' => $avisoCuentaSinCuit,
        ]);
    }
}

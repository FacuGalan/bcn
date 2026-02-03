<?php

namespace App\Livewire\Tesoreria;

use Livewire\Component;
use App\Models\Tesoreria;
use App\Models\MovimientoTesoreria;
use App\Models\ProvisionFondo;
use App\Models\RendicionFondo;
use App\Models\DepositoBancario;
use App\Models\ArqueoTesoreria;
use App\Models\Caja;
use App\Services\TesoreriaService;
use App\Services\SucursalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Componente Livewire: Reportes de Tesorería
 *
 * Reportes disponibles:
 * - Libro de tesorería (movimientos por período)
 * - Resumen de cajas por período
 * - Trazabilidad de efectivo
 * - Conciliación tesorería-banco
 */
class ReportesTesoreria extends Component
{
    // Tesorería activa
    public ?Tesoreria $tesoreria = null;

    // Tipo de reporte seleccionado
    public string $tipoReporte = 'libro';

    // Filtros
    public string $fechaDesde = '';
    public string $fechaHasta = '';
    public ?int $cajaId = null;
    public string $concepto = '';

    // Datos del reporte
    public array $datosReporte = [];
    public array $resumen = [];

    public function mount(): void
    {
        $this->fechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->fechaHasta = now()->format('Y-m-d');

        $this->cargarTesoreria();
    }

    public function cargarTesoreria(): void
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return;
        }

        $this->tesoreria = TesoreriaService::obtenerOCrear($sucursalId);
    }

    /**
     * Genera el reporte según el tipo seleccionado
     */
    public function generarReporte(): void
    {
        if (!$this->tesoreria) {
            return;
        }

        $desde = Carbon::parse($this->fechaDesde)->startOfDay();
        $hasta = Carbon::parse($this->fechaHasta)->endOfDay();

        switch ($this->tipoReporte) {
            case 'libro':
                $this->generarLibroTesoreria($desde, $hasta);
                break;
            case 'cajas':
                $this->generarResumenCajas($desde, $hasta);
                break;
            case 'trazabilidad':
                $this->generarTrazabilidad($desde, $hasta);
                break;
            case 'arqueos':
                $this->generarReporteArqueos($desde, $hasta);
                break;
        }
    }

    /**
     * Libro de Tesorería - Movimientos detallados con saldos
     */
    protected function generarLibroTesoreria(Carbon $desde, Carbon $hasta): void
    {
        $query = MovimientoTesoreria::where('tesoreria_id', $this->tesoreria->id)
            ->whereBetween('created_at', [$desde, $hasta])
            ->with('usuario')
            ->orderBy('created_at', 'asc');

        if ($this->concepto) {
            $query->where('concepto', 'like', "%{$this->concepto}%");
        }

        $movimientos = $query->get();

        // Obtener saldo inicial del período
        $primerMovimiento = $movimientos->first();
        $saldoInicial = $primerMovimiento ? $primerMovimiento->saldo_anterior : $this->tesoreria->saldo_actual;

        $this->datosReporte = $movimientos->map(function ($mov) {
            return [
                'fecha' => $mov->created_at->format('d/m/Y H:i'),
                'concepto' => $mov->concepto,
                'usuario' => $mov->usuario->name ?? __('Sistema'),
                'tipo' => $mov->tipo,
                'monto' => $mov->monto,
                'saldo_anterior' => $mov->saldo_anterior,
                'saldo_posterior' => $mov->saldo_posterior,
                'observaciones' => $mov->observaciones,
            ];
        })->toArray();

        $this->resumen = [
            'periodo' => [
                'desde' => $desde->format('d/m/Y'),
                'hasta' => $hasta->format('d/m/Y'),
            ],
            'saldo_inicial' => $saldoInicial,
            'saldo_final' => $movimientos->last()?->saldo_posterior ?? $saldoInicial,
            'total_ingresos' => $movimientos->where('tipo', 'ingreso')->sum('monto'),
            'total_egresos' => $movimientos->where('tipo', 'egreso')->sum('monto'),
            'cantidad_movimientos' => $movimientos->count(),
            'por_concepto' => $movimientos->groupBy('concepto')->map(function ($grupo) {
                return [
                    'cantidad' => $grupo->count(),
                    'ingresos' => $grupo->where('tipo', 'ingreso')->sum('monto'),
                    'egresos' => $grupo->where('tipo', 'egreso')->sum('monto'),
                ];
            })->toArray(),
        ];
    }

    /**
     * Resumen de Cajas - Provisiones y rendiciones por caja
     */
    protected function generarResumenCajas(Carbon $desde, Carbon $hasta): void
    {
        $sucursalId = SucursalService::getSucursalActiva();

        // Obtener todas las cajas de la sucursal
        $cajas = Caja::where('sucursal_id', $sucursalId)
            ->orderBy('nombre')
            ->get();

        $datosPorCaja = [];

        foreach ($cajas as $caja) {
            $provisiones = ProvisionFondo::where('caja_id', $caja->id)
                ->where('tesoreria_id', $this->tesoreria->id)
                ->whereBetween('fecha', [$desde, $hasta])
                ->confirmados()
                ->get();

            $rendiciones = RendicionFondo::where('caja_id', $caja->id)
                ->where('tesoreria_id', $this->tesoreria->id)
                ->whereBetween('fecha', [$desde, $hasta])
                ->get();

            $totalProvisiones = $provisiones->sum('monto');
            $totalRendiciones = $rendiciones->sum('monto_entregado');
            $totalDiferencias = $rendiciones->sum('diferencia');

            $datosPorCaja[] = [
                'caja_id' => $caja->id,
                'caja_nombre' => $caja->nombre,
                'caja_numero' => $caja->numero_formateado,
                'cantidad_provisiones' => $provisiones->count(),
                'total_provisiones' => $totalProvisiones,
                'cantidad_rendiciones' => $rendiciones->count(),
                'total_rendiciones' => $totalRendiciones,
                'total_diferencias' => $totalDiferencias,
                'sobrantes' => $rendiciones->where('diferencia', '>', 0)->sum('diferencia'),
                'faltantes' => abs($rendiciones->where('diferencia', '<', 0)->sum('diferencia')),
                'balance' => $totalRendiciones - $totalProvisiones,
            ];
        }

        $this->datosReporte = $datosPorCaja;

        $this->resumen = [
            'periodo' => [
                'desde' => $desde->format('d/m/Y'),
                'hasta' => $hasta->format('d/m/Y'),
            ],
            'total_provisiones' => collect($datosPorCaja)->sum('total_provisiones'),
            'total_rendiciones' => collect($datosPorCaja)->sum('total_rendiciones'),
            'total_diferencias' => collect($datosPorCaja)->sum('total_diferencias'),
            'total_sobrantes' => collect($datosPorCaja)->sum('sobrantes'),
            'total_faltantes' => collect($datosPorCaja)->sum('faltantes'),
            'cajas_con_operaciones' => collect($datosPorCaja)->filter(function ($c) {
                return $c['cantidad_provisiones'] > 0 || $c['cantidad_rendiciones'] > 0;
            })->count(),
        ];
    }

    /**
     * Trazabilidad de Efectivo - Seguimiento detallado
     */
    protected function generarTrazabilidad(Carbon $desde, Carbon $hasta): void
    {
        $trazabilidad = [];

        // Provisiones
        $provisiones = ProvisionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->with(['caja', 'usuarioEntrega', 'usuarioRecibe'])
            ->orderBy('fecha', 'asc')
            ->get();

        foreach ($provisiones as $prov) {
            $trazabilidad[] = [
                'fecha' => $prov->fecha->format('d/m/Y H:i'),
                'tipo' => 'provision',
                'origen' => __('Tesoreria'),
                'destino' => $prov->caja->nombre ?? __('Caja'),
                'monto' => $prov->monto,
                'usuario_entrega' => $prov->usuarioEntrega->name ?? 'N/A',
                'usuario_recibe' => $prov->usuarioRecibe->name ?? 'N/A',
                'estado' => $prov->estado,
                'observaciones' => $prov->observaciones,
            ];
        }

        // Rendiciones
        $rendiciones = RendicionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->with(['caja', 'usuarioEntrega', 'usuarioRecibe'])
            ->orderBy('fecha', 'asc')
            ->get();

        foreach ($rendiciones as $rend) {
            $trazabilidad[] = [
                'fecha' => $rend->fecha->format('d/m/Y H:i'),
                'tipo' => 'rendicion',
                'origen' => $rend->caja->nombre ?? __('Caja'),
                'destino' => __('Tesoreria'),
                'monto' => $rend->monto_entregado,
                'monto_sistema' => $rend->monto_sistema,
                'diferencia' => $rend->diferencia,
                'usuario_entrega' => $rend->usuarioEntrega->name ?? 'N/A',
                'usuario_recibe' => $rend->usuarioRecibe->name ?? __('Pendiente'),
                'estado' => $rend->estado,
                'observaciones' => $rend->observaciones,
            ];
        }

        // Depósitos
        $depositos = DepositoBancario::where('tesoreria_id', $this->tesoreria->id)
            ->whereBetween('fecha_deposito', [$desde, $hasta])
            ->with(['cuentaBancaria', 'usuario'])
            ->orderBy('fecha_deposito', 'asc')
            ->get();

        foreach ($depositos as $dep) {
            $trazabilidad[] = [
                'fecha' => $dep->fecha_deposito->format('d/m/Y'),
                'tipo' => 'deposito',
                'origen' => __('Tesoreria'),
                'destino' => $dep->cuentaBancaria->nombre_completo ?? __('Banco'),
                'monto' => $dep->monto,
                'usuario_entrega' => $dep->usuario->name ?? 'N/A',
                'estado' => $dep->estado,
                'comprobante' => $dep->numero_comprobante,
                'observaciones' => $dep->observaciones,
            ];
        }

        // Ordenar por fecha
        usort($trazabilidad, function ($a, $b) {
            return strcmp($a['fecha'], $b['fecha']);
        });

        $this->datosReporte = $trazabilidad;

        $this->resumen = [
            'periodo' => [
                'desde' => $desde->format('d/m/Y'),
                'hasta' => $hasta->format('d/m/Y'),
            ],
            'provisiones' => [
                'cantidad' => $provisiones->count(),
                'total' => $provisiones->sum('monto'),
            ],
            'rendiciones' => [
                'cantidad' => $rendiciones->count(),
                'total' => $rendiciones->sum('monto_entregado'),
                'diferencia_total' => $rendiciones->sum('diferencia'),
            ],
            'depositos' => [
                'cantidad' => $depositos->count(),
                'total' => $depositos->sum('monto'),
            ],
        ];
    }

    /**
     * Reporte de Arqueos
     */
    protected function generarReporteArqueos(Carbon $desde, Carbon $hasta): void
    {
        $arqueos = ArqueoTesoreria::where('tesoreria_id', $this->tesoreria->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->with(['usuario', 'supervisor'])
            ->orderBy('fecha', 'desc')
            ->get();

        $this->datosReporte = $arqueos->map(function ($arq) {
            return [
                'fecha' => $arq->fecha->format('d/m/Y H:i'),
                'usuario' => $arq->usuario->name ?? 'N/A',
                'supervisor' => $arq->supervisor->name ?? 'N/A',
                'saldo_sistema' => $arq->saldo_sistema,
                'saldo_contado' => $arq->saldo_contado,
                'diferencia' => $arq->diferencia,
                'tipo_diferencia' => $arq->tipo_diferencia,
                'estado' => $arq->estado,
                'observaciones' => $arq->observaciones,
            ];
        })->toArray();

        $this->resumen = [
            'periodo' => [
                'desde' => $desde->format('d/m/Y'),
                'hasta' => $hasta->format('d/m/Y'),
            ],
            'total_arqueos' => $arqueos->count(),
            'arqueos_cuadrados' => $arqueos->where('diferencia', 0)->count(),
            'arqueos_con_sobrante' => $arqueos->where('diferencia', '>', 0)->count(),
            'arqueos_con_faltante' => $arqueos->where('diferencia', '<', 0)->count(),
            'total_sobrantes' => $arqueos->where('diferencia', '>', 0)->sum('diferencia'),
            'total_faltantes' => abs($arqueos->where('diferencia', '<', 0)->sum('diferencia')),
        ];
    }

    /**
     * Obtiene las cajas disponibles para filtro
     */
    public function getCajasProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return collect();
        }

        return Caja::where('sucursal_id', $sucursalId)
            ->orderBy('nombre')
            ->get();
    }

    public function updatedTipoReporte(): void
    {
        $this->datosReporte = [];
        $this->resumen = [];
    }

    public function render()
    {
        return view('livewire.tesoreria.reportes-tesoreria', [
            'cajas' => $this->cajas,
        ]);
    }
}

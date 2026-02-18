<?php

namespace App\Livewire\Cajas;

use Livewire\Component;
use App\Models\Caja;
use App\Models\CierreTurno;
use App\Models\CierreTurnoCaja;
use App\Models\MovimientoCaja;
use App\Models\GrupoCierre;
use App\Models\ConceptoPago;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\Cobro;
use App\Models\CobroPago;
use App\Services\CajaService;
use App\Services\SucursalService;
use App\Services\TesoreriaService;
use App\Models\Tesoreria;
use App\Traits\SucursalAware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Componente Livewire: Turno Actual
 *
 * CONCEPTOS IMPORTANTES:
 * =====================
 * - TURNO: Período de trabajo con arqueo al cierre (genera CierreTurno)
 * - CAJA ACTIVA/INACTIVA: Si puede recibir operaciones durante el turno
 *
 * RESPONSABILIDADES:
 * =================
 * 1. Mostrar estado del turno actual (cajas abiertas + cerradas hoy)
 * 2. Agrupar cajas visualmente por grupo de cierre
 * 3. Activar/desactivar cajas sin cerrar turno
 * 4. Abrir/cerrar turno con arqueo
 * 5. Mostrar resumen de movimientos e ingresos/egresos
 */
class TurnoActual extends Component
{
    use SucursalAware;

    // Filtros y opciones de visualización
    public string $vistaMovimientos = 'agrupado';
    public ?int $cajaExpandida = null;
    public array $conceptosExpandidos = [];

    // Modal de cierre de turno
    public bool $showCierreModal = false;
    public ?int $cajaCierreId = null;
    public ?int $grupoCierreId = null;
    public array $cajasACerrar = [];
    public array $saldosDeclarados = [];
    public string $observacionesCierre = '';
    public bool $esCierreGrupal = false;
    public bool $cierreUsaFondoComun = false;
    public float $saldoFondoComunCierre = 0;
    public $saldoDeclaradoFondoComun = '';

    // Modal de apertura de turno
    public bool $showAperturaModal = false;
    public ?int $cajaAperturaId = null;
    public ?int $grupoAperturaId = null;
    public array $cajasAAbrir = [];
    public array $fondosIniciales = [];
    public bool $esAperturaGrupal = false;
    public bool $grupoUsaFondoComun = false;
    public $fondoComunTotal = ''; // String para permitir input vacío

    // Modal de detalle de movimientos
    public bool $showDetalleModal = false;
    public ?int $cajaDetalleId = null;
    public array $detalleMovimientos = [];
    public array $detalleOtrosConceptos = [];
    public array $detalleInfo = [];

    // Datos calculados
    public Collection $cajas;
    public array $totalesGenerales = [];
    public array $cajasTotalesPorConcepto = [];
    public array $cajasResumenMovimientos = [];
    public array $cajasOperaciones = [];

    protected $listeners = ['caja-actualizada' => 'cargarCajas'];

    public function mount(): void
    {
        $this->cajas = collect();
        $this->cargarCajas();
    }

    /**
     * Hook llamado cuando cambia la sucursal (desde SucursalAware trait)
     */
    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        // Cerrar modales abiertos
        $this->showCierreModal = false;
        $this->showAperturaModal = false;
        $this->showDetalleModal = false;

        // Recargar cajas de la nueva sucursal
        $this->cargarCajas();
    }

    /**
     * Carga las cajas del usuario organizadas por grupo
     * Muestra TODAS las cajas activas de la sucursal, incluyendo las que nunca fueron abiertas
     */
    public function cargarCajas(): void
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            $this->cajas = collect();
            return;
        }

        $cajaIdsPermitidas = $this->getCajaIdsPermitidas($sucursalId);

        // Query base: TODAS las cajas activas de la sucursal
        $query = Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->with(['grupoCierre', 'movimientos' => function ($q) {
                $q->whereNull('cierre_turno_id')
                  ->orderBy('created_at', 'desc');
            }]);

        if ($cajaIdsPermitidas !== null) {
            $query->whereIn('id', $cajaIdsPermitidas);
        }

        // Ya no filtramos por estado - mostramos TODAS las cajas activas

        $this->cajas = $query->orderBy('grupo_cierre_id')->orderBy('numero')->get();

        // Limpiar arrays de datos calculados
        $this->cajasTotalesPorConcepto = [];
        $this->cajasResumenMovimientos = [];
        $this->cajasOperaciones = [];

        // Calcular totales por caja
        foreach ($this->cajas as $caja) {
            $totalesPorConcepto = $this->calcularTotalesPorConcepto($caja);
            $resumenMovimientos = $this->calcularResumenMovimientos($caja);
            $cantidadOperaciones = $this->contarOperaciones($caja);

            // Guardar en arrays separados para persistir entre re-renders
            $this->cajasTotalesPorConcepto[$caja->id] = $totalesPorConcepto;
            $this->cajasResumenMovimientos[$caja->id] = $resumenMovimientos;
            $this->cajasOperaciones[$caja->id] = $cantidadOperaciones;

            // También asignar al modelo para uso en la vista
            $caja->totalesPorConcepto = $totalesPorConcepto;
            $caja->resumenMovimientos = $resumenMovimientos;
            $caja->cantidadOperaciones = $cantidadOperaciones;
            $caja->tieneMovimientosPendientes = $caja->movimientos->count() > 0;
            // Indicador si la caja nunca fue abierta
            $caja->nuncaAbierta = $caja->fecha_apertura === null;

            // Para cajas en grupos con fondo común: verificar si el turno del grupo está activo
            // El turno del grupo está activo si tiene saldo_fondo_comun > 0 o alguna caja abierta
            $caja->turnoGrupoActivo = $this->verificarTurnoGrupoActivo($caja);
        }

        $this->calcularTotalesGenerales();
    }

    /**
     * Obtiene los IDs de cajas permitidas para el usuario
     */
    protected function getCajaIdsPermitidas(int $sucursalId): ?array
    {
        try {
            $cajaIds = DB::connection('pymes_tenant')
                ->table('user_cajas')
                ->where('user_id', auth()->id())
                ->where('sucursal_id', $sucursalId)
                ->pluck('caja_id')
                ->toArray();

            return empty($cajaIds) ? null : $cajaIds;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Cuenta las operaciones (ventas + cobros) del turno actual
     * Solo retorna 0 si el turno está realmente cerrado (sin movimientos pendientes)
     * Si está pausada pero con movimientos pendientes, sigue contando
     */
    protected function contarOperaciones(Caja $caja): int
    {
        // Si el turno está cerrado (sin movimientos pendientes), no hay operaciones
        if ($this->esTurnoCerrado($caja)) {
            return 0;
        }

        $ventasCount = VentaPago::whereHas('venta', function ($q) use ($caja) {
            $q->where('caja_id', $caja->id)
              ->where('estado', 'completada')
              ->where('created_at', '>=', $caja->fecha_apertura ?? today());
        })->count();

        $cobrosCount = CobroPago::whereHas('cobro', function ($q) use ($caja) {
            $q->where('caja_id', $caja->id)
              ->where('estado', 'activo')
              ->where('created_at', '>=', $caja->fecha_apertura ?? today());
        })->count();

        return $ventasCount + $cobrosCount;
    }

    /**
     * Determina si el turno de una caja está realmente cerrado
     * (cerrada Y sin movimientos pendientes Y no pertenece a grupo con turno activo)
     * Una caja "pausada" tiene estado cerrada pero aún tiene movimientos pendientes
     * o pertenece a un grupo con fondo común que tiene turno activo
     */
    protected function esTurnoCerrado(Caja $caja): bool
    {
        // Si nunca fue abierta, el turno está cerrado
        if ($caja->fecha_apertura === null) {
            return true;
        }

        // Si está abierta, el turno no está cerrado
        if ($caja->estado === 'abierta') {
            return false;
        }

        // Si está cerrada, verificar si tiene movimientos pendientes
        // Si tiene movimientos pendientes = está pausada (turno aún abierto)
        $movimientos = $caja->movimientos ?? collect();
        if (!$movimientos->isEmpty()) {
            return false;
        }

        // Para cajas en grupos con fondo común: verificar si el turno del grupo está activo
        if ($this->verificarTurnoGrupoActivo($caja)) {
            return false; // El turno del grupo sigue activo, la caja está pausada
        }

        // No tiene movimientos pendientes y no está en grupo activo = turno cerrado
        return true;
    }

    /**
     * Verifica si una caja pertenece a un grupo con turno activo
     * Aplica solo a grupos con fondo común
     */
    protected function verificarTurnoGrupoActivo(Caja $caja): bool
    {
        // Si no tiene grupo, no aplica
        if (!$caja->grupo_cierre_id || !$caja->grupoCierre) {
            return false;
        }

        $grupo = $caja->grupoCierre;

        // Solo aplica a grupos con fondo común
        if (!$grupo->fondo_comun) {
            return false;
        }

        // El turno del grupo está activo si:
        // 1. Tiene saldo_fondo_comun > 0 (se abrió el turno con fondo)
        if (($grupo->saldo_fondo_comun ?? 0) > 0) {
            return true;
        }

        // 2. Alguna caja del grupo está abierta (no pausada)
        if ($grupo->tieneAlgunaAbierta()) {
            return true;
        }

        // 3. Alguna caja del grupo tiene movimientos pendientes (sin cierre_turno_id)
        $cajasGrupo = $this->cajas->where('grupo_cierre_id', $caja->grupo_cierre_id);
        foreach ($cajasGrupo as $cajaGrupo) {
            if (($cajaGrupo->movimientos ?? collect())->isNotEmpty()) {
                return true;
            }
        }

        // 4. Alguna caja del grupo tiene fecha_apertura > fecha_cierre (fue abierta recientemente)
        // Esto cubre el caso donde el fondo es $0 pero el turno fue abierto
        foreach ($cajasGrupo as $cajaGrupo) {
            if ($cajaGrupo->fecha_apertura !== null) {
                // Si fecha_cierre es null o fecha_apertura > fecha_cierre, el turno está activo
                if ($cajaGrupo->fecha_cierre === null || $cajaGrupo->fecha_apertura > $cajaGrupo->fecha_cierre) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Calcula los totales por concepto de pago para una caja
     * Solo retorna vacío si el turno está realmente cerrado
     */
    protected function calcularTotalesPorConcepto(Caja $caja): array
    {
        // Si el turno está cerrado, no hay conceptos
        if ($this->esTurnoCerrado($caja)) {
            return [];
        }

        $totales = [];

        $ventasPagos = VentaPago::whereHas('venta', function ($q) use ($caja) {
                $q->where('caja_id', $caja->id)
                  ->where('estado', 'completada')
                  ->where('created_at', '>=', $caja->fecha_apertura ?? today());
            })
            ->with(['conceptoPago', 'formaPago'])
            ->get();

        foreach ($ventasPagos as $pago) {
            $conceptoCodigo = $pago->conceptoPago?->codigo ?? 'otros';
            $conceptoNombre = $pago->conceptoPago?->nombre ?? 'Otros';

            if (!isset($totales[$conceptoCodigo])) {
                $totales[$conceptoCodigo] = [
                    'codigo' => $conceptoCodigo,
                    'nombre' => $conceptoNombre,
                    'monto' => 0,
                    'cantidad' => 0,
                ];
            }

            $totales[$conceptoCodigo]['monto'] += $pago->monto_final;
            $totales[$conceptoCodigo]['cantidad']++;
        }

        return $totales;
    }

    /**
     * Calcula resumen de movimientos por tipo
     * EXCLUYE movimientos de apertura y provisión de fondo de los ingresos operativos
     * Solo retorna ceros si el turno está realmente cerrado
     */
    protected function calcularResumenMovimientos(Caja $caja): array
    {
        // Si el turno está cerrado, no hay movimientos
        if ($this->esTurnoCerrado($caja)) {
            return [
                'ingresos' => 0,
                'egresos' => 0,
                'cantidadIngresos' => 0,
                'cantidadEgresos' => 0,
                'saldoCalculado' => 0,
                'totalMovimientos' => 0,
            ];
        }

        $movimientos = $caja->movimientos ?? collect();

        // Filtrar movimientos operativos (excluir apertura y provisiones de fondo)
        $tiposExcluidos = ['apertura', 'provision_fondo', 'rendicion_fondo'];
        $movimientosOperativos = $movimientos->filter(function ($m) use ($tiposExcluidos) {
            return !in_array($m->referencia_tipo, $tiposExcluidos);
        });

        $ingresos = $movimientosOperativos->where('tipo', 'ingreso')->sum('monto');
        $egresos = $movimientosOperativos->where('tipo', 'egreso')->sum('monto');

        return [
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'cantidadIngresos' => $movimientosOperativos->where('tipo', 'ingreso')->count(),
            'cantidadEgresos' => $movimientosOperativos->where('tipo', 'egreso')->count(),
            'saldoCalculado' => $caja->saldo_inicial + $ingresos - $egresos,
            'totalMovimientos' => $movimientos->count(),
        ];
    }

    /**
     * Calcula totales generales de todas las cajas
     * Considera el fondo común de los grupos cuando aplica
     * Incluye cajas pausadas que aún tienen turno activo
     */
    protected function calcularTotalesGenerales(): void
    {
        // Incluir cajas abiertas Y cajas pausadas con turno activo (movimientos pendientes)
        $cajasConTurnoActivo = $this->cajas->filter(function ($caja) {
            return !$this->esTurnoCerrado($caja);
        });

        // Calcular saldo inicial considerando fondos comunes de grupos
        $saldoInicial = 0;
        $gruposContados = []; // Para no contar el mismo grupo múltiples veces

        foreach ($cajasConTurnoActivo as $caja) {
            if ($caja->grupo_cierre_id && $caja->grupoCierre && $caja->grupoCierre->fondo_comun) {
                // Caja con fondo común: sumar el fondo del grupo (solo una vez)
                if (!in_array($caja->grupo_cierre_id, $gruposContados)) {
                    $saldoInicial += $caja->grupoCierre->saldo_fondo_comun ?? 0;
                    $gruposContados[] = $caja->grupo_cierre_id;
                }
            } else {
                // Caja individual o grupo sin fondo común: sumar saldo de la caja
                $saldoInicial += $caja->saldo_inicial;
            }
        }

        // Contar cajas activas (abiertas) vs pausadas/cerradas
        $cajasActivas = $cajasConTurnoActivo->where('estado', 'abierta')->count();
        $cajasPausadas = $cajasConTurnoActivo->where('estado', 'cerrada')->count();
        $cajasSinTurno = $this->cajas->count() - $cajasConTurnoActivo->count();

        $this->totalesGenerales = [
            'cajasAbiertas' => $cajasActivas,
            'cajasPausadas' => $cajasPausadas,
            'cajasCerradas' => $cajasSinTurno,
            'saldoInicial' => $saldoInicial,
            'saldoActual' => 0,
            'ingresos' => 0,
            'egresos' => 0,
            'operaciones' => 0,
            'movimientos' => 0,
            'porConcepto' => [],
        ];

        // Calcular ingresos, egresos y saldo actual
        $gruposContados = [];
        foreach ($cajasConTurnoActivo as $caja) {
            $this->totalesGenerales['ingresos'] += $caja->resumenMovimientos['ingresos'] ?? 0;
            $this->totalesGenerales['egresos'] += $caja->resumenMovimientos['egresos'] ?? 0;
            $this->totalesGenerales['operaciones'] += $caja->cantidadOperaciones ?? 0;
            $this->totalesGenerales['movimientos'] += $caja->resumenMovimientos['totalMovimientos'] ?? 0;

            // Saldo actual: para fondo común, es fondo_grupo + ingresos - egresos de todas las cajas
            if ($caja->grupo_cierre_id && $caja->grupoCierre && $caja->grupoCierre->fondo_comun) {
                if (!in_array($caja->grupo_cierre_id, $gruposContados)) {
                    // Sumar el fondo común del grupo
                    $this->totalesGenerales['saldoActual'] += $caja->grupoCierre->saldo_fondo_comun ?? 0;
                    $gruposContados[] = $caja->grupo_cierre_id;
                }
                // Sumar movimientos de esta caja al saldo actual
                $this->totalesGenerales['saldoActual'] += ($caja->resumenMovimientos['ingresos'] ?? 0) - ($caja->resumenMovimientos['egresos'] ?? 0);
            } else {
                // Caja individual: usar saldo_actual directamente
                $this->totalesGenerales['saldoActual'] += $caja->saldo_actual;
            }

            foreach ($caja->totalesPorConcepto ?? [] as $codigo => $concepto) {
                if (!isset($this->totalesGenerales['porConcepto'][$codigo])) {
                    $this->totalesGenerales['porConcepto'][$codigo] = [
                        'codigo' => $concepto['codigo'],
                        'nombre' => $concepto['nombre'],
                        'monto' => 0,
                        'cantidad' => 0,
                    ];
                }
                $this->totalesGenerales['porConcepto'][$codigo]['monto'] += $concepto['monto'];
                $this->totalesGenerales['porConcepto'][$codigo]['cantidad'] += $concepto['cantidad'];
            }
        }
    }

    /**
     * Activa una caja (permite operaciones sin abrir nuevo turno)
     */
    public function activarCaja(int $cajaId): void
    {
        try {
            $caja = Caja::find($cajaId);

            if (!$caja) {
                $this->dispatch('toast-error', message: 'Caja no encontrada');
                return;
            }

            // Si la caja nunca fue abierta, necesita apertura de turno
            if (!$caja->fecha_apertura) {
                $this->abrirModalApertura($cajaId);
                return;
            }

            $caja->update(['estado' => 'abierta']);

            CajaService::clearCache();
            $this->cargarCajas();

            // Notificar a otros componentes (CajaSelector, NuevaVenta)
            $this->dispatch('caja-actualizada', cajaId: $caja->id, accion: 'activada');

            $this->dispatch('toast-success', message: "Caja {$caja->nombre} activada");

        } catch (\Exception $e) {
            Log::error('Error al activar caja', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: 'Error al activar la caja');
        }
    }

    /**
     * Desactiva una caja (no permite operaciones pero no cierra turno)
     */
    public function desactivarCaja(int $cajaId): void
    {
        try {
            $caja = Caja::find($cajaId);

            if (!$caja) {
                $this->dispatch('toast-error', message: 'Caja no encontrada');
                return;
            }

            $caja->update([
                'estado' => 'cerrada',
                'fecha_cierre' => now(),
            ]);

            CajaService::clearCache();
            $this->cargarCajas();

            // Notificar a otros componentes (CajaSelector, NuevaVenta)
            $this->dispatch('caja-actualizada', cajaId: $caja->id, accion: 'pausada');

            $this->dispatch('toast-success', message: "Caja {$caja->nombre} desactivada");

        } catch (\Exception $e) {
            Log::error('Error al desactivar caja', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: 'Error al desactivar la caja');
        }
    }

    /**
     * Abre el modal de apertura de turno
     */
    public function abrirModalApertura(?int $cajaId = null, ?int $grupoId = null): void
    {
        $this->resetAperturaForm();

        if ($grupoId) {
            // Apertura grupal
            $grupo = GrupoCierre::with('cajas')->find($grupoId);
            if (!$grupo) {
                $this->dispatch('toast-error', message: 'Grupo no encontrado');
                return;
            }

            $this->esAperturaGrupal = true;
            $this->grupoAperturaId = $grupoId;
            $this->cajasAAbrir = $grupo->cajas->where('activo', true)->pluck('id')->toArray();

            // Detectar si usa fondo común
            $this->grupoUsaFondoComun = $grupo->usaFondoComun();
            $this->fondoComunTotal = ''; // Vacío para que el usuario ingrese el monto

            if (!$this->grupoUsaFondoComun) {
                // Fondo individual por caja
                foreach ($grupo->cajas->where('activo', true) as $caja) {
                    $this->fondosIniciales[$caja->id] = $this->calcularFondoInicialParaInput($caja);
                }
            }
        } elseif ($cajaId) {
            // Apertura individual
            $caja = Caja::find($cajaId);
            if (!$caja) {
                $this->dispatch('toast-error', message: 'Caja no encontrada');
                return;
            }

            $this->esAperturaGrupal = false;
            $this->cajaAperturaId = $cajaId;
            $this->cajasAAbrir = [$cajaId];
            $this->fondosIniciales[$cajaId] = $this->calcularFondoInicialParaInput($caja);
            $this->grupoUsaFondoComun = false;
        }

        $this->showAperturaModal = true;
    }

    /**
     * Calcula el fondo inicial según la configuración de la caja
     */
    protected function calcularFondoInicial(Caja $caja): float
    {
        switch ($caja->modo_carga_inicial ?? 'manual') {
            case 'ultimo_cierre':
                // Buscar último cierre de esta caja
                $ultimoCierre = CierreTurnoCaja::where('caja_id', $caja->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                return $ultimoCierre?->saldo_final ?? 0;

            case 'monto_fijo':
                return $caja->monto_fijo_inicial ?? 0;

            default:
                return 0;
        }
    }

    /**
     * Calcula el fondo inicial para mostrar en el input
     * Retorna string vacío para modo manual, número para modos automáticos
     */
    protected function calcularFondoInicialParaInput(Caja $caja): string|float
    {
        switch ($caja->modo_carga_inicial ?? 'manual') {
            case 'ultimo_cierre':
                $ultimoCierre = CierreTurnoCaja::where('caja_id', $caja->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                return $ultimoCierre?->saldo_final ?? '';

            case 'monto_fijo':
                return $caja->monto_fijo_inicial ?? '';

            default:
                return ''; // Manual: input vacío
        }
    }

    /**
     * Procesa la apertura del turno
     * Integrado con tesorería: provisiona fondos automáticamente
     */
    public function procesarApertura(): void
    {
        try {
            DB::beginTransaction();

            $usuarioId = auth()->id();
            $sucursalId = SucursalService::getSucursalActiva();

            // Obtener tesorería de la sucursal
            $tesoreria = TesoreriaService::obtenerOCrear($sucursalId);

            // Si es apertura grupal con fondo común
            if ($this->esAperturaGrupal && $this->grupoUsaFondoComun) {
                $grupo = GrupoCierre::with('cajas')->find($this->grupoAperturaId);
                if (!$grupo) {
                    throw new \Exception('Grupo no encontrado');
                }

                // Convertir a float (puede venir vacío)
                $fondoComun = $this->fondoComunTotal !== '' ? (float)$this->fondoComunTotal : 0;

                // Si hay tesorería ACTIVA y fondo > 0, hacer provisión desde tesorería
                if ($tesoreria && $tesoreria->activo && $fondoComun > 0) {
                    TesoreriaService::provisionarFondoGrupo(
                        $tesoreria,
                        $grupo,
                        $fondoComun,
                        $usuarioId,
                        'Apertura de turno con fondo común'
                    );
                }

                // Actualizar saldo del fondo común del grupo
                $grupo->saldo_fondo_comun = $fondoComun;
                $grupo->save();

                // Las cajas se abren con saldo 0, el fondo real está en el grupo
                foreach ($this->cajasAAbrir as $cajaId) {
                    $caja = Caja::find($cajaId);
                    if (!$caja) continue;

                    $caja->update([
                        'estado' => 'abierta',
                        'saldo_inicial' => 0,
                        'saldo_actual' => 0,
                        'fecha_apertura' => now(),
                        'fecha_cierre' => null,
                        'usuario_apertura_id' => $usuarioId,
                    ]);
                }
            } else {
                // Apertura normal (individual o grupal sin fondo común)
                // Usar CajaService con integración de tesorería
                foreach ($this->cajasAAbrir as $cajaId) {
                    $caja = Caja::find($cajaId);
                    if (!$caja) continue;

                    $fondoInicialRaw = $this->fondosIniciales[$cajaId] ?? '';
                    $fondoInicial = $fondoInicialRaw !== '' ? (float)$fondoInicialRaw : 0;

                    // Usar el servicio integrado con tesorería
                    $resultado = CajaService::abrirCajaConTesoreria(
                        $caja,
                        $fondoInicial,
                        $usuarioId,
                        $tesoreria
                    );

                    if (!$resultado['success']) {
                        throw new \Exception($resultado['message']);
                    }
                }
            }

            DB::commit();

            CajaService::clearCache();
            $this->showAperturaModal = false;
            $this->cargarCajas();

            // Notificar a otros componentes (CajaSelector, NuevaVenta)
            $this->dispatch('caja-actualizada', cajaId: $this->cajasAAbrir[0] ?? null, accion: 'turno_abierto');

            $mensaje = $this->esAperturaGrupal
                ? 'Turno del grupo abierto exitosamente'
                : 'Turno de caja abierto exitosamente';

            if ($this->grupoUsaFondoComun && $this->fondoComunTotal !== '') {
                $mensaje .= ' (Fondo común: $' . number_format((float)$this->fondoComunTotal, 2, ',', '.') . ')';
            }

            $this->dispatch('toast-success', message: $mensaje);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al abrir turno', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: 'Error al abrir el turno: ' . $e->getMessage());
        }
    }

    protected function resetAperturaForm(): void
    {
        $this->cajaAperturaId = null;
        $this->grupoAperturaId = null;
        $this->cajasAAbrir = [];
        $this->fondosIniciales = [];
        $this->esAperturaGrupal = false;
        $this->grupoUsaFondoComun = false;
        $this->fondoComunTotal = '';
    }

    /**
     * Abre el modal de cierre de turno
     */
    public function abrirModalCierre(?int $cajaId = null, ?int $grupoId = null): void
    {
        $this->resetCierreForm();

        if ($grupoId) {
            // Cierre grupal
            $grupo = GrupoCierre::with('cajas')->find($grupoId);
            if (!$grupo) {
                $this->dispatch('toast-error', message: 'Grupo no encontrado');
                return;
            }

            $cajasGrupo = $grupo->cajas->where('activo', true);

            $this->esCierreGrupal = true;
            $this->grupoCierreId = $grupoId;
            $this->cajasACerrar = $cajasGrupo->pluck('id')->toArray();

            // Detectar si usa fondo común
            $this->cierreUsaFondoComun = $grupo->fondo_comun;
            $this->saldoFondoComunCierre = $grupo->saldo_fondo_comun ?? 0;

            if ($this->cierreUsaFondoComun) {
                // Fondo común: un solo input para todo el grupo
                $this->saldoDeclaradoFondoComun = '';
            } else {
                // Fondo individual: input por cada caja
                foreach ($cajasGrupo as $caja) {
                    $this->saldosDeclarados[$caja->id] = '';
                }
            }
        } elseif ($cajaId) {
            $caja = $this->cajas->firstWhere('id', $cajaId);

            if (!$caja) {
                $this->dispatch('toast-error', message: 'Caja no encontrada');
                return;
            }

            // Verificar si es parte de un grupo
            if ($caja->grupo_cierre_id && !auth()->user()->can('func.cerrar_caja_individual')) {
                // Debe cerrar todo el grupo
                $this->abrirModalCierre(null, $caja->grupo_cierre_id);
                return;
            }

            $this->esCierreGrupal = false;
            $this->cajaCierreId = $cajaId;
            $this->cajasACerrar = [$cajaId];
            // NO pre-cargar saldo - el usuario debe ingresar el arqueo manualmente
            $this->saldosDeclarados[$cajaId] = '';
        }

        $this->showCierreModal = true;
    }

    protected function resetCierreForm(): void
    {
        $this->cajaCierreId = null;
        $this->grupoCierreId = null;
        $this->cajasACerrar = [];
        $this->saldosDeclarados = [];
        $this->observacionesCierre = '';
        $this->esCierreGrupal = false;
        $this->cierreUsaFondoComun = false;
        $this->saldoFondoComunCierre = 0;
        $this->saldoDeclaradoFondoComun = '';
    }

    /**
     * Cancela el cierre y recarga las cajas
     */
    public function cancelarCierre(): void
    {
        $this->showCierreModal = false;
        $this->resetCierreForm();
        $this->cargarCajas();
    }

    /**
     * Procesa el cierre del turno
     * Integrado con tesorería: rinde fondos automáticamente
     */
    public function procesarCierre(): void
    {
        try {
            DB::beginTransaction();

            $sucursalId = SucursalService::getSucursalActiva();
            $usuarioId = auth()->id();

            // Obtener tesorería de la sucursal
            $tesoreria = TesoreriaService::obtenerOCrear($sucursalId);

            // Crear registro de cierre de turno
            $cierreTurno = CierreTurno::create([
                'sucursal_id' => $sucursalId,
                'grupo_cierre_id' => $this->esCierreGrupal ? $this->grupoCierreId : null,
                'usuario_id' => $usuarioId,
                'tipo' => $this->esCierreGrupal ? 'grupo' : 'individual',
                'fecha_apertura' => $this->cajas->firstWhere('id', $this->cajasACerrar[0])?->fecha_apertura,
                'fecha_cierre' => now(),
                'total_saldo_inicial' => 0,
                'total_saldo_final' => 0,
                'total_ingresos' => 0,
                'total_egresos' => 0,
                'total_diferencia' => 0,
                'observaciones' => $this->observacionesCierre,
            ]);

            $totalSaldoInicial = 0;
            $totalSaldoFinal = 0;
            $totalIngresos = 0;
            $totalEgresos = 0;
            $totalDiferencia = 0;

            // Caso especial: Cierre con Fondo Común
            if ($this->cierreUsaFondoComun && $this->grupoCierreId) {
                $grupo = GrupoCierre::find($this->grupoCierreId);
                $datosConsolidados = $this->getDatosConsolidadosCierre();

                $totalSaldoInicial = $datosConsolidados['fondo_inicial'];
                $totalIngresos = $datosConsolidados['total_ingresos'];
                $totalEgresos = $datosConsolidados['total_egresos'];
                $saldoSistema = $datosConsolidados['saldo_sistema'];

                $saldoDeclarado = $this->saldoDeclaradoFondoComun !== ''
                    ? (float)$this->saldoDeclaradoFondoComun
                    : $saldoSistema;

                $totalDiferencia = $saldoDeclarado - $saldoSistema;
                $totalSaldoFinal = $saldoDeclarado;

                // Cerrar cada caja del grupo (sin saldo individual)
                foreach ($this->cajasACerrar as $cajaId) {
                    $caja = Caja::find($cajaId);
                    if (!$caja) continue;

                    // Calcular ingresos/egresos de esta caja específica
                    $ingCaja = MovimientoCaja::where('caja_id', $cajaId)
                        ->whereNull('cierre_turno_id')
                        ->where('tipo', 'ingreso')
                        ->where('referencia_tipo', '!=', 'apertura')
                        ->sum('monto');

                    $egCaja = MovimientoCaja::where('caja_id', $cajaId)
                        ->whereNull('cierre_turno_id')
                        ->where('tipo', 'egreso')
                        ->sum('monto');

                    // Calcular desgloses de formas de pago y conceptos
                    $desgloses = $this->calcularDesglosesCaja($cajaId);

                    // Crear detalle del cierre por caja (para auditoría)
                    CierreTurnoCaja::create([
                        'cierre_turno_id' => $cierreTurno->id,
                        'caja_id' => $cajaId,
                        'caja_nombre' => $caja->nombre,
                        'saldo_inicial' => 0, // Las cajas tienen saldo 0 con fondo común
                        'saldo_final' => 0,
                        'saldo_sistema' => 0,
                        'saldo_declarado' => 0,
                        'total_ingresos' => $ingCaja,
                        'total_egresos' => $egCaja,
                        'diferencia' => 0,
                        'desglose_formas_pago' => $desgloses['formas_pago'],
                        'desglose_conceptos' => $desgloses['conceptos'],
                    ]);

                    // Marcar ventas, venta_pagos, cobros y cobro_pagos con el cierre_turno_id
                    $this->marcarTransaccionesCierre($cajaId, $cierreTurno->id);

                    // Cerrar la caja (sin rendir a tesorería, eso se hace a nivel grupo)
                    $caja->update([
                        'estado' => 'cerrada',
                        'fecha_cierre' => now(),
                        'usuario_cierre_id' => $usuarioId,
                        'saldo_actual' => 0,
                    ]);

                    // Marcar movimientos como cerrados
                    MovimientoCaja::where('caja_id', $caja->id)
                        ->whereNull('cierre_turno_id')
                        ->update(['cierre_turno_id' => $cierreTurno->id]);
                }

                // Rendir el fondo común a tesorería (solo si está activa)
                if ($tesoreria && $tesoreria->activo && $saldoDeclarado > 0) {
                    TesoreriaService::rendirFondoGrupo(
                        $grupo,
                        $tesoreria,
                        $saldoDeclarado,
                        $saldoSistema,
                        $usuarioId,
                        $cierreTurno->id,
                        'Cierre de turno grupal con fondo común'
                    );
                }

                // Resetear el fondo común del grupo
                $grupo->update(['saldo_fondo_comun' => 0]);

            } else {
                // Cierre normal (individual o grupal sin fondo común)
                foreach ($this->cajasACerrar as $cajaId) {
                    $caja = Caja::find($cajaId);
                    if (!$caja) continue;

                    // Calcular totales EXCLUYENDO movimientos de apertura
                    $ingresos = MovimientoCaja::where('caja_id', $cajaId)
                        ->whereNull('cierre_turno_id')
                        ->where('tipo', 'ingreso')
                        ->where('referencia_tipo', '!=', 'apertura')
                        ->sum('monto');

                    $egresos = MovimientoCaja::where('caja_id', $cajaId)
                        ->whereNull('cierre_turno_id')
                        ->where('tipo', 'egreso')
                        ->sum('monto');

                    // El saldo del sistema es el saldo_actual de la caja
                    $saldoSistema = $caja->saldo_actual;
                    $saldoDeclarado = isset($this->saldosDeclarados[$cajaId]) && $this->saldosDeclarados[$cajaId] !== ''
                        ? (float)$this->saldosDeclarados[$cajaId]
                        : $caja->saldo_actual;
                    $diferencia = $saldoDeclarado - $saldoSistema;

                    // Calcular desgloses de formas de pago y conceptos
                    $desgloses = $this->calcularDesglosesCaja($cajaId);

                    // Crear detalle del cierre
                    CierreTurnoCaja::create([
                        'cierre_turno_id' => $cierreTurno->id,
                        'caja_id' => $cajaId,
                        'caja_nombre' => $caja->nombre,
                        'saldo_inicial' => $caja->saldo_inicial,
                        'saldo_final' => $saldoDeclarado,
                        'saldo_sistema' => $saldoSistema,
                        'saldo_declarado' => $saldoDeclarado,
                        'total_ingresos' => $ingresos,
                        'total_egresos' => $egresos,
                        'diferencia' => $diferencia,
                        'desglose_formas_pago' => $desgloses['formas_pago'],
                        'desglose_conceptos' => $desgloses['conceptos'],
                    ]);

                    // Marcar ventas, venta_pagos, cobros y cobro_pagos con el cierre_turno_id
                    $this->marcarTransaccionesCierre($cajaId, $cierreTurno->id);

                    // Usar CajaService con integración de tesorería para cerrar
                    $resultado = CajaService::cerrarCajaConTesoreria(
                        $caja,
                        $saldoDeclarado,
                        $usuarioId,
                        $cierreTurno,
                        $tesoreria,
                        $this->observacionesCierre
                    );

                    if (!$resultado['success']) {
                        throw new \Exception($resultado['message']);
                    }

                    $totalSaldoInicial += $caja->saldo_inicial;
                    $totalSaldoFinal += $saldoDeclarado;
                    $totalIngresos += $ingresos;
                    $totalEgresos += $egresos;
                    $totalDiferencia += $diferencia;
                }
            }

            // Actualizar totales del cierre
            $cierreTurno->update([
                'total_saldo_inicial' => $totalSaldoInicial,
                'total_saldo_final' => $totalSaldoFinal,
                'total_ingresos' => $totalIngresos,
                'total_egresos' => $totalEgresos,
                'total_diferencia' => $totalDiferencia,
            ]);

            DB::commit();

            CajaService::clearCache();
            $this->showCierreModal = false;
            $this->cargarCajas();

            // Notificar a otros componentes (CajaSelector, NuevaVenta)
            $this->dispatch('caja-actualizada', cajaId: $this->cajasACerrar[0] ?? null, accion: 'turno_cerrado');

            $mensaje = $this->esCierreGrupal
                ? 'Turno del grupo cerrado exitosamente'
                : 'Turno cerrado exitosamente';

            if ($totalDiferencia != 0) {
                $tipoDif = $totalDiferencia > 0 ? 'sobrante' : 'faltante';
                $mensaje .= ' - ' . ucfirst($tipoDif) . ': $' . number_format(abs($totalDiferencia), 2, ',', '.');
            }

            // Indicar que se rindió a tesorería
            if ($tesoreria && $totalSaldoFinal > 0) {
                $mensaje .= ' - Fondos rendidos a tesorería';
            }

            $this->dispatch('toast-success', message: $mensaje);

            // Disparar evento para imprimir el cierre de turno
            $this->dispatch('imprimir-cierre-turno', cierreId: $cierreTurno->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al cerrar turno', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('toast-error', message: 'Error al cerrar el turno: ' . $e->getMessage());
        }
    }

    /**
     * Calcula los desgloses de formas de pago y conceptos para una caja
     * Incluye tanto ventas como cobros del turno (sin cierre previo)
     */
    protected function calcularDesglosesCaja(int $cajaId): array
    {
        $desgloseFormasPago = [];
        $desgloseConceptos = [];

        // Obtener ventas pendientes de cierre de esta caja
        $ventas = Venta::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->where('estado', '!=', 'cancelada')
            ->with(['pagos.formaPago', 'pagos.conceptoPago'])
            ->get();

        // Procesar pagos de ventas
        foreach ($ventas as $venta) {
            foreach ($venta->pagos as $pago) {
                if ($pago->estado !== 'activo') continue;

                $formaPagoNombre = $pago->formaPago?->nombre ?? 'Sin forma de pago';
                $conceptoNombre = $pago->conceptoPago?->nombre ?? $pago->formaPago?->conceptoPago?->nombre ?? 'Ventas';

                // Acumular por forma de pago
                if (!isset($desgloseFormasPago[$formaPagoNombre])) {
                    $desgloseFormasPago[$formaPagoNombre] = 0;
                }
                $desgloseFormasPago[$formaPagoNombre] += (float) $pago->monto_final;

                // Acumular por concepto
                if (!isset($desgloseConceptos[$conceptoNombre])) {
                    $desgloseConceptos[$conceptoNombre] = 0;
                }
                $desgloseConceptos[$conceptoNombre] += (float) $pago->monto_final;
            }
        }

        // Obtener cobros pendientes de cierre de esta caja
        $cobros = Cobro::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->where('estado', 'activo')
            ->with(['pagos.formaPago', 'pagos.conceptoPago'])
            ->get();

        // Procesar pagos de cobros
        foreach ($cobros as $cobro) {
            foreach ($cobro->pagos as $pago) {
                if ($pago->estado !== 'activo') continue;

                $formaPagoNombre = $pago->formaPago?->nombre ?? 'Sin forma de pago';
                $conceptoNombre = $pago->conceptoPago?->nombre ?? 'Cobros Cta. Cte.';

                // Acumular por forma de pago
                if (!isset($desgloseFormasPago[$formaPagoNombre])) {
                    $desgloseFormasPago[$formaPagoNombre] = 0;
                }
                $desgloseFormasPago[$formaPagoNombre] += (float) $pago->monto_final;

                // Acumular por concepto (separado de ventas)
                if (!isset($desgloseConceptos[$conceptoNombre])) {
                    $desgloseConceptos[$conceptoNombre] = 0;
                }
                $desgloseConceptos[$conceptoNombre] += (float) $pago->monto_final;
            }
        }

        // Redondear valores
        foreach ($desgloseFormasPago as $key => $value) {
            $desgloseFormasPago[$key] = round($value, 2);
        }
        foreach ($desgloseConceptos as $key => $value) {
            $desgloseConceptos[$key] = round($value, 2);
        }

        return [
            'formas_pago' => $desgloseFormasPago,
            'conceptos' => $desgloseConceptos,
        ];
    }

    /**
     * Marca todas las transacciones de una caja con el cierre_turno_id
     * Incluye: ventas, venta_pagos, cobros, cobro_pagos
     */
    protected function marcarTransaccionesCierre(int $cajaId, int $cierreTurnoId): void
    {
        // Marcar ventas pendientes de cierre
        $ventasIds = Venta::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->where('estado', '!=', 'cancelada')
            ->pluck('id');

        if ($ventasIds->isNotEmpty()) {
            // Actualizar ventas
            Venta::whereIn('id', $ventasIds)
                ->update(['cierre_turno_id' => $cierreTurnoId]);

            // Actualizar venta_pagos de esas ventas (solo los que no tienen cierre)
            VentaPago::whereIn('venta_id', $ventasIds)
                ->whereNull('cierre_turno_id')
                ->where('estado', 'activo')
                ->update(['cierre_turno_id' => $cierreTurnoId]);
        }

        // Marcar cobros pendientes de cierre
        $cobrosIds = Cobro::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->where('estado', 'activo')
            ->pluck('id');

        if ($cobrosIds->isNotEmpty()) {
            // Actualizar cobros
            Cobro::whereIn('id', $cobrosIds)
                ->update(['cierre_turno_id' => $cierreTurnoId]);

            // Actualizar cobro_pagos de esos cobros (solo los que no tienen cierre)
            CobroPago::whereIn('cobro_id', $cobrosIds)
                ->whereNull('cierre_turno_id')
                ->where('estado', 'activo')
                ->update(['cierre_turno_id' => $cierreTurnoId]);
        }
    }

    /**
     * Expande/colapsa una caja para ver detalles
     */
    public function toggleCaja(int $cajaId): void
    {
        $this->cajaExpandida = $this->cajaExpandida === $cajaId ? null : $cajaId;
    }

    /**
     * Expande/colapsa un concepto
     */
    public function toggleConcepto(string $key): void
    {
        if (in_array($key, $this->conceptosExpandidos)) {
            $this->conceptosExpandidos = array_diff($this->conceptosExpandidos, [$key]);
        } else {
            $this->conceptosExpandidos[] = $key;
        }
    }

    /**
     * Cambia el modo de visualización
     */
    public function cambiarVistaMovimientos(string $vista): void
    {
        $this->vistaMovimientos = $vista;
    }

    // ==================== MODAL DE DETALLE DE MOVIMIENTOS ====================

    /**
     * Abre el modal de detalle de movimientos para una caja
     */
    public function abrirModalDetalle(int $cajaId): void
    {
        $caja = Caja::with('grupoCierre')->find($cajaId);
        if (!$caja) return;

        $this->cajaDetalleId = $cajaId;

        // Determinar si excluir apertura/provisión (fondo común)
        $esFondoComun = $caja->grupo_cierre_id
            && $caja->grupoCierre
            && $caja->grupoCierre->usaFondoComun();

        // ── Grilla 1: Movimientos en efectivo ──
        $movimientos = MovimientoCaja::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->orderBy('created_at', 'asc')
            ->get();

        $etiquetas = [
            'apertura' => 'Apertura',
            'venta' => 'Venta',
            'cobro' => 'Cobro',
            'compra' => 'Compra',
            'pago_proveedor' => 'Pago Proveedor',
            'ajuste' => 'Ajuste',
            'retiro' => 'Retiro',
            'transferencia' => 'Transferencia',
            'ingreso_manual' => 'Ingreso Manual',
            'egreso_manual' => 'Egreso Manual',
            'ingreso_manual_caja' => 'Ingreso Manual',
            'egreso_manual_caja' => 'Egreso Manual',
            'provision_fondo' => 'Provisión Fondo',
            'rendicion_fondo' => 'Rendición Fondo',
        ];

        $tiposExcluidosFondoComun = ['apertura', 'provision_fondo'];

        $saldoAcumulado = 0;
        $totalIngresos = 0;
        $totalEgresos = 0;
        $lista = [];

        foreach ($movimientos as $mov) {
            // Para fondo común, excluir apertura/provisión del balance
            if ($esFondoComun && in_array($mov->referencia_tipo, $tiposExcluidosFondoComun)) {
                continue;
            }

            if ($mov->tipo === 'ingreso') {
                $saldoAcumulado += $mov->monto;
                $totalIngresos += $mov->monto;
            } else {
                $saldoAcumulado -= $mov->monto;
                $totalEgresos += $mov->monto;
            }

            $lista[] = [
                'fecha' => $mov->created_at->format('H:i'),
                'concepto' => $mov->concepto,
                'tipo' => $mov->tipo,
                'monto' => $mov->monto,
                'etiqueta' => $etiquetas[$mov->referencia_tipo] ?? ucfirst($mov->referencia_tipo ?? 'Otro'),
                'referencia_tipo' => $mov->referencia_tipo,
                'saldo_acumulado' => round($saldoAcumulado, 2),
            ];
        }

        $this->detalleMovimientos = $lista;

        // ── Grilla 2: Otros medios de pago (no-efectivo) ──
        $fechaDesde = $caja->fecha_apertura ?? today();

        // VentaPago no-efectivo
        $ventaPagos = VentaPago::whereHas('venta', function ($q) use ($cajaId, $fechaDesde) {
                $q->where('caja_id', $cajaId)
                  ->where('estado', 'completada')
                  ->where('created_at', '>=', $fechaDesde);
            })
            ->where('afecta_caja', false)
            ->with(['conceptoPago', 'venta:id,numero'])
            ->get();

        // CobroPago no-efectivo
        $cobroPagos = CobroPago::whereHas('cobro', function ($q) use ($cajaId, $fechaDesde) {
                $q->where('caja_id', $cajaId)
                  ->where('created_at', '>=', $fechaDesde);
            })
            ->where('afecta_caja', false)
            ->with(['conceptoPago', 'cobro:id'])
            ->get();

        $conceptos = [];

        foreach ($ventaPagos as $pago) {
            $codigo = $pago->conceptoPago?->codigo ?? 'otros';
            $nombre = $pago->conceptoPago?->nombre ?? 'Otros';
            if (!isset($conceptos[$codigo])) {
                $conceptos[$codigo] = ['nombre' => $nombre, 'monto_total' => 0, 'cantidad' => 0, 'detalle' => []];
            }
            $conceptos[$codigo]['monto_total'] += $pago->monto_final;
            $conceptos[$codigo]['cantidad']++;
            $conceptos[$codigo]['detalle'][] = [
                'referencia' => 'Venta #' . ($pago->venta->numero ?? $pago->venta_id),
                'monto' => $pago->monto_final,
            ];
        }

        foreach ($cobroPagos as $pago) {
            $codigo = $pago->conceptoPago?->codigo ?? 'otros';
            $nombre = $pago->conceptoPago?->nombre ?? 'Otros';
            if (!isset($conceptos[$codigo])) {
                $conceptos[$codigo] = ['nombre' => $nombre, 'monto_total' => 0, 'cantidad' => 0, 'detalle' => []];
            }
            $conceptos[$codigo]['monto_total'] += $pago->monto_final;
            $conceptos[$codigo]['cantidad']++;
            $conceptos[$codigo]['detalle'][] = [
                'referencia' => 'Cobro #' . ($pago->cobro->id ?? $pago->cobro_id),
                'monto' => $pago->monto_final,
            ];
        }

        $this->detalleOtrosConceptos = array_values($conceptos);

        $this->detalleInfo = [
            'nombre' => $caja->nombre,
            'numero' => $caja->numero_formateado ?? '',
            'saldo_actual' => $caja->saldo_actual,
            'total_ingresos' => round($totalIngresos, 2),
            'total_egresos' => round($totalEgresos, 2),
            'cantidad_movimientos' => count($lista),
            'es_fondo_comun' => $esFondoComun,
        ];

        $this->showDetalleModal = true;
    }

    /**
     * Cierra el modal de detalle
     */
    public function cerrarModalDetalle(): void
    {
        $this->showDetalleModal = false;
        $this->cajaDetalleId = null;
        $this->detalleMovimientos = [];
        $this->detalleOtrosConceptos = [];
        $this->detalleInfo = [];
    }

    /**
     * Obtiene información de caja para modal de cierre
     * SIEMPRE calcula los valores frescos (las propiedades dinámicas se pierden en Livewire)
     * EXCLUYE movimientos de apertura del cálculo de ingresos operativos
     */
    public function getCajaParaCierre(int $cajaId): ?array
    {
        // Siempre cargar la caja fresca con sus movimientos
        // (las propiedades dinámicas como resumenMovimientos se pierden entre requests de Livewire)
        $caja = Caja::with(['grupoCierre', 'movimientos' => function ($q) {
            $q->whereNull('cierre_turno_id');
        }])->find($cajaId);

        if (!$caja) return null;

        // Calcular ingresos/egresos excluyendo movimientos de apertura
        $movimientos = $caja->movimientos ?? collect();
        $movimientosOperativos = $movimientos->filter(fn($m) => $m->referencia_tipo !== 'apertura');
        $ingresos = $movimientosOperativos->where('tipo', 'ingreso')->sum('monto');
        $egresos = $movimientosOperativos->where('tipo', 'egreso')->sum('monto');

        return [
            'id' => $caja->id,
            'nombre' => $caja->nombre,
            'numero' => $caja->numero_formateado ?? str_pad($caja->numero ?? $caja->id, 3, '0', STR_PAD_LEFT),
            'saldo_inicial' => $caja->saldo_inicial ?? 0,
            'saldo_actual' => $caja->saldo_actual ?? 0,
            'fecha_apertura' => $caja->fecha_apertura?->format('d/m/Y H:i'),
            'ingresos' => $ingresos,
            'egresos' => $egresos,
            'grupo' => $caja->grupoCierre?->nombre ?? null,
        ];
    }

    /**
     * Obtiene información consolidada del grupo para cierre con fondo común
     */
    public function getDatosConsolidadosCierre(): ?array
    {
        if (!$this->grupoCierreId || !$this->cierreUsaFondoComun) {
            return null;
        }

        $grupo = GrupoCierre::with('cajas')->find($this->grupoCierreId);
        if (!$grupo) return null;

        $cajasGrupo = $grupo->cajas->where('activo', true);

        $totalIngresos = 0;
        $totalEgresos = 0;
        $cajasList = [];

        foreach ($cajasGrupo as $caja) {
            // Cargar movimientos de cada caja
            $movimientos = MovimientoCaja::where('caja_id', $caja->id)
                ->whereNull('cierre_turno_id')
                ->get();

            $movimientosOperativos = $movimientos->filter(fn($m) => $m->referencia_tipo !== 'apertura');
            $ingresos = $movimientosOperativos->where('tipo', 'ingreso')->sum('monto');
            $egresos = $movimientosOperativos->where('tipo', 'egreso')->sum('monto');

            $totalIngresos += $ingresos;
            $totalEgresos += $egresos;

            $cajasList[] = [
                'id' => $caja->id,
                'nombre' => $caja->nombre,
                'ingresos' => $ingresos,
                'egresos' => $egresos,
            ];
        }

        $fondoInicial = $grupo->saldo_fondo_comun ?? 0;
        $saldoSistema = $fondoInicial + $totalIngresos - $totalEgresos;

        return [
            'grupo_id' => $grupo->id,
            'grupo_nombre' => $grupo->nombre ?? 'Grupo de Cajas',
            'cantidad_cajas' => $cajasGrupo->count(),
            'cajas' => $cajasList,
            'fondo_inicial' => $fondoInicial,
            'total_ingresos' => $totalIngresos,
            'total_egresos' => $totalEgresos,
            'saldo_sistema' => $saldoSistema,
        ];
    }

    /**
     * Obtiene información de caja para modal de apertura
     */
    public function getCajaParaApertura(int $cajaId): ?array
    {
        $caja = Caja::find($cajaId);
        if (!$caja) return null;

        return [
            'id' => $caja->id,
            'nombre' => $caja->nombre,
            'numero' => $caja->numero_formateado,
            'modo_carga' => $caja->modo_carga_inicial ?? 'manual',
            'monto_fijo' => $caja->monto_fijo_inicial ?? 0,
        ];
    }

    /**
     * Obtiene información del grupo para modal de apertura
     */
    public function getGrupoParaApertura(): ?array
    {
        if (!$this->grupoAperturaId) return null;

        $grupo = GrupoCierre::with('cajas')->find($this->grupoAperturaId);
        if (!$grupo) return null;

        return [
            'id' => $grupo->id,
            'nombre' => $grupo->nombre ?? 'Grupo de Cajas',
            'fondo_comun' => $grupo->fondo_comun,
            'saldo_fondo_comun' => $grupo->saldo_fondo_comun ?? 0,
            'cantidad_cajas' => $grupo->cajas->where('activo', true)->count(),
        ];
    }

    /**
     * Verifica si hay turno activo (al menos una caja con movimientos pendientes)
     */
    public function hayTurnoActivo(): bool
    {
        return $this->cajas->contains(function ($caja) {
            return $caja->estado === 'abierta' || $caja->tieneMovimientosPendientes;
        });
    }

    /**
     * Obtiene grupos de cierre de la sucursal
     */
    public function getGruposCierre(): Collection
    {
        $sucursalId = SucursalService::getSucursalActiva();
        if (!$sucursalId) return collect();

        return GrupoCierre::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->with('cajas')
            ->get();
    }

    /**
     * Obtiene las cajas agrupadas por grupo de cierre
     * Se calcula en cada render para evitar problemas de serialización
     */
    public function getCajasAgrupadas(): Collection
    {
        return $this->cajas->groupBy(function ($caja) {
            return $caja->grupo_cierre_id ?? 'individual_' . $caja->id;
        });
    }

    public function render()
    {
        return view('livewire.cajas.turno-actual', [
            'cajasAgrupadas' => $this->getCajasAgrupadas(),
        ]);
    }
}

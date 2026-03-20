<?php

namespace App\Livewire\Tesoreria;

use App\Models\ArqueoTesoreria;
use App\Models\Caja;
use App\Models\CierreTurno;
use App\Models\CuentaEmpresa;
use App\Models\DepositoBancario;
use App\Models\Moneda;
use App\Models\MovimientoCaja;
use App\Models\MovimientoTesoreria;
use App\Models\ProvisionFondo;
use App\Models\RendicionFondo;
use App\Models\Tesoreria;
use App\Models\TesoreriaSaldoMoneda;
use App\Services\SucursalService;
use App\Services\TesoreriaService;
use App\Traits\SucursalAware;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Componente Livewire: Gestión de Tesorería
 *
 * Funcionalidades:
 * - Ver saldo actual de tesorería
 * - Listar movimientos con filtros
 * - Provisionar fondo a caja
 * - Recibir rendición de caja
 * - Registrar depósito bancario
 * - Realizar arqueo
 */
class GestionTesoreria extends Component
{
    use SucursalAware;
    use WithPagination;

    // Tesorería activa
    public ?Tesoreria $tesoreria = null;

    // Filtros de movimientos
    public string $filtroTipo = '';

    public string $filtroFechaDesde = '';

    public string $filtroFechaHasta = '';

    public string $filtroConcepto = '';

    // Modal de provisión
    public bool $showProvisionModal = false;

    public ?int $cajaProvisionId = null;

    public float $montoProvision = 0;

    public string $observacionesProvision = '';

    public ?int $monedaProvisionId = null; // null = moneda principal (ARS)

    public float $montoProvisionMoneda = 0;

    // Modal de rendición
    public bool $showRendicionModal = false;

    public array $rendicionesPendientes = [];

    public ?int $rendicionSeleccionada = null;

    // Modal de depósito
    public bool $showDepositoModal = false;

    public ?int $cuentaEmpresaId = null;

    public float $montoDeposito = 0;

    public string $fechaDeposito = '';

    public string $numeroComprobante = '';

    public string $observacionesDeposito = '';

    // Modal de arqueo
    public bool $showArqueoModal = false;

    public float $saldoContado = 0;

    public string $observacionesArqueo = '';

    public ?int $monedaArqueoId = null;

    // Datos cargados
    public array $estadisticasHoy = [];

    // Vista activa (tabs)
    public string $vistaActiva = 'movimientos'; // movimientos, cajas, arqueos, depositos

    // Modal de detalle de arqueo
    public bool $showArqueoDetalleModal = false;

    public ?ArqueoTesoreria $arqueoDetalle = null;

    // Modal de rechazo de rendición
    public bool $showRechazoModal = false;

    public ?int $rendicionARechazar = null;

    public string $motivoRechazo = '';

    protected $listeners = ['tesoreria-actualizada' => 'cargarDatos'];

    public function mount(): void
    {
        $this->filtroFechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->filtroFechaHasta = now()->format('Y-m-d');
        $this->fechaDeposito = now()->format('Y-m-d');

        $this->cargarDatos();
    }

    /**
     * Hook llamado cuando cambia la sucursal (desde SucursalAware trait)
     */
    protected function onSucursalChanged($sucursalId = null, $sucursalNombre = null): void
    {
        // Cerrar todos los modales
        $this->showProvisionModal = false;
        $this->showRendicionModal = false;
        $this->showDepositoModal = false;
        $this->showArqueoModal = false;

        // Recargar datos de la nueva sucursal
        $this->cargarDatos();
    }

    public function cargarDatos(): void
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (! $sucursalId) {
            return;
        }

        // Obtener o crear tesorería
        $this->tesoreria = TesoreriaService::obtenerOCrear($sucursalId);

        // Cargar estadísticas del día
        $this->estadisticasHoy = TesoreriaService::estadisticasHoy($this->tesoreria);

        // Cargar rendiciones pendientes con flag de revertibilidad
        $rendiciones = RendicionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->pendientes()
            ->with(['caja', 'usuarioEntrega', 'cierreTurno.detalleCajas', 'cierreTurno.grupoCierre'])
            ->orderBy('fecha', 'desc')
            ->get();

        $this->rendicionesPendientes = $rendiciones->map(function ($rendicion) {
            $data = $rendicion->toArray();
            $data['puede_revertir'] = $this->evaluarRevertibilidad($rendicion);

            // Consolidar desglose de monedas desde el cierre asociado
            $data['desglose_monedas'] = $this->consolidarDesgloseMonedas($rendicion);

            return $data;
        })->toArray();
    }

    /**
     * Saldos de monedas extranjeras en tesorería
     */
    public function getSaldosMonedasProperty(): array
    {
        if (! $this->tesoreria) {
            return [];
        }

        return $this->tesoreria->getSaldosTodasMonedas();
    }

    /**
     * Monedas extranjeras activas (para select de provisión)
     */
    public function getMonedasExtranjeras(): array
    {
        if (! $this->tesoreria) {
            return [];
        }

        return $this->tesoreria->saldosMoneda()
            ->where('saldo_actual', '>', 0)
            ->with('moneda')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->moneda_id,
                'codigo' => $s->moneda->codigo,
                'simbolo' => $s->moneda->simbolo,
                'nombre' => $s->moneda->nombre,
                'saldo' => (float) $s->saldo_actual,
            ])
            ->toArray();
    }

    /**
     * Resumen de efectivo en cajas por moneda (cajas abiertas de la sucursal)
     */
    public function getResumenMonedasCajasProperty(): array
    {
        $sucursalId = SucursalService::getSucursalActiva();
        if (! $sucursalId) {
            return [];
        }

        $cajasAbiertas = Caja::where('sucursal_id', $sucursalId)
            ->where('estado', 'abierta')
            ->pluck('id');

        if ($cajasAbiertas->isEmpty()) {
            return [];
        }

        $tiposExcluidos = ['apertura', 'provision_fondo', 'rendicion_fondo'];
        $monedaPrincipal = Moneda::obtenerPrincipal();

        $movimientos = MovimientoCaja::whereIn('caja_id', $cajasAbiertas)
            ->whereNull('cierre_turno_id')
            ->whereNotIn('referencia_tipo', $tiposExcluidos)
            ->with('moneda')
            ->get();

        $resumen = [];

        if ($monedaPrincipal) {
            // Calcular saldo inicial total de cajas
            $saldoInicial = Caja::whereIn('id', $cajasAbiertas)->sum('saldo_inicial');
            $resumen[$monedaPrincipal->codigo] = [
                'codigo' => $monedaPrincipal->codigo,
                'simbolo' => $monedaPrincipal->simbolo,
                'nombre' => $monedaPrincipal->nombre,
                'es_principal' => true,
                'saldo' => $saldoInicial,
            ];
        }

        foreach ($movimientos as $mov) {
            $esExtranjera = $mov->moneda_id && $mov->monto_moneda_original > 0 && $mov->moneda && ! $mov->moneda->es_principal;

            if ($esExtranjera) {
                $moneda = $mov->moneda;
                $key = $moneda->codigo;
                if (! isset($resumen[$key])) {
                    $resumen[$key] = [
                        'codigo' => $moneda->codigo,
                        'simbolo' => $moneda->simbolo,
                        'nombre' => $moneda->nombre,
                        'es_principal' => false,
                        'saldo' => 0,
                    ];
                }
                $monto = (float) $mov->monto_moneda_original;
                $resumen[$key]['saldo'] += $mov->tipo === 'ingreso' ? $monto : -$monto;
            } elseif ($monedaPrincipal) {
                $monto = (float) $mov->monto;
                $resumen[$monedaPrincipal->codigo]['saldo'] += $mov->tipo === 'ingreso' ? $monto : -$monto;
            }
        }

        // Solo mostrar si hay más de 1 moneda
        return count($resumen) > 1 ? $resumen : [];
    }

    /**
     * Obtiene los movimientos paginados con filtros
     */
    public function getMovimientosProperty()
    {
        if (! $this->tesoreria) {
            return collect();
        }

        $query = MovimientoTesoreria::where('tesoreria_id', $this->tesoreria->id)
            ->with(['usuario', 'moneda']);

        // Filtro por tipo
        if ($this->filtroTipo) {
            $query->where('tipo', $this->filtroTipo);
        }

        // Filtro por fecha
        if ($this->filtroFechaDesde) {
            $query->whereDate('created_at', '>=', $this->filtroFechaDesde);
        }
        if ($this->filtroFechaHasta) {
            $query->whereDate('created_at', '<=', $this->filtroFechaHasta);
        }

        // Filtro por concepto
        if ($this->filtroConcepto) {
            $query->where('concepto', 'like', "%{$this->filtroConcepto}%");
        }

        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    /**
     * Obtiene las cajas disponibles para provisión
     */
    public function getCajasDisponiblesProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (! $sucursalId) {
            return collect();
        }

        return Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtiene las cuentas de empresa disponibles para depósitos
     */
    public function getCuentasEmpresaProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (! $sucursalId) {
            return collect();
        }

        return CuentaEmpresa::activas()
            ->with('moneda')
            ->orderBy('orden')
            ->get()
            ->filter(fn ($cuenta) => $cuenta->estaDisponibleEnSucursal($sucursalId))
            ->values();
    }

    /**
     * Obtiene el estado de todas las cajas de la sucursal
     */
    public function getEstadoCajasProperty()
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (! $sucursalId) {
            return collect();
        }

        return Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(function ($caja) {
                // Obtener último movimiento con usuario
                $ultimoMov = $caja->movimientos()->with('usuario:id,name')->latest()->first();

                return [
                    'id' => $caja->id,
                    'nombre' => $caja->nombre,
                    'numero' => $caja->numero_formateado ?? $caja->numero,
                    'saldo_actual' => $caja->saldo_actual,
                    'estado' => $caja->estado,
                    'esta_abierta' => $caja->estaAbierta(),
                    'ultimo_usuario' => $ultimoMov?->usuario?->name,
                    'ultimo_movimiento' => $ultimoMov?->created_at,
                ];
            });
    }

    /**
     * Obtiene los depósitos pendientes de confirmar
     */
    public function getDepositosPendientesProperty()
    {
        if (! $this->tesoreria) {
            return collect();
        }

        return DepositoBancario::where('tesoreria_id', $this->tesoreria->id)
            ->where('estado', DepositoBancario::ESTADO_PENDIENTE)
            ->with(['cuentaBancaria', 'cuentaEmpresa.moneda', 'moneda', 'usuario'])
            ->orderBy('fecha_deposito', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Obtiene el historial de arqueos
     */
    public function getArqueosProperty()
    {
        if (! $this->tesoreria) {
            return collect();
        }

        return ArqueoTesoreria::where('tesoreria_id', $this->tesoreria->id)
            ->with(['usuario:id,name', 'supervisor:id,name', 'moneda:id,codigo,simbolo,nombre'])
            ->orderBy('fecha', 'desc')
            ->limit(20)
            ->get();
    }

    /**
     * Obtiene el historial de provisiones recientes
     */
    public function getProvisionesRecientesProperty()
    {
        if (! $this->tesoreria) {
            return collect();
        }

        return ProvisionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->with(['caja:id,nombre', 'usuarioEntrega:id,name'])
            ->orderBy('fecha', 'desc')
            ->limit(10)
            ->get();
    }

    /**
     * Obtiene el historial de rendiciones recientes
     */
    public function getRendicionesRecientesProperty()
    {
        if (! $this->tesoreria) {
            return collect();
        }

        return RendicionFondo::where('tesoreria_id', $this->tesoreria->id)
            ->with(['caja:id,nombre', 'usuarioEntrega:id,name'])
            ->orderBy('fecha', 'desc')
            ->limit(10)
            ->get();
    }

    // ==================== PROVISIÓN DE FONDO ====================

    public function abrirModalProvision(): void
    {
        $this->reset(['cajaProvisionId', 'montoProvision', 'observacionesProvision', 'monedaProvisionId', 'montoProvisionMoneda']);
        $this->showProvisionModal = true;
    }

    public function updatedMonedaProvisionId(): void
    {
        $this->montoProvision = 0;
        $this->montoProvisionMoneda = 0;
    }

    public function procesarProvision(): void
    {
        $esMonedaExtranjera = ! empty($this->monedaProvisionId);

        if ($esMonedaExtranjera) {
            $this->validate([
                'cajaProvisionId' => 'required|exists:pymes_tenant.cajas,id',
                'montoProvisionMoneda' => 'required|numeric|min:0.01',
            ], [
                'cajaProvisionId.required' => __('Seleccione una caja'),
                'montoProvisionMoneda.required' => __('Ingrese el monto'),
                'montoProvisionMoneda.min' => __('El monto debe ser mayor a 0'),
            ]);
        } else {
            $this->validate([
                'cajaProvisionId' => 'required|exists:pymes_tenant.cajas,id',
                'montoProvision' => 'required|numeric|min:0.01',
            ], [
                'cajaProvisionId.required' => __('Seleccione una caja'),
                'montoProvision.required' => __('Ingrese el monto'),
                'montoProvision.min' => __('El monto debe ser mayor a 0'),
            ]);
        }

        try {
            $caja = Caja::find($this->cajaProvisionId);

            $provision = TesoreriaService::provisionarFondo(
                $this->tesoreria,
                $caja,
                $esMonedaExtranjera ? 0 : $this->montoProvision,
                auth()->id(),
                $this->observacionesProvision ?: null,
                $esMonedaExtranjera ? (int) $this->monedaProvisionId : null,
                $esMonedaExtranjera ? $this->montoProvisionMoneda : null
            );

            $this->showProvisionModal = false;
            $this->cargarDatos();

            if ($esMonedaExtranjera) {
                $moneda = Moneda::find($this->monedaProvisionId);
                $this->dispatch('toast-success', message: __('Provision de :monto realizada a :caja', [
                    'monto' => ($moneda->simbolo ?? '').' '.$this->montoProvisionMoneda,
                    'caja' => $caja->nombre,
                ]));
            } else {
                $this->dispatch('toast-success', message: __('Provision de :monto realizada a :caja', [
                    'monto' => '$'.$this->montoProvision,
                    'caja' => $caja->nombre,
                ]));
            }

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== CONFIRMACIÓN DE RENDICIÓN ====================

    public function abrirModalRendicion(): void
    {
        $this->cargarDatos();
        $this->rendicionSeleccionada = null;
        $this->showRendicionModal = true;
    }

    public function confirmarRendicion(int $rendicionId): void
    {
        try {
            $rendicion = RendicionFondo::find($rendicionId);

            if (! $rendicion) {
                throw new \Exception(__('Rendicion no encontrada'));
            }

            TesoreriaService::confirmarRendicion($rendicion, auth()->id());

            $this->cargarDatos();

            $this->dispatch('toast-success', message: __('Rendicion de caja :caja confirmada', ['caja' => $rendicion->caja->nombre]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== RECHAZO Y REVERSIÓN ====================

    public function abrirModalRechazo(int $rendicionId): void
    {
        $this->rendicionARechazar = $rendicionId;
        $this->motivoRechazo = '';
        $this->showRechazoModal = true;
    }

    public function rechazarYRevertirCierre(): void
    {
        try {
            $rendicion = RendicionFondo::find($this->rendicionARechazar);

            if (! $rendicion) {
                throw new \Exception(__('Rendicion no encontrada'));
            }

            TesoreriaService::rechazarYRevertirCierre(
                $rendicion,
                auth()->id(),
                $this->motivoRechazo ?: null
            );

            $this->showRechazoModal = false;
            $this->rendicionARechazar = null;
            $this->motivoRechazo = '';
            $this->cargarDatos();

            $this->dispatch('toast-success', message: __('Cierre de turno rechazado y revertido correctamente'));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    /**
     * Evalúa si una rendición pendiente puede ser revertida.
     * Condiciones: cierre no revertido, es el último cierre de esa caja/grupo,
     * y (para grupo sin fondo común) todas las rendiciones del cierre están pendientes.
     */
    protected function evaluarRevertibilidad(RendicionFondo $rendicion): bool
    {
        $cierre = $rendicion->cierreTurno;
        if (! $cierre || $cierre->estaRevertido()) {
            return false;
        }

        // Verificar que las cajas del cierre no estén abiertas con un nuevo turno
        foreach ($cierre->detalleCajas as $detalleCaja) {
            $caja = Caja::find($detalleCaja->caja_id);
            if ($caja && $caja->estado === 'abierta') {
                return false;
            }
        }

        // Verificar que sea el último cierre para esa caja/grupo
        if ($cierre->esIndividual()) {
            $detalleCaja = $cierre->detalleCajas->first();
            if (! $detalleCaja) {
                return false;
            }
            $ultimoCierre = CierreTurno::noRevertidos()
                ->whereHas('detalleCajas', fn ($q) => $q->where('caja_id', $detalleCaja->caja_id))
                ->orderBy('fecha_cierre', 'desc')
                ->first();
            if (! $ultimoCierre || $ultimoCierre->id !== $cierre->id) {
                return false;
            }
        } else {
            $ultimoCierreGrupo = CierreTurno::noRevertidos()
                ->where('grupo_cierre_id', $cierre->grupo_cierre_id)
                ->orderBy('fecha_cierre', 'desc')
                ->first();
            if (! $ultimoCierreGrupo || $ultimoCierreGrupo->id !== $cierre->id) {
                return false;
            }

            // Para grupo sin fondo común: verificar que todas las rendiciones estén pendientes
            $esGrupalConFondoComun = $cierre->grupoCierre && $cierre->grupoCierre->usaFondoComun();
            if (! $esGrupalConFondoComun) {
                $tieneNoPendientes = RendicionFondo::where('cierre_turno_id', $cierre->id)
                    ->where('estado', '!=', RendicionFondo::ESTADO_PENDIENTE)
                    ->exists();
                if ($tieneNoPendientes) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Resumen de monedas extranjeras en rendiciones pendientes
     */
    public function getResumenMonedasRendicionesProperty(): array
    {
        $monedas = [];
        foreach ($this->rendicionesPendientes as $rendicion) {
            if (empty($rendicion['desglose_monedas'])) {
                continue;
            }
            foreach ($rendicion['desglose_monedas'] as $codigo => $data) {
                if (! isset($monedas[$codigo])) {
                    $monedas[$codigo] = [
                        'codigo' => $data['codigo'] ?? $codigo,
                        'simbolo' => $data['simbolo'] ?? '',
                        'nombre' => $data['nombre'] ?? '',
                        'saldo' => 0,
                        'saldo_convertido' => 0,
                    ];
                }
                $monedas[$codigo]['saldo'] += $data['saldo'] ?? 0;
                $monedas[$codigo]['saldo_convertido'] += $data['saldo_convertido'] ?? 0;
            }
        }

        return $monedas;
    }

    /**
     * Consolida desglose de monedas desde las CierreTurnoCaja del cierre asociado a la rendición.
     * Retorna array de monedas extranjeras (excluye principal) con saldo consolidado.
     */
    protected function consolidarDesgloseMonedas(RendicionFondo $rendicion): array
    {
        $cierre = $rendicion->cierreTurno;
        if (! $cierre || ! $cierre->detalleCajas) {
            return [];
        }

        $esFondoComun = $cierre->esGrupal() && $cierre->grupoCierre?->fondo_comun;
        $monedas = [];

        foreach ($cierre->detalleCajas as $detalleCaja) {
            if (! $detalleCaja->desglose_monedas) {
                continue;
            }

            foreach ($detalleCaja->desglose_monedas as $codigo => $data) {
                // Solo incluir monedas extranjeras (la principal ya está en monto_entregado)
                if ($data['es_principal'] ?? false) {
                    continue;
                }

                if (! isset($monedas[$codigo])) {
                    $monedas[$codigo] = [
                        'codigo' => $data['codigo'] ?? $codigo,
                        'simbolo' => $data['simbolo'] ?? '',
                        'nombre' => $data['nombre'] ?? '',
                        'saldo' => 0,
                        'saldo_convertido' => 0,
                        'declarado' => null,
                    ];
                }

                $monedas[$codigo]['saldo'] += $data['saldo'] ?? 0;
                $monedas[$codigo]['saldo_convertido'] += $data['saldo_convertido'] ?? 0;

                if (isset($data['declarado']) && $data['declarado'] !== null) {
                    if ($esFondoComun) {
                        $monedas[$codigo]['declarado'] = $data['declarado'];
                    } else {
                        $monedas[$codigo]['declarado'] = ($monedas[$codigo]['declarado'] ?? 0) + $data['declarado'];
                    }
                }
            }
        }

        return $monedas;
    }

    // ==================== DEPÓSITO BANCARIO ====================

    public function abrirModalDeposito(): void
    {
        $this->reset(['cuentaEmpresaId', 'montoDeposito', 'numeroComprobante', 'observacionesDeposito']);
        $this->fechaDeposito = now()->format('Y-m-d');
        $this->showDepositoModal = true;
    }

    public function procesarDeposito(): void
    {
        $this->validate([
            'cuentaEmpresaId' => 'required|exists:pymes_tenant.cuentas_empresa,id',
            'montoDeposito' => 'required|numeric|min:0.01',
            'fechaDeposito' => 'required|date',
        ], [
            'cuentaEmpresaId.required' => __('Seleccione una cuenta'),
            'montoDeposito.required' => __('Ingrese el monto'),
            'montoDeposito.min' => __('El monto debe ser mayor a 0'),
            'fechaDeposito.required' => __('Ingrese la fecha del deposito'),
        ]);

        try {
            $cuenta = CuentaEmpresa::with('moneda')->find($this->cuentaEmpresaId);

            $deposito = TesoreriaService::registrarDepositoCuentaEmpresa(
                $this->tesoreria,
                $cuenta,
                $this->montoDeposito,
                Carbon::parse($this->fechaDeposito),
                auth()->id(),
                $this->numeroComprobante ?: null,
                $this->observacionesDeposito ?: null
            );

            $this->showDepositoModal = false;
            $this->cargarDatos();

            $simbolo = ($cuenta->moneda && ! $cuenta->moneda->es_principal) ? $cuenta->moneda->simbolo : '$';
            $this->dispatch('toast-success', message: __('Deposito de :monto registrado en :cuenta', [
                'monto' => $simbolo.' '.number_format($this->montoDeposito, 2, ',', '.'),
                'cuenta' => $cuenta->nombre_completo,
            ]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== ARQUEO ====================

    public function abrirModalArqueo(): void
    {
        $this->reset(['saldoContado', 'observacionesArqueo', 'monedaArqueoId']);
        $this->saldoContado = $this->tesoreria->saldo_actual;
        $this->showArqueoModal = true;
    }

    public function updatedMonedaArqueoId(): void
    {
        if ($this->monedaArqueoId) {
            $saldoMoneda = TesoreriaSaldoMoneda::obtenerOCrear($this->tesoreria->id, (int) $this->monedaArqueoId);
            $this->saldoContado = (float) $saldoMoneda->saldo_actual;
        } else {
            $this->saldoContado = (float) $this->tesoreria->saldo_actual;
        }
    }

    public function procesarArqueo(): void
    {
        $this->validate([
            'saldoContado' => 'required|numeric|min:0',
        ], [
            'saldoContado.required' => __('Ingrese el saldo contado'),
            'saldoContado.min' => __('El saldo no puede ser negativo'),
        ]);

        try {
            $monedaId = $this->monedaArqueoId ? (int) $this->monedaArqueoId : null;

            $arqueo = TesoreriaService::realizarArqueo(
                $this->tesoreria,
                $this->saldoContado,
                auth()->id(),
                $this->observacionesArqueo ?: null,
                $monedaId
            );

            $this->showArqueoModal = false;
            $this->cargarDatos();

            $diferencia = $arqueo->diferencia;
            $monedaInfo = $monedaId ? Moneda::find($monedaId) : null;
            $simbolo = $monedaInfo ? $monedaInfo->simbolo : '$';

            if ($diferencia == 0) {
                $this->dispatch('toast-success', message: __('Arqueo realizado - Caja cuadrada'));
            } elseif ($diferencia > 0) {
                $this->dispatch('toast-success', message: __('Arqueo realizado - Sobrante:')." {$simbolo}".number_format($diferencia, 2));
            } else {
                $this->dispatch('toast-error', message: __('Arqueo realizado - Faltante:')." {$simbolo}".number_format(abs($diferencia), 2));
            }

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== DEPÓSITOS PENDIENTES ====================

    public function confirmarDepositoBancario(int $depositoId): void
    {
        try {
            $deposito = DepositoBancario::with(['moneda', 'cuentaEmpresa.moneda'])->find($depositoId);

            if (! $deposito) {
                throw new \Exception(__('Deposito no encontrado'));
            }

            TesoreriaService::confirmarDeposito($deposito);

            $monedaDep = $deposito->moneda ?? $deposito->cuentaEmpresa?->moneda;
            $simbolo = ($monedaDep && ! $monedaDep->es_principal) ? $monedaDep->simbolo : '$';
            $this->dispatch('toast-success', message: __('Deposito de :monto confirmado', ['monto' => $simbolo.' '.number_format($deposito->monto, 2, ',', '.')]));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    public function cancelarDepositoBancario(int $depositoId): void
    {
        try {
            $deposito = DepositoBancario::find($depositoId);

            if (! $deposito) {
                throw new \Exception(__('Deposito no encontrado'));
            }

            $deposito->cancelar();
            $this->cargarDatos();

            $this->dispatch('toast-info', message: __('Deposito cancelado - Monto devuelto a tesoreria'));

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== ARQUEOS ====================

    public function verDetalleArqueo(int $arqueoId): void
    {
        $this->arqueoDetalle = ArqueoTesoreria::with(['usuario:id,name', 'supervisor:id,name', 'moneda:id,codigo,simbolo,nombre'])
            ->find($arqueoId);
        $this->showArqueoDetalleModal = true;
    }

    public function cerrarDetalleArqueo(): void
    {
        $this->showArqueoDetalleModal = false;
        $this->arqueoDetalle = null;
    }

    public function aprobarArqueo(int $arqueoId, bool $aplicarAjuste = false): void
    {
        try {
            $arqueo = ArqueoTesoreria::find($arqueoId);

            if (! $arqueo) {
                throw new \Exception(__('Arqueo no encontrado'));
            }

            TesoreriaService::aprobarArqueo($arqueo, auth()->id(), $aplicarAjuste);

            $this->showArqueoDetalleModal = false;
            $this->arqueoDetalle = null;

            $mensaje = $aplicarAjuste && $arqueo->diferencia != 0
                ? __('Arqueo aprobado y ajuste aplicado')
                : __('Arqueo aprobado');

            $this->dispatch('toast-success', message: $mensaje);

        } catch (\Exception $e) {
            $this->dispatch('toast-error', message: $e->getMessage());
        }
    }

    // ==================== CANCEL MODALS ====================

    public function cancelProvision(): void
    {
        $this->showProvisionModal = false;
    }

    public function cancelRendicion(): void
    {
        $this->showRendicionModal = false;
    }

    public function cancelRechazo(): void
    {
        $this->showRechazoModal = false;
    }

    public function cancelDeposito(): void
    {
        $this->showDepositoModal = false;
    }

    public function cancelArqueo(): void
    {
        $this->showArqueoModal = false;
    }

    // ==================== FILTROS ====================

    public function limpiarFiltros(): void
    {
        $this->reset(['filtroTipo', 'filtroConcepto']);
        $this->filtroFechaDesde = now()->startOfMonth()->format('Y-m-d');
        $this->filtroFechaHasta = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function updatedFiltroTipo(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroFechaDesde(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroFechaHasta(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroConcepto(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.tesoreria.gestion-tesoreria', [
            'movimientos' => $this->movimientos,
            'cajasDisponibles' => $this->cajasDisponibles,
            'cuentasEmpresa' => $this->cuentasEmpresa,
            'estadoCajas' => $this->estadoCajas,
            'depositosPendientes' => $this->depositosPendientes,
            'arqueos' => $this->arqueos,
            'provisionesRecientes' => $this->provisionesRecientes,
            'rendicionesRecientes' => $this->rendicionesRecientes,
            'saldosMonedas' => $this->saldosMonedas,
            'monedasExtranjeras' => $this->getMonedasExtranjeras(),
        ]);
    }
}

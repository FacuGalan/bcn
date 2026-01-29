<?php

namespace App\Traits;

use App\Models\Caja;
use App\Models\GrupoCierre;
use App\Models\CierreTurnoCaja;
use App\Services\CajaService;
use App\Services\SucursalService;
use App\Services\TesoreriaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait para manejar la apertura de turnos desde cualquier componente Livewire
 *
 * Uso: Incluir este trait en el componente y agregar el modal de apertura en la vista.
 *
 * Propiedades que agrega:
 * - showAperturaModal: bool
 * - cajaAperturaId: ?int
 * - grupoAperturaId: ?int
 * - cajasAAbrir: array
 * - fondosIniciales: array
 * - esAperturaGrupal: bool
 * - grupoUsaFondoComun: bool
 * - fondoComunTotal: string
 *
 * Métodos que agrega:
 * - abrirModalApertura(?int $cajaId, ?int $grupoId)
 * - procesarApertura()
 * - cancelarApertura()
 * - getCajaParaApertura(int $cajaId)
 * - getGrupoParaApertura()
 */
trait AperturaTurnoTrait
{
    // Modal de apertura de turno
    public bool $showAperturaModal = false;
    public ?int $cajaAperturaId = null;
    public ?int $grupoAperturaId = null;
    public array $cajasAAbrir = [];
    public array $fondosIniciales = [];
    public bool $esAperturaGrupal = false;
    public bool $grupoUsaFondoComun = false;
    public $fondoComunTotal = '';

    /**
     * Abre el modal de apertura de turno
     */
    public function abrirModalApertura(?int $cajaId = null, ?int $grupoId = null): void
    {
        $this->resetAperturaForm();

        // Si no se pasa nada, usar la caja activa
        if ($cajaId === null && $grupoId === null) {
            $cajaId = CajaService::getCajaActiva();
        }

        // Si es una caja individual, verificar si pertenece a un grupo
        if ($cajaId && !$grupoId) {
            $caja = Caja::find($cajaId);
            if ($caja && $caja->grupo_cierre_id) {
                // La caja pertenece a un grupo, abrir todo el grupo
                $grupoId = $caja->grupo_cierre_id;
                $cajaId = null;
            }
        }

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
            $this->fondoComunTotal = '';

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
     * Calcula el fondo inicial para mostrar en el input
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
                return '';
        }
    }

    /**
     * Procesa la apertura del turno
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

            // Actualizar el estado de la caja si el método existe
            if (method_exists($this, 'actualizarEstadoCaja')) {
                $this->actualizarEstadoCaja();
            }

            $mensaje = $this->esAperturaGrupal
                ? 'Turno del grupo abierto exitosamente'
                : 'Turno de caja abierto exitosamente';

            if ($this->grupoUsaFondoComun && $this->fondoComunTotal !== '') {
                $mensaje .= ' (Fondo común: $' . number_format((float)$this->fondoComunTotal, 2, ',', '.') . ')';
            }

            $this->dispatch('toast-success', message: $mensaje);

            // Disparar evento para que otros componentes se actualicen (CajaSelector, TurnoActual)
            $this->dispatch('caja-actualizada', cajaId: $this->cajasAAbrir[0] ?? null, accion: 'turno_abierto');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al abrir turno', ['error' => $e->getMessage()]);
            $this->dispatch('toast-error', message: 'Error al abrir el turno: ' . $e->getMessage());
        }
    }

    /**
     * Cancela la apertura
     */
    public function cancelarApertura(): void
    {
        $this->showAperturaModal = false;
        $this->resetAperturaForm();
    }

    /**
     * Resetea el formulario de apertura
     */
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
     * Obtiene información de caja para modal de apertura
     */
    public function getCajaParaApertura(int $cajaId): ?array
    {
        $caja = Caja::find($cajaId);
        if (!$caja) return null;

        return [
            'id' => $caja->id,
            'nombre' => $caja->nombre,
            'numero' => $caja->numero_formateado ?? str_pad($caja->numero ?? $caja->id, 3, '0', STR_PAD_LEFT),
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

        $cajasInfo = [];
        foreach ($grupo->cajas->where('activo', true) as $caja) {
            $cajasInfo[] = [
                'id' => $caja->id,
                'nombre' => $caja->nombre,
                'numero' => $caja->numero_formateado ?? str_pad($caja->numero ?? $caja->id, 3, '0', STR_PAD_LEFT),
                'modo_carga' => $caja->modo_carga_inicial ?? 'manual',
            ];
        }

        return [
            'id' => $grupo->id,
            'nombre' => $grupo->nombre ?? 'Grupo de Cajas',
            'fondo_comun' => $grupo->fondo_comun,
            'saldo_fondo_comun' => $grupo->saldo_fondo_comun ?? 0,
            'cantidad_cajas' => count($cajasInfo),
            'cajas' => $cajasInfo,
        ];
    }
}

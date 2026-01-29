<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\Tesoreria;
use App\Models\MovimientoCaja;
use App\Models\CierreTurno;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CajaService
{
    protected static ?Collection $cajasCache = null;
    protected static ?array $cajaIdsCache = null;
    protected static ?Caja $cajaActivaCache = null;

    /**
     * Obtiene todas las cajas disponibles para el usuario en la sucursal activa
     * FILTROS APLICADOS:
     * - Solo de la sucursal activa
     * - Solo cajas activas (activo = true)
     * - Solo cajas asignadas al usuario (si tiene restricciones)
     *
     * NOTA: Se muestran todas las cajas sin importar su estado (abierta/cerrada)
     * para que el usuario pueda ver y seleccionar cualquier caja a la que tenga acceso.
     * Use validarCajaOperativa() para verificar si puede realizar operaciones.
     *
     * @return Collection
     */
    public static function getCajasDisponibles(): Collection
    {
        if (self::$cajasCache !== null) {
            return self::$cajasCache;
        }

        if (!auth()->check()) {
            return collect();
        }

        // Verificar que hay un comercio activo antes de consultar el tenant
        $comercioActivoId = session('comercio_activo_id');
        if (!$comercioActivoId) {
            return collect();
        }

        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return collect();
        }

        try {
            // Obtener cajas asignadas al usuario en esta sucursal
            $cajaIdsPermitidas = DB::connection('pymes_tenant')
                ->table('user_cajas')
                ->where('user_id', auth()->id())
                ->where('sucursal_id', $sucursalId)
                ->pluck('caja_id')
                ->toArray();
        } catch (\Exception $e) {
            return collect();
        }

        // Construir query base: sucursal activa, cajas activas (sin filtrar por estado)
        $query = Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true);

        // Si el usuario tiene cajas específicas asignadas, filtrar por ellas
        if (!empty($cajaIdsPermitidas)) {
            $query->whereIn('id', $cajaIdsPermitidas);
        }

        $cajas = $query->orderBy('id', 'asc')->get();

        self::$cajasCache = $cajas;

        return $cajas;
    }

    public static function getCajaActiva(): ?int
    {
        if (!auth()->check()) {
            return null;
        }

        return session('caja_activa');
    }

    public static function getCajaActivaModel(): ?Caja
    {
        if (self::$cajaActivaCache !== null) {
            return self::$cajaActivaCache;
        }

        $cajaId = self::getCajaActiva();

        if (!$cajaId) {
            return null;
        }

        $caja = Caja::find($cajaId);

        if ($caja) {
            self::$cajaActivaCache = $caja;
        }

        return $caja;
    }

    public static function establecerCajaActiva(int $cajaId): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (!self::tieneAccesoACaja($cajaId)) {
            return false;
        }

        self::clearCache();

        session()->put('caja_activa', $cajaId);

        return true;
    }

    public static function establecerPrimeraCajaDisponible(): ?int
    {
        $cajas = self::getCajasDisponibles();

        if ($cajas->isEmpty()) {
            session()->forget('caja_activa');
            return null;
        }

        $primeraCaja = $cajas->first();

        session()->put('caja_activa', $primeraCaja->id);

        return $primeraCaja->id;
    }

    /**
     * Verifica si el usuario tiene acceso a una caja específica
     * Validaciones:
     * - Caja existe y pertenece a la sucursal activa
     * - Caja está activa (activo = true, no confundir con estado)
     * - Usuario tiene permiso (si hay restricciones)
     *
     * NOTA: No valida el estado de la caja (abierta/cerrada) para permitir
     * que el usuario pueda seleccionar cualquier caja a la que tenga acceso.
     * Use validarCajaOperativa() para verificar si puede realizar operaciones.
     *
     * @param int $cajaId
     * @return bool
     */
    public static function tieneAccesoACaja(int $cajaId): bool
    {
        if (!auth()->check()) {
            return false;
        }

        // Verificar que hay un comercio activo antes de consultar el tenant
        $comercioActivoId = session('comercio_activo_id');
        if (!$comercioActivoId) {
            return false;
        }

        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return false;
        }

        // Verificar que la caja existe y está activa (sin filtrar por estado)
        $caja = Caja::where('id', $cajaId)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->first();

        if (!$caja) {
            return false;
        }

        try {
            // Verificar si el usuario tiene restricciones de cajas
            $tieneRestriccion = DB::connection('pymes_tenant')
                ->table('user_cajas')
                ->where('user_id', auth()->id())
                ->where('sucursal_id', $sucursalId)
                ->exists();

            // Si no tiene restricciones, puede acceder a todas las cajas
            if (!$tieneRestriccion) {
                return true;
            }

            // Si tiene restricciones, verificar que tenga acceso a esta caja específica
            $tieneAcceso = DB::connection('pymes_tenant')
                ->table('user_cajas')
                ->where('user_id', auth()->id())
                ->where('caja_id', $cajaId)
                ->where('sucursal_id', $sucursalId)
                ->exists();

            return $tieneAcceso;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getCajaIdsDisponibles(): array
    {
        if (self::$cajaIdsCache !== null) {
            return self::$cajaIdsCache;
        }

        $ids = self::getCajasDisponibles()->pluck('id')->toArray();

        self::$cajaIdsCache = $ids;

        return $ids;
    }

    /**
     * Valida si una caja puede aceptar operaciones (ventas, movimientos, etc.)
     *
     * Estados posibles:
     * - 'operativa': La caja está abierta y puede aceptar operaciones
     * - 'pausada': La caja está cerrada pero tiene movimientos pendientes del turno actual
     * - 'sin_turno': La caja está cerrada y no tiene movimientos pendientes (turno cerrado)
     * - 'sin_acceso': El usuario no tiene acceso a la caja
     *
     * @param int|null $cajaId Si es null, usa la caja activa en sesión
     * @return array ['operativa' => bool, 'estado' => string, 'mensaje' => string, 'caja' => ?Caja]
     */
    public static function validarCajaOperativa(?int $cajaId = null): array
    {
        // Si no se proporciona cajaId, usar la activa en sesión
        if ($cajaId === null) {
            $cajaId = self::getCajaActiva();
        }

        // Si no hay caja
        if (!$cajaId) {
            return [
                'operativa' => false,
                'estado' => 'sin_caja',
                'mensaje' => 'No hay ninguna caja seleccionada',
                'caja' => null,
            ];
        }

        // Verificar acceso
        if (!self::tieneAccesoACaja($cajaId)) {
            return [
                'operativa' => false,
                'estado' => 'sin_acceso',
                'mensaje' => 'No tienes acceso a esta caja',
                'caja' => null,
            ];
        }

        // Obtener la caja
        $caja = Caja::find($cajaId);

        if (!$caja) {
            return [
                'operativa' => false,
                'estado' => 'sin_caja',
                'mensaje' => 'La caja no existe',
                'caja' => null,
            ];
        }

        // Si la caja está abierta, es operativa
        if ($caja->estado === 'abierta') {
            return [
                'operativa' => true,
                'estado' => 'operativa',
                'mensaje' => 'La caja está operativa',
                'caja' => $caja,
            ];
        }

        // Caja cerrada: verificar si está pausada o sin turno
        // Criterios para "pausada" (turno activo pero caja inactiva):
        // 1. Tiene movimientos pendientes (sin cierre_turno_id)
        // 2. fecha_apertura > fecha_cierre (fue abierta después del último cierre)
        // 3. Pertenece a grupo y alguna caja del grupo está abierta

        $tieneMovimientosPendientes = MovimientoCaja::where('caja_id', $cajaId)
            ->whereNull('cierre_turno_id')
            ->exists();

        // Verificar si fue abierta después del último cierre
        $abiertaRecientemente = $caja->fecha_apertura !== null
            && ($caja->fecha_cierre === null || $caja->fecha_apertura > $caja->fecha_cierre);

        // Verificar si pertenece a grupo con turno activo (alguna caja del grupo abierta)
        $grupoConTurnoActivo = false;
        if ($caja->grupo_cierre_id) {
            $grupo = $caja->grupoCierre;
            if ($grupo) {
                $grupoConTurnoActivo = $grupo->tieneAlgunaAbierta();
            }
        }

        if ($tieneMovimientosPendientes || $abiertaRecientemente || $grupoConTurnoActivo) {
            return [
                'operativa' => false,
                'estado' => 'pausada',
                'mensaje' => 'La caja está inactiva. Actívala para continuar operando.',
                'caja' => $caja,
            ];
        }

        // Sin movimientos pendientes ni apertura reciente ni grupo activo: turno cerrado
        return [
            'operativa' => false,
            'estado' => 'sin_turno',
            'mensaje' => 'La caja no tiene turno abierto. Abre un turno para comenzar a operar.',
            'caja' => $caja,
        ];
    }

    /**
     * Verifica rápidamente si la caja activa puede aceptar operaciones
     * Wrapper simplificado de validarCajaOperativa()
     *
     * @return bool
     */
    public static function cajaActivaOperativa(): bool
    {
        return self::validarCajaOperativa()['operativa'];
    }

    /**
     * Obtiene el estado de la caja activa
     * Wrapper simplificado de validarCajaOperativa()
     *
     * @return string 'operativa', 'pausada', 'sin_turno', 'sin_caja', 'sin_acceso'
     */
    public static function estadoCajaActiva(): string
    {
        return self::validarCajaOperativa()['estado'];
    }

    public static function clearCache(): void
    {
        self::$cajasCache = null;
        self::$cajaIdsCache = null;
        self::$cajaActivaCache = null;
    }

    // ==================== MÉTODOS DE APERTURA/CIERRE CON TESORERÍA ====================

    /**
     * Abre una caja con provisión desde tesorería (si aplica)
     *
     * @param Caja $caja
     * @param float $fondoInicial
     * @param int $usuarioId
     * @param Tesoreria|null $tesoreria Si no se proporciona, busca la tesorería de la sucursal
     * @return array ['success' => bool, 'message' => string, 'provision' => ?ProvisionFondo]
     */
    public static function abrirCajaConTesoreria(
        Caja $caja,
        float $fondoInicial,
        int $usuarioId,
        ?Tesoreria $tesoreria = null
    ): array {
        try {
            // Si la caja ya está abierta, no hacer nada
            if ($caja->estaAbierta()) {
                return [
                    'success' => false,
                    'message' => 'La caja ya está abierta',
                    'provision' => null,
                ];
            }

            return DB::transaction(function () use ($caja, $fondoInicial, $usuarioId, $tesoreria) {
                $provision = null;

                // Buscar tesorería de la sucursal si no se proporciona
                if ($tesoreria === null) {
                    $tesoreria = Tesoreria::where('sucursal_id', $caja->sucursal_id)
                        ->activas()
                        ->first();
                }

                // Si hay tesorería ACTIVA y fondo inicial > 0, hacer provisión
                if ($tesoreria && $tesoreria->activo && $fondoInicial > 0) {
                    // Verificar saldo suficiente
                    if (!$tesoreria->tieneSaldoSuficiente($fondoInicial)) {
                        return [
                            'success' => false,
                            'message' => 'Saldo insuficiente en tesorería. Disponible: $' . number_format($tesoreria->saldo_actual, 2, ',', '.'),
                            'provision' => null,
                        ];
                    }

                    // Crear provisión de fondo
                    $provision = TesoreriaService::provisionarFondo(
                        $tesoreria,
                        $caja,
                        $fondoInicial,
                        $usuarioId,
                        'Apertura de turno'
                    );
                }

                // Abrir la caja
                $caja->update([
                    'estado' => 'abierta',
                    'saldo_inicial' => $fondoInicial,
                    'saldo_actual' => $fondoInicial,
                    'fecha_apertura' => now(),
                    'fecha_cierre' => null,
                    'usuario_apertura_id' => $usuarioId,
                ]);

                // Registrar movimiento de apertura si hay fondo y no hay tesorería
                if ($fondoInicial > 0 && !$tesoreria) {
                    MovimientoCaja::crearApertura($caja, $fondoInicial, $usuarioId);
                }

                self::clearCache();

                Log::info('Caja abierta', [
                    'caja_id' => $caja->id,
                    'fondo_inicial' => $fondoInicial,
                    'tesoreria_id' => $tesoreria?->id,
                    'provision_id' => $provision?->id,
                ]);

                return [
                    'success' => true,
                    'message' => 'Caja abierta correctamente',
                    'provision' => $provision,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error al abrir caja', [
                'caja_id' => $caja->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al abrir la caja: ' . $e->getMessage(),
                'provision' => null,
            ];
        }
    }

    /**
     * Cierra una caja con rendición a tesorería (si aplica)
     *
     * @param Caja $caja
     * @param float $saldoDeclarado
     * @param int $usuarioId
     * @param CierreTurno|null $cierreTurno
     * @param Tesoreria|null $tesoreria
     * @param string|null $observaciones
     * @return array ['success' => bool, 'message' => string, 'rendicion' => ?RendicionFondo, 'diferencia' => float]
     */
    public static function cerrarCajaConTesoreria(
        Caja $caja,
        float $saldoDeclarado,
        int $usuarioId,
        ?CierreTurno $cierreTurno = null,
        ?Tesoreria $tesoreria = null,
        ?string $observaciones = null
    ): array {
        try {
            // Si la caja ya está cerrada, no hacer nada
            if ($caja->estaCerrada()) {
                return [
                    'success' => false,
                    'message' => 'La caja ya está cerrada',
                    'rendicion' => null,
                    'diferencia' => 0,
                ];
            }

            return DB::transaction(function () use ($caja, $saldoDeclarado, $usuarioId, $cierreTurno, $tesoreria, $observaciones) {
                $rendicion = null;

                // El saldo del sistema es el saldo_actual de la caja (ya refleja todos los movimientos)
                $saldoSistema = $caja->saldo_actual;
                $diferencia = $saldoDeclarado - $saldoSistema;

                // Buscar tesorería de la sucursal si no se proporciona
                if ($tesoreria === null) {
                    $tesoreria = Tesoreria::where('sucursal_id', $caja->sucursal_id)
                        ->activas()
                        ->first();
                }

                // Si hay tesorería ACTIVA y monto a rendir > 0, hacer rendición
                $seRindio = false;
                if ($tesoreria && $tesoreria->activo && $saldoDeclarado > 0) {
                    $rendicion = TesoreriaService::rendirFondo(
                        $caja,
                        $tesoreria,
                        $saldoDeclarado,
                        $saldoSistema,
                        $usuarioId,
                        $cierreTurno?->id,
                        $observaciones
                    );
                    $seRindio = true;
                }

                // Cerrar la caja
                $caja->update([
                    'estado' => 'cerrada',
                    'fecha_cierre' => now(),
                    'usuario_cierre_id' => $usuarioId,
                    // Saldo queda en 0 si se rindió a tesorería, sino queda el declarado
                    'saldo_actual' => $seRindio ? 0 : $saldoDeclarado,
                ]);

                // Marcar movimientos como cerrados si hay cierre de turno
                if ($cierreTurno) {
                    MovimientoCaja::where('caja_id', $caja->id)
                        ->whereNull('cierre_turno_id')
                        ->update(['cierre_turno_id' => $cierreTurno->id]);
                }

                self::clearCache();

                Log::info('Caja cerrada', [
                    'caja_id' => $caja->id,
                    'saldo_declarado' => $saldoDeclarado,
                    'saldo_sistema' => $saldoSistema,
                    'diferencia' => $diferencia,
                    'tesoreria_id' => $tesoreria?->id,
                    'rendicion_id' => $rendicion?->id,
                ]);

                return [
                    'success' => true,
                    'message' => 'Caja cerrada correctamente',
                    'rendicion' => $rendicion,
                    'diferencia' => $diferencia,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Error al cerrar caja', [
                'caja_id' => $caja->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al cerrar la caja: ' . $e->getMessage(),
                'rendicion' => null,
                'diferencia' => 0,
            ];
        }
    }

    /**
     * Obtiene la tesorería de la sucursal activa
     */
    public static function getTesoreriaActiva(): ?Tesoreria
    {
        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return null;
        }

        return Tesoreria::where('sucursal_id', $sucursalId)
            ->activas()
            ->first();
    }

    /**
     * Verifica si la sucursal activa tiene tesorería configurada
     */
    public static function tieneTesoreria(): bool
    {
        return self::getTesoreriaActiva() !== null;
    }
}

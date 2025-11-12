<?php

namespace App\Services;

use App\Models\Caja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
     * - Solo cajas ABIERTAS (estado = 'abierta')
     * - Solo cajas asignadas al usuario (si tiene restricciones)
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

        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return collect();
        }

        // Obtener cajas asignadas al usuario en esta sucursal
        $cajaIdsPermitidas = DB::connection('pymes_tenant')
            ->table('user_cajas')
            ->where('user_id', auth()->id())
            ->where('sucursal_id', $sucursalId)
            ->pluck('caja_id')
            ->toArray();

        // Construir query base: sucursal activa, cajas activas Y ABIERTAS
        $query = Caja::where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->where('estado', 'abierta'); // SOLO CAJAS ABIERTAS

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
     * - Caja está activa
     * - Caja está ABIERTA
     * - Usuario tiene permiso (si hay restricciones)
     *
     * @param int $cajaId
     * @return bool
     */
    public static function tieneAccesoACaja(int $cajaId): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $sucursalId = SucursalService::getSucursalActiva();

        if (!$sucursalId) {
            return false;
        }

        // Verificar que la caja existe, está activa Y ABIERTA
        $caja = Caja::where('id', $cajaId)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->where('estado', 'abierta') // SOLO CAJAS ABIERTAS
            ->first();

        if (!$caja) {
            return false;
        }

        // Verificar si el usuario tiene restricciones de cajas
        $tieneRestriccion = DB::connection('pymes_tenant')
            ->table('user_cajas')
            ->where('user_id', auth()->id())
            ->where('sucursal_id', $sucursalId)
            ->exists();

        // Si no tiene restricciones, puede acceder a todas las cajas abiertas
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

    public static function clearCache(): void
    {
        self::$cajasCache = null;
        self::$cajaIdsCache = null;
        self::$cajaActivaCache = null;
    }
}

<?php

namespace App\Services;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Servicio: SucursalService
 *
 * Centraliza toda la lógica relacionada con sucursales:
 * - Obtener sucursal activa de la sesión
 * - Validar acceso del usuario a sucursales
 * - Listar sucursales disponibles para el usuario
 *
 * OPTIMIZADO: Usa caché durante el request para evitar consultas repetidas
 *
 * FASE 4 - Sistema Multi-Sucursal
 */
class SucursalService
{
    /**
     * Caché de sucursales disponibles durante el request
     *
     * @var Collection|null
     */
    protected static ?Collection $sucursalesCache = null;

    /**
     * Caché de IDs de sucursales disponibles durante el request
     *
     * @var array|null
     */
    protected static ?array $sucursalIdsCache = null;

    /**
     * Caché del modelo de sucursal activa durante el request
     *
     * @var Sucursal|null
     */
    protected static ?Sucursal $sucursalActivaCache = null;

    /**
     * Obtiene el ID de la sucursal activa de la sesión
     *
     * @return int|null
     */
    public static function getSucursalActiva(): ?int
    {
        return session('sucursal_id');
    }

    /**
     * Obtiene el modelo de la sucursal activa
     * OPTIMIZADO: Usa caché durante el request
     *
     * @return Sucursal|null
     */
    public static function getSucursalActivaModel(): ?Sucursal
    {
        $sucursalId = self::getSucursalActiva();

        if (!$sucursalId) {
            return null;
        }

        // Si ya está en caché y es la misma sucursal, retornar caché
        if (self::$sucursalActivaCache && self::$sucursalActivaCache->id === $sucursalId) {
            return self::$sucursalActivaCache;
        }

        // Buscar en la colección de sucursales disponibles primero (evita otra query)
        $sucursales = self::getSucursalesDisponibles();
        $sucursal = $sucursales->firstWhere('id', $sucursalId);

        if ($sucursal) {
            self::$sucursalActivaCache = $sucursal;
            return $sucursal;
        }

        // Fallback: buscar directamente en BD
        $sucursal = Sucursal::find($sucursalId);
        self::$sucursalActivaCache = $sucursal;

        return $sucursal;
    }

    /**
     * Obtiene todas las sucursales a las que el usuario tiene acceso
     * OPTIMIZADO: Cachea durante el request para evitar consultas repetidas
     *
     * @return Collection
     */
    public static function getSucursalesDisponibles(): Collection
    {
        // Si ya está en caché, retornar caché
        if (self::$sucursalesCache !== null) {
            return self::$sucursalesCache;
        }

        $user = Auth::user();

        if (!$user) {
            self::$sucursalesCache = collect();
            return self::$sucursalesCache;
        }

        // Obtener sucursales a las que el usuario tiene acceso desde model_has_roles
        $sucursalIds = DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $user->id)
            ->pluck('sucursal_id')
            ->unique()
            ->toArray();

        if (empty($sucursalIds)) {
            self::$sucursalesCache = collect();
            self::$sucursalIdsCache = [];
            return self::$sucursalesCache;
        }

        // Cachear los IDs también
        self::$sucursalIdsCache = $sucursalIds;

        // Si tiene sucursal_id = 0, significa acceso a TODAS las sucursales
        if (in_array(0, $sucursalIds)) {
            self::$sucursalesCache = Sucursal::where('activa', true)
                ->orderBy('es_principal', 'desc')
                ->orderBy('nombre')
                ->get();

            return self::$sucursalesCache;
        }

        // Si no, retornar solo las sucursales específicas a las que tiene acceso
        self::$sucursalesCache = Sucursal::whereIn('id', $sucursalIds)
            ->where('activa', true)
            ->orderBy('es_principal', 'desc')
            ->orderBy('nombre')
            ->get();

        return self::$sucursalesCache;
    }

    /**
     * Verifica si el usuario tiene acceso a una sucursal específica
     * OPTIMIZADO: Usa caché de IDs para evitar consulta completa
     *
     * @param int $sucursalId
     * @return bool
     */
    public static function tieneAccesoASucursal(int $sucursalId): bool
    {
        // Si ya tenemos los IDs en caché, usar esos
        if (self::$sucursalIdsCache !== null) {
            // Si tiene sucursal_id = 0, tiene acceso a todas
            if (in_array(0, self::$sucursalIdsCache)) {
                return true;
            }

            return in_array($sucursalId, self::$sucursalIdsCache);
        }

        // Si no hay caché, obtener sucursales (esto poblará el caché)
        $sucursalesDisponibles = self::getSucursalesDisponibles();

        return $sucursalesDisponibles->contains('id', $sucursalId);
    }

    /**
     * Establece la sucursal activa en la sesión
     * Solo si el usuario tiene acceso a ella
     *
     * @param int $sucursalId
     * @return bool
     */
    public static function setSucursalActiva(int $sucursalId): bool
    {
        if (!self::tieneAccesoASucursal($sucursalId)) {
            return false;
        }

        session(['sucursal_id' => $sucursalId]);
        return true;
    }

    /**
     * Obtiene la sucursal principal del usuario
     *
     * @return Sucursal|null
     */
    public static function getSucursalPrincipal(): ?Sucursal
    {
        $sucursales = self::getSucursalesDisponibles();

        // Buscar la principal
        $principal = $sucursales->firstWhere('es_principal', true);

        // Si no hay principal, retornar la primera
        return $principal ?? $sucursales->first();
    }

    /**
     * Valida que haya una sucursal activa válida
     * Si no hay, establece la principal o primera disponible
     *
     * @return void
     */
    public static function validarYEstablecerSucursalActiva(): void
    {
        $sucursalActual = self::getSucursalActiva();

        // Si no hay sucursal en sesión o no tiene acceso a la actual
        if (!$sucursalActual || !self::tieneAccesoASucursal($sucursalActual)) {
            $principal = self::getSucursalPrincipal();

            if ($principal) {
                self::setSucursalActiva($principal->id);
            }
        }
    }

    /**
     * Limpia el caché de sucursales
     * Útil cuando se cambian permisos o se agregan/eliminan sucursales
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::$sucursalesCache = null;
        self::$sucursalIdsCache = null;
        self::$sucursalActivaCache = null;
    }
}

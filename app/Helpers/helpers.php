<?php

use App\Services\SucursalService;
use App\Services\CajaService;
use App\Models\Sucursal;
use App\Models\Caja;

if (!function_exists('sucursal_activa')) {
    /**
     * Obtiene el ID de la sucursal activa de la sesión
     *
     * @return int|null
     */
    function sucursal_activa(): ?int
    {
        return SucursalService::getSucursalActiva();
    }
}

if (!function_exists('sucursal_activa_model')) {
    /**
     * Obtiene el modelo de la sucursal activa
     *
     * @return Sucursal|null
     */
    function sucursal_activa_model(): ?Sucursal
    {
        return SucursalService::getSucursalActivaModel();
    }
}

if (!function_exists('tiene_acceso_sucursal')) {
    /**
     * Verifica si el usuario tiene acceso a una sucursal
     *
     * @param int $sucursalId
     * @return bool
     */
    function tiene_acceso_sucursal(int $sucursalId): bool
    {
        return SucursalService::tieneAccesoASucursal($sucursalId);
    }
}

// =====================================================
// HELPERS DE CAJAS
// =====================================================

if (!function_exists('caja_activa')) {
    /**
     * Obtiene el ID de la caja activa de la sesión
     *
     * @return int|null
     */
    function caja_activa(): ?int
    {
        return CajaService::getCajaActiva();
    }
}

if (!function_exists('caja_activa_model')) {
    /**
     * Obtiene el modelo de la caja activa
     *
     * @return Caja|null
     */
    function caja_activa_model(): ?Caja
    {
        return CajaService::getCajaActivaModel();
    }
}

if (!function_exists('tiene_acceso_caja')) {
    /**
     * Verifica si el usuario tiene acceso a una caja
     *
     * @param int $cajaId
     * @return bool
     */
    function tiene_acceso_caja(int $cajaId): bool
    {
        return CajaService::tieneAccesoACaja($cajaId);
    }
}

<?php

use App\Models\Caja;
use App\Models\Sucursal;
use App\Services\CajaService;
use App\Services\SucursalService;

if (! function_exists('sucursal_activa')) {
    /**
     * Obtiene el ID de la sucursal activa de la sesión
     */
    function sucursal_activa(): ?int
    {
        return SucursalService::getSucursalActiva();
    }
}

if (! function_exists('sucursal_activa_model')) {
    /**
     * Obtiene el modelo de la sucursal activa
     */
    function sucursal_activa_model(): ?Sucursal
    {
        return SucursalService::getSucursalActivaModel();
    }
}

if (! function_exists('tiene_acceso_sucursal')) {
    /**
     * Verifica si el usuario tiene acceso a una sucursal
     */
    function tiene_acceso_sucursal(int $sucursalId): bool
    {
        return SucursalService::tieneAccesoASucursal($sucursalId);
    }
}

if (! function_exists('es_multi_sucursal')) {
    /**
     * Determina si el comercio actual tiene más de una sucursal activa.
     * Nivel COMERCIO, no usuario — independiente de permisos.
     */
    function es_multi_sucursal(): bool
    {
        return SucursalService::esMultiSucursal();
    }
}

// =====================================================
// HELPERS DE CAJAS
// =====================================================

if (! function_exists('caja_activa')) {
    /**
     * Obtiene el ID de la caja activa de la sesión
     */
    function caja_activa(): ?int
    {
        return CajaService::getCajaActiva();
    }
}

if (! function_exists('caja_activa_model')) {
    /**
     * Obtiene el modelo de la caja activa
     */
    function caja_activa_model(): ?Caja
    {
        return CajaService::getCajaActivaModel();
    }
}

if (! function_exists('tiene_acceso_caja')) {
    /**
     * Verifica si el usuario tiene acceso a una caja
     */
    function tiene_acceso_caja(int $cajaId): bool
    {
        return CajaService::tieneAccesoACaja($cajaId);
    }
}

// =====================================================
// HELPERS DE FORMATEO DE NÚMEROS Y PRECIOS
// =====================================================

if (! function_exists('formato_precio')) {
    /**
     * Formatea un valor como precio con separadores argentinos
     * Separador de miles: punto (.)
     * Separador de decimales: coma (,)
     *
     * @param  float|int|string|null  $valor
     * @param  bool  $conSigno  Si incluir el signo $ al inicio
     */
    function formato_precio($valor, int $decimales = 2, bool $conSigno = false): string
    {
        $valor = floatval($valor ?? 0);
        $formateado = number_format($valor, $decimales, ',', '.');

        return $conSigno ? '$ '.$formateado : $formateado;
    }
}

if (! function_exists('formato_numero')) {
    /**
     * Formatea un número con separadores argentinos
     * Separador de miles: punto (.)
     * Separador de decimales: coma (,)
     *
     * @param  float|int|string|null  $valor
     */
    function formato_numero($valor, int $decimales = 2): string
    {
        $valor = floatval($valor ?? 0);

        return number_format($valor, $decimales, ',', '.');
    }
}

if (! function_exists('formato_porcentaje')) {
    /**
     * Formatea un valor como porcentaje con separadores argentinos
     *
     * @param  float|int|string|null  $valor
     */
    function formato_porcentaje($valor, int $decimales = 2): string
    {
        $valor = floatval($valor ?? 0);

        return number_format($valor, $decimales, ',', '.').'%';
    }
}

if (! function_exists('formato_cantidad')) {
    /**
     * Formatea una cantidad (puede tener decimales o no según el valor)
     *
     * @param  float|int|string|null  $valor
     */
    function formato_cantidad($valor, int $decimales = 3): string
    {
        $valor = floatval($valor ?? 0);
        // Si es un número entero, no mostrar decimales
        if (floor($valor) == $valor) {
            return number_format($valor, 0, ',', '.');
        }

        return number_format($valor, $decimales, ',', '.');
    }
}

<?php

namespace App\Services;

use App\Models\CanalVenta;
use App\Models\Categoria;
use App\Models\CondicionIva;
use App\Models\FormaPago;
use App\Models\FormaVenta;
use App\Models\Moneda;
use App\Models\Sucursal;
use App\Models\TipoIva;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cache centralizado de catálogos que raramente cambian.
 * Evita queries repetidas en mount() y computed properties de Livewire.
 *
 * Todas las claves incluyen comercio_activo_id para multi-tenant.
 * TTL: 1 hora. Invalidar con CatalogoCache::clear() al modificar catálogos.
 */
class CatalogoCache
{
    private const TTL = 3600;

    protected static function key(string $name): string
    {
        return "cat:{$name}:" . session('comercio_activo_id', 0);
    }

    public static function formasVenta(): Collection
    {
        return Cache::remember(self::key('formas_venta'), self::TTL,
            fn () => FormaVenta::activas()->get()
        );
    }

    public static function canalesVenta(): Collection
    {
        return Cache::remember(self::key('canales_venta'), self::TTL,
            fn () => CanalVenta::activos()->get()
        );
    }

    public static function formasPago(): Collection
    {
        return Cache::remember(self::key('formas_pago'), self::TTL,
            fn () => FormaPago::where('activo', true)->orderBy('nombre')->get()
        );
    }

    public static function categorias(): Collection
    {
        return Cache::remember(self::key('categorias'), self::TTL,
            fn () => Categoria::where('activo', true)->orderBy('nombre')->get()
        );
    }

    public static function tiposIva(): Collection
    {
        return Cache::remember(self::key('tipos_iva'), self::TTL,
            fn () => TipoIva::orderBy('porcentaje')->get()
        );
    }

    /** Solo sucursales activas */
    public static function sucursales(): Collection
    {
        return Cache::remember(self::key('sucursales'), self::TTL,
            fn () => Sucursal::where('activa', true)->orderBy('es_principal', 'desc')->orderBy('nombre')->get()
        );
    }

    /** Todas las sucursales (para pantallas de config admin) */
    public static function sucursalesTodas(): Collection
    {
        return Cache::remember(self::key('sucursales_todas'), self::TTL,
            fn () => Sucursal::orderBy('es_principal', 'desc')->orderBy('nombre')->get()
        );
    }

    public static function monedas(): Collection
    {
        return Cache::remember(self::key('monedas'), self::TTL,
            fn () => Moneda::activas()->orderBy('orden')->get()
        );
    }

    public static function condicionesIva(): Collection
    {
        return Cache::remember(self::key('condiciones_iva'), self::TTL,
            fn () => CondicionIva::orderBy('nombre')->get()
        );
    }

    /** Limpiar toda la caché de catálogos del comercio actual */
    public static function clear(?int $comercioId = null): void
    {
        $id = $comercioId ?? session('comercio_activo_id', 0);
        $catalogs = [
            'formas_venta', 'canales_venta', 'formas_pago', 'categorias',
            'tipos_iva', 'sucursales', 'sucursales_todas', 'monedas', 'condiciones_iva',
        ];

        foreach ($catalogs as $name) {
            Cache::forget("cat:{$name}:{$id}");
        }
    }
}

<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Modelo de Permiso Multi-Tenant
 *
 * Extiende el modelo de Spatie Permission para trabajar con tabla compartida
 * en la base de datos pymes (sin prefijo).
 *
 * La tabla permissions es compartida entre todos los comercios, definiendo
 * el catálogo maestro de permisos disponibles en el sistema.
 *
 * Estructura de permisos:
 * - Permisos de menú: menu.{slug} (ej: menu.ventas, menu.ventas.nueva-venta)
 * - Permisos funcionales: {modulo}.{accion} (ej: ventas.modificar-precios, articulos.eliminar)
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @package App\Models
 * @version 1.0.0
 */
class Permission extends SpatiePermission
{
    /**
     * La conexión de base de datos a usar
     * Se usa 'pymes' para tabla compartida (sin prefijo)
     *
     * @var string
     */
    protected $connection = 'pymes';

    /**
     * Los atributos que se pueden asignar masivamente
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'guard_name',
    ];

    /**
     * Verifica si el permiso es de tipo menú
     *
     * @return bool
     */
    public function isMenuPermission(): bool
    {
        return str_starts_with($this->name, 'menu.');
    }

    /**
     * Obtiene el slug del item de menú asociado
     * Solo aplica si es un permiso de menú
     *
     * @return string|null
     */
    public function getMenuSlug(): ?string
    {
        if (!$this->isMenuPermission()) {
            return null;
        }

        return str_replace('menu.', '', $this->name);
    }
}

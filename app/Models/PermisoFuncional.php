<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Modelo para permisos funcionales del sistema.
 *
 * Los permisos funcionales son permisos específicos para habilitar/deshabilitar
 * funcionalidades concretas del sistema (no relacionadas con el menú).
 *
 * Ejemplo: seleccion_cuit, modificar_precios, anular_ventas, etc.
 *
 * Estos permisos se sincronizan con la tabla 'permissions' de Spatie
 * usando el prefijo 'func.' (ej: func.seleccion_cuit)
 */
class PermisoFuncional extends Model
{
    /**
     * Conexión a la base de datos compartida
     */
    protected $connection = 'pymes';

    /**
     * Tabla del modelo
     */
    protected $table = 'permisos_funcionales';

    /**
     * Campos asignables masivamente
     */
    protected $fillable = [
        'codigo',
        'etiqueta',
        'descripcion',
        'grupo',
        'orden',
        'activo',
    ];

    /**
     * Casts de atributos
     */
    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Prefijo para los permisos en Spatie
     */
    public const PERMISSION_PREFIX = 'func.';

    /**
     * Obtiene el nombre completo del permiso para Spatie
     */
    public function getPermissionName(): string
    {
        return self::PERMISSION_PREFIX . $this->codigo;
    }

    /**
     * Obtiene todos los permisos funcionales activos agrupados
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getActivosAgrupados()
    {
        return static::where('activo', true)
            ->orderBy('grupo')
            ->orderBy('orden')
            ->get()
            ->groupBy('grupo');
    }

    /**
     * Sincroniza este permiso con la tabla permissions de Spatie
     * Crea el permiso si no existe
     */
    public function syncToSpatie(): void
    {
        $permissionName = $this->getPermissionName();

        $exists = DB::connection('pymes')
            ->table('permissions')
            ->where('name', $permissionName)
            ->where('guard_name', 'web')
            ->exists();

        if (!$exists) {
            DB::connection('pymes')
                ->table('permissions')
                ->insert([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Sincroniza todos los permisos funcionales activos con Spatie
     */
    public static function syncAllToSpatie(): void
    {
        $permisos = static::where('activo', true)->get();

        foreach ($permisos as $permiso) {
            $permiso->syncToSpatie();
        }
    }

    /**
     * Obtiene los IDs de permisos Spatie para los códigos dados
     *
     * @param array $codigos Array de códigos (sin prefijo)
     * @return array Array de permission IDs
     */
    public static function getPermissionIds(array $codigos): array
    {
        $names = array_map(fn($codigo) => self::PERMISSION_PREFIX . $codigo, $codigos);

        return DB::connection('pymes')
            ->table('permissions')
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('id')
            ->toArray();
    }

    /**
     * Obtiene los códigos de permisos funcionales asignados a un rol
     *
     * @param int $roleId
     * @return array
     */
    public static function getCodigosForRole(int $roleId): array
    {
        // Obtener los IDs de permisos del rol desde la tabla tenant
        $permissionIds = DB::connection('pymes_tenant')
            ->table('role_has_permissions')
            ->where('role_id', $roleId)
            ->pluck('permission_id')
            ->toArray();

        if (empty($permissionIds)) {
            return [];
        }

        // Obtener los nombres de permisos funcionales desde la tabla compartida (sin prefijo)
        $permissions = DB::connection('pymes')
            ->table('permissions')
            ->whereIn('id', $permissionIds)
            ->where('name', 'like', self::PERMISSION_PREFIX . '%')
            ->pluck('name')
            ->toArray();

        // Quitar el prefijo para devolver solo los códigos
        return array_map(
            fn($name) => str_replace(self::PERMISSION_PREFIX, '', $name),
            $permissions
        );
    }
}

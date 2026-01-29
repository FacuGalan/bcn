<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Modelo de Rol Multi-Tenant
 *
 * Extiende el modelo de Spatie Permission para trabajar con tablas prefijadas
 * en la base de datos pymes según el comercio activo.
 *
 * Las tablas de roles tienen prefijo por comercio: {prefix}_roles
 * Ejemplo: 000001_roles, 000002_roles, etc.
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
class Role extends SpatieRole
{
    /**
     * La conexión de base de datos a usar
     * Se usa pymes_tenant para trabajar con las tablas prefijadas
     *
     * @var string
     */
    protected $connection = 'pymes_tenant';

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
     * Verifica si el rol es protegido (no se puede modificar ni eliminar)
     *
     * @return bool
     */
    public function isProtected(): bool
    {
        return $this->name === 'Super Administrador';
    }

    /**
     * Verifica si el tenant está correctamente configurado
     *
     * @return bool
     */
    protected static function isTenantConfigured(): bool
    {
        $prefix = config('database.connections.pymes_tenant.prefix', '');
        return !empty($prefix);
    }

    /**
     * Da permisos al rol
     * Sobrescribe el método de Spatie para usar la conexión correcta
     *
     * @param mixed ...$permissions
     * @return $this
     */
    public function givePermissionTo(...$permissions)
    {
        if (!static::isTenantConfigured()) {
            Log::warning('Role::givePermissionTo() - Tenant no configurado');
            return $this;
        }

        $permissions = collect($permissions)
            ->flatten()
            ->map(function ($permission) {
                if (is_string($permission)) {
                    return Permission::where('name', $permission)->firstOrFail();
                }
                return $permission;
            })
            ->map(function ($permission) {
                return [
                    'permission_id' => $permission->id,
                    'role_id' => $this->id,
                ];
            })
            ->toArray();

        try {
            DB::connection('pymes_tenant')
                ->table('role_has_permissions')
                ->insert($permissions);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Role::givePermissionTo() - Error de base de datos: ' . $e->getMessage());
        }

        return $this;
    }

    /**
     * Verifica si el rol tiene un permiso específico
     *
     * @param mixed $permission
     * @param string|null $guardName
     * @return bool
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        // Super Administrador tiene TODOS los permisos
        if ($this->name === 'Super Administrador') {
            return true;
        }

        if (!static::isTenantConfigured()) {
            return false;
        }

        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }

        if (!$permission) {
            return false;
        }

        try {
            return DB::connection('pymes_tenant')
                ->table('role_has_permissions')
                ->where('role_id', $this->id)
                ->where('permission_id', $permission->id)
                ->exists();
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Role::hasPermissionTo() - Error de base de datos: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si este rol es Super Administrador
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->name === 'Super Administrador';
    }
}

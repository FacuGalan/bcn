<?php

namespace App\Models;

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
     * Da permisos al rol
     * Sobrescribe el método de Spatie para usar la conexión correcta
     *
     * @param mixed ...$permissions
     * @return $this
     */
    public function givePermissionTo(...$permissions)
    {
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

        \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('role_has_permissions')
            ->insert($permissions);

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
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }

        if (!$permission) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('role_has_permissions')
            ->where('role_id', $this->id)
            ->where('permission_id', $permission->id)
            ->exists();
    }
}

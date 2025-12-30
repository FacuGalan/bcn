<?php

namespace App\Observers;

use App\Models\MenuItem;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Observer para MenuItem
 *
 * Automatiza la gestión de permisos de menú:
 * - Cuando se crea un MenuItem, crea automáticamente el permiso menu.{slug}
 * - Asigna el permiso a roles de administrador en todos los tenants
 * - Actualiza permisos si cambia el slug
 * - Limpia permisos cuando se elimina un MenuItem
 */
class MenuItemObserver
{
    /**
     * Handle the MenuItem "created" event.
     *
     * Cuando se crea un nuevo item de menú:
     * 1. Crea el permiso menu.{slug} si no existe
     * 2. Lo asigna a los roles de administrador en todos los tenants
     */
    public function created(MenuItem $menuItem): void
    {
        try {
            // Crear el permiso si no existe
            $permissionName = $menuItem->getPermissionName();
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName],
                [
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            // Asignar a roles de administrador en todos los tenants
            $this->assignToAdminRoles($permission->id);

            Log::info("MenuItem created: Permission '{$permissionName}' created and assigned to admin roles", [
                'menu_item_id' => $menuItem->id,
                'permission_id' => $permission->id,
            ]);
        } catch (\Exception $e) {
            Log::error("Error creating permission for MenuItem: {$e->getMessage()}", [
                'menu_item_id' => $menuItem->id,
                'slug' => $menuItem->slug,
            ]);
        }
    }

    /**
     * Handle the MenuItem "updated" event.
     *
     * Si cambió el slug, actualiza el nombre del permiso correspondiente
     */
    public function updated(MenuItem $menuItem): void
    {
        try {
            // Verificar si cambió el slug
            if ($menuItem->wasChanged('slug')) {
                $oldSlug = $menuItem->getOriginal('slug');
                $newSlug = $menuItem->slug;

                $oldPermissionName = 'menu.' . $oldSlug;
                $newPermissionName = 'menu.' . $newSlug;

                // Actualizar el permiso existente
                $permission = Permission::where('name', $oldPermissionName)->first();

                if ($permission) {
                    $permission->update(['name' => $newPermissionName]);

                    Log::info("MenuItem updated: Permission renamed", [
                        'menu_item_id' => $menuItem->id,
                        'old_permission' => $oldPermissionName,
                        'new_permission' => $newPermissionName,
                    ]);
                } else {
                    // Si no existía el permiso anterior, crear uno nuevo
                    $this->created($menuItem);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error updating permission for MenuItem: {$e->getMessage()}", [
                'menu_item_id' => $menuItem->id,
                'slug' => $menuItem->slug,
            ]);
        }
    }

    /**
     * Handle the MenuItem "deleted" event.
     *
     * Elimina el permiso asociado cuando se borra un MenuItem
     */
    public function deleted(MenuItem $menuItem): void
    {
        try {
            $permissionName = $menuItem->getPermissionName();
            $permission = Permission::where('name', $permissionName)->first();

            if ($permission) {
                // Eliminar de todas las tablas role_has_permissions en todos los tenants
                $this->removeFromAllTenants($permission->id);

                // Eliminar el permiso
                $permission->delete();

                Log::info("MenuItem deleted: Permission removed", [
                    'menu_item_id' => $menuItem->id,
                    'permission' => $permissionName,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error deleting permission for MenuItem: {$e->getMessage()}", [
                'menu_item_id' => $menuItem->id,
                'slug' => $menuItem->slug,
            ]);
        }
    }

    /**
     * Handle the MenuItem "restored" event.
     *
     * Cuando se restaura un MenuItem, recrear su permiso y asignaciones
     */
    public function restored(MenuItem $menuItem): void
    {
        $this->created($menuItem);
    }

    /**
     * Handle the MenuItem "force deleted" event.
     *
     * Limpieza definitiva del permiso
     */
    public function forceDeleted(MenuItem $menuItem): void
    {
        $this->deleted($menuItem);
    }

    /**
     * Asigna un permiso a los roles de administrador en todos los tenants
     *
     * @param int $permissionId ID del permiso a asignar
     * @return void
     */
    protected function assignToAdminRoles(int $permissionId): void
    {
        $tenants = $this->getAllTenants();

        foreach ($tenants as $tenantPrefix) {
            try {
                // Obtener roles de administrador (Super Administrador y Administrador)
                $adminRoleIds = DB::connection('pymes_tenant')
                    ->table("{$tenantPrefix}roles")
                    ->whereIn('name', ['Super Administrador', 'Administrador'])
                    ->pluck('id')
                    ->toArray();

                if (empty($adminRoleIds)) {
                    continue;
                }

                // Preparar registros para insertar
                $records = [];
                foreach ($adminRoleIds as $roleId) {
                    // Verificar si ya existe la relación
                    $exists = DB::connection('pymes_tenant')
                        ->table("{$tenantPrefix}role_has_permissions")
                        ->where('permission_id', $permissionId)
                        ->where('role_id', $roleId)
                        ->exists();

                    if (!$exists) {
                        $records[] = [
                            'permission_id' => $permissionId,
                            'role_id' => $roleId,
                        ];
                    }
                }

                // Insertar en batch
                if (!empty($records)) {
                    DB::connection('pymes_tenant')
                        ->table("{$tenantPrefix}role_has_permissions")
                        ->insert($records);
                }

                Log::info("Permission assigned to admin roles in tenant {$tenantPrefix}", [
                    'permission_id' => $permissionId,
                    'role_ids' => $adminRoleIds,
                ]);
            } catch (\Exception $e) {
                Log::error("Error assigning permission to tenant {$tenantPrefix}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Elimina un permiso de todos los tenants
     *
     * @param int $permissionId ID del permiso a eliminar
     * @return void
     */
    protected function removeFromAllTenants(int $permissionId): void
    {
        $tenants = $this->getAllTenants();

        foreach ($tenants as $tenantPrefix) {
            try {
                DB::connection('pymes_tenant')
                    ->table("{$tenantPrefix}role_has_permissions")
                    ->where('permission_id', $permissionId)
                    ->delete();

                Log::info("Permission removed from tenant {$tenantPrefix}", [
                    'permission_id' => $permissionId,
                ]);
            } catch (\Exception $e) {
                Log::error("Error removing permission from tenant {$tenantPrefix}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Obtiene todos los prefijos de tenants existentes
     *
     * @return array Array de prefijos (ej: ['000001_', '000002_'])
     */
    protected function getAllTenants(): array
    {
        try {
            // Obtener todas las tablas de la base de datos pymes
            $tables = DB::connection('pymes_tenant')
                ->select('SHOW TABLES');

            $tenants = [];
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];

                // Buscar tablas que terminen en '_roles' (indicador de tenant)
                if (preg_match('/^(\d{6}_)roles$/', $tableName, $matches)) {
                    $tenants[] = $matches[1];
                }
            }

            return array_unique($tenants);
        } catch (\Exception $e) {
            Log::error("Error getting tenants: {$e->getMessage()}");
            return [];
        }
    }
}

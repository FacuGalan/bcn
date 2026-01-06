<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\PermisoFuncional;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder para roles y permisos predeterminados
 *
 * Este seeder:
 * 1. Verifica/crea permisos compartidos desde el menú (tabla sin prefijo)
 * 2. Crea roles por comercio con diferentes niveles de acceso (tabla con prefijo)
 * 3. Asigna permisos compartidos a roles del comercio
 *
 * Roles creados:
 * - Administrador: Acceso total a todo el menú
 * - Gerente: Acceso a dashboard, ventas y artículos (lectura/escritura)
 * - Vendedor: Acceso limitado a ventas (solo crear y ver listado)
 * - Visualizador: Acceso de solo lectura a dashboard y reportes
 *
 * IMPORTANTE:
 * - Debe ejecutarse después de MenuItemSeeder
 * - Requiere TenantService configurado (comercio activo)
 * - Los permisos son compartidos, los roles son por comercio
 *
 * @package Database\Seeders
 * @version 2.0.0
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Verificar/crear permisos compartidos desde el menú
        $permissions = $this->ensurePermissionsFromMenu();

        // 2. Sincronizar permisos funcionales con Spatie
        PermisoFuncional::syncAllToSpatie();

        // 3. Limpiar roles del comercio actual (son por comercio)
        Role::query()->delete();

        // 4. Crear roles predeterminados para el comercio
        $roles = $this->createDefaultRoles();

        // 5. Asignar permisos a roles
        $this->assignPermissionsToRoles($roles, $permissions);

        // 6. Asignar roles a usuarios existentes (opcional)
        $this->assignRolesToUsers($roles);
    }

    /**
     * Verifica/crea permisos compartidos para cada item del menú
     *
     * Los permisos son compartidos entre todos los comercios, por lo que
     * solo se crean si no existen. Retorna todos los permisos existentes.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function ensurePermissionsFromMenu(): \Illuminate\Support\Collection
    {
        $menuItems = MenuItem::all();
        $permissions = collect();

        foreach ($menuItems as $item) {
            $permissionName = $item->getPermissionName();

            // Buscar o crear el permiso (usando firstOrCreate para evitar duplicados)
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $permissions->push($permission);
        }

        return $permissions;
    }

    /**
     * Crea roles predeterminados del sistema
     *
     * @return \Illuminate\Support\Collection
     */
    protected function createDefaultRoles(): \Illuminate\Support\Collection
    {
        $roles = collect();

        // Rol: Super Administrador (acceso total, protegido)
        $roles->push(Role::create([
            'name' => 'Super Administrador',
            'guard_name' => 'web',
        ]));

        // Rol: Administrador (acceso total)
        $roles->push(Role::create([
            'name' => 'Administrador',
            'guard_name' => 'web',
        ]));

        // Rol: Gerente (acceso amplio excepto configuración de usuarios)
        $roles->push(Role::create([
            'name' => 'Gerente',
            'guard_name' => 'web',
        ]));

        // Rol: Vendedor (acceso limitado a ventas)
        $roles->push(Role::create([
            'name' => 'Vendedor',
            'guard_name' => 'web',
        ]));

        // Rol: Visualizador (solo lectura)
        $roles->push(Role::create([
            'name' => 'Visualizador',
            'guard_name' => 'web',
        ]));

        return $roles;
    }

    /**
     * Asigna permisos a cada rol según su nivel de acceso
     *
     * @param \Illuminate\Support\Collection $roles
     * @param \Illuminate\Support\Collection $permissions
     * @return void
     */
    protected function assignPermissionsToRoles($roles, $permissions): void
    {
        // Obtener todos los permisos funcionales
        $funcPermissions = Permission::where('name', 'like', PermisoFuncional::PERMISSION_PREFIX . '%')
            ->pluck('name')
            ->toArray();

        // Combinar permisos de menú + funcionales para acceso total
        $allPermissions = array_merge(
            $permissions->pluck('name')->toArray(),
            $funcPermissions
        );

        // Super Administrador: Acceso a TODO (protegido)
        // Nota: hasPermissionTo() ya retorna true para Super Administrador,
        // pero asignamos los permisos explícitamente para consistencia
        $superAdminRole = $roles->firstWhere('name', 'Super Administrador');
        $superAdminRole->givePermissionTo($allPermissions);

        // Administrador: Acceso a TODO (menú + funcionales)
        $adminRole = $roles->firstWhere('name', 'Administrador');
        $adminRole->givePermissionTo($allPermissions);

        // Gerente: Ventas + Artículos + Empresa (sin Dashboard, accesible desde logo)
        $gerenteRole = $roles->firstWhere('name', 'Gerente');
        $gerentePermissions = $permissions->filter(function ($perm) {
            return str_starts_with($perm->name, 'menu.ventas')
                || str_starts_with($perm->name, 'menu.articulos')
                || $perm->name === 'menu.configuracion'
                || $perm->name === 'menu.empresa';
        });
        $gerenteRole->givePermissionTo($gerentePermissions->pluck('name')->toArray());

        // Vendedor: Ventas (solo nueva y listado)
        $vendedorRole = $roles->firstWhere('name', 'Vendedor');
        $vendedorPermissions = $permissions->filter(function ($perm) {
            return $perm->name === 'menu.ventas'
                || $perm->name === 'menu.nueva-venta'
                || $perm->name === 'menu.listado-ventas';
        });
        $vendedorRole->givePermissionTo($vendedorPermissions->pluck('name')->toArray());

        // Visualizador: Reportes solamente
        $visualizadorRole = $roles->firstWhere('name', 'Visualizador');
        $visualizadorPermissions = $permissions->filter(function ($perm) {
            return $perm->name === 'menu.ventas'
                || $perm->name === 'menu.reportes-ventas';
        });
        $visualizadorRole->givePermissionTo($visualizadorPermissions->pluck('name')->toArray());
    }

    /**
     * Asigna roles a usuarios existentes (opcional, para testing)
     *
     * @param \Illuminate\Support\Collection $roles
     * @return void
     */
    protected function assignRolesToUsers($roles): void
    {
        // Asignar rol Super Administrador al usuario 'admin' si existe
        $admin = User::where('username', 'admin')->first();
        if ($admin) {
            $superAdminRole = $roles->firstWhere('name', 'Super Administrador');
            $admin->assignRole($superAdminRole);
        }

        // Asignar rol Vendedor a 'user1' si existe
        $user1 = User::where('username', 'user1')->first();
        if ($user1) {
            $vendedorRole = $roles->firstWhere('name', 'Vendedor');
            $user1->assignRole($vendedorRole);
        }

        // Asignar rol Gerente a 'multiuser' si existe
        $multiuser = User::where('username', 'multiuser')->first();
        if ($multiuser) {
            $gerenteRole = $roles->firstWhere('name', 'Gerente');
            $multiuser->assignRole($gerenteRole);
        }
    }
}

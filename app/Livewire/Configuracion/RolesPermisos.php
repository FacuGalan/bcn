<?php

namespace App\Livewire\Configuracion;

use App\Models\Role;
use App\Models\Permission;
use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para gestión de roles y permisos
 *
 * Permite crear, editar, eliminar y listar roles del comercio activo.
 * Incluye asignación de permisos basados en los items del menú y permisos funcionales.
 *
 * @package App\Livewire\Configuracion
 */
#[Layout('layouts.app')]
class RolesPermisos extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public string $search = '';

    // Propiedades del modal
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $roleId = null;
    public bool $isSuperAdmin = false;

    // Propiedades del formulario
    public string $name = '';
    public array $selectedPermissions = [];
    public array $selectedFuncPermissions = []; // Permisos funcionales seleccionados (códigos)

    // Colección de permisos agrupados (menú)
    public $groupedPermissions;

    // Colección de permisos funcionales agrupados
    public $permisosFuncionales;

    /**
     * Inicialización del componente
     * Carga los permisos disponibles agrupados por módulo
     */
    public function mount(): void
    {
        $this->loadGroupedPermissions();
        $this->loadPermisosFuncionales();
    }

    /**
     * Carga los permisos funcionales agrupados
     */
    protected function loadPermisosFuncionales(): void
    {
        // Convertir a array para evitar problemas de serialización de Livewire
        $grouped = PermisoFuncional::getActivosAgrupados();
        $this->permisosFuncionales = $grouped->map(fn($permisos) => $permisos->toArray())->toArray();
    }

    /**
     * Carga los permisos agrupados por módulo desde la base de datos
     */
    protected function loadGroupedPermissions(): void
    {
        $menuItems = MenuItem::whereNull('parent_id')->with('children')->orderBy('orden')->get();
        $grouped = [];

        foreach ($menuItems as $parent) {
            $parentPermission = Permission::where('name', $parent->getPermissionName())->first();

            if ($parentPermission) {
                $grouped[$parent->nombre] = [
                    'parent' => $parentPermission,
                    'children' => []
                ];

                foreach ($parent->children as $child) {
                    $childPermission = Permission::where('name', $child->getPermissionName())->first();
                    if ($childPermission) {
                        $grouped[$parent->nombre]['children'][] = $childPermission;
                    }
                }
            }
        }

        $this->groupedPermissions = $grouped;
    }

    /**
     * Actualiza la búsqueda y resetea la paginación
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Obtiene los roles del comercio actual con filtros aplicados
     */
    protected function getRoles()
    {
        $query = Role::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        $roles = $query->orderBy('name')->paginate(10);

        // Cargar conteos de forma optimizada
        $this->loadRoleCounts($roles);

        return $roles;
    }

    /**
     * Carga los conteos de usuarios y permisos para los roles
     */
    protected function loadRoleCounts($roles): void
    {
        $roleIds = $roles->pluck('id')->toArray();

        if (empty($roleIds)) {
            return;
        }

        // Obtener conteos de usuarios
        $userCounts = DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->whereIn('role_id', $roleIds)
            ->where('model_type', \App\Models\User::class)
            ->select('role_id', DB::raw('count(*) as count'))
            ->groupBy('role_id')
            ->pluck('count', 'role_id');

        // Obtener conteos de permisos
        $permissionCounts = DB::connection('pymes_tenant')
            ->table('role_has_permissions')
            ->whereIn('role_id', $roleIds)
            ->select('role_id', DB::raw('count(*) as count'))
            ->groupBy('role_id')
            ->pluck('count', 'role_id');

        // Asignar conteos a cada rol
        foreach ($roles as $role) {
            $role->users_count = $userCounts->get($role->id, 0);
            $role->permissions_count = $permissionCounts->get($role->id, 0);
        }
    }

    /**
     * Abre el modal para crear un nuevo rol
     */
    public function create(): void
    {
        $this->reset(['name', 'selectedPermissions', 'selectedFuncPermissions', 'roleId']);
        $this->editMode = false;
        $this->isSuperAdmin = false;
        $this->showModal = true;
    }

    /**
     * Abre el modal para editar un rol existente
     */
    public function edit(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        $this->roleId = $role->id;
        $this->name = $role->name;
        $this->isSuperAdmin = $role->name === 'Super Administrador';

        // Obtener permisos de menú actuales del rol
        $permissions = DB::connection('pymes_tenant')
            ->table('role_has_permissions')
            ->where('role_id', $role->id)
            ->pluck('permission_id')
            ->toArray();

        $this->selectedPermissions = $permissions;

        // Obtener permisos funcionales del rol (códigos sin prefijo)
        $this->selectedFuncPermissions = PermisoFuncional::getCodigosForRole($role->id);

        $this->editMode = true;
        $this->showModal = true;
    }

    /**
     * Guarda el rol (crear o actualizar)
     */
    public function save(): void
    {
        // Super Administrador no puede ser modificado
        if ($this->isSuperAdmin) {
            $this->dispatch('notify', message: 'El rol Super Administrador no puede ser modificado', type: 'warning');
            $this->showModal = false;
            return;
        }

        $this->validate([
            'name' => 'required|string|max:125|unique:pymes_tenant.roles,name,' . $this->roleId,
            'selectedPermissions' => 'nullable|array',
            'selectedPermissions.*' => 'exists:pymes.permissions,id',
            'selectedFuncPermissions' => 'nullable|array',
        ]);

        DB::transaction(function () {
            if ($this->editMode) {
                // Actualizar rol existente
                $role = Role::findOrFail($this->roleId);
                $role->name = $this->name;
                $role->save();

                $message = 'Rol actualizado correctamente';
            } else {
                // Crear nuevo rol
                $role = Role::create([
                    'name' => $this->name,
                    'guard_name' => 'web',
                ]);

                $message = 'Rol creado correctamente';
            }

            // Sincronizar permisos
            // Primero eliminar todos los permisos actuales
            DB::connection('pymes_tenant')
                ->table('role_has_permissions')
                ->where('role_id', $role->id)
                ->delete();

            // Combinar permisos de menú + permisos funcionales
            $allPermissionIds = $this->selectedPermissions;

            // Obtener IDs de permisos funcionales seleccionados
            if (!empty($this->selectedFuncPermissions)) {
                $funcPermissionIds = PermisoFuncional::getPermissionIds($this->selectedFuncPermissions);
                $allPermissionIds = array_merge($allPermissionIds, $funcPermissionIds);
            }

            // Insertar todos los permisos
            if (!empty($allPermissionIds)) {
                $insertData = array_map(function ($permissionId) use ($role) {
                    return [
                        'permission_id' => $permissionId,
                        'role_id' => $role->id,
                    ];
                }, array_unique($allPermissionIds));

                DB::connection('pymes_tenant')
                    ->table('role_has_permissions')
                    ->insert($insertData);
            }

            $this->dispatch('role-saved');
            $this->dispatch('notify', message: $message, type: 'success');
        });

        $this->showModal = false;
        $this->reset(['name', 'selectedPermissions', 'selectedFuncPermissions', 'roleId']);
    }

    /**
     * Cancela la edición y cierra el modal
     */
    public function cancel(): void
    {
        $this->showModal = false;
        $this->reset(['name', 'selectedPermissions', 'selectedFuncPermissions', 'roleId', 'isSuperAdmin']);
    }

    /**
     * Elimina un rol
     */
    public function delete(int $roleId): void
    {
        $role = Role::findOrFail($roleId);

        // Validar que no sea Super Administrador
        if ($role->name === 'Super Administrador') {
            $this->dispatch('notify', message: 'El rol Super Administrador no puede ser eliminado', type: 'error');
            return;
        }

        // Verificar si el rol está siendo usado
        $usersCount = DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->where('role_id', $roleId)
            ->count();

        if ($usersCount > 0) {
            $this->dispatch('notify', message: "No se puede eliminar el rol porque tiene {$usersCount} usuario(s) asignado(s)", type: 'error');
            return;
        }

        DB::transaction(function () use ($role) {
            // Eliminar permisos del rol
            DB::connection('pymes_tenant')
                ->table('role_has_permissions')
                ->where('role_id', $role->id)
                ->delete();

            // Eliminar el rol
            $role->delete();
        });

        $this->dispatch('notify', message: 'Rol eliminado correctamente', type: 'success');
    }

    /**
     * Obtiene la cantidad de usuarios asignados a un rol
     */
    public function getUsersCount(Role $role): int
    {
        return DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();
    }

    /**
     * Obtiene la cantidad de permisos asignados a un rol
     */
    public function getPermissionsCount(Role $role): int
    {
        return DB::connection('pymes_tenant')
            ->table('role_has_permissions')
            ->where('role_id', $role->id)
            ->count();
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        return view('livewire.configuracion.roles-permisos', [
            'roles' => $this->getRoles(),
        ]);
    }
}

<?php

namespace App\Livewire\Configuracion;

use App\Models\User;
use App\Models\Role;
use App\Models\Comercio;
use App\Models\Sucursal;
use App\Models\Caja;
use App\Services\SucursalService;
use App\Services\CajaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

/**
 * Componente Livewire para gestión de usuarios
 *
 * Permite crear, editar, listar y gestionar el estado de los usuarios del comercio activo.
 * Incluye asignación de roles y permisos por usuario.
 *
 * @package App\Livewire\Configuracion
 */
#[Layout('layouts.app')]
class Usuarios extends Component
{
    use WithPagination;

    // Propiedades de filtros
    public string $search = '';
    public string $filterStatus = 'all'; // all, active, inactive
    public string $filterRole = 'all';
    public bool $showFilters = false;

    // Propiedades del modal
    public bool $showModal = false;
    public bool $editMode = false;
    public ?int $userId = null;

    // Propiedades del formulario
    public string $name = '';
    public string $username = '';
    public string $email = '';
    public string $telefono = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $activo = true;
    public ?int $roleId = null;
    public ?string $passwordVisible = null;
    public bool $currentUserIsSuperAdmin = false;

    // Sucursales
    public array $selectedSucursales = [];
    public $sucursales;

    // Cajas (por sucursal)
    public array $selectedCajas = []; // Formato: ['sucursal_id' => [caja_ids]]

    // Colección de roles disponibles
    public $roles;

    // Comercio actual
    public ?Comercio $comercio = null;

    /**
     * Inicialización del componente
     * Carga los roles disponibles del comercio activo
     */
    public function mount(): void
    {
        $comercioId = session('comercio_activo_id');
        $this->comercio = Comercio::findOrFail($comercioId);
        $this->loadRoles();
        $this->loadSucursales();

        // Verificar si el usuario autenticado es Super Administrador
        $this->currentUserIsSuperAdmin = auth()->user()->hasRole('Super Administrador');
    }

    /**
     * Carga las sucursales disponibles desde la base de datos
     */
    protected function loadSucursales(): void
    {
        $this->sucursales = Sucursal::where('activa', true)
            ->orderBy('es_principal', 'desc')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Obtiene las cajas disponibles agrupadas por sucursal
     */
    protected function getCajas()
    {
        // Cargar todas las cajas activas agrupadas por sucursal
        $todasCajas = Caja::where('activo', true)
            ->orderBy('sucursal_id')
            ->orderBy('id', 'asc')
            ->get();

        // Agrupar por sucursal_id
        return $todasCajas->groupBy('sucursal_id');
    }

    /**
     * Carga los roles disponibles desde la base de datos
     */
    protected function loadRoles(): void
    {
        $this->roles = Role::orderBy('name')->get();
    }

    /**
     * Verifica si un rol es Super Administrador
     */
    protected function isSuperAdminRole(?int $roleId): bool
    {
        if (!$roleId) {
            return false;
        }
        $role = Role::find($roleId);
        return $role && $role->name === 'Super Administrador';
    }

    /**
     * Cuenta la cantidad de super administradores en el comercio
     */
    protected function countSuperAdmins(): int
    {
        $superAdminRole = Role::where('name', 'Super Administrador')->first();
        if (!$superAdminRole) {
            return 0;
        }

        return DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->where('role_id', $superAdminRole->id)
            ->count();
    }

    /**
     * Actualiza la búsqueda y resetea la paginación
     */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza el filtro de estado y resetea la paginación
     */
    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Actualiza el filtro de rol y resetea la paginación
     */
    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    /**
     * Cuando se actualizan las sucursales seleccionadas, limpia las cajas de sucursales que ya no están seleccionadas
     */
    public function updatedSelectedSucursales(): void
    {
        // Si hay sucursales seleccionadas, limpiar cajas de sucursales no seleccionadas
        if (!empty($this->selectedSucursales)) {
            // Convertir a strings para consistencia con claves de selectedCajas
            $sucursalesIds = array_map('strval', $this->selectedSucursales);
            $this->selectedCajas = array_filter(
                $this->selectedCajas,
                fn($key) => in_array((string)$key, $sucursalesIds),
                ARRAY_FILTER_USE_KEY
            );
        }
    }

    /**
     * Toggle de selección de caja individual
     */
    public function toggleCaja(int $sucursalId, int $cajaId): void
    {
        // Usar string como clave para consistencia con Livewire (JSON usa strings)
        $sucursalIdKey = (string)$sucursalId;

        // Inicializar el array de la sucursal si no existe
        if (!isset($this->selectedCajas[$sucursalIdKey]) || !is_array($this->selectedCajas[$sucursalIdKey])) {
            $this->selectedCajas[$sucursalIdKey] = [];
        }

        // Buscar si la caja ya está en el array
        $index = array_search($cajaId, $this->selectedCajas[$sucursalIdKey]);

        if ($index !== false) {
            // Si existe, quitarla
            unset($this->selectedCajas[$sucursalIdKey][$index]);
            // Reindexar el array
            $this->selectedCajas[$sucursalIdKey] = array_values($this->selectedCajas[$sucursalIdKey]);
        } else {
            // Si no existe, agregarla
            $this->selectedCajas[$sucursalIdKey][] = $cajaId;
        }

        // Si el array quedó vacío, eliminarlo
        if (empty($this->selectedCajas[$sucursalIdKey])) {
            unset($this->selectedCajas[$sucursalIdKey]);
        }
    }

    /**
     * Alterna la visibilidad de los filtros
     */
    public function toggleFilters(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    /**
     * Obtiene los usuarios del comercio actual con filtros aplicados
     */
    protected function getUsers()
    {
        $comercioId = session('comercio_activo_id');

        $query = User::whereHas('comercios', function ($q) use ($comercioId) {
            $q->where('comercio_id', $comercioId);
        });

        // Filtro de búsqueda
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('username', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        // Filtro de estado
        if ($this->filterStatus !== 'all') {
            $query->where('activo', $this->filterStatus === 'active');
        }

        // Filtro de rol
        if ($this->filterRole !== 'all') {
            // Obtener IDs de usuarios que tienen este rol
            $userIds = DB::connection('pymes_tenant')
                ->table('model_has_roles')
                ->where('role_id', $this->filterRole)
                ->where('model_type', User::class)
                ->pluck('model_id');

            $query->whereIn('id', $userIds);
        }

        // Eager load roles con una sola query
        $users = $query->orderBy('name')->paginate(10);

        // Cargar roles para todos los usuarios de una vez
        $this->loadUserRoles($users);

        return $users;
    }

    /**
     * Carga los roles de los usuarios de forma eficiente
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $users
     */
    protected function loadUserRoles($users): void
    {
        $userIds = $users->pluck('id')->toArray();

        if (empty($userIds)) {
            return;
        }

        // Obtener todos los roles de los usuarios en una sola query
        $rolesByUser = DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->whereIn('model_id', $userIds)
            ->where('model_type', User::class)
            ->get()
            ->groupBy('model_id');

        // Obtener los IDs únicos de roles
        $roleIds = $rolesByUser->flatten()->pluck('role_id')->unique()->toArray();

        // Cargar todos los roles en una sola query
        $roles = Role::whereIn('id', $roleIds)->get()->keyBy('id');

        // Asignar los roles a cada usuario
        foreach ($users as $user) {
            $userRoleIds = $rolesByUser->get($user->id, collect())->pluck('role_id');
            $user->loadedRoles = $roles->whereIn('id', $userRoleIds)->values();
        }
    }

    /**
     * Obtiene el rol del usuario en el comercio actual
     */
    public function getUserRole(User $user): ?string
    {
        // Usar los roles pre-cargados si están disponibles
        if (isset($user->loadedRoles)) {
            return $user->loadedRoles->isNotEmpty() ? $user->loadedRoles->first()->name : null;
        }

        // Obtener roles del usuario
        $roles = $user->roles();
        return $roles->isNotEmpty() ? $roles->first()->name : null;
    }

    /**
     * Abre el modal para crear un nuevo usuario
     */
    public function create(): void
    {
        // Validar que no se exceda el límite de usuarios
        if (!$this->comercio->canAddMoreUsers()) {
            $this->dispatch('notify',
                message: "No se pueden agregar más usuarios. Límite alcanzado: {$this->comercio->max_usuarios} usuarios.",
                type: 'error'
            );
            return;
        }

        $this->reset(['name', 'username', 'email', 'telefono', 'password', 'password_confirmation', 'activo', 'roleId', 'userId', 'passwordVisible', 'selectedSucursales', 'selectedCajas']);
        $this->editMode = false;
        $this->activo = true;
        $this->showModal = true;
    }

    /**
     * Abre el modal para editar un usuario existente
     */
    public function edit(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->userId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->telefono = $user->telefono ?? '';
        $this->activo = $user->activo;

        // Obtener el rol actual del usuario
        $roles = $user->roles();
        $this->roleId = $roles->isNotEmpty() ? $roles->first()->id : null;

        // Si el usuario autenticado es Super Admin, cargar la contraseña visible y las sucursales del usuario
        $this->passwordVisible = $this->currentUserIsSuperAdmin ? $user->getPasswordVisible() : null;

        // Cargar sucursales del usuario
        if ($this->currentUserIsSuperAdmin) {
            $this->selectedSucursales = DB::connection('pymes_tenant')
                ->table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $user->id)
                ->where('sucursal_id', '>', 0)  // Excluir sucursal_id = 0 (todas)
                ->pluck('sucursal_id')
                ->toArray();

            // Cargar cajas asignadas del usuario, agrupadas por sucursal
            $cajasAsignadas = DB::connection('pymes_tenant')
                ->table('user_cajas')
                ->where('user_id', $user->id)
                ->get();

            // Agrupar cajas por sucursal (usar strings como claves para consistencia con Livewire)
            $this->selectedCajas = [];
            foreach ($cajasAsignadas as $asignacion) {
                $sucursalIdKey = (string)$asignacion->sucursal_id;
                if (!isset($this->selectedCajas[$sucursalIdKey])) {
                    $this->selectedCajas[$sucursalIdKey] = [];
                }
                $this->selectedCajas[$sucursalIdKey][] = $asignacion->caja_id;
            }
        }

        $this->password = '';
        $this->password_confirmation = '';
        $this->editMode = true;
        $this->showModal = true;
    }

    /**
     * Guarda el usuario (crear o actualizar)
     */
    public function save(): void
    {
        // Validar límite de usuarios al crear
        if (!$this->editMode && !$this->comercio->canAddMoreUsers()) {
            $this->dispatch('notify',
                message: "No se pueden agregar más usuarios. Límite alcanzado: {$this->comercio->max_usuarios} usuarios.",
                type: 'error'
            );
            return;
        }

        // Validar que no se elimine el último super administrador
        if ($this->editMode) {
            $user = User::findOrFail($this->userId);
            $currentRoles = $user->roles();
            $currentIsSuperAdmin = $currentRoles->isNotEmpty() && $currentRoles->first()->name === 'Super Administrador';
            $newIsSuperAdmin = $this->isSuperAdminRole($this->roleId);

            // Si estaba como super admin y ya no lo es, validar que no sea el último
            if ($currentIsSuperAdmin && !$newIsSuperAdmin) {
                if ($this->countSuperAdmins() <= 1) {
                    $this->dispatch('notify',
                        message: 'No se puede cambiar el rol. Debe existir al menos un Super Administrador en el comercio.',
                        type: 'error'
                    );
                    return;
                }
            }
        }

        $rules = [
            'name' => 'required|string|max:191',
            'username' => 'required|string|max:191|unique:config.users,username,' . $this->userId,
            'email' => 'required|email|max:191|unique:config.users,email,' . $this->userId,
            'telefono' => 'nullable|string|max:50',
            'activo' => 'boolean',
            'roleId' => 'nullable|exists:pymes_tenant.roles,id',
        ];

        if (!$this->editMode) {
            $rules['password'] = 'required|string|min:6|confirmed';
        } elseif ($this->password) {
            $rules['password'] = 'nullable|string|min:6|confirmed';
        }

        $this->validate($rules);

        DB::transaction(function () {
            if ($this->editMode) {
                // Actualizar usuario existente
                $user = User::findOrFail($this->userId);
                $user->name = $this->name;
                $user->username = $this->username;
                $user->email = $this->email;
                $user->telefono = $this->telefono ?: null;
                $user->activo = $this->activo;

                if ($this->password) {
                    $user->password = Hash::make($this->password);
                    $user->setPasswordVisible($this->password);
                }

                $user->save();

                $message = 'Usuario actualizado correctamente';
            } else {
                // Crear nuevo usuario
                $user = User::create([
                    'name' => $this->name,
                    'username' => $this->username,
                    'email' => $this->email,
                    'telefono' => $this->telefono ?: null,
                    'password' => Hash::make($this->password),
                    'activo' => $this->activo,
                ]);

                $user->setPasswordVisible($this->password);
                $user->save();

                // Asociar al comercio actual
                $comercioId = session('comercio_activo_id');
                $user->attachToComercio($comercioId);

                $message = 'Usuario creado correctamente';
            }

            // Asignar/actualizar rol y sucursales
            if ($this->roleId) {
                // Eliminar roles actuales
                DB::connection('pymes_tenant')
                    ->table('model_has_roles')
                    ->where('model_id', $user->id)
                    ->where('model_type', User::class)
                    ->delete();

                // Determinar sucursales a asignar
                $sucursalesToAssign = [];

                if ($this->currentUserIsSuperAdmin && !empty($this->selectedSucursales)) {
                    // Si es Super Admin editando, usar las sucursales seleccionadas
                    $sucursalesToAssign = $this->selectedSucursales;
                } else {
                    // Si no es Super Admin o no hay selección, asignar sucursal_id = 0 (todas)
                    $sucursalesToAssign = [0];
                }

                // Asignar el rol con las sucursales correspondientes
                $role = Role::findOrFail($this->roleId);

                foreach ($sucursalesToAssign as $sucursalId) {
                    DB::connection('pymes_tenant')
                        ->table('model_has_roles')
                        ->insert([
                            'role_id' => $role->id,
                            'model_type' => User::class,
                            'model_id' => $user->id,
                            'sucursal_id' => $sucursalId,
                        ]);
                }
            }

            // Gestionar cajas por sucursal (solo si es Super Admin)
            if ($this->currentUserIsSuperAdmin) {
                // Siempre eliminar asignaciones previas de cajas
                DB::connection('pymes_tenant')
                    ->table('user_cajas')
                    ->where('user_id', $user->id)
                    ->delete();

                // Guardar las cajas seleccionadas independientemente de las sucursales
                // - Sin sucursales seleccionadas = acceso a todas las sucursales
                // - Sin cajas seleccionadas en una sucursal = acceso a todas las cajas de esa sucursal
                // - Pero se pueden restringir cajas específicas aunque tenga acceso a todas las sucursales
                foreach ($this->selectedCajas as $sucursalIdKey => $cajas) {
                    if (is_array($cajas) && !empty($cajas)) {
                        foreach ($cajas as $cajaId) {
                            DB::connection('pymes_tenant')
                                ->table('user_cajas')
                                ->insert([
                                    'user_id' => $user->id,
                                    'caja_id' => (int)$cajaId,
                                    'sucursal_id' => (int)$sucursalIdKey,
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);
                        }
                    }
                }
            }

            // Limpiar caché si se modificó el usuario autenticado
            if ($user->id === auth()->id()) {
                SucursalService::clearCache();
                CajaService::clearCache();
            }

            $this->dispatch('user-saved');
            $this->dispatch('notify', message: $message, type: 'success');
        });

        $this->showModal = false;
        $this->reset(['name', 'username', 'email', 'telefono', 'password', 'password_confirmation', 'activo', 'roleId', 'userId', 'passwordVisible', 'selectedSucursales', 'selectedCajas']);
    }

    /**
     * Cancela la edición y cierra el modal
     */
    public function cancel(): void
    {
        $this->showModal = false;
        $this->reset(['name', 'username', 'email', 'telefono', 'password', 'password_confirmation', 'activo', 'roleId', 'userId', 'passwordVisible', 'selectedSucursales', 'selectedCajas']);
    }

    /**
     * Cambia el estado activo/inactivo de un usuario
     */
    public function toggleStatus(int $userId): void
    {
        $user = User::findOrFail($userId);

        // Validar que no se desactive a sí mismo
        if ($user->id === auth()->id()) {
            $this->dispatch('notify', message: 'No puedes desactivarte a ti mismo', type: 'error');
            return;
        }

        $user->activo = !$user->activo;
        $user->save();

        $status = $user->activo ? 'activado' : 'desactivado';
        $this->dispatch('notify', message: "Usuario {$status} correctamente", type: 'success');
    }

    /**
     * Renderiza el componente
     */
    public function render()
    {
        return view('livewire.configuracion.usuarios', [
            'users' => $this->getUsers(),
        ]);
    }
}

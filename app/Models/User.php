<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Comercio;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Role;

/**
 * Modelo User
 *
 * Representa un usuario del sistema. Los usuarios están centralizados en la base CONFIG
 * y pueden tener acceso a múltiples comercios con roles/permisos independientes en cada uno.
 *
 * @package App\Models
 * @author BCN Pymes
 * @version 1.0.0
 *
 * @property int $id ID único del usuario
 * @property string $name Nombre completo del usuario
 * @property string $username Nombre de usuario para login
 * @property string $email Correo electrónico del usuario
 * @property \Illuminate\Support\Carbon|null $email_verified_at Fecha de verificación del email
 * @property string $password Contraseña hasheada
 * @property string|null $password_visible Contraseña cifrada para visualización
 * @property int $max_concurrent_sessions Número máximo de sesiones simultáneas
 * @property bool $activo Indica si el usuario está activo (puede iniciar sesión)
 * @property string|null $remember_token Token para recordar sesión
 * @property \Illuminate\Support\Carbon $created_at Fecha de creación
 * @property \Illuminate\Support\Carbon $updated_at Fecha de última actualización
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Comercio[] $comercios Comercios asociados
 * @property-read int $comercios_count Cantidad de comercios asociados
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Conexión de base de datos a utilizar
     *
     * @var string
     */
    protected $connection = 'config';

    /**
     * Guard name para Spatie Permission
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * Atributos asignables en masa
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'password_visible',
        'max_concurrent_sessions',
        'activo',
        'dark_mode',
    ];

    /**
     * Atributos que deben ocultarse en serialización
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'password_visible',
        'remember_token',
    ];

    /**
     * Obtiene los atributos que deben ser casteados a tipos nativos
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'dark_mode' => 'boolean',
        ];
    }

    /**
     * Relación many-to-many con comercios
     *
     * Un usuario puede tener acceso a múltiples comercios y cada comercio
     * puede tener múltiples usuarios.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function comercios(): BelongsToMany
    {
        return $this->belongsToMany(Comercio::class, 'comercio_user', 'user_id', 'comercio_id')
            ->withTimestamps();
    }

    /**
     * Verifica si el usuario tiene acceso a un comercio específico
     *
     * @param int|Comercio $comercio Comercio o ID del comercio
     * @return bool
     */
    public function hasAccessToComercio($comercio): bool
    {
        $comercioId = $comercio instanceof Comercio ? $comercio->id : $comercio;
        return $this->comercios()->where('comercio_id', $comercioId)->exists();
    }

    /**
     * Obtiene el primer comercio asociado al usuario
     *
     * Útil para redireccionar después del login si el usuario solo tiene un comercio
     *
     * @return Comercio|null
     */
    public function getFirstComercio(): ?Comercio
    {
        return $this->comercios()->first();
    }

    /**
     * Asocia el usuario a un comercio
     *
     * @param int|Comercio $comercio Comercio o ID del comercio
     * @return void
     */
    public function attachToComercio($comercio): void
    {
        $comercioId = $comercio instanceof Comercio ? $comercio->id : $comercio;

        if (!$this->hasAccessToComercio($comercioId)) {
            $this->comercios()->attach($comercioId);
        }
    }

    /**
     * Desasocia el usuario de un comercio
     *
     * @param int|Comercio $comercio Comercio o ID del comercio
     * @return void
     */
    public function detachFromComercio($comercio): void
    {
        $comercioId = $comercio instanceof Comercio ? $comercio->id : $comercio;
        $this->comercios()->detach($comercioId);
    }

    /**
     * Establece la contraseña visible (cifrada)
     *
     * Cifra la contraseña usando Laravel encryption para almacenarla de forma
     * que pueda ser descifrada posteriormente por administradores.
     *
     * NOTA DE SEGURIDAD: Solo usar cuando sea absolutamente necesario.
     *
     * @param string $plainPassword Contraseña en texto plano
     * @return void
     */
    public function setPasswordVisible(string $plainPassword): void
    {
        $this->password_visible = encrypt($plainPassword);
    }

    /**
     * Obtiene la contraseña visible descifrada
     *
     * Descifra la contraseña almacenada en password_visible y la retorna
     * en texto plano.
     *
     * NOTA DE SEGURIDAD:
     * - Este método debe usarse solo por administradores autorizados
     * - Considerar registrar en logs cada vez que se llame
     * - Limitar acceso con policies o gates
     *
     * @return string|null Contraseña en texto plano o null si no existe
     */
    public function getPasswordVisible(): ?string
    {
        if (!$this->password_visible) {
            return null;
        }

        try {
            return decrypt($this->password_visible);
        } catch (\Exception $e) {
            // Si falla el descifrado (por ejemplo, APP_KEY cambió), retornar null
            return null;
        }
    }

    /**
     * Verifica si el usuario tiene contraseña visible configurada
     *
     * @return bool True si tiene password_visible configurado
     */
    public function hasPasswordVisible(): bool
    {
        return !empty($this->password_visible);
    }

    /**
     * Obtiene los items del menú permitidos para este usuario
     *
     * Filtra los items del menú basándose en los permisos del usuario
     * en el comercio activo. Solo retorna items raíz.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllowedMenuItems(): \Illuminate\Support\Collection
    {
        // Obtener todos los items raíz activos
        $rootItems = MenuItem::roots()->get();

        // Cargar todos los permisos del usuario de una vez
        $userPermissions = $this->loadAllPermissions();

        // Filtrar por permisos usando la lista cargada
        return $rootItems->filter(function ($item) use ($userPermissions) {
            $permissionName = $item->getPermissionName();
            return in_array($permissionName, $userPermissions);
        });
    }

    /**
     * Obtiene los items hijos del menú permitidos para un item padre
     *
     * @param MenuItem $parentItem Item padre
     * @return \Illuminate\Support\Collection
     */
    public function getAllowedChildrenMenuItems(MenuItem $parentItem): \Illuminate\Support\Collection
    {
        // Cargar todos los permisos del usuario de una vez
        $userPermissions = $this->loadAllPermissions();

        return $parentItem->children->filter(function ($item) use ($userPermissions) {
            $permissionName = $item->getPermissionName();
            return in_array($permissionName, $userPermissions);
        });
    }

    /**
     * Carga todos los permisos del usuario de una vez
     * Usa caché para evitar múltiples queries
     *
     * @return array Array de nombres de permisos
     */
    protected function loadAllPermissions(): array
    {
        $cacheKey = 'user_permissions_' . $this->id . '_' . session('comercio_activo_id');

        return cache()->remember($cacheKey, 300, function () {
            if (!session()->has('comercio_activo_id')) {
                return [];
            }

            // Verificar que el tenant esté configurado
            $prefix = config('database.connections.pymes_tenant.prefix', '');
            if (empty($prefix)) {
                return [];
            }

            $roles = $this->roles();
            if ($roles->isEmpty()) {
                return [];
            }

            $roleIds = $roles->pluck('id')->toArray();

            try {
                // Obtener todos los permission_ids en una sola query
                $permissionIds = DB::connection('pymes_tenant')
                    ->table('role_has_permissions')
                    ->whereIn('role_id', $roleIds)
                    ->pluck('permission_id')
                    ->unique()
                    ->toArray();

                if (empty($permissionIds)) {
                    return [];
                }

                // Obtener los nombres de permisos en una sola query
                return Permission::whereIn('id', $permissionIds)
                    ->pluck('name')
                    ->toArray();
            } catch (\Illuminate\Database\QueryException $e) {
                Log::error('User::loadAllPermissions() - Error de base de datos: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Verifica si el usuario puede acceder a un item del menú
     *
     * @param MenuItem $item Item del menú
     * @return bool
     */
    public function canAccessMenuItem(MenuItem $item): bool
    {
        $permissionName = $item->getPermissionName();
        return $this->hasPermissionTo($permissionName);
    }

    /**
     * Verifica si el usuario tiene un permiso específico en el comercio actual
     *
     * Sobrescribe el método de Spatie para asegurar que use la conexión correcta
     *
     * @param string|\Spatie\Permission\Contracts\Permission $permission Nombre del permiso o instancia
     * @param string|null $guardName Guard name (por defecto 'web')
     * @return bool
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Si no hay comercio activo, no hay permisos
        if (!session()->has('comercio_activo_id')) {
            return false;
        }

        try {
            // Asegurar que estamos usando la conexión correcta
            if (is_string($permission)) {
                $permissionModel = Permission::where('name', $permission)
                    ->where('guard_name', $guardName ?? $this->guard_name ?? 'web')
                    ->first();

                if (!$permissionModel) {
                    return false;
                }

                $permission = $permissionModel;
            }

            // Verificar si el usuario tiene el permiso a través de sus roles
            $roles = $this->roles(); // Ya retorna una Collection

            foreach ($roles as $role) {
                if ($role->hasPermissionTo($permission)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // En caso de error, devolver false por seguridad
            Log::error('Error checking permission: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene los roles del usuario en el comercio activo y sucursal activa
     * Usa la conexión pymes_tenant para obtener roles del comercio activo
     *
     * Si hay sucursal activa: retorna roles con sucursal_id = 0 (todas) O sucursal_id = sucursal activa
     * Si NO hay sucursal activa: retorna TODOS los roles del usuario (sin filtrar por sucursal)
     *
     * @return \Illuminate\Support\Collection
     */
    public function roles()
    {
        // Verificar que hay un comercio activo
        if (!session()->has('comercio_activo_id')) {
            return collect();
        }

        // IMPORTANTE: Verificar que el tenant esté configurado antes de hacer queries
        // Esto previene errores cuando la conexión pymes_tenant no tiene prefijo establecido
        try {
            $prefix = config('database.connections.pymes_tenant.prefix', '');
            if (empty($prefix)) {
                // El tenant no está configurado, intentar configurarlo
                $comercioId = session('comercio_activo_id');
                if ($comercioId) {
                    $tenantService = app(\App\Services\TenantService::class);
                    $tenantService->setComercio($comercioId);
                    $prefix = config('database.connections.pymes_tenant.prefix', '');
                }

                // Si aún no hay prefijo, retornar vacío para evitar errores
                if (empty($prefix)) {
                    Log::warning('User::roles() - Tenant no configurado, retornando colección vacía', [
                        'user_id' => $this->id,
                        'comercio_id' => $comercioId ?? null
                    ]);
                    return collect();
                }
            }
        } catch (\Exception $e) {
            Log::error('User::roles() - Error verificando configuración de tenant: ' . $e->getMessage());
            return collect();
        }

        // Obtener sucursal activa (si existe)
        $sucursalActiva = session('sucursal_id');

        try {
            // Construir query base
            $query = DB::connection('pymes_tenant')
                ->table('model_has_roles')
                ->where('model_id', $this->id)
                ->where('model_type', static::class);

            // Si hay sucursal activa, filtrar por sucursal
            if ($sucursalActiva) {
                $query->where(function($subQuery) use ($sucursalActiva) {
                    // Incluir roles con sucursal_id = 0 (acceso a todas las sucursales)
                    $subQuery->where('sucursal_id', 0)
                        // O roles específicos de la sucursal activa
                        ->orWhere('sucursal_id', $sucursalActiva);
                });
            }
            // Si NO hay sucursal activa, retornar TODOS los roles (sin filtrar)
            // Esto permite que el sistema funcione mientras se establece la sucursal

            $roleIds = $query->pluck('role_id')->unique();

            if ($roleIds->isEmpty()) {
                return collect();
            }

            // Obtener los roles completos desde la tabla de roles en pymes_tenant
            return Role::whereIn('id', $roleIds)->get();
        } catch (\Illuminate\Database\QueryException $e) {
            // Capturar errores de base de datos (tabla no existe, etc.)
            Log::error('User::roles() - Error de base de datos: ' . $e->getMessage(), [
                'user_id' => $this->id,
                'comercio_id' => session('comercio_activo_id'),
                'prefix' => config('database.connections.pymes_tenant.prefix', '')
            ]);
            return collect();
        }
    }

    /**
     * Asigna un rol al usuario en el comercio activo
     *
     * @param Role|string $role Instancia de Role o nombre del rol
     * @return void
     */
    public function assignRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        // Verificar si ya tiene el rol
        $exists = DB::connection('pymes_tenant')
            ->table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_id', $this->id)
            ->where('model_type', static::class)
            ->exists();

        if (!$exists) {
            DB::connection('pymes_tenant')
                ->table('model_has_roles')
                ->insert([
                    'role_id' => $role->id,
                    'model_id' => $this->id,
                    'model_type' => static::class,
                ]);
        }
    }

    /**
     * Verifica si el usuario tiene un rol específico
     *
     * @param string|Role $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        $roles = $this->roles(); // Ya retorna una Collection

        if (is_string($role)) {
            return $roles->contains('name', $role);
        }

        return $roles->contains('id', $role->id);
    }

    /**
     * Verifica si el usuario tiene alguno de los roles especificados
     *
     * @param array $roles
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    // ========================================
    // RELACIONES CON MODELOS DEL TENANT
    // ========================================

    /**
     * Obtiene las ventas realizadas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ventas()
    {
        return $this->hasMany(\App\Models\Venta::class, 'usuario_id');
    }

    /**
     * Obtiene las compras realizadas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function compras()
    {
        return $this->hasMany(\App\Models\Compra::class, 'usuario_id');
    }

    /**
     * Obtiene los movimientos de caja realizados por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function movimientosCaja()
    {
        return $this->hasMany(\App\Models\MovimientoCaja::class, 'usuario_id');
    }

    /**
     * Obtiene las transferencias de stock solicitadas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transferenciasStockSolicitadas()
    {
        return $this->hasMany(\App\Models\TransferenciaStock::class, 'usuario_solicita_id');
    }

    /**
     * Obtiene las transferencias de stock aprobadas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transferenciasStockAprobadas()
    {
        return $this->hasMany(\App\Models\TransferenciaStock::class, 'usuario_aprueba_id');
    }

    /**
     * Obtiene las transferencias de stock recibidas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transferenciasStockRecibidas()
    {
        return $this->hasMany(\App\Models\TransferenciaStock::class, 'usuario_recibe_id');
    }

    /**
     * Obtiene las transferencias de efectivo solicitadas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transferenciasEfectivoSolicitadas()
    {
        return $this->hasMany(\App\Models\TransferenciaEfectivo::class, 'usuario_solicita_id');
    }

    /**
     * Obtiene las transferencias de efectivo autorizadas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transferenciasEfectivoAutorizadas()
    {
        return $this->hasMany(\App\Models\TransferenciaEfectivo::class, 'usuario_autoriza_id');
    }

    /**
     * Obtiene las transferencias de efectivo recibidas por el usuario
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transferenciasEfectivoRecibidas()
    {
        return $this->hasMany(\App\Models\TransferenciaEfectivo::class, 'usuario_recibe_id');
    }
}

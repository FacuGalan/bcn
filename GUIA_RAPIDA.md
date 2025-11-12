# Guía Rápida - BCN Pymes

Esta guía proporciona referencias rápidas para trabajar con el proyecto.

---

## Índice de Documentación

📚 **Documentos Principales:**
- **README.md** - Introducción y setup del proyecto
- **ARQUITECTURA.md** - Arquitectura completa del sistema ⭐ LEER PRIMERO
- **ROADMAP.md** - Próximos pasos y funcionalidades planificadas
- **ESTRUCTURA_MULTITENANT.md** - Detalles del sistema multi-tenant
- **PASSWORD_VISIBLE_GUIA.md** - Sistema de contraseñas recuperables

---

## Ubicaciones Clave

### Modelos
```
app/Models/
├── User.php                # Usuario centralizado (config DB)
├── Comercio.php            # Comercio/PYME (config DB)
├── Role.php                # Rol con prefijo (pymes_tenant)
├── Permission.php          # Permiso compartido (pymes)
└── MenuItem.php            # Item del menú compartido (pymes)
```

### Servicios
```
app/Services/
├── TenantService.php         # Gestión del tenant (comercio activo)
└── SessionManagerService.php # Control de sesiones concurrentes
```

### Livewire Components
```
app/Livewire/
├── ComercioSelector.php           # Selector de comercio
├── DynamicMenu.php                # Menú dinámico
└── Configuracion/
    ├── Usuarios.php               # CRUD de usuarios
    └── RolesPermisos.php          # CRUD de roles y permisos
```

### Middleware
```
app/Http/Middleware/
├── TenantMiddleware.php          # Valida comercio activo
└── ConfigureTenantMiddleware.php # Configura tenant en cada request
```

### Comandos
```
app/Console/Commands/
├── InitComercioCommand.php       # php artisan comercio:init {id}
└── SeedComercioMenuCommand.php   # php artisan comercio:seed-menu {id}
```

---

## Conexiones de Base de Datos

### Config (Centralizada)
```php
'config' => [
    'database' => env('DB_DATABASE_CONFIG', 'config'),
    // Almacena: usuarios, comercios, user_comercio, sessions
]
```

### Pymes (Con prefijo dinámico)
```php
'pymes' => [
    'database' => env('DB_DATABASE', 'pymes'),
    // Almacena: menu_items, permissions (compartidos)
]

'pymes_tenant' => [
    'database' => 'pymes', // Dinámico según comercio
    'prefix' => '',        // Dinámico según comercio (ej: 000001_)
    // Almacena: roles, model_has_roles, articulos, ventas (con prefijo)
]
```

---

## Comandos Útiles

### Inicializar un Comercio
```bash
# Crear tablas con prefijo
php artisan comercio:init 1

# Poblar menú y permisos
php artisan comercio:seed-menu 1
```

### Limpiar Caché
```bash
# Limpiar caché de la aplicación
php artisan cache:clear

# Limpiar configuración cacheada
php artisan config:clear

# Limpiar rutas cacheadas
php artisan route:clear

# Limpiar vistas compiladas
php artisan view:clear
```

### Compilar Assets
```bash
# Desarrollo (con watch)
npm run dev

# Producción (minificado)
npm run build
```

---

## Patrones Comunes

### Obtener Comercio Activo
```php
// En cualquier parte
$tenantService = app(TenantService::class);
$comercio = $tenantService->getComercio();

// En Livewire
$comercioId = session('comercio_activo_id');
$comercio = Comercio::find($comercioId);
```

### Verificar Permisos
```php
// En código
if (auth()->user()->hasPermissionTo('menu.configuracion')) {
    // Hacer algo
}

// En Blade
@can('menu.configuracion')
    <a href="#">Link</a>
@endcan

// En Livewire
$this->authorize('menu.configuracion.usuarios');
```

### Trabajar con Tablas Prefijadas
```php
// Usar modelo con conexión pymes_tenant
Role::all(); // Usa prefijo automáticamente

// Query builder
DB::connection('pymes_tenant')
    ->table('roles') // El prefijo se aplica automáticamente
    ->get();
```

### Caché de Permisos
```php
// Los permisos se cachean automáticamente por 5 minutos
// Key: user_permissions_{user_id}_{comercio_id}

// Limpiar caché manualmente
cache()->forget("user_permissions_{$userId}_{$comercioId}");
```

---

## Estructura de Permisos

### Formato
Todos los permisos siguen: `menu.{slug}`

### Ejemplos
```
menu.dashboard                  → Dashboard
menu.ventas                     → Módulo de ventas
menu.ventas.nueva-venta         → Nueva venta
menu.configuracion              → Configuración
menu.configuracion.usuarios     → Gestión de usuarios
```

### Roles Predefinidos
1. **Super Administrador** - Todos los permisos
2. **Gerente** - Casi todos excepto configuración crítica
3. **Vendedor** - Ventas e inventario
4. **Visualizador** - Solo lectura

---

## Flujos Principales

### Login
```
1. Usuario ingresa credenciales
2. Validar límite de sesiones
3. Si excede: mostrar sesiones activas para cerrar
4. Login exitoso
5. ¿Tiene múltiples comercios?
   Sí → Mostrar selector
   No → Establecer comercio automático
6. Configurar tenant (prefijo)
7. Redirigir a dashboard
```

### Cambio de Comercio
```
1. Usuario click en nombre del comercio
2. Mostrar selector
3. Usuario selecciona nuevo comercio
4. Validar acceso
5. Cambiar comercio activo en sesión
6. Reconfigurar prefijo
7. Limpiar caché de permisos/menú
8. Redirigir a dashboard
```

### Request con Tenant
```
1. Request llega
2. ConfigureTenantMiddleware
   - Configura prefijo si hay comercio en sesión
3. TenantMiddleware (rutas protegidas)
   - Valida comercio activo
   - Valida acceso del usuario
4. Controller/Livewire
   - Usa tablas con prefijo automáticamente
5. Response
```

---

## Optimizaciones Aplicadas

✅ **Modales instantáneos** - Alpine.js en lugar de wire:click
✅ **Queries N+1 eliminadas** - Eager loading en usuarios/roles
✅ **Caché de menú** - 5 minutos para items y permisos
✅ **Conteos batch** - GROUP BY en lugar de loops
✅ **Caché en memoria** - TenantService cachea comercio actual

---

## Troubleshooting

### "No hay comercio activo"
```php
// Verificar sesión
dd(session('comercio_activo_id'));

// Establecer manualmente (solo desarrollo)
$tenantService = app(TenantService::class);
$tenantService->setComercio(1);
```

### "Tabla no encontrada"
```php
// Verificar que el prefijo esté configurado
dd(config('database.connections.pymes_tenant.prefix'));

// Verificar que el comercio esté inicializado
php artisan comercio:init {comercio_id}
```

### "Permiso denegado"
```php
// Verificar permisos del usuario
$user = auth()->user();
dd($user->loadAllPermissions());

// Verificar roles
dd($user->roles());

// Ejecutar seeder de permisos
php artisan comercio:seed-menu {comercio_id}
```

### "Sesión límite alcanzado"
```php
// Ver sesiones activas
$sessionManager = app(SessionManagerService::class);
dd($sessionManager->getSessionsInfo($user));

// Aumentar límite
$user->max_concurrent_sessions = 5;
$user->save();
```

---

## Testing

### Probar Multi-Tenant
```php
// 1. Crear dos comercios
$comercio1 = Comercio::create(['mail' => 'comercio1@test.com', 'nombre' => 'Comercio 1']);
$comercio2 = Comercio::create(['mail' => 'comercio2@test.com', 'nombre' => 'Comercio 2']);

// 2. Inicializar tablas
php artisan comercio:init 1
php artisan comercio:init 2

// 3. Verificar tablas
// Deberían existir: 000001_roles, 000002_roles

// 4. Crear usuario con acceso a ambos
$user->attachToComercio($comercio1);
$user->attachToComercio($comercio2);

// 5. Probar switch
$tenantService->setComercio($comercio1);
Role::create(['name' => 'Admin 1']);

$tenantService->setComercio($comercio2);
Role::create(['name' => 'Admin 2']);

// 6. Verificar aislamiento
$tenantService->setComercio($comercio1);
dd(Role::all()); // Solo "Admin 1"

$tenantService->setComercio($comercio2);
dd(Role::all()); // Solo "Admin 2"
```

---

## Convenciones

### Nombres
- **Modelos:** PascalCase singular (User, Comercio)
- **Métodos:** camelCase (getComercio, hasPermission)
- **Vistas:** kebab-case (roles-permisos.blade.php)
- **Rutas:** kebab-case con punto (comercio.selector, configuracion.usuarios)

### Commits
```
feat: Nueva funcionalidad
fix: Corrección de bug
refactor: Refactorización sin cambio de funcionalidad
docs: Cambios en documentación
style: Formato de código
perf: Mejora de rendimiento
test: Añadir o modificar tests
```

---

## Enlaces Rápidos

- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [Livewire 3 Docs](https://livewire.laravel.com/docs)
- [Alpine.js Docs](https://alpinejs.dev)
- [Tailwind CSS](https://tailwindcss.com/docs)
- [Spatie Permission](https://spatie.be/docs/laravel-permission)

---

## Contacto

Para dudas técnicas, consultar con el equipo de desarrollo de BCN Pymes.

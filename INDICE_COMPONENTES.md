# Índice de Componentes - BCN Pymes

Referencia rápida de todos los componentes del sistema con su ubicación y propósito.

---

## 📁 Modelos (app/Models/)

| Archivo | Conexión | Propósito | Documentación |
|---------|----------|-----------|---------------|
| `User.php` | config | Usuario del sistema (multi-comercio) | Ver ARQUITECTURA.md §Modelos |
| `Comercio.php` | config | Comercio/PYME con prefijo de tablas | Ver ARQUITECTURA.md §Modelos |
| `Role.php` | pymes_tenant | Rol con prefijo por comercio | Ver ARQUITECTURA.md §Modelos |
| `Permission.php` | pymes | Permiso compartido entre comercios | Ver ARQUITECTURA.md §Modelos |
| `MenuItem.php` | pymes | Item del menú jerárquico compartido | Ver ARQUITECTURA.md §Modelos |

---

## 🔧 Servicios (app/Services/)

| Archivo | Propósito | Métodos Clave |
|---------|-----------|---------------|
| `TenantService.php` | Gestión del tenant (comercio activo) | `setComercio()`, `getComercio()`, `switchComercio()` |
| `SessionManagerService.php` | Control de sesiones concurrentes | `hasReachedSessionLimit()`, `freeSessionSpace()`, `getSessionsInfo()` |

**📖 Ver:** ARQUITECTURA.md §Servicios

---

## 🚪 Middleware (app/Http/Middleware/)

| Archivo | Aplicado a | Propósito |
|---------|------------|-----------|
| `TenantMiddleware.php` | Rutas protegidas | Valida comercio activo y acceso del usuario |
| `ConfigureTenantMiddleware.php` | Todos los requests web | Configura prefijo automáticamente si hay comercio |

**📖 Ver:** ARQUITECTURA.md §Middleware

---

## ⚡ Componentes Livewire (app/Livewire/)

### Principales

| Archivo | Ruta | Propósito |
|---------|------|-----------|
| `ComercioSelector.php` | /comercio/selector | Permite elegir comercio al usuario |
| `DynamicMenu.php` | (componente) | Renderiza menú según permisos con caché |
| `Forms/LoginForm.php` | (form) | Formulario de login con validación de sesiones |

### Configuración

| Archivo | Ruta | Propósito |
|---------|------|-----------|
| `Configuracion/Usuarios.php` | /configuracion/usuarios | CRUD de usuarios con eager loading optimizado |
| `Configuracion/RolesPermisos.php` | /configuracion/roles | CRUD de roles con conteos batch |

**📖 Ver:** ARQUITECTURA.md §Componentes-Livewire

---

## 🎨 Vistas (resources/views/)

### Layouts

| Archivo | Propósito |
|---------|-----------|
| `layouts/app.blade.php` | Layout principal con menú dinámico |
| `layouts/guest.blade.php` | Layout para páginas sin autenticación |

### Livewire

| Archivo | Componente |
|---------|------------|
| `livewire/comercio-selector.blade.php` | Selector de comercio |
| `livewire/dynamic-menu.blade.php` | Menú dinámico |
| `livewire/configuracion/usuarios.blade.php` | Gestión de usuarios |
| `livewire/configuracion/roles-permisos.blade.php` | Gestión de roles |

### Componentes

| Archivo | Propósito |
|---------|-----------|
| `components/modal.blade.php` | Modal reutilizable de Breeze |
| `components/toast-notifications.blade.php` | Sistema de notificaciones toast |
| `components/application-logo.blade.php` | Logo BCN Pymes |

---

## 🛠️ Comandos Artisan (app/Console/Commands/)

| Archivo | Comando | Propósito |
|---------|---------|-----------|
| `InitComercioCommand.php` | `comercio:init {id}` | Crea tablas con prefijo para un comercio |
| `SeedComercioMenuCommand.php` | `comercio:seed-menu {id}` | Pobla menú, roles y permisos |

**📖 Ver:** ARQUITECTURA.md §Comandos-Artisan

---

## 📊 Seeders (database/seeders/)

| Archivo | Propósito |
|---------|-----------|
| `MenuItemSeeder.php` | Crea estructura del menú (13 items) |
| `RolePermissionSeeder.php` | Crea roles y asigna permisos (4 roles) |
| `ComercioUserSeeder.php` | Crea comercio y usuario de prueba |

---

## 🗃️ Migraciones (database/migrations/)

### Config DB

| Archivo | Tabla | Propósito |
|---------|-------|-----------|
| `0001_01_01_000000_create_users_table.php` | users | Usuarios centralizados |
| `..._create_comercios_table.php` | comercios | Comercios del sistema |
| `..._create_user_comercio_table.php` | user_comercio | Relación many-to-many |
| `..._create_sessions_table.php` | sessions | Control de sesiones |

### Pymes DB

| Archivo | Tabla | Propósito |
|---------|-------|-----------|
| `..._create_menu_items_table.php` | menu_items | Estructura del menú (sin prefijo) |
| Tablas con prefijo | {prefix}_* | Creadas dinámicamente por `comercio:init` |

---

## 📝 Configuración (config/)

| Archivo | Propósito Clave |
|---------|----------------|
| `database.php` | Conexiones: config, pymes, pymes_tenant (con prefijo dinámico) |
| `session.php` | Driver: database, lifetime: 120 minutos |
| `permission.php` | Configuración de Spatie Permission |
| `livewire.php` | Configuración de Livewire |

---

## 🎯 Rutas (routes/)

| Archivo | Propósito |
|---------|-----------|
| `web.php` | Rutas principales de la aplicación |
| `auth.php` | Rutas de autenticación (Breeze) |

### Rutas Principales

```php
// Autenticación
/login                          → Login
/register                       → Registro
/forgot-password                → Recuperar contraseña

// Selector de comercio
/comercio/selector              → Selector de comercio

// Aplicación (requiere tenant)
/dashboard                      → Dashboard
/configuracion/usuarios         → Gestión de usuarios
/configuracion/roles            → Gestión de roles
```

---

## 🧪 Tests (tests/)

_Pendiente de implementación_

Estructura sugerida:
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   └── SessionLimitTest.php
│   ├── Tenant/
│   │   ├── ComercioSelectorTest.php
│   │   └── TenantIsolationTest.php
│   └── Configuracion/
│       ├── UsuariosTest.php
│       └── RolesPermisosTest.php
└── Unit/
    ├── Models/
    │   ├── UserTest.php
    │   └── ComercioTest.php
    └── Services/
        ├── TenantServiceTest.php
        └── SessionManagerServiceTest.php
```

---

## 📚 Documentación

| Archivo | Contenido |
|---------|-----------|
| `README.md` | Introducción y setup del proyecto |
| `ARQUITECTURA.md` | ⭐ Arquitectura completa y detallada |
| `GUIA_RAPIDA.md` | Referencia rápida y patrones comunes |
| `INDICE_COMPONENTES.md` | Este archivo (índice de todos los componentes) |
| `ROADMAP.md` | Funcionalidades planificadas |
| `ESTRUCTURA_MULTITENANT.md` | Detalles técnicos del multi-tenant |
| `PASSWORD_VISIBLE_GUIA.md` | Sistema de contraseñas recuperables |

---

## 🔍 Búsqueda Rápida

### ¿Dónde está...?

**La lógica de login?**
→ `app/Livewire/Forms/LoginForm.php`

**El selector de comercio?**
→ `app/Livewire/ComercioSelector.php`

**La configuración del prefijo?**
→ `app/Services/TenantService.php:configureConnection()`

**El menú dinámico?**
→ `app/Livewire/DynamicMenu.php`

**La validación de permisos?**
→ `app/Models/User.php:hasPermissionTo()`

**El control de sesiones?**
→ `app/Services/SessionManagerService.php`

**La gestión de usuarios?**
→ `app/Livewire/Configuracion/Usuarios.php`

**El middleware de tenant?**
→ `app/Http/Middleware/TenantMiddleware.php`

---

## 🗺️ Mapa de Dependencias

```
┌─────────────────────────────────────────────────────┐
│                    Request                           │
└───────────────────┬─────────────────────────────────┘
                    │
    ┌───────────────▼───────────────┐
    │  ConfigureTenantMiddleware    │──► TenantService
    └───────────────┬───────────────┘
                    │
    ┌───────────────▼───────────────┐
    │     TenantMiddleware          │──► TenantService
    │  (solo rutas protegidas)      │    User::hasAccessToComercio()
    └───────────────┬───────────────┘
                    │
    ┌───────────────▼───────────────┐
    │    Controller/Livewire        │
    │                               │
    │  DynamicMenu ───────────────► │──► User::getAllowedMenuItems()
    │  Usuarios ──────────────────► │    User::roles()
    │  RolesPermisos ─────────────► │    Role::users()
    │                               │
    └───────────────────────────────┘
```

---

## 💡 Tips de Desarrollo

### Debug del Tenant

```php
// Ver comercio activo
dd(app(TenantService::class)->getComercio());

// Ver prefijo actual
dd(config('database.connections.pymes_tenant.prefix'));

// Ver permisos del usuario
dd(auth()->user()->loadAllPermissions());
```

### Logs Útiles

```php
// En TenantService
\Log::info('Comercio establecido', [
    'comercio_id' => $comercio->id,
    'prefix' => $prefix,
]);

// En LoginForm
\Log::info('Login exitoso', [
    'user_id' => $user->id,
    'comercios_count' => $user->comercios->count(),
]);
```

### Caché Keys a Recordar

```
menu_parent_items_{user_id}_{comercio_id}
menu_children_items_{parent_id}_{user_id}_{comercio_id}
user_permissions_{user_id}_{comercio_id}
```

---

## 🔗 Referencias Externas

- **Laravel 11:** https://laravel.com/docs/11.x
- **Livewire 3:** https://livewire.laravel.com/docs
- **Alpine.js:** https://alpinejs.dev
- **Tailwind CSS:** https://tailwindcss.com/docs
- **Spatie Permission:** https://spatie.be/docs/laravel-permission
- **Laravel Breeze:** https://laravel.com/docs/11.x/starter-kits#breeze

---

**Última actualización:** 2025-11-06
**Versión del documento:** 1.0.0

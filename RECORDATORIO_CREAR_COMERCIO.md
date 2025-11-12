# 🔮 FUTURO: Sistema de Creación Automática de Comercios

> **IMPORTANTE**: Este archivo es un recordatorio para implementar DESPUÉS de tener la estructura completa de tablas del negocio.
>
> **Estado**: ⏸️ PENDIENTE
>
> **Implementar cuando tengamos**: Ventas, Artículos, Clientes, Inventario, y otras tablas principales del negocio.

---

## 🎯 Objetivo

Crear un sistema automatizado que permita dar de alta nuevos comercios con todos sus datos y tablas iniciales mediante un solo comando.

---

## 📦 Componentes a Crear

### 1. Comando Artisan

**Ubicación**: `app/Console/Commands/ComercioCreate.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\ComercioSetupService;
use Illuminate\Console\Command;

class ComercioCreate extends Command
{
    protected $signature = 'comercio:create
                            {--name= : Nombre del comercio}
                            {--email= : Email del comercio}
                            {--database= : Base de datos (pymes, pymes1, resto, etc.)}
                            {--admin-username= : Username del administrador}
                            {--admin-email= : Email del administrador}
                            {--admin-password= : Password del administrador}';

    protected $description = 'Crea un nuevo comercio con toda su estructura de tablas y datos iniciales';

    public function handle()
    {
        $this->info('🏪 Creando nuevo comercio...');

        // TODO: Implementar lógica completa
        // 1. Solicitar datos interactivamente si no se pasaron por parámetros
        // 2. Validar datos
        // 3. Llamar a ComercioSetupService
        // 4. Mostrar progreso con barra de progreso
        // 5. Mostrar resumen final

        return Command::SUCCESS;
    }
}
```

**Uso planeado**:
```bash
# Modo interactivo
php artisan comercio:create

# Modo con parámetros
php artisan comercio:create \
  --name="Ferretería Central" \
  --email="ferreteria@example.com" \
  --database="pymes" \
  --admin-username="admin_ferreteria" \
  --admin-email="admin@ferreteria.com" \
  --admin-password="SecurePass123"
```

---

### 2. Servicio de Setup

**Ubicación**: `app/Services/ComercioSetupService.php`

```php
<?php

namespace App\Services;

use App\Models\Comercio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ComercioSetupService
{
    /**
     * Crea un nuevo comercio completo
     *
     * @param array $comercioData Datos del comercio
     * @param array $adminData Datos del usuario administrador
     * @return Comercio
     */
    public function createComercio(array $comercioData, array $adminData): Comercio
    {
        DB::beginTransaction();

        try {
            // 1. Crear registro del comercio
            $comercio = $this->createComercioRecord($comercioData);

            // 2. Crear todas las tablas con prefijo
            $this->createComercioTables($comercio);

            // 3. Crear roles por defecto
            $this->createDefaultRoles($comercio);

            // 4. Asignar permisos a roles
            $this->assignPermissionsToRoles($comercio);

            // 5. Crear/vincular usuario administrador
            $admin = $this->createAdminUser($comercio, $adminData);

            // 6. Insertar datos semilla del comercio
            $this->seedComercioData($comercio);

            DB::commit();

            return $comercio;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Crea el registro del comercio en la BD config
     */
    protected function createComercioRecord(array $data): Comercio
    {
        // TODO: Implementar
    }

    /**
     * Crea todas las tablas con prefijo del comercio
     */
    protected function createComercioTables(Comercio $comercio): void
    {
        $prefix = $comercio->getTablePrefix();
        $database = $comercio->database_name;

        // Configurar tenant
        app(TenantService::class)->setComercio($comercio);

        // TODO: Crear cada tabla
        // - Tablas Spatie (roles, permissions, etc.)
        // - Tablas de negocio (ventas, articulos, etc.)
        // - Usar migrations o SQL directo
    }

    /**
     * Crea los roles por defecto del comercio
     */
    protected function createDefaultRoles(Comercio $comercio): void
    {
        // TODO: Implementar
        // Roles: Administrador, Gerente, Vendedor, Visualizador
    }

    /**
     * Asigna permisos compartidos a los roles del comercio
     */
    protected function assignPermissionsToRoles(Comercio $comercio): void
    {
        // TODO: Implementar
        // Leer permisos de tabla compartida
        // Asignar según el rol
    }

    /**
     * Crea o vincula el usuario administrador del comercio
     */
    protected function createAdminUser(Comercio $comercio, array $adminData): User
    {
        // TODO: Implementar
        // - Buscar usuario existente por email/username
        // - Si no existe, crear
        // - Vincular a comercio
        // - Asignar rol Administrador
    }

    /**
     * Inserta datos semilla específicos del comercio
     */
    protected function seedComercioData(Comercio $comercio): void
    {
        // TODO: Implementar
        // - Categorías por defecto
        // - Configuraciones iniciales
        // - Cualquier dato maestro necesario
    }
}
```

---

### 3. Migraciones Modulares

**Ubicación**: `database/migrations/comercio_template/`

Crear un directorio con las migraciones "plantilla" que se ejecutarán para cada comercio:

```
database/migrations/comercio_template/
├── 001_create_roles_tables.php
├── 002_create_ventas_tables.php
├── 003_create_articulos_tables.php
├── 004_create_clientes_tables.php
├── 005_create_inventario_tables.php
└── ...
```

Cada migración tendrá una estructura que permita ejecutarse con un prefijo dinámico.

---

### 4. Seeders por Comercio

**Ubicación**: `database/seeders/Comercio/`

```
database/seeders/Comercio/
├── ComercioRolesSeeder.php
├── ComercioConfiguracionSeeder.php
├── ComercioCategoriesSeeder.php
└── ...
```

---

## 📋 Checklist de Implementación

### Fase 1: Preparación
- [ ] Tener todas las tablas de negocio definidas (ventas, artículos, etc.)
- [ ] Documentar estructura de cada tabla
- [ ] Definir datos semilla mínimos requeridos

### Fase 2: Creación de Componentes
- [ ] Crear `ComercioSetupService`
- [ ] Crear `ComercioCreate` command
- [ ] Crear migraciones plantilla
- [ ] Crear seeders de comercio

### Fase 3: Testing
- [ ] Test unitario para `ComercioSetupService`
- [ ] Test de integración para comando completo
- [ ] Verificar rollback en caso de error
- [ ] Test con diferentes bases de datos (pymes, resto)

### Fase 4: Documentación
- [ ] Actualizar README con instrucciones
- [ ] Documentar parámetros del comando
- [ ] Crear guía de troubleshooting

---

## 🎨 Flujo Visual del Comando

```
┌─────────────────────────────────────────┐
│ php artisan comercio:create             │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Solicitar datos del comercio:           │
│ • Nombre                                │
│ • Email                                 │
│ • Base de datos (pymes/resto/pymes1)    │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Solicitar datos del administrador:      │
│ • Username                              │
│ • Email                                 │
│ • Password                              │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Validar todos los datos                 │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Crear registro en tabla comercios       │
│ • ID auto-generado: 3                   │
│ • Prefijo calculado: 000003_            │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Crear tablas Spatie:                    │
│ • 000003_roles                          │
│ • 000003_role_has_permissions           │
│ • 000003_model_has_roles                │
│ • 000003_model_has_permissions          │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Crear tablas de negocio:                │
│ • 000003_ventas                         │
│ • 000003_articulos                      │
│ • 000003_clientes                       │
│ • ... (todas las demás)                 │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Crear roles por defecto:                │
│ • Administrador                         │
│ • Gerente                               │
│ • Vendedor                              │
│ • Visualizador                          │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Asignar permisos a roles:               │
│ • Administrador: todos                  │
│ • Gerente: parcial                      │
│ • Vendedor: limitado                    │
│ • Visualizador: solo lectura            │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Crear/vincular usuario administrador:   │
│ • Crear usuario si no existe            │
│ • Asociar a comercio                    │
│ • Asignar rol Administrador             │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ Insertar datos semilla:                 │
│ • Categorías por defecto                │
│ • Configuraciones iniciales             │
│ • Datos maestros                        │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│ ✅ Comercio creado exitosamente         │
│                                         │
│ Datos de acceso:                        │
│ • Email comercio: ferreteria@example.com│
│ • Username admin: admin_ferreteria      │
│ • Password: SecurePass123               │
│                                         │
│ URL de acceso: http://tuapp.com/login   │
└─────────────────────────────────────────┘
```

---

## 🔍 Consideraciones Importantes

### 1. Transacciones
Todo el proceso debe estar en una transacción para poder hacer rollback si algo falla.

### 2. Validaciones
- Email del comercio único
- Username del admin único (si se crea)
- Base de datos debe existir
- Verificar que no existan ya las tablas con ese prefijo

### 3. Logging
Registrar cada paso del proceso para debugging y auditoría.

### 4. Progreso Visual
Usar barra de progreso de Laravel para mostrar avance:
```php
$bar = $this->output->createProgressBar(10);
$bar->start();
// ... hacer algo
$bar->advance();
// ... etc
$bar->finish();
```

### 5. Manejo de Errores
Capturar y mostrar errores descriptivos. Si falla en mitad del proceso, hacer rollback completo.

---

## 📝 Ejemplo de Output Esperado

```
🏪 Creando nuevo comercio...

Datos del comercio:
  Nombre: Ferretería Central
  Email: ferreteria@example.com
  Base de datos: pymes
  Prefijo: 000003_

✓ Registro de comercio creado
✓ Tablas Spatie creadas (4/4)
✓ Tablas de negocio creadas (15/15)
✓ Roles creados (4/4)
✓ Permisos asignados (13 permisos)
✓ Usuario administrador creado
✓ Datos semilla insertados

═══════════════════════════════════════════
✅ Comercio creado exitosamente

Información de acceso:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
  Email comercio: ferreteria@example.com
  Username admin: admin_ferreteria
  Email admin:    admin@ferreteria.com
  Password:       SecurePass123
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ID del comercio: 3
Prefijo de tablas: 000003_
Base de datos: pymes

Puede acceder al sistema en:
http://tuapp.com/login

Tiempo total: 3.2 segundos
```

---

## 🚀 Comando de Eliminación (Bonus)

También sería útil crear:

```bash
php artisan comercio:delete {comercio_id} --force
```

Para eliminar un comercio y todas sus tablas (con confirmación).

---

## 📚 Referencias

Ver también:
- `ESTRUCTURA_MULTITENANT.md` - Documentación de la arquitectura actual
- `app/Services/TenantService.php` - Servicio de configuración de tenant
- `database/seeders/RolePermissionSeeder.php` - Ejemplo de asignación de permisos

---

**Última actualización**: 2025-11-04
**Recordar implementar después de tener**: Estructura completa de tablas del negocio

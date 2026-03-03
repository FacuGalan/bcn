# BCN Pymes - Instrucciones del Proyecto

## Preferencias
- Responder siempre en español

## Stack
- Laravel 11 + Livewire 3 + Alpine.js + Tailwind CSS
- PHP 8.2+, MySQL 8, Node.js 18+

## Architecture Patterns
- **Multi-tenant**: Uses `pymes_tenant` DB connection. Models set `protected $connection = 'pymes_tenant'`.
- **Sucursal-aware**: Components listen for `sucursal-changed` event, use `sucursal_activa()` helper. NO para componentes de catálogo global (GestionarGruposOpcionales, GestionarRecetas)
- **Append-only ledger**: MovimientoCuentaCorriente, MovimientoStock y MovimientoCuentaEmpresa usan contraasientos para anulaciones (ambos quedan 'activo', se cancelan matemáticamente)
- **Cuentas empresa**: Sistema de cuentas bancarias/billeteras con saldo, movimientos ledger, vinculación automática forma de pago → cuenta
- **Stock dual system**: tabla `stock` = caché de stock actual, `movimientos_stock` = historial completo
- **Services pattern**: Lógica de negocio en `app/Services/`, componentes Livewire llaman a services
- **morphMap**: `AppServiceProvider::boot()` mapea `'Articulo'` → `Articulo::class`, `'Opcional'` → `Opcional::class` para relaciones polimórficas

## Databases & Connections (3 conexiones)
- **config**: users, comercios, comercio_user, condiciones_iva, provincias, localidades
- **pymes** (shared): menu_items, permissions, permisos_funcionales (sin prefijo, compartidas)
- **pymes_tenant**: tablas con prefijo `{NNNNNN}_` por comercio (ej: 000001_ventas, 000005_ventas)

### Prefijo tenant
- Formato: `str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_'`
- Ejemplo: comercio ID 1 → prefijo `000001_`, comercio ID 5 → prefijo `000005_`
- El prefijo se guarda en `config.comercios.prefijo`

## Provisioning de Comercios

### Comando
```
php artisan comercio:provision --nombre="Mi Comercio" --database=pymes --mail=admin@mail.com
```

### Qué hace
1. Crea comercio en `config.comercios`
2. Crea usuario admin (username: admin{id}, password: admin1234)
3. Asocia usuario ↔ comercio
4. Ejecuta `database/sql/tenant_tables.sql` con el prefijo real
5. Configura conexión pymes_tenant
6. Seed: 1 sucursal, 1 caja, 3 tipos IVA, 7 conceptos pago, 7 formas pago, 5 roles, 3 monedas, 11 conceptos movimiento cuenta
7. Asigna permisos a roles y rol Super Admin al usuario
8. Marca todas las migraciones como ejecutadas en {PREFIX}migrations

### Archivo: `app/Console/Commands/ProvisionComercioCommand.php`

## Template SQL: `database/sql/tenant_tables.sql`

- **Source of truth** para la estructura de tablas tenant
- Usa placeholder `{{PREFIX}}` que se reemplaza al ejecutar
- El comando provision también:
  - Prefixa constraint names que no tienen `{{PREFIX}}` (evita colisiones)
  - Prefixa UNIQUE KEY names sin prefijo
  - Limpia AUTO_INCREMENT para que empiecen en 1

### Cómo regenerar tenant_tables.sql
1. Lee `SHOW CREATE TABLE` para cada tabla `000001_*`
2. Reemplaza `000001_` con `{{PREFIX}}`
3. Limpia AUTO_INCREMENT
4. Lee `SHOW CREATE VIEW` para vistas, quita DEFINER
5. Escribe el resultado en `database/sql/tenant_tables.sql`

## Migraciones

### Estructura actual (baseline limpio)
```
0001_01_01_000000_create_users_table.php       → config: users, sessions, password_reset_tokens
0001_01_01_000001_create_cache_table.php        → config: cache, jobs, failed_jobs
0001_01_01_000002_create_config_tables.php      → config: comercios, comercio_user, condiciones_iva, provincias, localidades
0001_01_01_000003_create_shared_tables.php      → pymes: menu_items, permissions, permisos_funcionales
0001_01_01_000004_tenant_tables_baseline.php    → marcador: tablas tenant creadas por provision
```

### Tablas de migraciones (3 lugares)
- `config.migrations` — trackeadas por `php artisan migrate`
- `pymes.migrations` — referencia compartida
- `{PREFIX}migrations` — por comercio (ej: 000001_migrations, 000005_migrations)

### WORKFLOW: Agregar/modificar columna en tabla tenant

**Paso 1: Crear migración**
```php
// database/migrations/2026_MM_DD_HHMMSS_add_campo_to_tabla.php
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}mi_tabla`
                    ADD COLUMN `nuevo_campo` varchar(100) DEFAULT NULL
                    AFTER `campo_existente`
                ");
            } catch (\Exception $e) {
                // Si falla para un comercio, continuar con el siguiente
                continue;
            }
        }
    }
    // down() con misma lógica inversa y try/catch
};
```

**Paso 2:** `php artisan migrate` (agregar `--force` en producción)
**Paso 3:** Regenerar `tenant_tables.sql` para que nuevos comercios tengan la estructura

### WORKFLOW: Crear nueva tabla tenant
Misma estructura que modificar columna, pero con `CREATE TABLE` en lugar de `ALTER TABLE`.

### WORKFLOW: Modificar tabla compartida (pymes)
```php
Schema::connection('pymes')->table('menu_items', function (Blueprint $table) {
    $table->string('nuevo_campo')->nullable();
});
```

### WORKFLOW: Modificar tabla config
```php
Schema::table('users', function (Blueprint $table) {
    $table->string('nuevo_campo')->nullable();
});
```

## Sistema de Sucursales

### Acceso por usuario
- El acceso a sucursales es **por usuario**, NO por rol
- Se almacena en `model_has_roles.sucursal_id` (0 = acceso a TODAS las sucursales)
- Solo Super Administradores asignan sucursales a usuarios desde el componente Usuarios
- `SucursalService::getSucursalesDisponibles()` respeta las asignaciones del usuario
- Selector de sucursal visible solo si el usuario tiene 2+ sucursales

### Eventos de cambio de sucursal (sin recarga de página)
- `SucursalSelector` emite: `sucursal-changed` (con ID+nombre), `sucursal-cambiada` (solo ID), `notify`
- Cambio toma ~150ms (vs 800ms con recarga completa)
- Cache se limpia al cambiar sucursal

### Patrón obligatorio en componentes Livewire con datos por sucursal
```php
// 1. Listener
protected $listeners = ['sucursal-changed' => 'handleSucursalChanged'];

// 2. Handler: cerrar modales + resetear paginación
public function handleSucursalChanged($sucursalId = null, $sucursalNombre = null)
{
    $this->showModal = false;
    $this->resetPage();
}

// 3. En render(): siempre obtener sucursal fresca
public function render()
{
    $sucursalId = sucursal_activa();
    // ... queries filtradas por $sucursalId
}
```

- **SucursalAware trait** (`app/Traits/SucursalAware.php`) automatiza este patrón
- NO usar en componentes de catálogo global (GestionarGruposOpcionales, GestionarRecetas)

## Sistema de Cajas

- Las cajas son **opcionales** — solo necesarias para módulos de venta/cobranza
- Permisos de caja por usuario en tabla `user_cajas` (vacío = acceso a TODAS las cajas activas de la sucursal)
- `CajaService` maneja caché de 3 niveles + verificación de permisos
- `CajaAware` trait (análogo a SucursalAware) para componentes que dependen de caja
- Helper: `caja_activa()` retorna ID de caja actual
- Middleware `CajaMiddleware` auto-selecciona primera caja disponible si no hay activa
- Eventos: escuchar `caja-changed` similar a `sucursal-changed`

## Sistema de Permisos de Menú

### Cómo funciona
- Cada `MenuItem` genera un permiso con nombre `menu.{slug}` via `getPermissionName()`
- Permisos se guardan en tabla compartida `pymes.permissions`
- Asignaciones rol↔permiso en tabla tenant `{PREFIX}role_has_permissions`
- El menú se filtra en `User::getAllowedMenuItems()` y `getAllowedChildrenMenuItems()`
- Permisos se cachean por usuario (`user_permissions_{id}_{comercio}`) → limpiar con `cache:clear`

### Automatización con MenuItemObserver
- `app/Observers/MenuItemObserver.php` se ejecuta al crear/editar/eliminar MenuItem via Eloquent
- Al crear: crea permiso `menu.{slug}` y lo asigna a Super Admin + Admin en todos los tenants
- Al editar slug: actualiza nombre del permiso
- Al eliminar: elimina permiso y asignaciones
- Registrado en `AppServiceProvider::boot()`

### Comando: `php artisan menu:create`
- `app/Console/Commands/CreateMenuItem.php`
- Interactivo o con opciones: `--nombre`, `--slug`, `--parent`, `--route`, `--icono`, `--orden`

### WORKFLOW: Agregar nuevo módulo/menú
1. **Migración de menu_items** (tabla compartida `pymes.menu_items`) — CUIDADO: slug tiene UNIQUE constraint
2. **Migración de permisos** (crear en `pymes.permissions` + asignar a roles Super Admin/Admin por comercio)
3. **Actualizar ProvisionComercioCommand** (`seedRolesYPermisos()`) — para otros roles agregar al filtro
4. **Actualizar MenuItemSeeder** para que `php artisan db:seed` refleje la estructura actual

## PrecioService

- Archivo: `app/Services/PrecioService.php`
- Método principal: `calcularPrecioFinal()`
- 4 niveles de especificidad: genérico → canal → forma de pago → forma+canal
- Aplica promociones, valida límite de 70% descuento, calcula IVA
- Resultado: precio base, promociones aplicadas, descuentos, precio final con IVA
- Soporta recargo por cuotas (installments)

## CuentaEmpresaService

- Archivo: `app/Services/CuentaEmpresaService.php`
- Gestiona cuentas de empresa (bancos + billeteras digitales) con movimientos ledger
- **Vinculación forma de pago → cuenta**: Al asignar `cuenta_empresa_id` en `formas_pago`, los pagos de ventas/cobros generan automáticamente un movimiento ingreso en la cuenta
- Métodos: `registrarMovimientoAutomatico()`, `revertirMovimiento()` (contraasiento), `registrarMovimientoManual()`, `transferirEntreCuentas()`
- Integrado en: NuevaVenta (2 puntos), VentaService (cancelar + anular), CobroService (registrar + anular), TesoreriaService (depósitos)
- Saldos: `cuentas_empresa.saldo_actual` = caché, `movimientos_cuenta_empresa` = historial completo

## Roles Predefinidos
- **Super Administrador**: Acceso total, gestiona usuarios y sucursales, recibe automáticamente todos los permisos
- **Administrador**: Acceso total operativo, recibe automáticamente todos los permisos
- **Gerente**: Sin configuración de usuarios, con acceso a Stock y reportes
- **Vendedor**: Solo ventas y operaciones básicas
- **Visualizador**: Solo lectura

## Key Files
- **Models**: `app/Models/` — Stock, MovimientoStock, Articulo, GrupoOpcional, Opcional, Receta, RecetaIngrediente, Produccion, ProduccionDetalle, ProduccionIngrediente, Sucursal, Caja, Venta, Compra
- **Models Bancos**: `app/Models/` — Moneda, TipoCambio, CuentaEmpresa, MovimientoCuentaEmpresa, ConceptoMovimientoCuenta, TransferenciaCuentaEmpresa
- **Services**: `app/Services/` — VentaService, CompraService, StockService, TransferenciaStockService, OpcionalService, ProduccionService, PrecioService, CajaService, SucursalService, TenantService, CuentaEmpresaService
- **Provision**: `app/Console/Commands/ProvisionComercioCommand.php`
- **Observer**: `app/Observers/MenuItemObserver.php`
- **Traits**: `app/Traits/SucursalAware.php`, `app/Traits/CajaAware.php`
- **Livewire Articulos**: `app/Livewire/Articulos/` — GestionarArticulos, GestionarGruposOpcionales, GestionarRecetas
- **Livewire Stock**: `app/Livewire/Stock/` — StockInventario, MovimientosStock, InventarioGeneral, Produccion
- **Livewire Bancos**: `app/Livewire/Bancos/` — ResumenCuentas, GestionCuentas, MovimientosCuenta, TransferenciasCuenta
- **Livewire Ventas**: `app/Livewire/Ventas/` — NuevaVenta, ListadoVentas
- **Livewire Config**: `app/Livewire/Configuracion/` — ConfiguracionEmpresa, Usuarios, RolesPermisos, GestionMonedas
- **Routes**: `routes/web.php` — grouped con middleware `['auth', 'verified', 'tenant']`
- **Middleware**: `app/Http/Middleware/` — TenantMiddleware, CajaMiddleware
- **Translations**: `lang/{es,en,pt}.json` — ordenados alfabéticamente, usar helper `__()`

## Menu Structure

### Bancos
1. Resumen (slug: resumen-cuentas, ruta: bancos.resumen)
2. Cuentas (slug: cuentas-empresa, ruta: bancos.cuentas)
3. Movimientos (slug: movimientos-cuenta, ruta: bancos.movimientos)
4. Transferencias (slug: transferencias-cuenta, ruta: bancos.transferencias)

### Artículos
1. Listado de Artículos (slug: listado-articulos)
2. Opcionales (slug: grupos-opcionales)
3. Categorías (slug: categorias)
4. Etiquetas (slug: etiquetas)
5. Listas de Precios (slug: listas-precios)
6. Promociones (slug: promociones)
7. Formas de Pago (slug: formas-pago)

### Stock
1. Inventario (slug: inventario)
2. Movimientos (slug: movimientos-stock)
3. Inventario General (slug: inventario-general)
4. Recetas (slug: recetas) — movida desde Artículos
5. Producción (slug: produccion)

### Configuración
1. Usuarios (slug: usuarios, ruta: configuracion.usuarios)
2. Roles y Permisos (slug: roles-permisos, ruta: configuracion.roles)
3. Empresa (slug: empresa, ruta: configuracion.empresa)
4. Impresoras (slug: impresoras, ruta: configuracion.impresoras)
5. Formas de Pago (slug: formas-pago, ruta: configuracion.formas-pago)
6. Monedas (slug: monedas, ruta: configuracion.monedas)

## Cache Keys Importantes
- `menu_parent_items` — Items padre del menú (5 min TTL)
- `menu_children_{parentId}` — Hijos de un item de menú
- `user_permissions_{userId}_{comercioId}` — Permisos del usuario
- `cajas_sucursal_{sucursalId}` — Cajas disponibles por sucursal
- Limpiar todo: `php artisan optimize:clear`

## Translation Process
- Add to all 3 JSON files: es.json (key=value), en.json, pt.json
- Files must remain alphabetically sorted by key
- Use PHP script approach for bulk additions to maintain sort order

## TenantService
- Archivo: `app/Services/TenantService.php`
- `setComercio($id)` configura prefix + database en conexión pymes_tenant
- `configureConnection()` hace: Config::set + DB::purge + DB::reconnect + setTablePrefix
- En contexto CLI (artisan), no hay sesión → configurar manualmente

## Modelo Comercio — Notas
- Tabla: `config.comercios`
- Campo `cuit` es NOT NULL y UNIQUE (usar placeholder al crear: 'PROV-' . time())
- Campo email (no 'mail')
- `$fillable` del modelo incluye: nombre, mail (alias), database_name, prefijo, max_usuarios
- `getTablePrefix()` retorna `{prefijo}_`

## Comandos Artisan Útiles
- `php artisan comercio:provision` — Crear nuevo comercio completo
- `php artisan menu:create` — Crear item de menú con permisos automáticos
- `php artisan optimize:clear` — Limpiar todas las cachés
- `php artisan migrate --force` — Ejecutar migraciones en producción
- `php artisan precios:procesar-programados` — Procesar cambios de precio programados (ejecutado por scheduler cada minuto)

## Scheduler
- Configurado en `bootstrap/app.php` con `->withSchedule()`
- `precios:procesar-programados` se ejecuta cada minuto
- Requiere cron en servidor: `* * * * * cd /var/www/html/bcn && php artisan schedule:run >> /dev/null 2>&1`

## Troubleshooting
- **Menú no aparece**: Verificar permisos en `permissions` + asignación en `role_has_permissions` + `cache:clear`
- **Datos de otra sucursal**: Verificar que el componente escucha `sucursal-changed` y usa `sucursal_activa()` en render
- **Permiso denegado**: Verificar `model_has_roles` tiene la sucursal correcta para el usuario
- **Cambio de sucursal no funciona**: Verificar listener + handler que cierre modales y resetee paginación
- **Caja no seleccionada**: Verificar `user_cajas` y que haya cajas activas en la sucursal

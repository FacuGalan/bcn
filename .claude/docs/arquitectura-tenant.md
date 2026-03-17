# Arquitectura Multi-Tenant

## 3 Conexiones de Base de Datos

### config (default)
Tablas: `users`, `comercios`, `comercio_user`, `condiciones_iva`, `provincias`, `localidades`, `sessions`, `password_reset_tokens`, `cache`, `jobs`, `failed_jobs`

### pymes (compartida)
Tablas sin prefijo: `menu_items`, `permissions`, `permisos_funcionales`, `migrations`

### pymes_tenant (por comercio)
Tablas con prefijo `{NNNNNN}_` por comercio. Ej: `000001_ventas`, `000005_articulos`

## Prefijo Tenant

```php
$prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';
// Comercio ID 1 → 000001_
// Comercio ID 5 → 000005_
```

El prefijo se guarda en `config.comercios.prefijo`.

## TenantService

- Archivo: `app/Services/TenantService.php`
- `setComercio($id)` configura prefix + database en conexión pymes_tenant
- `configureConnection()`: Config::set → DB::purge → DB::reconnect → setTablePrefix
- En contexto CLI (artisan) no hay sesión → configurar manualmente

## Modelo Comercio

- Tabla: `config.comercios`
- `cuit` es NOT NULL y UNIQUE → usar placeholder `'PROV-' . time()` al crear
- Campo `email` (no 'mail'), pero `$fillable` incluye 'mail' como alias
- `getTablePrefix()` retorna `{prefijo}_`

## Modelos Tenant

Todo modelo que use tablas tenant DEBE tener:
```php
protected $connection = 'pymes_tenant';
```

## Provisioning de Comercios

### Comando
```bash
php artisan comercio:provision --nombre="Mi Comercio" --database=pymes --mail=admin@mail.com
```

### Qué hace
1. Crea comercio en `config.comercios`
2. Crea usuario admin (username: `admin{id}`, password: `admin1234`)
3. Asocia usuario ↔ comercio
4. Ejecuta `database/sql/tenant_tables.sql` con el prefijo real
5. Configura conexión pymes_tenant
6. Seed: 1 sucursal, 1 caja, 3 tipos IVA, 7 conceptos pago, 7 formas pago, 5 roles, 3 monedas, 11 conceptos movimiento cuenta
7. Asigna permisos a roles y rol Super Admin al usuario
8. Marca todas las migraciones como ejecutadas en `{PREFIX}migrations`

### Archivo: `app/Console/Commands/ProvisionComercioCommand.php`

## Template SQL

- **Source of truth**: `database/sql/tenant_tables.sql`
- Usa placeholder `{{PREFIX}}` que se reemplaza al ejecutar
- El comando provision también prefixa constraint names y UNIQUE KEY names sin prefijo
- Limpia AUTO_INCREMENT para que empiecen en 1

### Cómo regenerar tenant_tables.sql
1. Lee `SHOW CREATE TABLE` para cada tabla `000001_*`
2. Reemplaza `000001_` con `{{PREFIX}}`
3. Limpia AUTO_INCREMENT
4. Lee `SHOW CREATE VIEW` para vistas, quita DEFINER
5. Escribe en `database/sql/tenant_tables.sql`

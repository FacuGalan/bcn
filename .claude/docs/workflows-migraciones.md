# Workflows de Migraciones

## Estructura Baseline (5 migraciones)

```
0001_01_01_000000_create_users_table.php       → config: users, sessions, password_reset_tokens
0001_01_01_000001_create_cache_table.php        → config: cache, jobs, failed_jobs
0001_01_01_000002_create_config_tables.php      → config: comercios, comercio_user, condiciones_iva, provincias, localidades
0001_01_01_000003_create_shared_tables.php      → pymes: menu_items, permissions, permisos_funcionales
0001_01_01_000004_tenant_tables_baseline.php    → marcador: tablas tenant creadas por provision
```

## Tablas de Migraciones (3 lugares)

- `config.migrations` — trackeadas por `php artisan migrate`
- `pymes.migrations` — referencia compartida
- `{PREFIX}migrations` — por comercio (ej: `000001_migrations`)

---

## WORKFLOW: Agregar/modificar columna en tabla tenant

### Paso 1: Crear migración

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
                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}mi_tabla`
                    DROP COLUMN `nuevo_campo`
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
```

### Paso 2: Ejecutar
```bash
php artisan migrate
```

### Paso 3: Regenerar tenant_tables.sql
Asegura que nuevos comercios (provisioning) tengan la estructura actualizada.

---

## WORKFLOW: Crear nueva tabla tenant

Misma estructura que modificar columna, pero con `CREATE TABLE`:

```php
foreach ($comercios as $comercio) {
    $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';
    try {
        DB::connection('pymes')->statement("
            CREATE TABLE `{$prefix}nueva_tabla` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `nombre` varchar(255) NOT NULL,
                `sucursal_id` bigint unsigned NOT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (\Exception $e) {
        continue;
    }
}
```

Paso 2-3: Ejecutar + regenerar tenant_tables.sql

---

## WORKFLOW: Modificar tabla compartida (pymes)

```php
Schema::connection('pymes')->table('menu_items', function (Blueprint $table) {
    $table->string('nuevo_campo')->nullable();
});
```

---

## WORKFLOW: Modificar tabla config

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('nuevo_campo')->nullable();
});
```

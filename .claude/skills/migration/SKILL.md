---
name: migration
description: Crear migración siguiendo convenciones multi-tenant del proyecto. Soporta tablas tenant, compartidas y config.
user-invocable: true
argument-hint: "[descripción del cambio]"
---

# Migration — Crear Migración

Tu trabajo es crear migraciones siguiendo las convenciones multi-tenant de BCN Pymes.

## Al ejecutar este skill:

### 1. Determinar tipo de migración

Preguntar al usuario (o inferir del argumento):
- **A) Agregar/modificar columna en tabla tenant** — La más común
- **B) Crear nueva tabla tenant**
- **C) Modificar tabla compartida (pymes)**
- **D) Modificar tabla config**

### 2. Pedir detalles
- Tabla(s) afectada(s)
- Columna(s): nombre, tipo, default, nullable, posición (AFTER)
- Foreign keys si aplica
- Índices si aplica

### 3. Leer template
- Leer `.claude/docs/workflows-migraciones.md` para el template correcto según el tipo

### 4. Generar migración

**Nombre del archivo**: `database/migrations/{YYYY}_{MM}_{DD}_{HHMMSS}_{descripcion}.php`

Usar la fecha/hora actual para el timestamp.

**Para tipo A y B (tenant)**:
```php
$comercios = DB::connection('config')->table('comercios')->get();
foreach ($comercios as $comercio) {
    $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT) . '_';
    try {
        DB::connection('pymes')->statement("SQL CON {$prefix}");
    } catch (\Exception $e) {
        continue;
    }
}
```

**Para tipo C (pymes)**:
```php
Schema::connection('pymes')->table('tabla', function (Blueprint $table) { ... });
```

**Para tipo D (config)**:
```php
Schema::table('tabla', function (Blueprint $table) { ... });
```

### 5. Recordar post-pasos
- Informar: `php artisan migrate` para ejecutar
- Si es tenant: recordar regenerar `database/sql/tenant_tables.sql`
- Si se modificó modelo: verificar `$fillable` y relaciones

## Reglas
- SIEMPRE incluir `down()` con la operación inversa
- SIEMPRE usar try/catch para migraciones tenant
- NUNCA usar Schema builder para tablas tenant (usar SQL raw con prefijo)
- Para tablas tenant, usar `DB::connection('pymes')` (no pymes_tenant)
- Al crear/modificar tablas, actualizar `docs/ai-knowledge-base.md` sección "Modelo de Datos" con las columnas, tipos y relaciones

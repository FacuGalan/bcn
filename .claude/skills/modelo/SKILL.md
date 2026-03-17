---
name: modelo
description: Crear modelo Eloquent siguiendo los patrones del proyecto (conexión tenant, scopes, casts, relaciones).
user-invocable: true
argument-hint: "[nombre-modelo]"
---

# Modelo — Crear Modelo Eloquent con Patrones del Proyecto

Tu trabajo es crear un Model en `app/Models/` siguiendo los patrones establecidos en BCN Pymes.

## Antes de generar:

1. Leer un modelo existente similar:
   - Simple: `app/Models/Categoria.php`
   - Con relaciones: `app/Models/Articulo.php`
   - Con morph: `app/Models/Receta.php`

## Patrón obligatorio para modelos tenant

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Si aplica
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NombreModelo extends Model
{
    use SoftDeletes; // Solo si la tabla tiene deleted_at

    protected $connection = 'pymes_tenant'; // OBLIGATORIO para tenant
    protected $table = 'nombre_tabla';       // Sin prefijo, se agrega automáticamente

    protected $fillable = [
        'campo1',
        'campo2',
        'sucursal_id', // Si es sucursal-aware
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'cantidad' => 'decimal:2',
        'activo' => 'boolean',
        'fecha' => 'datetime',
    ];

    // ==================
    // RELACIONES
    // ==================

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class)->withTrashed();
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleModelo::class);
    }

    // ==================
    // SCOPES
    // ==================

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================
    // HELPERS
    // ==================

    public function estaActivo(): bool
    {
        return $this->activo === true;
    }
}
```

## Reglas

### Conexión
- **Modelos tenant**: `protected $connection = 'pymes_tenant'` — SIEMPRE
- **Modelos config**: NO definir `$connection` (usa default)
- **Modelos pymes compartidas**: `protected $connection = 'pymes'`

### Tabla
- Nombre SIN prefijo: `protected $table = 'articulos'` (el prefijo se agrega automáticamente por TenantService)

### Fillable
- Listar TODOS los campos que se pueden asignar masivamente
- Incluir `sucursal_id` si es sucursal-aware
- NO incluir `id`, `created_at`, `updated_at`, `deleted_at`

### Casts
- Decimales: `'decimal:2'` para precios y cantidades
- Booleanos: `'boolean'` para campos activo/inactivo
- Fechas: `'datetime'` para campos fecha que no sean created_at/updated_at

### Relaciones
- Usar type hints de retorno: `: BelongsTo`, `: HasMany`, etc.
- `withTrashed()` en BelongsTo si el modelo relacionado usa SoftDeletes
- `withPivot()` y `withTimestamps()` en BelongsToMany si la tabla pivot tiene datos
- Para polimórficas: verificar que el tipo esté en morphMap de AppServiceProvider

### Scopes
- Nombres en español, camelCase: `scopeActivos`, `scopePorSucursal`, `scopeBajoMinimo`
- Retornar `$query` para encadenar
- Scope para `sucursal_id` si es sucursal-aware

### SoftDeletes
- Usar SOLO si la tabla tiene columna `deleted_at`
- Agregar `->withTrashed()` en relaciones que apunten a este modelo

### Helpers
- Métodos de consulta: `tieneStock()`, `estaBajoMinimo()`
- Retornan bool, string o valor simple
- NO contienen lógica de negocio compleja (eso va en Services)

---
name: service
description: Crear service PHP siguiendo los patrones del proyecto (transacciones, logging, excepciones, conexión tenant).
user-invocable: true
argument-hint: "[nombre-service]"
---

# Service — Crear Service con Patrones del Proyecto

Tu trabajo es crear un Service en `app/Services/` siguiendo los patrones establecidos en BCN Pymes.

## Antes de generar:

1. Leer un service existente como referencia:
   - Simple: `app/Services/StockService.php`
   - Complejo: `app/Services/VentaService.php`
   - Con cuentas: `app/Services/CuentaEmpresaService.php`

## Patrón obligatorio

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class NombreService
{
    /**
     * Descripción breve del método.
     */
    public function metodoPublico(array $data): Model
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // 1. Validar precondiciones
            $this->validarAlgo($data);

            // 2. Ejecutar lógica de negocio
            $resultado = Modelo::create([...]);

            // 3. Operaciones relacionadas
            $this->operacionRelacionada($resultado);

            DB::connection('pymes_tenant')->commit();

            Log::info('Operación exitosa', [
                'modelo_id' => $resultado->id,
                'accion' => 'crear',
            ]);

            return $resultado->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            Log::error('Error en operación', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Métodos privados para lógica interna.
     */
    private function validarAlgo(array $data): void
    {
        if (/* condición inválida */) {
            throw new Exception(__('Mensaje de error para el usuario'));
        }
    }
}
```

## Reglas

### Transacciones
- **SIEMPRE** usar `DB::connection('pymes_tenant')->beginTransaction()` para operaciones que modifican datos
- **SIEMPRE** commit explícito + rollback en catch
- **NUNCA** `DB::transaction()` sin especificar conexión (usaría config por defecto)

### Logging
- `Log::info()` después de operaciones exitosas con datos relevantes (IDs, acción)
- `Log::error()` en catch con mensaje de error y datos de contexto
- NO loggear datos sensibles (passwords, tokens)

### Excepciones
- Throw `Exception` con mensaje traducible: `throw new Exception(__('Mensaje'))`
- Livewire captura la excepción y la muestra al usuario
- NO usar return false/null para errores — usar excepciones

### Métodos
- **Públicos**: acciones de negocio (crear, actualizar, cancelar, anular)
- **Privados**: validaciones, operaciones internas
- Nombres en español: `crearVenta()`, `cancelarCompra()`, `ajustarStock()`
- Retornar modelo fresh: `return $modelo->fresh()`

### Conexión
- Para queries tenant: `DB::connection('pymes_tenant')`
- Para queries config: `DB::connection('config')`
- Para queries pymes compartidas: `DB::connection('pymes')`

### Patrón ledger (si aplica)
Para operaciones con movimientos (stock, cuenta corriente, cuenta empresa):
```php
// Crear movimiento (nunca editar/eliminar)
MovimientoStock::create([
    'tipo' => 'ingreso',
    'cantidad' => $cantidad,
    'estado' => 'activo',
    // ...
]);

// Anular = crear contraasiento
MovimientoStock::create([
    'tipo' => 'egreso',          // Opuesto al original
    'cantidad' => $cantidad,      // Misma cantidad
    'estado' => 'activo',         // También activo
    'referencia_id' => $originalId,
    // ...
]);

// Actualizar caché
Stock::where(...)->update(['stock_actual' => DB::raw('stock_actual + ' . $cantidad)]);
```

### Documentación
- Al crear un service nuevo, actualizar `docs/ai-knowledge-base.md` sección "Lógica de Negocio" con los métodos públicos, reglas de negocio y flujos del nuevo service

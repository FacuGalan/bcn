---
name: concepto-pago-interno
description: Crear un ConceptoPago + FormaPago semilla para uso interno del sistema (ej: Canje Puntos, Crédito interno, Rebote por anulación). La FP queda con solo_sistema=true para no aparecer en el selector de cobro pero sí en reportes por concepto/forma de pago.
user-invocable: true
argument-hint: "[nombre del nuevo concepto, ej: 'Crédito interno']"
---

# Concepto-Pago-Interno — Crear concepto + FP semilla solo del sistema

Tu trabajo es crear un **ConceptoPago** + una **FormaPago** semilla con `solo_sistema=true` siguiendo el patrón establecido en el PR del Repaso 1 (commit que introdujo "Canje Puntos"). Estas formas de pago son las que el sistema usa internamente al persistir `VentaPago`s pero **NO** deben aparecer en el selector de cobro al cajero.

## Cuándo usar este skill

Usar cuando se necesita una "forma de pago" que:
- El cajero no debe poder elegir manualmente al cobrar.
- El sistema usa al crear `VentaPago` para que los reportes agrupen correctamente.
- Tiene que existir como semilla en TODOS los comercios (existentes y nuevos).

Ejemplos donde aplica:
- **Canje Puntos** (ya implementado): pagos hechos con puntos del programa de fidelización.
- **Devolución / Rebote**: si en algún momento se persiste el monto devuelto en una anulación.
- **Crédito interno**: notas de crédito aplicadas como pago.
- **Compensación**: ajustes contables que se persisten como pago.

NO usar para formas de pago que el cajero sí debe elegir (efectivo, tarjeta, etc.) — esas se agregan al seeder normal de `ProvisionComercioCommand`.

## Al ejecutar este skill

### 1. Pedir datos al usuario

Preguntar (o inferir del argument-hint):
- **Nombre legible** (ej: "Canje Puntos", "Crédito Interno")
- **Código** del concepto (snake_case, ej: `canje_puntos`, `credito_interno`)
- **Código** de la FP (UPPER_SNAKE, ej: `CANJE_PUNTOS`, `CREDITO_INTERNO`)
- **Descripción breve** del concepto

Validar:
- El código del concepto NO debe colisionar con los existentes: `efectivo`, `tarjeta_debito`, `tarjeta_credito`, `transferencia`, `wallet`, `cheque`, `credito_cliente`, `canje_puntos`.
- El código de la FP NO debe colisionar con: `EFEC`, `TDEB`, `TCRE`, `TRAN`, `MPAG`, `CTACTE`, `MIXTO`, `CANJE_PUNTOS`.

### 2. Crear migración tenant

Generar archivo en `database/migrations/{timestamp}_add_{codigo_concepto}_fp_sistema.php` siguiendo este template (basado en `2026_05_07_140000_add_canje_puntos_fp_y_articulos_canjeados_monto.php`):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea ConceptoPago "{NOMBRE}" + FormaPago "{NOMBRE}" (solo_sistema=true)
 * en cada comercio existente.
 *
 * Para nuevos comercios el provisioning lo replica automáticamente desde
 * `ProvisionComercioCommand`. Mantener sincronizados.
 *
 * Forma de pago interna: NO aparece en el selector del cajero
 * (CatalogoCache::formasPago la excluye por solo_sistema=true).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                // 1. Concepto
                $existeConcepto = DB::connection('pymes')->table("{$prefix}conceptos_pago")
                    ->where('codigo', '{CODIGO_CONCEPTO}')
                    ->exists();
                if (! $existeConcepto) {
                    $maxOrden = DB::connection('pymes')->table("{$prefix}conceptos_pago")->max('orden') ?? 0;
                    DB::connection('pymes')->table("{$prefix}conceptos_pago")->insert([
                        'codigo' => '{CODIGO_CONCEPTO}',
                        'nombre' => '{NOMBRE}',
                        'descripcion' => '{DESCRIPCION}',
                        'permite_cuotas' => false,
                        'permite_vuelto' => false,
                        'activo' => true,
                        'orden' => $maxOrden + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $conceptoId = DB::connection('pymes')->table("{$prefix}conceptos_pago")
                    ->where('codigo', '{CODIGO_CONCEPTO}')
                    ->value('id');

                // 2. FormaPago solo_sistema
                $existeFp = DB::connection('pymes')->table("{$prefix}formas_pago")
                    ->where('codigo', '{CODIGO_FP}')
                    ->exists();
                if (! $existeFp && $conceptoId) {
                    $maxOrden = DB::connection('pymes')->table("{$prefix}formas_pago")->max('orden') ?? 0;
                    DB::connection('pymes')->table("{$prefix}formas_pago")->insert([
                        'nombre' => '{NOMBRE}',
                        'codigo' => '{CODIGO_FP}',
                        'concepto_pago_id' => $conceptoId,
                        // 'concepto' es ENUM legacy con valores fijos; usar 'otro'
                        // para casos nuevos. La trazabilidad real va por concepto_pago_id.
                        'concepto' => 'otro',
                        'permite_cuotas' => false,
                        'es_mixta' => false,
                        'activo' => true,
                        'solo_sistema' => true,
                        'orden' => $maxOrden + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                \Log::warning("Migración {CODIGO_CONCEPTO} fp_sistema falló para comercio {$comercio->id}: ".$e->getMessage());

                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();
        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            try {
                DB::connection('pymes')->table("{$prefix}formas_pago")
                    ->where('codigo', '{CODIGO_FP}')->delete();
                DB::connection('pymes')->table("{$prefix}conceptos_pago")
                    ->where('codigo', '{CODIGO_CONCEPTO}')->delete();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};
```

Reemplazar todos los `{...}` por los valores ingresados.

### 3. Actualizar `ProvisionComercioCommand`

Para que **nuevos comercios** también nazcan con esta FP/concepto. Editar `app/Console/Commands/ProvisionComercioCommand.php`:

**A. En el array `$conceptos`** (alrededor de línea 352), agregar al final:
```php
['codigo' => '{CODIGO_CONCEPTO}', 'nombre' => '{NOMBRE}', 'permite_cuotas' => false, 'permite_vuelto' => false, 'orden' => N],
```
(N = siguiente disponible)

**B. En el array `$formasPago`** (alrededor de línea 374), agregar al final:
```php
[
    'nombre' => '{NOMBRE}',
    'codigo' => '{CODIGO_FP}',
    'concepto_pago_id' => $conceptoIds['{CODIGO_CONCEPTO}'],
    'concepto' => 'otro',
    'permite_cuotas' => false,
    'es_mixta' => false,
    'solo_sistema' => true,
    'orden' => M,
],
```
(M = siguiente disponible)

### 4. Recordar al usuario los siguientes pasos manuales

Tras correr el skill, el usuario tiene que hacer:

1. **Aplicar migración**: `php artisan migrate`
2. **Verificar**: `SELECT * FROM 000001_formas_pago WHERE codigo = '{CODIGO_FP}'` debería mostrar `solo_sistema = 1`.
3. **Verificar selector**: la nueva FP NO debe aparecer en el selector de cobro de NuevaVenta.
4. **Usar la FP en el código**: el sistema tiene que decidir CUÁNDO crear `VentaPago` con esta FP. Buscar en `WithPagosDesglose` un patrón similar a:
   ```php
   $idFp{NOMBRE} = FormaPago::where('codigo', '{CODIGO_FP}')->value('id');
   VentaPago::create([
       'forma_pago_id' => $idFp{NOMBRE} ?? $this->formaPagoId,
       'es_pago_puntos' => true,  // o equivalente para tu caso
       ...
   ]);
   ```
5. **Regenerar** `database/sql/tenant_tables.sql` si la migración tocó schema (este patrón NO toca schema, solo seeders).
6. **Tests**: agregar test que verifique que la FP existe y NO aparece en `CatalogoCache::formasPago()`.

## Reglas

- SIEMPRE `solo_sistema = true` en estas FP.
- SIEMPRE `concepto = 'otro'` (el ENUM legacy no tiene los códigos nuevos).
- SIEMPRE iterar comercios en la migración (try/catch por comercio).
- SIEMPRE agregar al `ProvisionComercioCommand` para nuevos comercios.
- NUNCA agregar la FP al selector de cobro (CatalogoCache la filtra automáticamente por `solo_sistema=true`).
- Cuando los reportes lo necesiten, agrupar por `forma_pago_id` resolverá automático bajo el nombre de la FP nueva.

## Cómo se relaciona con el resto del sistema

- **`CatalogoCache::formasPago()`**: filtra `solo_sistema = false` → la FP nueva NO aparece en selector.
- **`ProvisionComercioCommand`**: provisioning de comercios nuevos los crea automáticamente.
- **Reportes por forma de pago**: SUM por `forma_pago_id` los agrupa correctamente.
- **Reportes por concepto**: SUM por `concepto_pago_id` los agrupa correctamente.
- **Caja real**: `VentaPago.afecta_caja=false` para que NO afecte el cierre de caja del cajero.

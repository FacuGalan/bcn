<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vínculo CuentaEmpresa ↔ Integraciones de Pago (Fase 1): concepto de ledger
 * propio para los cobros por integración.
 *
 * Concepto `cobro_integracion` (ingreso, de sistema): lo usa
 * CobroIntegracionService al registrar el MovimientoCuentaEmpresa de un cobro
 * confirmado por integración (RF-04). Concepto separado de `venta`/`cobro`
 * para que la conciliación (Paso 3) pueda filtrar estos movimientos y
 * matchearlos contra el reporte del proveedor.
 *
 * Insert idempotente por código. El seed para comercios nuevos se agrega en
 * ProvisionComercioCommand::seedConceptosMovimientoCuenta().
 *
 * Ref: .claude/specs/vinculo-cuenta-empresa-integraciones.md (Fase 1, D9).
 */
return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();
        $now = now();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $existe = DB::connection('pymes')
                    ->table("{$prefix}conceptos_movimiento_cuenta")
                    ->where('codigo', 'cobro_integracion')
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::connection('pymes')->table("{$prefix}conceptos_movimiento_cuenta")->insert([
                    'codigo' => 'cobro_integracion',
                    'nombre' => 'Cobro por integración de pago',
                    'tipo' => 'ingreso',
                    'es_sistema' => true,
                    'orden' => 12,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Exception $e) {
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
                DB::connection('pymes')
                    ->table("{$prefix}conceptos_movimiento_cuenta")
                    ->where('codigo', 'cobro_integracion')
                    ->delete();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

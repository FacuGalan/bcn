<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pago online en tienda — Checkout Pro (Fase 1): concepto de ledger propio
 * para la propina online (RF-T83).
 *
 * Concepto `propina_online` (ingreso, de sistema): al acreditarse un checkout
 * con propina, la CuentaEmpresa registra DOS movimientos — el cobro del pedido
 * (concepto `cobro_integracion`, como siempre) y la propina con este concepto
 * separado. Así la propina nunca se mezcla con la venta y el módulo futuro de
 * rendición al repartidor filtra por concepto.
 *
 * Insert idempotente por código. El seed para comercios nuevos se agrega en
 * ProvisionComercioCommand::seedConceptosMovimientoCuenta().
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (Fase 1, RF-T83).
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
                    ->where('codigo', 'propina_online')
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::connection('pymes')->table("{$prefix}conceptos_movimiento_cuenta")->insert([
                    'codigo' => 'propina_online',
                    'nombre' => 'Propina online',
                    'tipo' => 'ingreso',
                    'es_sistema' => true,
                    'orden' => 19,
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
                    ->where('codigo', 'propina_online')
                    ->delete();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

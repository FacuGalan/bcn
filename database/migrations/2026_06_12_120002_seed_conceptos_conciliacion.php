<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación de cuenta (Fase 1): conceptos de ledger para los movimientos
 * que genera la conciliación contra el proveedor de pago.
 *
 * Conceptos separados por naturaleza (mismo razonamiento que D9 del Paso 2):
 * habilitan reportes/filtros finos por concepto.
 * - comision_integracion (egreso): comisión del proveedor por cada cobro.
 * - retiro_integracion (egreso): retiro a banco desde el proveedor.
 * - devolucion_integracion (egreso): devolución/contracargo en el proveedor.
 * - acreditacion_integracion (ingreso): rendiciones, transferencias recibidas,
 *   cobros por fuera del sistema.
 * - ajuste_conciliacion (ambos): ajuste inicial (cierre de D11) y residuales.
 *
 * Insert idempotente por código. El seed para comercios nuevos se agrega en
 * ProvisionComercioCommand.
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 1).
 */
return new class extends Migration
{
    private const CONCEPTOS = [
        ['codigo' => 'comision_integracion', 'nombre' => 'Comisión del proveedor de pago', 'tipo' => 'egreso', 'orden' => 13],
        ['codigo' => 'retiro_integracion', 'nombre' => 'Retiro a banco desde el proveedor', 'tipo' => 'egreso', 'orden' => 14],
        ['codigo' => 'devolucion_integracion', 'nombre' => 'Devolución/contracargo en el proveedor', 'tipo' => 'egreso', 'orden' => 15],
        ['codigo' => 'acreditacion_integracion', 'nombre' => 'Acreditación en el proveedor de pago', 'tipo' => 'ingreso', 'orden' => 16],
        ['codigo' => 'ajuste_conciliacion', 'nombre' => 'Ajuste por conciliación', 'tipo' => 'ambos', 'orden' => 17],
    ];

    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();
        $now = now();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            foreach (self::CONCEPTOS as $concepto) {
                try {
                    $existe = DB::connection('pymes')
                        ->table("{$prefix}conceptos_movimiento_cuenta")
                        ->where('codigo', $concepto['codigo'])
                        ->exists();

                    if ($existe) {
                        continue;
                    }

                    DB::connection('pymes')->table("{$prefix}conceptos_movimiento_cuenta")->insert([
                        'codigo' => $concepto['codigo'],
                        'nombre' => $concepto['nombre'],
                        'tipo' => $concepto['tipo'],
                        'es_sistema' => true,
                        'orden' => $concepto['orden'],
                        'activo' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();
        $codigos = array_column(self::CONCEPTOS, 'codigo');

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')
                    ->table("{$prefix}conceptos_movimiento_cuenta")
                    ->whereIn('codigo', $codigos)
                    ->delete();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

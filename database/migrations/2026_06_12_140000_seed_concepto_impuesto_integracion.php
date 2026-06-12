<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación de cuenta: concepto de ledger para impuestos y retenciones
 * que el proveedor de pago descuenta (percepciones/retenciones IIBB, IVA,
 * etc.). Lo usan las filas tipo `impuesto` de la conciliación: tanto las
 * filas tax_* del reporte como el residuo bruto - comisión - neto de un
 * cobro matcheado.
 *
 * El tratamiento impositivo COMPLETO (cálculo según condición de IVA por
 * CUIT) es un feature aparte; este concepto registra lo que el proveedor
 * ya descontó para que el saldo de la cuenta converja.
 *
 * Insert idempotente por código. El seed para comercios nuevos se agrega en
 * ProvisionComercioCommand.
 */
return new class extends Migration
{
    private const CONCEPTO = [
        'codigo' => 'impuesto_integracion',
        'nombre' => 'Impuestos y retenciones del proveedor de pago',
        'tipo' => 'egreso',
        'orden' => 18,
    ];

    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();
        $now = now();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $existe = DB::connection('pymes')
                    ->table("{$prefix}conceptos_movimiento_cuenta")
                    ->where('codigo', self::CONCEPTO['codigo'])
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::connection('pymes')->table("{$prefix}conceptos_movimiento_cuenta")->insert([
                    'codigo' => self::CONCEPTO['codigo'],
                    'nombre' => self::CONCEPTO['nombre'],
                    'tipo' => self::CONCEPTO['tipo'],
                    'es_sistema' => true,
                    'orden' => self::CONCEPTO['orden'],
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
                    ->where('codigo', self::CONCEPTO['codigo'])
                    ->delete();
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

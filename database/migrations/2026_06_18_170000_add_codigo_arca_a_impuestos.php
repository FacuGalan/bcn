<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 5b — agrega `codigo_arca` (código de tributo del WS de ARCA,
 * FEParamGetTiposTributos) al catálogo de impuestos tenant. Hoy ese código solo
 * vive en `comprobante_fiscal_tributos`; tenerlo en el catálogo evita hardcodear
 * el mapeo tributo→código al armar el array `Tributos[]` para AFIP.
 *
 * Códigos confirmados en homologación (ver comando `arca:tipos-tributos`):
 *   6 = Percepción de IVA, 7 = Percepción de IIBB, 99 = Otros.
 * Las retenciones / IVA débito-crédito NO viajan en el comprobante (son sufridos
 * o aditivos), por eso quedan en NULL.
 */
return new class extends Migration
{
    /**
     * Resuelve el codigo_arca a partir del `codigo` del catálogo de impuestos.
     * Fuente única del mapeo: la consume esta migración (comercios existentes) y
     * ProvisionComercioCommand::seedImpuestos (comercios nuevos). NULL = no viaja
     * en el comprobante (retenciones / IVA débito-crédito).
     */
    public static function codigoArcaPara(string $codigo): ?int
    {
        return match (true) {
            $codigo === 'perc_iva' => 6,
            str_starts_with($codigo, 'perc_iibb_') => 7,
            $codigo === 'otro' => 99,
            default => null,
        };
    }

    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                $tabla = "{$prefix}impuestos";

                $existe = DB::connection('pymes')->select(
                    "SHOW COLUMNS FROM `{$tabla}` LIKE 'codigo_arca'"
                );

                if (empty($existe)) {
                    DB::connection('pymes')->statement(
                        "ALTER TABLE `{$tabla}` ADD COLUMN `codigo_arca` smallint DEFAULT NULL COMMENT 'Código de tributo del WS de ARCA (FEParamGetTiposTributos)' AFTER `jurisdiccion`"
                    );
                }

                // Percepción de IVA → 6, percepciones de IIBB → 7, otro → 99.
                DB::connection('pymes')->table($tabla)->where('codigo', 'perc_iva')->update(['codigo_arca' => 6]);
                DB::connection('pymes')->table($tabla)->where('codigo', 'like', 'perc_iibb_%')->update(['codigo_arca' => 7]);
                DB::connection('pymes')->table($tabla)->where('codigo', 'otro')->update(['codigo_arca' => 99]);
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
                DB::connection('pymes')->statement(
                    "ALTER TABLE `{$prefix}impuestos` DROP COLUMN `codigo_arca`"
                );
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

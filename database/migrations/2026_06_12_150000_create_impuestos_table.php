<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sistema impositivo (Fase 1): catálogo de impuestos argentinos.
 *
 * Tabla descriptiva seeded por el sistema (RF-01): IVA débito/crédito,
 * percepciones/retenciones de IVA, IIBB por jurisdicción (ISO 3166-2),
 * ganancias, ley 25.413, SIRCREB. La config por CUIT decide si el comercio
 * está alcanzado (cuit_impuesto_configs). Extensible con impuestos custom
 * del comercio (es_sistema=false).
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-01, Fase 1).
 */
return new class extends Migration
{
    private const JURISDICCIONES = [
        'AR-C' => 'CABA',
        'AR-B' => 'Buenos Aires',
        'AR-K' => 'Catamarca',
        'AR-H' => 'Chaco',
        'AR-U' => 'Chubut',
        'AR-X' => 'Córdoba',
        'AR-W' => 'Corrientes',
        'AR-E' => 'Entre Ríos',
        'AR-P' => 'Formosa',
        'AR-Y' => 'Jujuy',
        'AR-L' => 'La Pampa',
        'AR-F' => 'La Rioja',
        'AR-M' => 'Mendoza',
        'AR-N' => 'Misiones',
        'AR-Q' => 'Neuquén',
        'AR-R' => 'Río Negro',
        'AR-A' => 'Salta',
        'AR-J' => 'San Juan',
        'AR-D' => 'San Luis',
        'AR-Z' => 'Santa Cruz',
        'AR-S' => 'Santa Fe',
        'AR-G' => 'Santiago del Estero',
        'AR-V' => 'Tierra del Fuego',
        'AR-T' => 'Tucumán',
    ];

    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    CREATE TABLE IF NOT EXISTS `{$prefix}impuestos` (
                        `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        `codigo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `tipo` enum('iva','iibb','ganancias','credito_debito','otro') COLLATE utf8mb4_unicode_ci NOT NULL,
                        `naturaleza_default` enum('percepcion','retencion','debito_fiscal','credito_fiscal','tributo') COLLATE utf8mb4_unicode_ci NOT NULL,
                        `jurisdiccion` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'AR nacional o ISO 3166-2 provincial (AR-C, AR-B...)',
                        `es_sistema` tinyint(1) NOT NULL DEFAULT '1',
                        `activo` tinyint(1) NOT NULL DEFAULT '1',
                        `created_at` timestamp NULL DEFAULT NULL,
                        `updated_at` timestamp NULL DEFAULT NULL,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `{$prefix}uq_impuestos_codigo` (`codigo`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");

                $this->seedCatalogo($prefix);
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
                DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$prefix}impuestos`");
            } catch (\Exception $e) {
                continue;
            }
        }
    }

    private function seedCatalogo(string $prefix): void
    {
        $now = now();
        $filas = [];

        foreach (self::catalogo() as $impuesto) {
            $filas[] = $impuesto + ['es_sistema' => 1, 'activo' => 1, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach ($filas as $fila) {
            $existe = DB::connection('pymes')
                ->table("{$prefix}impuestos")
                ->where('codigo', $fila['codigo'])
                ->exists();

            if (! $existe) {
                DB::connection('pymes')->table("{$prefix}impuestos")->insert($fila);
            }
        }
    }

    /**
     * Catálogo completo (~56 impuestos). Compartido con ProvisionComercioCommand
     * vía duplicación deliberada: las migraciones deben ser inmutables.
     */
    public static function catalogo(): array
    {
        $impuestos = [
            ['codigo' => 'iva_debito', 'nombre' => 'IVA Débito Fiscal', 'tipo' => 'iva', 'naturaleza_default' => 'debito_fiscal', 'jurisdiccion' => 'AR'],
            ['codigo' => 'iva_credito', 'nombre' => 'IVA Crédito Fiscal', 'tipo' => 'iva', 'naturaleza_default' => 'credito_fiscal', 'jurisdiccion' => 'AR'],
            ['codigo' => 'perc_iva', 'nombre' => 'Percepción de IVA', 'tipo' => 'iva', 'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR'],
            ['codigo' => 'ret_iva', 'nombre' => 'Retención de IVA', 'tipo' => 'iva', 'naturaleza_default' => 'retencion', 'jurisdiccion' => 'AR'],
            ['codigo' => 'ret_ganancias', 'nombre' => 'Retención de Ganancias', 'tipo' => 'ganancias', 'naturaleza_default' => 'retencion', 'jurisdiccion' => 'AR'],
            ['codigo' => 'imp_creditos_debitos', 'nombre' => 'Impuesto a los Créditos y Débitos (Ley 25.413)', 'tipo' => 'credito_debito', 'naturaleza_default' => 'tributo', 'jurisdiccion' => 'AR'],
            ['codigo' => 'ret_sircreb', 'nombre' => 'Retención SIRCREB (IIBB sobre acreditaciones)', 'tipo' => 'iibb', 'naturaleza_default' => 'retencion', 'jurisdiccion' => 'AR'],
            ['codigo' => 'otro', 'nombre' => 'Otro impuesto/tributo', 'tipo' => 'otro', 'naturaleza_default' => 'tributo', 'jurisdiccion' => null],
        ];

        foreach (self::JURISDICCIONES as $iso => $nombre) {
            $slug = strtolower(str_replace('-', '_', $iso));
            $impuestos[] = ['codigo' => "perc_iibb_{$slug}", 'nombre' => "Percepción IIBB {$nombre}", 'tipo' => 'iibb', 'naturaleza_default' => 'percepcion', 'jurisdiccion' => $iso];
            $impuestos[] = ['codigo' => "ret_iibb_{$slug}", 'nombre' => "Retención IIBB {$nombre}", 'tipo' => 'iibb', 'naturaleza_default' => 'retencion', 'jurisdiccion' => $iso];
        }

        return $impuestos;
    }
};

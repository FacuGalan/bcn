<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        $columnas = [
            'cliente_nombre_snapshot' => [
                'definition' => "varchar(255) DEFAULT NULL COMMENT 'Snapshot del nombre del cliente al momento de la venta'",
                'after' => 'cliente_id',
            ],
            'cliente_cuit_snapshot' => [
                'definition' => "varchar(20) DEFAULT NULL COMMENT 'Snapshot del CUIT del cliente al momento de la venta'",
                'after' => 'cliente_nombre_snapshot',
            ],
            'cliente_condicion_iva_snapshot' => [
                'definition' => "varchar(100) DEFAULT NULL COMMENT 'Snapshot de la condición IVA del cliente al momento de la venta'",
                'after' => 'cliente_cuit_snapshot',
            ],
            'cupon_codigo_snapshot' => [
                'definition' => "varchar(50) DEFAULT NULL COMMENT 'Snapshot del código del cupón al momento de la venta'",
                'after' => 'cupon_id',
            ],
            'cupon_descripcion_snapshot' => [
                'definition' => "varchar(500) DEFAULT NULL COMMENT 'Snapshot de la descripción del cupón al momento de la venta'",
                'after' => 'cupon_codigo_snapshot',
            ],
        ];

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas";

            foreach ($columnas as $columna => $config) {
                try {
                    $colExists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$tabla}'
                        AND COLUMN_NAME = '{$columna}'
                    ");

                    if (empty($colExists)) {
                        $definition = $config['definition'];
                        $after = $config['after'];
                        DB::connection('pymes')->statement("
                            ALTER TABLE `{$tabla}`
                            ADD COLUMN `{$columna}` {$definition}
                            AFTER `{$after}`
                        ");
                    }
                } catch (\Exception $e) {
                    \Log::warning("Migración add_snapshots_cliente_cupon falló para comercio {$comercio->id} columna {$columna}: ".$e->getMessage());

                    continue;
                }
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        $columnas = [
            'cupon_descripcion_snapshot',
            'cupon_codigo_snapshot',
            'cliente_condicion_iva_snapshot',
            'cliente_cuit_snapshot',
            'cliente_nombre_snapshot',
        ];

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas";

            foreach ($columnas as $columna) {
                try {
                    $colExists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$tabla}'
                        AND COLUMN_NAME = '{$columna}'
                    ");

                    if (! empty($colExists)) {
                        DB::connection('pymes')->statement("
                            ALTER TABLE `{$tabla}` DROP COLUMN `{$columna}`
                        ");
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas_detalle";

            try {
                // 1. Soltar la FK actual sobre articulo_id (si existe)
                $fkName = "{$prefix}ventas_detalle_articulo_id_foreign";
                $fkExists = DB::connection('pymes')->select("
                    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND CONSTRAINT_NAME = '{$fkName}'
                ");

                if (! empty($fkExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}` DROP FOREIGN KEY `{$fkName}`
                    ");
                }

                // 2. Hacer articulo_id nullable
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$tabla}`
                    MODIFY COLUMN `articulo_id` bigint(20) unsigned DEFAULT NULL
                ");

                // 3. Recrear FK ahora con ON DELETE SET NULL y aceptando null
                $fkRecreated = DB::connection('pymes')->select("
                    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND CONSTRAINT_NAME = '{$fkName}'
                ");

                if (empty($fkRecreated)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD CONSTRAINT `{$fkName}`
                        FOREIGN KEY (`articulo_id`) REFERENCES `{$prefix}articulos` (`id`)
                        ON DELETE SET NULL
                    ");
                }

                // 4. Agregar es_concepto (bool)
                $esConceptoExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'es_concepto'
                ");

                if (empty($esConceptoExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `es_concepto` tinyint(1) NOT NULL DEFAULT 0
                        COMMENT 'Si true, es un concepto libre sin artículo asociado'
                        AFTER `articulo_id`
                    ");
                }

                // 5. Agregar concepto_descripcion
                $descExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'concepto_descripcion'
                ");

                if (empty($descExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `concepto_descripcion` varchar(255) DEFAULT NULL
                        COMMENT 'Descripción del concepto libre (si es_concepto=1)'
                        AFTER `es_concepto`
                    ");
                }

                // 6. Agregar concepto_categoria_id (FK opcional a categorias)
                $catIdExists = DB::connection('pymes')->select("
                    SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND COLUMN_NAME = 'concepto_categoria_id'
                ");

                if (empty($catIdExists)) {
                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD COLUMN `concepto_categoria_id` bigint(20) unsigned DEFAULT NULL
                        COMMENT 'Categoría opcional del concepto (para IVA)'
                        AFTER `concepto_descripcion`
                    ");

                    // Índice + FK a categorias (ON DELETE SET NULL)
                    $idxName = 'idx_vd_concepto_categoria';
                    $fkCatName = 'fk_vd_concepto_categoria';

                    DB::connection('pymes')->statement("
                        ALTER TABLE `{$tabla}`
                        ADD KEY `{$idxName}` (`concepto_categoria_id`),
                        ADD CONSTRAINT `{$fkCatName}`
                        FOREIGN KEY (`concepto_categoria_id`)
                        REFERENCES `{$prefix}categorias` (`id`)
                        ON DELETE SET NULL
                    ");
                }
            } catch (\Exception $e) {
                \Log::warning("Migración concepto_libre falló para comercio {$comercio->id}: ".$e->getMessage());

                continue;
            }
        }
    }

    public function down(): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
            $tabla = "{$prefix}ventas_detalle";

            try {
                $fkCatName = 'fk_vd_concepto_categoria';
                $idxName = 'idx_vd_concepto_categoria';

                $fkCatExists = DB::connection('pymes')->select("
                    SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = '{$tabla}'
                    AND CONSTRAINT_NAME = '{$fkCatName}'
                ");

                if (! empty($fkCatExists)) {
                    DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` DROP FOREIGN KEY `{$fkCatName}`");
                }

                DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` DROP INDEX IF EXISTS `{$idxName}`");

                foreach (['concepto_categoria_id', 'concepto_descripcion', 'es_concepto'] as $col) {
                    $colExists = DB::connection('pymes')->select("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = '{$tabla}'
                        AND COLUMN_NAME = '{$col}'
                    ");

                    if (! empty($colExists)) {
                        DB::connection('pymes')->statement("ALTER TABLE `{$tabla}` DROP COLUMN `{$col}`");
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

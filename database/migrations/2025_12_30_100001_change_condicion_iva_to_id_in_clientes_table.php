<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Migración: Cambiar condicion_iva de texto a condicion_iva_id
 *
 * Reemplaza el campo de texto condicion_iva por una FK a la tabla condiciones_iva
 * en la base de datos config.
 *
 * Mapeo de valores existentes:
 * - 'consumidor_final' -> código AFIP 5 (Consumidor Final)
 * - 'monotributista' -> código AFIP 6 (Responsable Monotributo)
 * - 'responsable_inscripto' -> código AFIP 1 (IVA Responsable Inscripto)
 * - 'exento' -> código AFIP 4 (IVA Sujeto Exento)
 */
return new class extends Migration
{
    /**
     * Mapeo de valores de texto a códigos AFIP
     */
    private array $mapeoCondiciones = [
        'consumidor_final' => 5,
        'monotributista' => 6,
        'responsable_inscripto' => 1,
        'exento' => 4,
        'no_responsable' => 3,
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $prefix = DB::connection('pymes_tenant')->getTablePrefix();
        $tableName = $prefix . 'clientes';

        // 1. Agregar la nueva columna condicion_iva_id
        $columnsId = DB::connection('pymes_tenant')
            ->select("SHOW COLUMNS FROM `{$tableName}` LIKE 'condicion_iva_id'");

        if (empty($columnsId)) {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` ADD COLUMN `condicion_iva_id` INT UNSIGNED NULL COMMENT 'FK a condiciones_iva en config' AFTER `tipo_cliente`"
            );
        }

        // 2. Obtener los IDs de condiciones_iva desde la BD config
        $condicionesIva = DB::connection('config')
            ->table('condiciones_iva')
            ->pluck('id', 'codigo')
            ->toArray();

        // 3. Migrar los datos existentes
        foreach ($this->mapeoCondiciones as $textoAntiguo => $codigoAfip) {
            if (isset($condicionesIva[$codigoAfip])) {
                $idCondicion = $condicionesIva[$codigoAfip];
                DB::connection('pymes_tenant')->statement(
                    "UPDATE `{$tableName}` SET `condicion_iva_id` = ? WHERE `condicion_iva` = ?",
                    [$idCondicion, $textoAntiguo]
                );
            }
        }

        // 4. Establecer valor por defecto para registros sin mapeo (consumidor final)
        $idConsumidorFinal = $condicionesIva[5] ?? 5; // código AFIP 5 = Consumidor Final
        DB::connection('pymes_tenant')->statement(
            "UPDATE `{$tableName}` SET `condicion_iva_id` = ? WHERE `condicion_iva_id` IS NULL",
            [$idConsumidorFinal]
        );

        // 5. Eliminar la columna antigua de texto
        $columnsTexto = DB::connection('pymes_tenant')
            ->select("SHOW COLUMNS FROM `{$tableName}` LIKE 'condicion_iva'");

        if (!empty($columnsTexto)) {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` DROP COLUMN `condicion_iva`"
            );
        }

        // 6. Agregar índice a la nueva columna
        DB::connection('pymes_tenant')->statement(
            "ALTER TABLE `{$tableName}` ADD INDEX `idx_condicion_iva_id` (`condicion_iva_id`)"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $prefix = DB::connection('pymes_tenant')->getTablePrefix();
        $tableName = $prefix . 'clientes';

        // 1. Recrear la columna de texto
        $columnsTexto = DB::connection('pymes_tenant')
            ->select("SHOW COLUMNS FROM `{$tableName}` LIKE 'condicion_iva'");

        if (empty($columnsTexto)) {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` ADD COLUMN `condicion_iva` VARCHAR(50) NOT NULL DEFAULT 'consumidor_final' COMMENT 'Condición IVA' AFTER `tipo_cliente`"
            );
        }

        // 2. Obtener los IDs de condiciones_iva desde la BD config
        $condicionesIva = DB::connection('config')
            ->table('condiciones_iva')
            ->pluck('codigo', 'id')
            ->toArray();

        // 3. Mapeo inverso de código AFIP a texto
        $mapeoInverso = array_flip($this->mapeoCondiciones);

        // 4. Migrar los datos de vuelta
        foreach ($condicionesIva as $id => $codigoAfip) {
            if (isset($mapeoInverso[$codigoAfip])) {
                $textoCondicion = $mapeoInverso[$codigoAfip];
                DB::connection('pymes_tenant')->statement(
                    "UPDATE `{$tableName}` SET `condicion_iva` = ? WHERE `condicion_iva_id` = ?",
                    [$textoCondicion, $id]
                );
            }
        }

        // 5. Establecer valor por defecto para registros sin mapeo
        DB::connection('pymes_tenant')->statement(
            "UPDATE `{$tableName}` SET `condicion_iva` = 'consumidor_final' WHERE `condicion_iva` = '' OR `condicion_iva` IS NULL"
        );

        // 6. Eliminar el índice
        try {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` DROP INDEX `idx_condicion_iva_id`"
            );
        } catch (\Exception $e) {
            // Ignorar si el índice no existe
        }

        // 7. Eliminar la columna condicion_iva_id
        $columnsId = DB::connection('pymes_tenant')
            ->select("SHOW COLUMNS FROM `{$tableName}` LIKE 'condicion_iva_id'");

        if (!empty($columnsId)) {
            DB::connection('pymes_tenant')->statement(
                "ALTER TABLE `{$tableName}` DROP COLUMN `condicion_iva_id`"
            );
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Agregar campos faltantes a artículos
 *
 * Agrega campos que faltan en la tabla articulos.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar campos faltantes a todas las tablas de artículos existentes
        $comercios = ['000001', '000002'];

        // Helper para verificar si existe una columna
        $columnExists = function($tableName, $columnName) {
            $result = DB::connection('pymes_tenant')->select("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ?
                AND COLUMN_NAME = ?
                AND TABLE_SCHEMA = DATABASE()
            ", [$tableName, $columnName]);
            return !empty($result);
        };

        foreach ($comercios as $comercioId) {
            $tableName = "{$comercioId}_articulos";

            if (Schema::connection('pymes_tenant')->hasTable($tableName)) {
                // Agregar categoria_id si no existe
                if (!$columnExists($tableName, 'categoria_id')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->unsignedBigInteger('categoria_id')->nullable()->after('descripcion')->comment('Categoría del artículo');
                    });
                }

                // Agregar marca si no existe
                if (!$columnExists($tableName, 'marca')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->string('marca', 100)->nullable()->after('categoria_id')->comment('Marca del artículo');
                    });
                }

                // Agregar unidad_medida si no existe
                if (!$columnExists($tableName, 'unidad_medida')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->string('unidad_medida', 20)->default('unidad')->after('marca')->comment('Unidad de medida');
                    });
                }

                // Agregar codigo_barra si no existe
                if (!$columnExists($tableName, 'codigo_barra')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->string('codigo_barra', 100)->nullable()->after('unidad_medida')->comment('Código de barras');
                    });
                }

                // Agregar es_servicio si no existe
                if (!$columnExists($tableName, 'es_servicio')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->boolean('es_servicio')->default(false)->after('codigo_barra')->comment('Si es servicio o producto');
                    });
                }

                // Agregar controla_stock si no existe
                if (!$columnExists($tableName, 'controla_stock')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->boolean('controla_stock')->default(true)->after('es_servicio')->comment('Si controla stock');
                    });
                }

                // Agregar precio_iva_incluido si no existe
                if (!$columnExists($tableName, 'precio_iva_incluido')) {
                    Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                        $table->boolean('precio_iva_incluido')->default(true)->after('tipo_iva_id')->comment('Si los precios incluyen IVA');
                    });
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar campos de todas las tablas de artículos existentes
        $comercios = ['000001', '000002'];

        foreach ($comercios as $comercioId) {
            $tableName = "{$comercioId}_articulos";

            if (Schema::connection('pymes_tenant')->hasTable($tableName)) {
                Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                    $table->dropColumn([
                        'marca',
                        'unidad_medida',
                        'codigo_barra',
                        'es_servicio',
                        'controla_stock',
                        'precio_iva_incluido'
                    ]);
                });
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Agregar campo icono a todas las tablas de categorías existentes
        $comercios = ['000001', '000002'];

        foreach ($comercios as $comercioId) {
            $tableName = "{$comercioId}_categorias";

            if (Schema::connection('pymes_tenant')->hasTable($tableName)) {
                Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                    $table->string('icono', 50)->nullable()->after('color')->comment('Nombre del icono (ej: heroicon-o-tag)');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar campo icono de todas las tablas de categorías existentes
        $comercios = ['000001', '000002'];

        foreach ($comercios as $comercioId) {
            $tableName = "{$comercioId}_categorias";

            if (Schema::connection('pymes_tenant')->hasTable($tableName)) {
                Schema::connection('pymes_tenant')->table($tableName, function (Blueprint $table) {
                    $table->dropColumn('icono');
                });
            }
        }
    }
};

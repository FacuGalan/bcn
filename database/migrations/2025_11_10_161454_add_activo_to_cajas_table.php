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
        Schema::connection('pymes_tenant')->table('cajas', function (Blueprint $table) {
            $table->boolean('activo')
                  ->default(true)
                  ->after('sucursal_id')
                  ->comment('Indica si la caja está activa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('cajas', function (Blueprint $table) {
            $table->dropColumn('activo');
        });
    }
};

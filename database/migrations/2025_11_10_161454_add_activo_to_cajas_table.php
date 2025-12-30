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
        $connection = 'pymes_tenant';

        if (Schema::connection($connection)->hasTable('cajas') &&
            !Schema::connection($connection)->hasColumn('cajas', 'activo')) {
            Schema::connection($connection)->table('cajas', function (Blueprint $table) {
                $table->boolean('activo')
                      ->default(true)
                      ->after('sucursal_id')
                      ->comment('Indica si la caja está activa');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = 'pymes_tenant';

        if (Schema::connection($connection)->hasTable('cajas') &&
            Schema::connection($connection)->hasColumn('cajas', 'activo')) {
            Schema::connection($connection)->table('cajas', function (Blueprint $table) {
                $table->dropColumn('activo');
            });
        }
    }
};

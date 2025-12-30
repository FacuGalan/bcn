<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conexion de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('cuits', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::connection($this->connection)->table('puntos_venta', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('cuits', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::connection($this->connection)->table('puntos_venta', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};

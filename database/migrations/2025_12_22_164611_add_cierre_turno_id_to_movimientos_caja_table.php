<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar cierre_turno_id a movimientos_caja
 *
 * Permite rastrear en qué cierre de turno fue incluido cada movimiento.
 * Si es NULL, el movimiento aún no ha sido cerrado.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('movimientos_caja', function (Blueprint $table) {
            $table->unsignedBigInteger('cierre_turno_id')
                ->nullable()
                ->comment('NULL = no cerrado aún');

            $table->index(['cierre_turno_id']);

            $table->foreign('cierre_turno_id')
                ->references('id')
                ->on('cierres_turno')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('movimientos_caja', function (Blueprint $table) {
            $table->dropForeign(['cierre_turno_id']);
            $table->dropColumn('cierre_turno_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Agrega campos de reversión a cierres_turno y rendicion_fondos
 * para permitir rechazar rendiciones y revertir cierres de turno completos.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        $prefix = DB::connection($this->connection)->getTablePrefix();

        // Agregar 'rechazado' al enum de estado en rendicion_fondos
        DB::connection($this->connection)->statement(
            "ALTER TABLE {$prefix}rendicion_fondos MODIFY COLUMN estado ENUM('pendiente','confirmado','cancelado','rechazado') NOT NULL DEFAULT 'pendiente'"
        );

        // Agregar campos de rechazo a rendicion_fondos
        Schema::connection($this->connection)->table('rendicion_fondos', function (Blueprint $table) {
            $table->text('motivo_rechazo')->nullable()->after('observaciones');
            $table->unsignedBigInteger('usuario_rechazo_id')->nullable()->after('motivo_rechazo');
            $table->timestamp('fecha_rechazo')->nullable()->after('usuario_rechazo_id');
        });

        // Agregar campos de reversión a cierres_turno
        Schema::connection($this->connection)->table('cierres_turno', function (Blueprint $table) {
            $table->boolean('revertido')->default(false)->after('observaciones');
            $table->timestamp('fecha_reversion')->nullable()->after('revertido');
            $table->unsignedBigInteger('usuario_reversion_id')->nullable()->after('fecha_reversion');
            $table->text('motivo_reversion')->nullable()->after('usuario_reversion_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('cierres_turno', function (Blueprint $table) {
            $table->dropColumn(['revertido', 'fecha_reversion', 'usuario_reversion_id', 'motivo_reversion']);
        });

        Schema::connection($this->connection)->table('rendicion_fondos', function (Blueprint $table) {
            $table->dropColumn(['motivo_rechazo', 'usuario_rechazo_id', 'fecha_rechazo']);
        });

        $prefix = DB::connection($this->connection)->getTablePrefix();
        DB::connection($this->connection)->statement(
            "ALTER TABLE {$prefix}rendicion_fondos MODIFY COLUMN estado ENUM('pendiente','confirmado','cancelado') NOT NULL DEFAULT 'pendiente'"
        );
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla cierres_turno
 *
 * Registra cada cierre de turno realizado, ya sea individual o grupal.
 * Guarda información del usuario que cerró, fechas y tipo de cierre.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('cierres_turno', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('grupo_cierre_id')->nullable()->comment('NULL si fue cierre individual');
            $table->unsignedBigInteger('usuario_id')->comment('Usuario que realizó el cierre');

            $table->enum('tipo', ['individual', 'grupo'])->default('individual');

            $table->dateTime('fecha_apertura')->nullable()->comment('Fecha/hora de apertura más antigua del turno');
            $table->dateTime('fecha_cierre')->comment('Fecha/hora del cierre');

            // Totales consolidados (suma de todas las cajas del cierre)
            $table->decimal('total_saldo_inicial', 14, 2)->default(0)->comment('Suma de saldos iniciales');
            $table->decimal('total_saldo_final', 14, 2)->default(0)->comment('Suma de saldos finales');
            $table->decimal('total_ingresos', 14, 2)->default(0)->comment('Suma de ingresos');
            $table->decimal('total_egresos', 14, 2)->default(0)->comment('Suma de egresos');
            $table->decimal('total_diferencia', 14, 2)->default(0)->comment('Diferencia total (faltante/sobrante)');

            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices
            $table->index(['sucursal_id', 'fecha_cierre']);
            $table->index(['usuario_id']);
            $table->index(['grupo_cierre_id']);
            $table->index(['tipo']);

            // Foreign keys
            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales')
                ->onDelete('cascade');

            $table->foreign('grupo_cierre_id')
                ->references('id')
                ->on('grupos_cierre')
                ->onDelete('set null');

            // Nota: usuario_id no tiene FK porque users está en otra conexión (config)
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cierres_turno');
    }
};

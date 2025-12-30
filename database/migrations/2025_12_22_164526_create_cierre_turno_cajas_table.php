<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla cierre_turno_cajas (pivot)
 *
 * Guarda el detalle de cada caja que participó en un cierre de turno.
 * Permite tener el desglose individual cuando se hace un cierre grupal.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('cierre_turno_cajas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cierre_turno_id');
            $table->unsignedBigInteger('caja_id');

            // Snapshot de la caja al momento del cierre
            $table->string('caja_nombre', 100)->comment('Nombre de la caja al momento del cierre');

            // Montos de la caja
            $table->decimal('saldo_inicial', 14, 2)->default(0);
            $table->decimal('saldo_final', 14, 2)->default(0);
            $table->decimal('saldo_sistema', 14, 2)->default(0)->comment('Saldo calculado por el sistema');
            $table->decimal('saldo_declarado', 14, 2)->default(0)->comment('Saldo declarado por el usuario');

            // Totales de movimientos
            $table->decimal('total_ingresos', 14, 2)->default(0);
            $table->decimal('total_egresos', 14, 2)->default(0);

            // Diferencia (saldo_declarado - saldo_sistema)
            $table->decimal('diferencia', 14, 2)->default(0)->comment('Positivo = sobrante, Negativo = faltante');

            // Desglose por forma de pago (JSON como TEXT para compatibilidad)
            $table->text('desglose_formas_pago')->nullable()->comment('JSON con desglose por forma de pago');

            // Desglose por concepto (JSON como TEXT para compatibilidad)
            $table->text('desglose_conceptos')->nullable()->comment('JSON con desglose por concepto');

            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices
            $table->unique(['cierre_turno_id', 'caja_id']);
            $table->index(['caja_id']);

            // Foreign keys
            $table->foreign('cierre_turno_id')
                ->references('id')
                ->on('cierres_turno')
                ->onDelete('cascade');

            $table->foreign('caja_id')
                ->references('id')
                ->on('cajas')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('cierre_turno_cajas');
    }
};

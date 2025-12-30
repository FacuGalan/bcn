<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de conceptos de pago (tipos base de pago).
     * Esta tabla es fija y no editable por el usuario.
     * Los conceptos agrupan las formas de pago por tipo.
     */
    public function up(): void
    {
        Schema::create('conceptos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->boolean('permite_cuotas')->default(false);
            $table->boolean('permite_vuelto')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();

            $table->index('activo');
            $table->index('orden');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conceptos_pago');
    }
};

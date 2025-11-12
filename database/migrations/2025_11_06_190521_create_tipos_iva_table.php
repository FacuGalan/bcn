<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Tipos de IVA
 *
 * Contiene los códigos de IVA según normativa argentina.
 * Se usa en artículos y para calcular IVA en ventas/compras.
 *
 * FASE 1 - Sistema Multi-Sucursal (Extensión IVA)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('tipos_iva', function (Blueprint $table) {
            $table->id();
            $table->integer('codigo')->unique()->comment('Código AFIP (3=Exento, 4=10.5%, 5=21%)');
            $table->string('nombre', 50)->comment('Descripción del tipo de IVA');
            $table->decimal('porcentaje', 5, 2)->comment('Porcentaje de IVA (0, 10.5, 21)');
            $table->boolean('activo')->default(true)->comment('Si está activo');
            $table->timestamps();

            // Índices
            $table->index('codigo', 'idx_codigo');
            $table->index('activo', 'idx_activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('tipos_iva');
    }
};

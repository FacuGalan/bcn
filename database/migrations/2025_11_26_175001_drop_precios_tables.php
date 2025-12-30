<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Eliminar tablas de precios obsoletas
 *
 * Elimina las tablas del sistema anterior de precios:
 * - precios_base: Reemplazada por el nuevo sistema de listas_precios
 * - precios_old: Backup del sistema legacy ya no necesario
 *
 * IMPORTANTE: Esta migración es destructiva. Asegurarse de que no hay
 * datos importantes antes de ejecutar.
 *
 * FASE 2 - Sistema de Listas de Precios
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Eliminar tabla precios_base (sistema anterior)
        Schema::connection('pymes_tenant')->dropIfExists('precios_base');

        // Eliminar tabla precios_old (backup legacy)
        Schema::connection('pymes_tenant')->dropIfExists('precios_old');

        // Eliminar tabla precios si existe (legacy original)
        Schema::connection('pymes_tenant')->dropIfExists('precios');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recrear precios_base si es necesario hacer rollback
        Schema::connection('pymes_tenant')->create('precios_base', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('articulo_id');
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('forma_venta_id')->nullable();
            $table->unsignedBigInteger('canal_venta_id')->nullable();
            $table->decimal('precio', 12, 2);
            $table->boolean('activo')->default(true);
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->timestamps();
        });
    }
};

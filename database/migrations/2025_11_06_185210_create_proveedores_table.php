<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Tabla Proveedores
 *
 * Gestiona los proveedores del comercio.
 * Puede incluir proveedores externos y sucursales internas como proveedores.
 * También soporta proveedores que sean clientes (conciliación).
 *
 * FASE 1 - Sistema Multi-Sucursal
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes_tenant')->create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('cuit', 20)->nullable();
            $table->text('direccion')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->boolean('es_sucursal_interna')->default(false)->comment('Si es otra sucursal del comercio');
            $table->unsignedBigInteger('sucursal_id')->nullable()->comment('Si es sucursal interna, referencia');
            $table->boolean('es_tambien_cliente')->default(false)->comment('Si también es cliente');
            $table->unsignedBigInteger('cliente_id')->nullable()->comment('Si es cliente, referencia para conciliación');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Foreign keys
            $table->foreign('sucursal_id', 'fk_proveedores_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('set null');

            $table->foreign('cliente_id', 'fk_proveedores_cliente')
                  ->references('id')
                  ->on('clientes')
                  ->onDelete('set null');

            // Índices
            $table->index('cuit', 'idx_cuit');
            $table->index('es_sucursal_interna', 'idx_es_sucursal_interna');
            $table->index('activo', 'idx_activo');
        });

        // Índice parcial en nombre (primeros 191 caracteres)
        DB::connection('pymes_tenant')->statement(
            'ALTER TABLE `' . DB::connection('pymes_tenant')->getTablePrefix() . 'proveedores`
             ADD INDEX idx_nombre (nombre(191))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('proveedores');
    }
};

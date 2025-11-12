<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Compras
 *
 * Registra las compras de cada sucursal.
 * Cada compra pertenece a una sucursal específica.
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
        Schema::connection('pymes_tenant')->create('compras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->unsignedBigInteger('proveedor_id');
            $table->string('numero_comprobante', 50);
            $table->enum('tipo_comprobante', ['factura_a', 'factura_b', 'factura_c', 'remito', 'nota_credito', 'nota_debito']);
            $table->date('fecha');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('estado', ['pendiente', 'pagada', 'parcial', 'anulada'])->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            // Foreign keys
            $table->foreign('sucursal_id', 'fk_compras_sucursal')
                  ->references('id')
                  ->on('sucursales')
                  ->onDelete('restrict');

            $table->foreign('proveedor_id', 'fk_compras_proveedor')
                  ->references('id')
                  ->on('proveedores')
                  ->onDelete('restrict');

            // Índices
            $table->index(['sucursal_id', 'fecha'], 'idx_sucursal_fecha');
            $table->index('proveedor_id', 'idx_proveedor');
            $table->index('estado', 'idx_estado');
            $table->index('fecha', 'idx_fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('compras');
    }
};

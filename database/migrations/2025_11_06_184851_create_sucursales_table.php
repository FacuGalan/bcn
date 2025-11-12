<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Sucursales
 *
 * Crea la tabla para gestionar sucursales de cada comercio.
 * Cada comercio puede tener múltiples sucursales.
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
        Schema::connection('pymes_tenant')->create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre de la sucursal');
            $table->string('codigo', 50)->comment('Código corto (ej: CENTRO, NORTE)');
            $table->text('direccion')->nullable()->comment('Dirección física');
            $table->string('telefono', 50)->nullable()->comment('Teléfono de contacto');
            $table->string('email', 100)->nullable()->comment('Email de contacto');
            $table->boolean('es_principal')->default(false)->comment('Si es la sucursal principal/central');
            $table->unsignedBigInteger('datos_fiscales_id')->nullable()->comment('Si factura con datos propios');
            $table->boolean('activa')->default(true)->comment('Si está operativa');
            $table->text('configuracion')->nullable()->comment('Configuraciones específicas (JSON)');
            $table->timestamps();

            // Índices
            $table->index('codigo', 'idx_codigo');
            $table->index('activa', 'idx_activa');
            $table->index('es_principal', 'idx_es_principal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('sucursales');
    }
};

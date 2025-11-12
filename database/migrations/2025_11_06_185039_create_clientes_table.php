<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migración: Tabla Clientes
 *
 * Tabla maestra de clientes compartidos entre sucursales.
 * Cada cliente puede tener características diferentes en cada sucursal
 * (ver tabla pivot clientes_sucursales).
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
        Schema::connection('pymes_tenant')->create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255)->comment('Nombre o razón social');
            $table->string('email', 100)->nullable()->comment('Email de contacto');
            $table->string('telefono', 50)->nullable()->comment('Teléfono de contacto');
            $table->text('direccion')->nullable()->comment('Dirección');
            $table->string('cuit', 20)->nullable()->comment('CUIT/CUIL');
            $table->enum('tipo_cliente', ['consumidor_final', 'monotributista', 'responsable_inscripto'])
                  ->default('consumidor_final');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Índices
            $table->index('email', 'idx_email');
            $table->index('cuit', 'idx_cuit');
            $table->index('activo', 'idx_activo');
        });

        // Índice parcial en nombre (primeros 191 caracteres)
        DB::connection('pymes_tenant')->statement(
            'ALTER TABLE `' . DB::connection('pymes_tenant')->getTablePrefix() . 'clientes`
             ADD INDEX idx_nombre (nombre(191))'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->dropIfExists('clientes');
    }
};

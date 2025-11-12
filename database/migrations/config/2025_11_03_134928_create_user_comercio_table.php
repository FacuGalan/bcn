<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla pivot user_comercio en la base de datos CONFIG
 *
 * Esta tabla establece la relación many-to-many entre usuarios y comercios,
 * permitiendo que un usuario tenga acceso a múltiples comercios y viceversa.
 *
 * @package Database\Migrations\Config
 * @author BCN Pymes
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones para crear la tabla pivot user_comercio
     *
     * Campos:
     * - id: Identificador único del registro
     * - user_id: FK al usuario
     * - comercio_id: FK al comercio
     * - created_at: Fecha de creación del registro
     * - updated_at: Fecha de última actualización
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection('config')->create('user_comercio', function (Blueprint $table) {
            $table->id()->comment('ID único del registro pivot');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->comment('FK al usuario');
            $table->foreignId('comercio_id')->constrained('comercios')->onDelete('cascade')->comment('FK al comercio');
            $table->timestamps();

            // Evitar registros duplicados (un usuario no puede estar 2 veces en el mismo comercio)
            $table->unique(['user_id', 'comercio_id'], 'uk_user_comercio');

            // Índices para optimizar búsquedas
            $table->index('user_id', 'idx_user_comercio_user');
            $table->index('comercio_id', 'idx_user_comercio_comercio');
        });
    }

    /**
     * Revierte las migraciones eliminando la tabla user_comercio
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection('config')->dropIfExists('user_comercio');
    }
};

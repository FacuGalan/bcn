<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla comercios en la base de datos CONFIG
 *
 * Esta tabla almacena la información básica de cada comercio/PYME del sistema.
 * Cada comercio tendrá sus propias tablas con prefijo en la base de datos PYMES.
 *
 * @package Database\Migrations\Config
 * @author BCN Pymes
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones para crear la tabla comercios
     *
     * Campos:
     * - id: Identificador único del comercio (autoincremental)
     * - mail: Email del comercio (único, usado para login)
     * - nombre: Nombre comercial del negocio
     * - created_at: Fecha de creación del registro
     * - updated_at: Fecha de última actualización
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection('config')->create('comercios', function (Blueprint $table) {
            $table->id()->comment('ID único del comercio');
            $table->string('mail')->unique()->comment('Email del comercio para login');
            $table->string('nombre')->comment('Nombre comercial del negocio');
            $table->timestamps();

            // Índices para optimizar búsquedas
            $table->index('mail', 'idx_comercios_mail');
        });
    }

    /**
     * Revierte las migraciones eliminando la tabla comercios
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection('config')->dropIfExists('comercios');
    }
};

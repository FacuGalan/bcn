<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear tablas de usuarios y autenticación en la base de datos CONFIG
 *
 * Crea las siguientes tablas:
 * - users: Usuarios del sistema (centralizados, pueden acceder a múltiples comercios)
 * - password_reset_tokens: Tokens para recuperación de contraseña
 * - sessions: Sesiones de usuarios autenticados
 *
 * @package Database\Migrations\Config
 * @author BCN Pymes
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Ejecuta las migraciones para crear las tablas de usuarios
     *
     * Tabla users:
     * - id: Identificador único del usuario
     * - name: Nombre completo del usuario
     * - username: Nombre de usuario para login (único)
     * - email: Correo electrónico del usuario (único)
     * - email_verified_at: Fecha de verificación del email
     * - password: Contraseña hasheada
     * - remember_token: Token para "recordar sesión"
     * - created_at: Fecha de creación
     * - updated_at: Fecha de actualización
     *
     * @return void
     */
    public function up(): void
    {
        Schema::connection('config')->create('users', function (Blueprint $table) {
            $table->id()->comment('ID único del usuario');
            $table->string('name')->comment('Nombre completo del usuario');
            $table->string('username')->unique()->comment('Nombre de usuario para login');
            $table->string('email')->unique()->comment('Correo electrónico del usuario');
            $table->timestamp('email_verified_at')->nullable()->comment('Fecha de verificación del email');
            $table->string('password')->comment('Contraseña hasheada');
            $table->rememberToken();
            $table->timestamps();

            // Índices para optimizar búsquedas
            $table->index('username', 'idx_users_username');
            $table->index('email', 'idx_users_email');
        });

        Schema::connection('config')->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary()->comment('Email del usuario');
            $table->string('token')->comment('Token de recuperación');
            $table->timestamp('created_at')->nullable()->comment('Fecha de creación del token');
        });

        Schema::connection('config')->create('sessions', function (Blueprint $table) {
            $table->string('id')->primary()->comment('ID de la sesión');
            $table->foreignId('user_id')->nullable()->index()->comment('FK al usuario');
            $table->string('ip_address', 45)->nullable()->comment('Dirección IP del usuario');
            $table->text('user_agent')->nullable()->comment('User agent del navegador');
            $table->longText('payload')->comment('Datos de la sesión');
            $table->integer('last_activity')->index()->comment('Timestamp de última actividad');
        });
    }

    /**
     * Revierte las migraciones eliminando las tablas de usuarios
     *
     * @return void
     */
    public function down(): void
    {
        Schema::connection('config')->dropIfExists('users');
        Schema::connection('config')->dropIfExists('password_reset_tokens');
        Schema::connection('config')->dropIfExists('sessions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexión: config
 * Tablas: users, password_reset_tokens, sessions
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('telefono', 50)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('activo')->default(true);
            $table->boolean('dark_mode')->default(false);
            $table->string('locale', 5)->default('es');
            $table->foreignId('ultimo_comercio_id')->nullable()->constrained('comercios')->nullOnDelete();
            $table->boolean('is_system_admin')->default(false)->comment('Super admin del sistema');
            $table->text('password_visible')->nullable();
            $table->integer('max_concurrent_sessions')->default(3);
            $table->rememberToken();
            $table->timestamps();

            $table->index('username', 'idx_users_username');
            $table->index('email', 'idx_users_email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

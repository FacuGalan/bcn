<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('password')->comment('Si el usuario está activo');
            $table->text('password_visible')->nullable()->after('activo')->comment('Contraseña recuperable encriptada');
            $table->integer('max_concurrent_sessions')->default(3)->after('password_visible')->comment('Máximo de sesiones concurrentes permitidas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->dropColumn(['activo', 'password_visible', 'max_concurrent_sessions']);
        });
    }
};

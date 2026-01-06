<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos para:
 * - Recordar el último comercio usado por el usuario
 * - Marcar usuarios como administradores de sistema (acceso a todos los comercios)
 */
return new class extends Migration
{
    /**
     * Conexión a usar (base de datos compartida)
     */
    protected $connection = 'config';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            // Último comercio usado - para recordar la selección del usuario
            $table->unsignedBigInteger('ultimo_comercio_id')->nullable()->after('dark_mode');
            $table->foreign('ultimo_comercio_id')
                ->references('id')
                ->on('comercios')
                ->onDelete('set null');

            // Administrador de sistema - acceso a todos los comercios
            $table->boolean('is_system_admin')->default(false)->after('ultimo_comercio_id')
                ->comment('Si es true, el usuario tiene acceso a TODOS los comercios del sistema');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('users', function (Blueprint $table) {
            $table->dropForeign(['ultimo_comercio_id']);
            $table->dropColumn(['ultimo_comercio_id', 'is_system_admin']);
        });
    }
};

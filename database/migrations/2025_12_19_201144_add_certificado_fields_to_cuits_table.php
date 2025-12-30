<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conexión de base de datos
     */
    protected $connection = 'pymes_tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('cuits', function (Blueprint $table) {
            $table->string('certificado_path')->nullable()->after('entorno_afip')->comment('Path al certificado AFIP encriptado');
            $table->string('clave_path')->nullable()->after('certificado_path')->comment('Path a la clave privada encriptada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('cuits', function (Blueprint $table) {
            $table->dropColumn(['certificado_path', 'clave_path']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla de configuración de empresa
 *
 * Esta tabla almacena los datos generales del comercio.
 * Solo debe existir un registro por comercio.
 */
return new class extends Migration
{
    /**
     * La conexión de base de datos a usar
     */
    protected $connection = 'pymes_tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create('empresa_config', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->text('direccion')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('empresa_config');
    }
};

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
        Schema::connection('config')->create('comercios', function (Blueprint $table) {
            $table->id()->comment('ID único del comercio');
            $table->string('nombre')->comment('Nombre del comercio');
            $table->string('razon_social')->nullable()->comment('Razón social del comercio');
            $table->string('cuit', 20)->unique()->comment('CUIT del comercio');
            $table->string('database_name')->unique()->comment('Nombre de la base de datos tenant');
            $table->string('email')->nullable()->comment('Email de contacto del comercio');
            $table->string('telefono')->nullable()->comment('Teléfono de contacto');
            $table->string('direccion')->nullable()->comment('Dirección física del comercio');
            $table->boolean('activo')->default(true)->comment('Estado del comercio');
            $table->timestamp('fecha_alta')->useCurrent()->comment('Fecha de alta del comercio');
            $table->timestamp('fecha_vencimiento')->nullable()->comment('Fecha de vencimiento del servicio');
            $table->timestamps();

            $table->index('activo');
            $table->index('fecha_vencimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('config')->dropIfExists('comercios');
    }
};

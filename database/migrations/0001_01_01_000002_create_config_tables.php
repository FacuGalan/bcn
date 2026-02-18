<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexión: config
 * Tablas: comercios, comercio_user, condiciones_iva, provincias, localidades
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('razon_social')->nullable();
            $table->string('cuit', 20);
            $table->string('database_name');
            $table->string('prefijo', 20)->nullable();
            $table->unsignedInteger('max_usuarios')->default(10);
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activo')->default(true)->index();
            $table->timestamp('fecha_alta')->useCurrent();
            $table->timestamp('fecha_vencimiento')->nullable()->index();
            $table->timestamps();

            $table->unique('cuit');
        });

        Schema::create('comercio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('fecha_asignacion')->useCurrent();
            $table->timestamps();

            $table->unique(['comercio_id', 'user_id']);
        });

        Schema::create('condiciones_iva', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('codigo');
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();

            $table->unique('codigo');
        });

        Schema::create('provincias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10);
            $table->string('nombre', 100);
            $table->timestamps();

            $table->unique('codigo');
        });

        Schema::create('localidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provincia_id')->constrained()->cascadeOnDelete();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('nombre', 150);
            $table->timestamps();

            $table->index(['provincia_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localidades');
        Schema::dropIfExists('provincias');
        Schema::dropIfExists('condiciones_iva');
        Schema::dropIfExists('comercio_user');
        Schema::dropIfExists('comercios');
    }
};

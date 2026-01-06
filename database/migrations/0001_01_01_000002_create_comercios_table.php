<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comercios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('razon_social')->nullable();
            $table->string('cuit', 13)->nullable();
            $table->string('direccion')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('logo')->nullable();
            $table->string('table_prefix', 20)->unique()->comment('Prefijo para tablas del comercio');
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('max_usuarios')->default(5)->comment('Máximo de usuarios permitidos');
            $table->timestamps();
        });

        Schema::create('comercio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comercio_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->boolean('es_admin')->default(false)->comment('Es administrador del comercio');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['comercio_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comercio_user');
        Schema::dropIfExists('comercios');
    }
};
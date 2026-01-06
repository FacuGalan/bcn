<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla define los permisos funcionales disponibles en el sistema.
     * Los permisos se crean automáticamente en la tabla 'permissions' de Spatie
     * con el prefijo 'func.' + codigo.
     */
    public function up(): void
    {
        Schema::connection('pymes')->create('permisos_funcionales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique()->comment('Código único del permiso (sin prefijo func.)');
            $table->string('etiqueta', 100)->comment('Etiqueta para mostrar en la UI');
            $table->string('descripcion', 255)->nullable()->comment('Descripción detallada del permiso');
            $table->string('grupo', 50)->comment('Grupo para agrupar en la UI (Facturación, Ventas, etc.)');
            $table->unsignedSmallInteger('orden')->default(0)->comment('Orden dentro del grupo');
            $table->boolean('activo')->default(true)->comment('Si el permiso está activo y visible');
            $table->timestamps();

            $table->index(['grupo', 'orden']);
            $table->index('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes')->dropIfExists('permisos_funcionales');
    }
};

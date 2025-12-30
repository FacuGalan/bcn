<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para crear la tabla grupos_cierre
 *
 * Los grupos de cierre permiten que varias cajas compartan el cierre de turno.
 * Cuando se cierra el turno de una caja que pertenece a un grupo,
 * se cierran todas las cajas del grupo y los movimientos se consolidan.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('grupos_cierre', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->string('nombre', 100)->nullable()->comment('Nombre descriptivo del grupo');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['sucursal_id', 'activo']);

            // FK sin constraint para evitar problemas de engine
            $table->foreign('sucursal_id')
                ->references('id')
                ->on('sucursales')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('grupos_cierre');
    }
};

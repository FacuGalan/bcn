<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para la tabla de items del menú
 *
 * Esta tabla almacena la estructura jerárquica del menú de navegación
 * de cada comercio. Soporta múltiples niveles (padre → hijo → nieto).
 *
 * IMPORTANTE: Esta tabla debe crearse con prefijo por comercio en la BD 'pymes'
 * Usar: php artisan comercio:init {id} para crear las tablas de un nuevo comercio
 *
 * @package Database\Migrations
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('pymes')->create('menu_items', function (Blueprint $table) {
            $table->id();

            // Jerarquía
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('menu_items')
                ->cascadeOnDelete()
                ->comment('ID del item padre (null = raíz)');

            // Información básica
            $table->string('nombre', 100)
                ->comment('Nombre visible en el menú');

            $table->string('slug', 100)
                ->unique()
                ->comment('Identificador único (ej: ventas.nueva-venta)');

            $table->string('icono', 100)
                ->nullable()
                ->comment('Icono de Heroicons (ej: heroicon-o-shopping-cart)');

            // Configuración de navegación
            $table->enum('route_type', ['route', 'component', 'none'])
                ->default('none')
                ->comment('Tipo: route=ruta Laravel, component=Livewire, none=solo agrupa');

            $table->string('route_value', 255)
                ->nullable()
                ->comment('Valor de la ruta o nombre del componente');

            // Visualización
            $table->integer('orden')
                ->default(0)
                ->comment('Orden de aparición en el menú');

            $table->boolean('activo')
                ->default(true)
                ->comment('Si está activo y visible en el menú');

            $table->timestamps();

            // Índices para mejorar performance
            $table->index('parent_id');
            $table->index('activo');
            $table->index(['parent_id', 'orden']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes')->dropIfExists('menu_items');
    }
};

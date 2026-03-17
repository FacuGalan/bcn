<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conexión: pymes (compartida, sin prefijo)
 * Tablas: menu_items, permissions, permisos_funcionales
 *
 * Estas tablas son compartidas entre todos los comercios.
 * Los permisos se referencian desde las tablas tenant role_has_permissions.
 */
return new class extends Migration
{
    protected $connection = 'pymes';

    public function up(): void
    {
        // Dropear primero si existen (para RefreshDatabase en testing)
        // FK checks deshabilitados porque tablas tenant 000001_* referencian permissions
        if (app()->runningUnitTests()) {
            \Illuminate\Support\Facades\DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS=0');
            Schema::connection('pymes')->dropIfExists('permisos_funcionales');
            Schema::connection('pymes')->dropIfExists('permissions');
            Schema::connection('pymes')->dropIfExists('menu_items');
            \Illuminate\Support\Facades\DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        Schema::connection('pymes')->create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('nombre', 100);
            $table->string('slug', 100);
            $table->string('icono', 100)->nullable();
            $table->enum('route_type', ['route', 'component', 'none'])->default('none');
            $table->string('route_value', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('menu_items')->cascadeOnDelete();
            $table->index(['parent_id', 'orden']);
            $table->unique('slug');
        });

        Schema::connection('pymes')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('pymes')->create('permisos_funcionales', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('etiqueta', 100);
            $table->string('descripcion', 255)->nullable();
            $table->string('grupo', 50);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['grupo', 'orden']);
        });
    }

    public function down(): void
    {
        \Illuminate\Support\Facades\DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::connection('pymes')->dropIfExists('permisos_funcionales');
        Schema::connection('pymes')->dropIfExists('permissions');
        Schema::connection('pymes')->dropIfExists('menu_items');
        \Illuminate\Support\Facades\DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS=1');
    }
};

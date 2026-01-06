<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración: Tabla Impresoras
 *
 * Almacena las impresoras configuradas en el sistema.
 * Cada impresora puede ser térmica (ESC/POS) o láser/inkjet (HTML).
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('impresoras', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->comment('Nombre amigable de la impresora');
            $table->string('nombre_sistema', 255)->comment('Nombre exacto devuelto por QZ Tray');
            $table->enum('tipo', ['termica', 'laser_inkjet'])->default('termica');
            $table->enum('formato_papel', ['80mm', '58mm', 'a4', 'carta'])->default('80mm');
            $table->unsignedTinyInteger('ancho_caracteres')->default(48)->comment('Caracteres por línea');
            $table->boolean('activa')->default(true);
            $table->text('configuracion')->nullable()->comment('Config adicional: cortador, cajon, etc. (JSON)');
            $table->timestamps();
            $table->softDeletes();

            $table->index('activa', 'idx_impresoras_activa');
            $table->index('tipo', 'idx_impresoras_tipo');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('impresoras');
    }
};

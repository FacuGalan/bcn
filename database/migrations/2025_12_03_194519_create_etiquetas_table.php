<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('etiquetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_etiqueta_id')->constrained('grupos_etiquetas')->onDelete('cascade');
            $table->string('nombre', 100);
            $table->string('codigo', 50)->nullable();
            $table->string('color', 7)->nullable();
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['grupo_etiqueta_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('etiquetas');
    }
};

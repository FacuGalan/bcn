<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->create('articulo_etiqueta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('articulo_id')->constrained('articulos')->onDelete('cascade');
            $table->foreignId('etiqueta_id')->constrained('etiquetas')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['articulo_id', 'etiqueta_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('articulo_etiqueta');
    }
};

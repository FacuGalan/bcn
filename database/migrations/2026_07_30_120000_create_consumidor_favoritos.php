<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-T41 (ronda cuenta consumidor): comercios favoritos del consumidor,
 * por TIENDA (una tienda = comercio+sucursal, igual que el marketplace).
 * BD CONFIG: el favorito es de la cuenta global, cross-comercio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->create('consumidor_favoritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumidor_id')->constrained('consumidores')->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['consumidor_id', 'tienda_id'], 'uq_consumidor_tienda');
        });
    }

    public function down(): void
    {
        Schema::connection('config')->dropIfExists('consumidor_favoritos');
    }
};

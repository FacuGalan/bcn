<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla almacena los artículos que componen un combo/pack.
     * Solo se usa cuando tipo = 'combo'
     */
    public function up(): void
    {
        Schema::create('promocion_especial_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_especial_id');
            $table->unsignedBigInteger('articulo_id');
            $table->integer('cantidad')->default(1); // Cantidad requerida de este artículo
            $table->timestamps();

            // Índices
            $table->index('promocion_especial_id', 'promo_esp_item_promo_idx');
            $table->index('articulo_id', 'promo_esp_item_art_idx');
            $table->unique(['promocion_especial_id', 'articulo_id'], 'promo_esp_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocion_especial_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-PWA Clase B — Fase 1: índice global token/código → comercio + sucursal.
 *
 * Vive en DB config (compartida) para que las pantallas públicas (llamador,
 * consultor de precios) puedan resolver el tenant SIN sesión a partir del token
 * de la URL, sin escanear los N tenants. Mismo patrón que mercadopago_collector_index.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (Modelo de Datos — Tabla nueva config).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->create('pantalla_publica_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique()->comment('Token largo no adivinable: nombra el canal Reverb y autoriza endpoints');
            $table->string('codigo_corto', 8)->unique()->comment('Código corto humano para vincular TVs (canje → token)');
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->unsignedBigInteger('sucursal_id')->comment('FK lógica cross-DB a {prefix}sucursales.id');
            $table->timestamps();

            // Un único registro por sucursal de cada comercio.
            $table->unique(['comercio_id', 'sucursal_id'], 'idx_pantalla_token_comercio_sucursal');
        });
    }

    public function down(): void
    {
        Schema::connection('config')->dropIfExists('pantalla_publica_tokens');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integraciones de Pago — Fase 1: tabla resolutora multi-tenant para webhooks MP.
 *
 * Vive en DB config (compartida) para que el webhook global pueda resolver
 * comercio + sucursal a partir del user_id MP del payload SIN escanear los N
 * tenants. Se sincroniza vía hooks de IntegracionPagoSucursal (saved/deleted).
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (RF-18, sección "Modelo
 * de Datos / Tablas nuevas (CONFIG)").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->create('mercadopago_collector_index', function (Blueprint $table) {
            $table->id();
            $table->string('user_id_externo', 100)->comment('user_id MP (collector_id en payloads de payment)');
            $table->enum('modo', ['test', 'produccion']);
            $table->foreignId('comercio_id')->constrained('comercios')->cascadeOnDelete();
            $table->unsignedBigInteger('sucursal_id')->comment('FK lógica cross-DB a {prefix}sucursales.id');
            $table->unsignedBigInteger('integracion_pago_sucursal_id')->comment('FK lógica cross-DB a {prefix}integraciones_pago_sucursales.id');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            // Un user_id MP no puede aparecer en dos sucursales/comercios para el mismo modo.
            $table->unique(['user_id_externo', 'modo'], 'idx_mp_collector_user_modo');
            $table->index(['comercio_id', 'activo'], 'idx_mp_collector_comercio_activo');
        });
    }

    public function down(): void
    {
        Schema::connection('config')->dropIfExists('mercadopago_collector_index');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RF-T66 (spec tienda-sesion-persistente): dispositivos recordados del
 * consumidor — par selector/validator rotativo (el validator se persiste
 * SOLO hasheado; el par viaja en una cookie cifrada de la tienda, nunca el
 * Bearer). BD CONFIG: la cuenta es global cross-comercio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->create('consumidor_dispositivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumidor_id')->constrained('consumidores')->cascadeOnDelete();
            $table->string('selector', 24)->unique();
            $table->char('validator_hash', 64);
            $table->string('nombre', 120)->nullable();
            $table->string('ip_ultima', 45)->nullable();
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamp('expira_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('config')->dropIfExists('consumidor_dispositivos');
    }
};

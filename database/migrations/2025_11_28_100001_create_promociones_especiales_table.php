<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promociones_especiales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();

            // Tipo: 'nxm' (2x1, 3x2, etc.) o 'combo' (pack con precio fijo)
            $table->enum('tipo', ['nxm', 'combo']);

            // Para NxM: lleva N, paga M
            $table->integer('nxm_lleva')->nullable(); // N (cantidad que lleva)
            $table->integer('nxm_paga')->nullable();  // M (cantidad que paga)

            // Para NxM: aplica a artículo o categoría específica
            $table->unsignedBigInteger('nxm_articulo_id')->nullable();
            $table->unsignedBigInteger('nxm_categoria_id')->nullable();

            // Para Combo: precio fijo del pack
            $table->decimal('combo_precio_fijo', 12, 2)->nullable();

            // Prioridad (para resolver conflictos entre promociones)
            $table->integer('prioridad')->default(1);

            // Estado
            $table->boolean('activo')->default(true);

            // Vigencia
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();

            // Restricciones de horario
            $table->text('dias_semana')->nullable(); // JSON: [1,2,3,4,5] = Lun-Vie
            $table->time('hora_desde')->nullable();
            $table->time('hora_hasta')->nullable();

            // Condiciones adicionales (igual que promociones normales)
            $table->unsignedBigInteger('forma_venta_id')->nullable();
            $table->unsignedBigInteger('canal_venta_id')->nullable();
            $table->unsignedBigInteger('forma_pago_id')->nullable();

            // Límites de uso
            $table->integer('usos_maximos')->nullable();
            $table->integer('usos_actuales')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['sucursal_id', 'activo'], 'promo_esp_sucursal_activo_idx');
            $table->index(['tipo', 'activo'], 'promo_esp_tipo_activo_idx');
            $table->index(['vigencia_desde', 'vigencia_hasta'], 'promo_esp_vigencia_idx');
            $table->index('nxm_articulo_id', 'promo_esp_articulo_idx');
            $table->index('nxm_categoria_id', 'promo_esp_categoria_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promociones_especiales');
    }
};

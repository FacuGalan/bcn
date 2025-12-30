<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconstruye las tablas de promociones especiales con la nueva estructura
     * que soporta 4 tipos:
     * - nxm: NxM básico (triggers = rewards implícito)
     * - nxm_avanzado: NxM con triggers y rewards separados
     * - combo: Combo/Pack básico (grupos de 1 artículo)
     * - menu: Menú con grupos de múltiples opciones
     */
    public function up(): void
    {
        // Eliminar tablas anteriores
        Schema::dropIfExists('promocion_especial_escalas');
        Schema::dropIfExists('promocion_especial_items');
        Schema::dropIfExists('promociones_especiales');

        // Crear tabla principal
        Schema::create('promociones_especiales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sucursal_id');
            $table->string('nombre');
            $table->text('descripcion')->nullable();

            // Tipo de promoción
            $table->enum('tipo', ['nxm', 'nxm_avanzado', 'combo', 'menu']);

            // ===== Para NxM básico (cuando triggers = rewards) =====
            // Si nxm_articulo_id está seteado, aplica a ese artículo
            // Si nxm_categoria_id está seteado, aplica a esa categoría
            // Si usa_escalas = false, usa nxm_lleva y nxm_paga directamente
            $table->integer('nxm_lleva')->nullable();
            $table->integer('nxm_paga')->nullable();
            $table->unsignedBigInteger('nxm_articulo_id')->nullable();
            $table->unsignedBigInteger('nxm_categoria_id')->nullable();
            $table->boolean('usa_escalas')->default(false);

            // ===== Para Combo/Menú =====
            // precio_tipo: 'fijo' = precio fijo, 'porcentaje' = descuento %
            $table->enum('precio_tipo', ['fijo', 'porcentaje'])->default('fijo');
            $table->decimal('precio_valor', 12, 2)->nullable(); // Precio fijo o % descuento

            // Prioridad (menor = mayor prioridad)
            $table->integer('prioridad')->default(1);

            // Estado
            $table->boolean('activo')->default(true);

            // Vigencia
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();

            // Restricciones de horario
            $table->text('dias_semana')->nullable(); // JSON: ['lunes', 'martes', ...]
            $table->time('hora_desde')->nullable();
            $table->time('hora_hasta')->nullable();

            // Condiciones adicionales
            $table->unsignedBigInteger('forma_venta_id')->nullable();
            $table->unsignedBigInteger('canal_venta_id')->nullable();
            $table->unsignedBigInteger('forma_pago_id')->nullable();

            // Límites de uso
            $table->integer('usos_maximos')->nullable();
            $table->integer('usos_actuales')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // Índices
            $table->index(['sucursal_id', 'activo'], 'promo_esp_sucursal_idx');
            $table->index(['tipo', 'activo'], 'promo_esp_tipo_idx');
            $table->index(['vigencia_desde', 'vigencia_hasta'], 'promo_esp_vigencia_idx');
            $table->index('nxm_articulo_id', 'promo_esp_articulo_idx');
            $table->index('nxm_categoria_id', 'promo_esp_categoria_idx');
        });

        // Tabla de grupos (para NxM avanzado, Combo y Menú)
        Schema::create('promocion_especial_grupos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_especial_id');

            // Nombre del grupo (para menú: "Plato principal", "Bebida", etc.)
            $table->string('nombre')->nullable();

            // Cantidad requerida de este grupo
            $table->integer('cantidad')->default(1);

            // Para NxM avanzado: define si es trigger, reward o ambos
            $table->boolean('es_trigger')->default(false);
            $table->boolean('es_reward')->default(false);

            // Orden de visualización
            $table->integer('orden')->default(0);

            $table->timestamps();

            $table->index('promocion_especial_id', 'promo_esp_grupo_promo_idx');
        });

        // Tabla de artículos por grupo
        Schema::create('promocion_especial_grupo_articulos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grupo_id');
            $table->unsignedBigInteger('articulo_id');
            $table->timestamps();

            $table->index('grupo_id', 'promo_esp_grupo_art_grupo_idx');
            $table->index('articulo_id', 'promo_esp_grupo_art_art_idx');
            $table->unique(['grupo_id', 'articulo_id'], 'promo_esp_grupo_art_unique');
        });

        // Tabla de escalas (para NxM básico y avanzado)
        Schema::create('promocion_especial_escalas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promocion_especial_id');

            // Rango de cantidad para aplicar esta escala
            $table->integer('cantidad_desde');
            $table->integer('cantidad_hasta')->nullable(); // null = sin límite

            // NxM de esta escala
            $table->integer('lleva');
            $table->integer('paga');

            $table->timestamps();

            $table->index('promocion_especial_id', 'promo_esp_escala_promo_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promocion_especial_escalas');
        Schema::dropIfExists('promocion_especial_grupo_articulos');
        Schema::dropIfExists('promocion_especial_grupos');
        Schema::dropIfExists('promociones_especiales');
    }
};

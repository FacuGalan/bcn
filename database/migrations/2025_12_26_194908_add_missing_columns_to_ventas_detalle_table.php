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
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('tipo_iva_id')->nullable()->after('articulo_id');
            $table->decimal('precio_sin_iva', 12, 2)->default(0)->after('precio_unitario');
            $table->decimal('descuento', 12, 2)->default(0)->after('precio_sin_iva');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pymes_tenant')->table('ventas_detalle', function (Blueprint $table) {
            $table->dropColumn(['tipo_iva_id', 'precio_sin_iva', 'descuento']);
        });
    }
};

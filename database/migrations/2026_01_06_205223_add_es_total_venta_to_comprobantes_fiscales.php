<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->table('comprobantes_fiscales', function (Blueprint $table) {
            $table->boolean('es_total_venta')->default(true)->after('observaciones')
                ->comment('true = factura por total de venta, false = factura parcial (mixto)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection($this->connection)->table('comprobantes_fiscales', function (Blueprint $table) {
            $table->dropColumn('es_total_venta');
        });
    }
};

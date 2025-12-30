<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            $table->string('codigo_barras', 50)->nullable()->after('codigo')->index();
        });
    }

    public function down(): void
    {
        Schema::table('articulos', function (Blueprint $table) {
            $table->dropColumn('codigo_barras');
        });
    }
};

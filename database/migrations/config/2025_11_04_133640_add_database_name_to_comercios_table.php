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
        Schema::connection('config')->table('comercios', function (Blueprint $table) {
            $table->string('database_name', 50)->default('pymes');
            $table->index('database_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('config')->table('comercios', function (Blueprint $table) {
            $table->dropIndex(['database_name']);
            $table->dropColumn('database_name');
        });
    }
};

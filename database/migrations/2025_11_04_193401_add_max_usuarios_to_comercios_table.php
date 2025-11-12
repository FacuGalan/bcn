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
            $table->unsignedInteger('max_usuarios')->default(10)->after('database_name')
                ->comment('Cantidad máxima de usuarios permitidos para este comercio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('config')->table('comercios', function (Blueprint $table) {
            $table->dropColumn('max_usuarios');
        });
    }
};

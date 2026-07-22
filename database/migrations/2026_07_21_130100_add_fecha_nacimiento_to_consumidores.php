<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->table('consumidores', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->after('telefono')
                ->comment('Cumpleaños (RF-T19): se copia del checkout para pre-llenar en otras tiendas');
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('consumidores', function (Blueprint $table) {
            $table->dropColumn('fecha_nacimiento');
        });
    }
};

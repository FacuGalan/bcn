<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('config')->table('consumidores', function (Blueprint $table) {
            // RF-T49: Sign in with Google. google_id = claim `sub` del ID
            // token (identificador estable de la cuenta Google, el email
            // puede cambiar). Las cuentas creadas via Google no tienen
            // password → pasa a nullable.
            $table->string('google_id', 64)->nullable()->unique()->after('password')
                ->comment('Sub de Google (RF-T49): cuenta linkeada a Sign in with Google');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::connection('config')->table('consumidores', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
            $table->string('password')->nullable(false)->change();
        });
    }
};

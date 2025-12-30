<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La conexión de base de datos que debe usar la migración.
     *
     * @var string
     */
    protected $connection = 'config';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::connection('config')->table('users', function (Blueprint $table) {
                $table->boolean('dark_mode')->default(false)->after('activo');
            });
        } catch (\Exception $e) {
            // Column already exists, skip
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('config')->table('users', function (Blueprint $table) {
            $table->dropColumn('dark_mode');
        });
    }
};

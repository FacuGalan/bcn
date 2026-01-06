<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea las tablas compartidas (sin prefijo de tenant)
 * Incluye: permissions, menu_items, permisos_funcionales
 */
return new class extends Migration
{
    protected $connection = 'pymes';

    public function up(): void
    {
        $sqlFile = database_path('sql/shared_tables.sql');
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            DB::connection($this->connection)->unprepared($sql);
        }
    }

    public function down(): void
    {
        DB::connection($this->connection)->statement('SET FOREIGN_KEY_CHECKS=0');
        DB::connection($this->connection)->statement('DROP TABLE IF EXISTS `menu_items`');
        DB::connection($this->connection)->statement('DROP TABLE IF EXISTS `permisos_funcionales`');
        DB::connection($this->connection)->statement('DROP TABLE IF EXISTS `permissions`');
        DB::connection($this->connection)->statement('SET FOREIGN_KEY_CHECKS=1');
    }
};
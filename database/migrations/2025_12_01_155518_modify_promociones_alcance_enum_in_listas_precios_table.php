<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = DB::connection('pymes_tenant');
        $prefix = $connection->getTablePrefix();
        $tableName = $prefix . 'listas_precios';

        // Primero convertir valores existentes
        $connection->statement("UPDATE `{$tableName}` SET promociones_alcance = 'todos' WHERE promociones_alcance = 'ninguno'");
        $connection->statement("UPDATE `{$tableName}` SET promociones_alcance = 'excluir_lista' WHERE promociones_alcance = 'solo_lista'");

        // Modificar el enum
        $connection->statement("ALTER TABLE `{$tableName}` MODIFY COLUMN promociones_alcance ENUM('todos', 'excluir_lista') DEFAULT 'todos'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = DB::connection('pymes_tenant');
        $prefix = $connection->getTablePrefix();
        $tableName = $prefix . 'listas_precios';

        // Revertir el enum
        $connection->statement("ALTER TABLE `{$tableName}` MODIFY COLUMN promociones_alcance ENUM('todos', 'solo_lista', 'ninguno') DEFAULT 'todos'");

        // Convertir valores de vuelta
        $connection->statement("UPDATE `{$tableName}` SET promociones_alcance = 'solo_lista' WHERE promociones_alcance = 'excluir_lista'");
    }
};

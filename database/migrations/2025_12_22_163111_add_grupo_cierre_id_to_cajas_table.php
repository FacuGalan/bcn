<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración para agregar grupo_cierre_id a la tabla cajas
 *
 * Si grupo_cierre_id es null, la caja cierra de forma individual.
 * Si tiene un valor, la caja comparte cierre con las demás cajas del mismo grupo.
 */
return new class extends Migration
{
    protected $connection = 'pymes_tenant';

    public function up(): void
    {
        Schema::connection($this->connection)->table('cajas', function (Blueprint $table) {
            $table->foreignId('grupo_cierre_id')
                ->nullable()
                ->after('monto_fijo_inicial')
                ->constrained('grupos_cierre')
                ->onDelete('set null')
                ->comment('Grupo de cierre compartido (null = cierra individual)');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('cajas', function (Blueprint $table) {
            $table->dropForeign(['grupo_cierre_id']);
            $table->dropColumn('grupo_cierre_id');
        });
    }
};

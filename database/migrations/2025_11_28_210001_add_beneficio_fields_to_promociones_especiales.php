<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Verifica si una columna existe usando consulta directa (compatible con MariaDB)
     */
    protected function columnExists(string $table, string $column): bool
    {
        $prefix = DB::connection('pymes_tenant')->getTablePrefix();
        $fullTable = $prefix . $table;

        $result = DB::select("SHOW COLUMNS FROM `{$fullTable}` LIKE '{$column}'");
        return count($result) > 0;
    }

    /**
     * Agrega campos para el tipo de beneficio en promociones NxM
     * Cambia la semántica de "lleva N paga M" a "lleva N, bonifica M con X% descuento"
     *
     * beneficio_tipo: 'gratis' (100% dto) o 'descuento' (porcentaje configurable)
     * beneficio_porcentaje: porcentaje de descuento cuando tipo es 'descuento'
     */
    public function up(): void
    {
        // Agregar campos a la tabla principal (si no existen)
        if (!$this->columnExists('promociones_especiales', 'nxm_bonifica')) {
            Schema::table('promociones_especiales', function (Blueprint $table) {
                $table->integer('nxm_bonifica')->nullable()->after('nxm_paga');
            });
        }

        if (!$this->columnExists('promociones_especiales', 'beneficio_tipo')) {
            Schema::table('promociones_especiales', function (Blueprint $table) {
                $table->enum('beneficio_tipo', ['gratis', 'descuento'])->default('gratis')->after('nxm_bonifica');
            });
        }

        if (!$this->columnExists('promociones_especiales', 'beneficio_porcentaje')) {
            Schema::table('promociones_especiales', function (Blueprint $table) {
                $table->decimal('beneficio_porcentaje', 5, 2)->nullable()->after('beneficio_tipo');
            });
        }

        // Convertir datos existentes a nueva semántica
        // bonifica = lleva - paga (la cantidad que se bonifica, no la que se paga)
        DB::connection('pymes_tenant')
            ->table('promociones_especiales')
            ->whereNotNull('nxm_paga')
            ->whereNotNull('nxm_lleva')
            ->whereNull('nxm_bonifica')
            ->update([
                'nxm_bonifica' => DB::raw('nxm_lleva - nxm_paga')
            ]);

        // Agregar campos a la tabla de escalas (si no existen)
        if (!$this->columnExists('promocion_especial_escalas', 'bonifica')) {
            Schema::table('promocion_especial_escalas', function (Blueprint $table) {
                $table->integer('bonifica')->nullable()->after('paga');
            });
        }

        if (!$this->columnExists('promocion_especial_escalas', 'beneficio_tipo')) {
            Schema::table('promocion_especial_escalas', function (Blueprint $table) {
                $table->enum('beneficio_tipo', ['gratis', 'descuento'])->default('gratis')->after('bonifica');
            });
        }

        if (!$this->columnExists('promocion_especial_escalas', 'beneficio_porcentaje')) {
            Schema::table('promocion_especial_escalas', function (Blueprint $table) {
                $table->decimal('beneficio_porcentaje', 5, 2)->nullable()->after('beneficio_tipo');
            });
        }

        // Convertir escalas: bonifica = lleva - paga
        DB::connection('pymes_tenant')
            ->table('promocion_especial_escalas')
            ->whereNotNull('paga')
            ->whereNotNull('lleva')
            ->whereNull('bonifica')
            ->update([
                'bonifica' => DB::raw('lleva - paga')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->columnExists('promocion_especial_escalas', 'bonifica')) {
            Schema::table('promocion_especial_escalas', function (Blueprint $table) {
                $table->dropColumn('bonifica');
            });
        }
        if ($this->columnExists('promocion_especial_escalas', 'beneficio_tipo')) {
            Schema::table('promocion_especial_escalas', function (Blueprint $table) {
                $table->dropColumn('beneficio_tipo');
            });
        }
        if ($this->columnExists('promocion_especial_escalas', 'beneficio_porcentaje')) {
            Schema::table('promocion_especial_escalas', function (Blueprint $table) {
                $table->dropColumn('beneficio_porcentaje');
            });
        }

        if ($this->columnExists('promociones_especiales', 'nxm_bonifica')) {
            Schema::table('promociones_especiales', function (Blueprint $table) {
                $table->dropColumn('nxm_bonifica');
            });
        }
        if ($this->columnExists('promociones_especiales', 'beneficio_tipo')) {
            Schema::table('promociones_especiales', function (Blueprint $table) {
                $table->dropColumn('beneficio_tipo');
            });
        }
        if ($this->columnExists('promociones_especiales', 'beneficio_porcentaje')) {
            Schema::table('promociones_especiales', function (Blueprint $table) {
                $table->dropColumn('beneficio_porcentaje');
            });
        }
    }
};

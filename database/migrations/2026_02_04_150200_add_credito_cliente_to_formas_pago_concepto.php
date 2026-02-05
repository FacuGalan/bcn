<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega 'credito_cliente' al ENUM de concepto en formas_pago
     * para permitir formas de pago de tipo cuenta corriente.
     */
    public function up(): void
    {
        // Obtener todos los comercios
        $comercios = DB::connection('config')
            ->table('comercios')
            ->select('id')
            ->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad((string) $comercio->id, 6, '0', STR_PAD_LEFT) . '_';
            $tableName = $prefix . 'formas_pago';

            try {
                // Modificar el ENUM para incluir credito_cliente
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$tableName}`
                    MODIFY COLUMN `concepto` ENUM(
                        'efectivo',
                        'tarjeta_debito',
                        'tarjeta_credito',
                        'transferencia',
                        'wallet',
                        'cheque',
                        'credito_cliente',
                        'otro'
                    ) NOT NULL DEFAULT 'otro'
                ");
            } catch (\Exception $e) {
                // Si falla para un comercio, continuar con el siguiente
                continue;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Obtener todos los comercios
        $comercios = DB::connection('config')
            ->table('comercios')
            ->select('id')
            ->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad((string) $comercio->id, 6, '0', STR_PAD_LEFT) . '_';
            $tableName = $prefix . 'formas_pago';

            try {
                // Primero, actualizar registros con credito_cliente a 'otro'
                DB::connection('pymes')
                    ->table($tableName)
                    ->where('concepto', 'credito_cliente')
                    ->update(['concepto' => 'otro']);

                // Revertir el ENUM
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$tableName}`
                    MODIFY COLUMN `concepto` ENUM(
                        'efectivo',
                        'tarjeta_debito',
                        'tarjeta_credito',
                        'transferencia',
                        'wallet',
                        'cheque',
                        'otro'
                    ) NOT NULL DEFAULT 'otro'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

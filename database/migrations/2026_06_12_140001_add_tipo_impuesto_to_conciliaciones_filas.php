<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación: tipo de fila `impuesto` para impuestos/retenciones que el
 * proveedor descuenta. Lo generan las filas tax_* del reporte y el residuo
 * bruto - comisión - neto de cobros matcheados.
 */
return new class extends Migration
{
    private const ENUM_CON_IMPUESTO = "enum('cobro','comision','impuesto','devolucion','contracargo','retiro','retiro_cancelado','acreditacion','ajuste_inicial','otro')";

    private const ENUM_SIN_IMPUESTO = "enum('cobro','comision','devolucion','contracargo','retiro','retiro_cancelado','acreditacion','ajuste_inicial','otro')";

    public function up(): void
    {
        $this->modificarEnum(self::ENUM_CON_IMPUESTO);
    }

    public function down(): void
    {
        $this->modificarEnum(self::ENUM_SIN_IMPUESTO);
    }

    private function modificarEnum(string $enum): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')->statement("
                    ALTER TABLE `{$prefix}conciliaciones_filas`
                    MODIFY `tipo` {$enum} COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tipo normalizado provider-agnostic'
                ");
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

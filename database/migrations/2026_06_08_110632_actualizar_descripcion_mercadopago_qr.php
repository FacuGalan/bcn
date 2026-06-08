<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Integraciones de Pago — corrige la descripción del catálogo `mercadopago_qr`.
 *
 * Motivo: el texto anterior decía "QR dinámico (monto fijo) y QR estático
 * (monto libre)", lo cual es engañoso: ambos modos cobran un monto definido
 * y enviado desde el sistema. Se aclara la redacción.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md
 */
return new class extends Migration
{
    private string $descripcionNueva = 'Cobros con Mercado Pago: QR dinámico y QR estático, ambos con monto definido (enviado desde el sistema).';

    private string $descripcionVieja = 'Cobros con Mercado Pago: QR dinámico (monto fijo) y QR estático (monto libre).';

    public function up(): void
    {
        $this->actualizarDescripcion($this->descripcionVieja, $this->descripcionNueva);
    }

    public function down(): void
    {
        $this->actualizarDescripcion($this->descripcionNueva, $this->descripcionVieja);
    }

    private function actualizarDescripcion(string $desde, string $hacia): void
    {
        $comercios = DB::connection('config')->table('comercios')->get();

        foreach ($comercios as $comercio) {
            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';

            try {
                DB::connection('pymes')
                    ->table($prefix.'integraciones_pago')
                    ->where('codigo', 'mercadopago_qr')
                    ->where('descripcion', $desde)
                    ->update(['descripcion' => $hacia]);
            } catch (\Exception $e) {
                continue;
            }
        }
    }
};

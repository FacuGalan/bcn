<?php

namespace Tests\Unit\Services;

use App\Services\ARCA\ComprobanteFiscalService;
use Tests\TestCase;

/**
 * RF-V4 (hardening fiscal saliente, tanda 2): clasificación única del 0%.
 * La rama que consume el desglose armado por el frontend debe aplicar la MISMA
 * regla que calcularDetallesIva: alícuota 0% ⇒ EXENTO (ImpOpEx), fuera de
 * AlicIva y del neto gravado. Antes acumulaba todo en neto_gravado y el mismo
 * ítem 0% quedaba clasificado distinto según el camino de emisión.
 *
 * Lógica pura (sin BD ni AFIP): se expone el método protegido vía subclase.
 */
class ComprobanteFiscalClasificacionTest extends TestCase
{
    private function service(): object
    {
        return new class extends ComprobanteFiscalService
        {
            public function clasificar(array $desglose): array
            {
                return $this->clasificarDesgloseFrontend($desglose);
            }
        };
    }

    public function test_alicuota_cero_va_a_exento_no_a_gravado(): void
    {
        $detalles = $this->service()->clasificar([
            'por_alicuota' => [
                ['alicuota' => 21, 'neto' => 100.0, 'iva' => 21.0],
                ['alicuota' => 0, 'neto' => 50.0, 'iva' => 0.0],
            ],
        ]);

        $this->assertSame(100.0, $detalles['neto_gravado']);
        $this->assertSame(50.0, $detalles['neto_exento']);
        $this->assertSame(21.0, $detalles['iva_total']);

        // AlicIva solo lleva alícuotas gravadas (la fila 0% no se informa).
        $this->assertCount(1, $detalles['alicuotas']);
        $this->assertSame(21, $detalles['alicuotas'][0]['porcentaje']);
    }

    public function test_desglose_todo_exento_no_genera_alicuotas(): void
    {
        $detalles = $this->service()->clasificar([
            'por_alicuota' => [
                ['alicuota' => 0, 'neto' => 100.0, 'iva' => 0.0],
            ],
        ]);

        $this->assertSame(0.0, $detalles['neto_gravado']);
        $this->assertSame(100.0, $detalles['neto_exento']);
        $this->assertSame(0.0, $detalles['iva_total']);
        $this->assertSame([], $detalles['alicuotas']);
    }

    /** Sin exentos el comportamiento es idéntico al previo (regresión). */
    public function test_desglose_solo_gravado_sin_cambios(): void
    {
        $detalles = $this->service()->clasificar([
            'por_alicuota' => [
                ['alicuota' => 21, 'neto' => 100.0, 'iva' => 21.0],
                ['alicuota' => 10.5, 'neto' => 200.0, 'iva' => 21.0],
            ],
        ]);

        $this->assertSame(300.0, $detalles['neto_gravado']);
        $this->assertSame(0.0, $detalles['neto_exento']);
        $this->assertSame(42.0, $detalles['iva_total']);
        $this->assertCount(2, $detalles['alicuotas']);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Services\Pedidos\AsignadorBasesAjustePagos;
use PHPUnit\Framework\TestCase;

/**
 * Regla "bienes-primero con tope" (RF-03, spec
 * multi-pago-consistente-y-panel-delivery): calculadora pura, sin BD.
 */
class AsignadorBasesAjustePagosTest extends TestCase
{
    public function test_pago_unico_que_cubre_todo_toma_los_bienes_completos(): void
    {
        $pagos = AsignadorBasesAjustePagos::asignar(
            [['monto' => 1950.0, 'ajuste_porcentaje' => -10.0]],
            950.0,
        );

        $this->assertEqualsWithDelta(950.0, $pagos[0]['base_ajuste'], 0.001);
    }

    public function test_el_pago_con_descuento_absorbe_los_bienes_con_tope_en_su_monto(): void
    {
        // Caso del spec: bienes $950 + envío $1000. Efectivo $1000 (−10%) y
        // transferencia $950 (0%): el efectivo cubre TODOS los bienes (950,
        // no un prorrateo) y la transferencia queda en el envío.
        $pagos = AsignadorBasesAjustePagos::asignar([
            ['monto' => 1000.0, 'ajuste_porcentaje' => -10.0],
            ['monto' => 950.0, 'ajuste_porcentaje' => 0.0],
        ], 950.0);

        $this->assertEqualsWithDelta(950.0, $pagos[0]['base_ajuste'], 0.001);
        $this->assertEqualsWithDelta(0.0, $pagos[1]['base_ajuste'], 0.001);
    }

    public function test_bienes_restantes_van_al_segundo_pago(): void
    {
        $pagos = AsignadorBasesAjustePagos::asignar([
            ['monto' => 600.0, 'ajuste_porcentaje' => -10.0],
            ['monto' => 400.0, 'ajuste_porcentaje' => 0.0],
        ], 1000.0);

        $this->assertEqualsWithDelta(600.0, $pagos[0]['base_ajuste'], 0.001);
        $this->assertEqualsWithDelta(400.0, $pagos[1]['base_ajuste'], 0.001);
    }

    public function test_es_orden_independiente(): void
    {
        $directo = AsignadorBasesAjustePagos::asignar([
            ['id' => 'efectivo', 'monto' => 1000.0, 'ajuste_porcentaje' => -10.0],
            ['id' => 'transferencia', 'monto' => 950.0, 'ajuste_porcentaje' => 0.0],
        ], 950.0);
        $invertido = AsignadorBasesAjustePagos::asignar([
            ['id' => 'transferencia', 'monto' => 950.0, 'ajuste_porcentaje' => 0.0],
            ['id' => 'efectivo', 'monto' => 1000.0, 'ajuste_porcentaje' => -10.0],
        ], 950.0);

        $baseDe = fn (array $pagos, string $id) => collect($pagos)->firstWhere('id', $id)['base_ajuste'];

        $this->assertEqualsWithDelta($baseDe($directo, 'efectivo'), $baseDe($invertido, 'efectivo'), 0.001);
        $this->assertEqualsWithDelta($baseDe($directo, 'transferencia'), $baseDe($invertido, 'transferencia'), 0.001);
    }

    public function test_dos_descuentos_nunca_superan_los_bienes(): void
    {
        // Edge del spec (D1): bases 900 + 50 = 950 (el min() ingenuo daría
        // 900 + 950 = 1850 y descontaría dos veces la misma mercadería).
        $pagos = AsignadorBasesAjustePagos::asignar([
            ['monto' => 900.0, 'ajuste_porcentaje' => -10.0],
            ['monto' => 1050.0, 'ajuste_porcentaje' => -5.0],
        ], 950.0);

        $this->assertEqualsWithDelta(900.0, $pagos[0]['base_ajuste'], 0.001);
        $this->assertEqualsWithDelta(50.0, $pagos[1]['base_ajuste'], 0.001);
    }

    public function test_el_recargo_recibe_bienes_al_final(): void
    {
        // Pro-cliente: el descuento absorbe bienes primero; el recargo solo
        // recibe lo que sobra.
        $pagos = AsignadorBasesAjustePagos::asignar([
            ['monto' => 600.0, 'ajuste_porcentaje' => 10.0],
            ['monto' => 400.0, 'ajuste_porcentaje' => -10.0],
        ], 500.0);

        $this->assertEqualsWithDelta(100.0, $pagos[0]['base_ajuste'], 0.001);
        $this->assertEqualsWithDelta(400.0, $pagos[1]['base_ajuste'], 0.001);
    }

    public function test_sin_bienes_todas_las_bases_son_cero(): void
    {
        $pagos = AsignadorBasesAjustePagos::asignar([
            ['monto' => 600.0, 'ajuste_porcentaje' => -10.0],
            ['monto' => 400.0, 'ajuste_porcentaje' => 0.0],
        ], 0.0);

        $this->assertEqualsWithDelta(0.0, $pagos[0]['base_ajuste'], 0.001);
        $this->assertEqualsWithDelta(0.0, $pagos[1]['base_ajuste'], 0.001);
    }

    public function test_preserva_claves_extra_y_el_orden_original(): void
    {
        $pagos = AsignadorBasesAjustePagos::asignar([
            ['forma_pago_id' => 7, 'nombre' => 'Tarjeta', 'monto' => 500.0, 'ajuste_porcentaje' => 5.0],
            ['forma_pago_id' => 3, 'nombre' => 'Efectivo', 'monto' => 500.0, 'ajuste_porcentaje' => -10.0],
        ], 800.0);

        $this->assertSame(7, $pagos[0]['forma_pago_id'], 'El orden de salida es el de entrada');
        $this->assertSame('Tarjeta', $pagos[0]['nombre']);
        $this->assertEqualsWithDelta(300.0, $pagos[0]['base_ajuste'], 0.001);
        $this->assertEqualsWithDelta(500.0, $pagos[1]['base_ajuste'], 0.001);
    }
}

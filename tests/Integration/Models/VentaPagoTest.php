<?php

namespace Tests\Integration\Models;

use App\Models\FormaPago;
use App\Models\Venta;
use App\Models\VentaPago;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class VentaPagoTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Crea una Venta + FormaPago + VentaPago para tests que lo necesiten.
     */
    private function crearVentaConPago(array $pagoOverrides = []): VentaPago
    {
        $venta = $this->crearVentaBasica();
        $fpData = $this->crearFormaPagoEfectivo();

        return VentaPago::create(array_merge([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fpData['formaPago']->id,
            'concepto_pago_id' => $fpData['concepto']->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ], $pagoOverrides));
    }

    public function test_calcular_monto_con_ajuste_recargo(): void
    {
        $resultado = VentaPago::calcularMontoConAjuste(1000, 10);

        $this->assertEquals(1000, $resultado['monto_base']);
        $this->assertEquals(10, $resultado['ajuste_porcentaje']);
        $this->assertEquals(100, $resultado['monto_ajuste']);
        $this->assertEquals(1100, $resultado['monto_final']);
    }

    public function test_calcular_monto_con_ajuste_descuento(): void
    {
        $resultado = VentaPago::calcularMontoConAjuste(1000, -5);

        $this->assertEquals(1000, $resultado['monto_base']);
        $this->assertEquals(-5, $resultado['ajuste_porcentaje']);
        $this->assertEquals(-50, $resultado['monto_ajuste']);
        $this->assertEquals(950, $resultado['monto_final']);
    }

    public function test_calcular_monto_con_cuotas(): void
    {
        $resultado = VentaPago::calcularMontoConCuotas(1000, 3, 15);

        $this->assertEquals(3, $resultado['cuotas']);
        $this->assertEquals(15, $resultado['recargo_cuotas_porcentaje']);
        $this->assertEquals(150, $resultado['recargo_cuotas_monto']);
        $this->assertEquals(383.33, $resultado['monto_cuota']);
        $this->assertEquals(1150, $resultado['monto_total']);
    }

    public function test_aplicar_cobro_reduce_saldo(): void
    {
        $pago = $this->crearVentaConPago([
            'monto_final' => 1000,
            'saldo_pendiente' => 1000,
            'es_cuenta_corriente' => true,
        ]);

        $nuevoSaldo = $pago->aplicarCobro(300);

        $this->assertEquals(700, $nuevoSaldo);
        $pago->refresh();
        $this->assertEquals('700.00', $pago->saldo_pendiente);
    }

    public function test_aplicar_cobro_no_supera_saldo(): void
    {
        $pago = $this->crearVentaConPago([
            'monto_final' => 1000,
            'saldo_pendiente' => 1000,
            'es_cuenta_corriente' => true,
        ]);

        $nuevoSaldo = $pago->aplicarCobro(1500);

        $this->assertEquals(0, $nuevoSaldo);
        $pago->refresh();
        $this->assertEquals('0.00', $pago->saldo_pendiente);
    }

    public function test_revertir_cobro_aumenta_saldo(): void
    {
        $pago = $this->crearVentaConPago([
            'monto_final' => 1000,
            'saldo_pendiente' => 500,
            'es_cuenta_corriente' => true,
        ]);

        $nuevoSaldo = $pago->revertirCobro(300);

        $this->assertEquals(800, $nuevoSaldo);
        $pago->refresh();
        $this->assertEquals('800.00', $pago->saldo_pendiente);
    }

    public function test_revertir_cobro_no_supera_monto_final(): void
    {
        $pago = $this->crearVentaConPago([
            'monto_final' => 1000,
            'saldo_pendiente' => 800,
            'es_cuenta_corriente' => true,
        ]);

        $nuevoSaldo = $pago->revertirCobro(500);

        $this->assertEquals(1000, $nuevoSaldo);
        $pago->refresh();
        $this->assertEquals('1000.00', $pago->saldo_pendiente);
    }

    public function test_scope_cuenta_corriente(): void
    {
        $this->crearVentaConPago(['es_cuenta_corriente' => true]);
        $this->crearVentaConPago(['es_cuenta_corriente' => true]);
        $this->crearVentaConPago(['es_cuenta_corriente' => false]);

        $cc = VentaPago::cuentaCorriente()->count();

        $this->assertEquals(2, $cc);
    }

    public function test_scope_con_saldo_pendiente(): void
    {
        $this->crearVentaConPago(['saldo_pendiente' => 500]);
        $this->crearVentaConPago(['saldo_pendiente' => 100]);
        $this->crearVentaConPago(['saldo_pendiente' => 0]);

        $conSaldo = VentaPago::conSaldoPendiente()->count();

        $this->assertEquals(2, $conSaldo);
    }

    public function test_tiene_cuotas_true_con_mas_de_una(): void
    {
        $pago = $this->crearVentaConPago(['cuotas' => 3]);

        $this->assertTrue($pago->tieneCuotas());
    }

    public function test_es_recargo_true_ajuste_positivo(): void
    {
        $pago = $this->crearVentaConPago(['ajuste_porcentaje' => 10]);

        $this->assertTrue($pago->esRecargo());
        $this->assertFalse($pago->esDescuento());
    }

    public function test_es_descuento_true_ajuste_negativo(): void
    {
        $pago = $this->crearVentaConPago(['ajuste_porcentaje' => -5]);

        $this->assertTrue($pago->esDescuento());
        $this->assertFalse($pago->esRecargo());
    }
}

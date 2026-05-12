<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorDetalle;
use App\Models\Sucursal;
use App\Services\Impresion\PlantillasComanda;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests de PlantillasComanda. Cubre que el contenido ESC/POS y HTML refleja
 * los datos del pedido (encabezado, items, observaciones, beeper, totales).
 *
 * No probamos la transmisión a impresora — solo la generación de contenido.
 */
class PlantillasComandaTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected PlantillasComanda $plantillas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->plantillas = new PlantillasComanda;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_comanda_escpos_incluye_numero_pedido_y_items(): void
    {
        $pedido = $this->pedidoConItem('Hamburguesa', 2);

        $contenido = $this->plantillas->generarComandaESCPOS($pedido);

        $this->assertStringContainsString('Pedido #'.$pedido->numero, $contenido);
        $this->assertStringContainsString('2 x Hamburguesa', $contenido);
        $this->assertStringStartsWith("\x1B\x40", $contenido, 'Debe iniciar con ESC @');
        $this->assertStringEndsWith("\x1D\x56\x00", $contenido, 'Debe terminar con corte de papel');
    }

    public function test_comanda_escpos_muestra_beeper_si_sucursal_lo_usa(): void
    {
        Sucursal::find($this->sucursalId)->update(['usa_beepers' => true]);

        $pedido = $this->pedidoConItem('Café', 1);
        $pedido->update(['numero_beeper' => '42']);

        $contenido = $this->plantillas->generarComandaESCPOS($pedido->fresh());

        $this->assertStringContainsString('BEEPER 42', $contenido);
    }

    public function test_comanda_escpos_no_muestra_beeper_si_sucursal_no_lo_usa(): void
    {
        Sucursal::find($this->sucursalId)->update(['usa_beepers' => false]);

        $pedido = $this->pedidoConItem('Café', 1);
        $pedido->update(['numero_beeper' => '42']);

        $contenido = $this->plantillas->generarComandaESCPOS($pedido->fresh());

        $this->assertStringNotContainsString('BEEPER', $contenido);
    }

    public function test_comanda_html_envuelve_items_y_titulo(): void
    {
        $pedido = $this->pedidoConItem('Pizza', 1);

        $html = $this->plantillas->generarComandaHTML($pedido);

        $this->assertStringContainsString('Pizza', $html);
        $this->assertStringContainsString('Pedido #'.$pedido->numero, $html);
        $this->assertStringContainsString('<div class="comanda"', $html);
    }

    public function test_precuenta_escpos_muestra_total_final(): void
    {
        $pedido = $this->pedidoConItem('Empanada', 3, totalFinal: 4500.55);

        $contenido = $this->plantillas->generarPrecuentaESCPOS($pedido);

        $this->assertStringContainsString('PRECUENTA', $contenido);
        $this->assertStringContainsString('TOTAL', $contenido);
        $this->assertStringContainsString('4.500,55', $contenido);
    }

    public function test_precuenta_html_incluye_total_y_subtotal(): void
    {
        $pedido = $this->pedidoConItem('Combo', 1, totalFinal: 1234.50);

        $html = $this->plantillas->generarPrecuentaHTML($pedido);

        $this->assertStringContainsString('TOTAL', $html);
        $this->assertStringContainsString('1.234,50', $html);
    }

    private function pedidoConItem(string $nombreItem, float $cantidad, float $totalFinal = 1000): PedidoMostrador
    {
        $pedido = PedidoMostrador::create([
            'numero' => 42,
            'sucursal_id' => $this->sucursalId,
            'usuario_id' => 1,
            'fecha' => now(),
            'estado_pedido' => PedidoMostrador::ESTADO_CONFIRMADO,
            'estado_pago' => PedidoMostrador::ESTADO_PAGO_PENDIENTE,
            'subtotal' => $totalFinal,
            'iva' => 0,
            'descuento' => 0,
            'total' => $totalFinal,
            'total_final' => $totalFinal,
            'ajuste_forma_pago' => 0,
        ]);

        PedidoMostradorDetalle::create([
            'pedido_mostrador_id' => $pedido->id,
            'es_concepto' => true,
            'concepto_descripcion' => $nombreItem,
            'cantidad' => $cantidad,
            'precio_unitario' => $totalFinal / max($cantidad, 1),
            'subtotal' => $totalFinal,
            'total' => $totalFinal,
        ]);

        return $pedido->fresh(['detalles', 'sucursal']);
    }
}

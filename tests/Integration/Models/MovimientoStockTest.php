<?php

namespace Tests\Integration\Models;

use App\Models\MovimientoStock;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Tests\TestCase;
use Tests\Traits\WithTenant;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithCaja;
use Tests\Traits\WithVentaHelpers;

class MovimientoStockTest extends TestCase
{
    use WithTenant, WithSucursal, WithCaja, WithVentaHelpers;

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

    /** @test */
    public function crear_movimiento_venta_registra_salida(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);
        $venta = $this->crearVentaBasica(['_articulo' => $articulo]);
        $ventaDetalle = VentaDetalle::where('venta_id', $venta->id)->first();

        $movimiento = MovimientoStock::crearMovimientoVenta(
            $articulo->id,
            $this->sucursalId,
            5,
            $venta->id,
            $ventaDetalle->id,
            'Venta de prueba',
            1
        );

        $this->assertEquals('0.00', $movimiento->entrada);
        $this->assertEquals('5.00', $movimiento->salida);
        $this->assertEquals(MovimientoStock::TIPO_VENTA, $movimiento->tipo);
        $this->assertEquals('activo', $movimiento->estado);
        $this->assertEquals($venta->id, $movimiento->venta_id);
        $this->assertEquals($ventaDetalle->id, $movimiento->venta_detalle_id);
    }

    /** @test */
    public function crear_movimiento_anulacion_venta_registra_entrada(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);
        $venta = $this->crearVentaBasica(['_articulo' => $articulo]);
        $ventaDetalle = VentaDetalle::where('venta_id', $venta->id)->first();

        $movimiento = MovimientoStock::crearMovimientoAnulacionVenta(
            $articulo->id,
            $this->sucursalId,
            5,
            $venta->id,
            $ventaDetalle->id,
            'Anulacion venta de prueba',
            1
        );

        $this->assertEquals('5.00', $movimiento->entrada);
        $this->assertEquals('0.00', $movimiento->salida);
        $this->assertEquals(MovimientoStock::TIPO_ANULACION_VENTA, $movimiento->tipo);
        $this->assertEquals('activo', $movimiento->estado);
    }

    /** @test */
    public function crear_movimiento_ajuste_positivo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);

        $movimiento = MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            5,
            'Ajuste positivo de prueba',
            1
        );

        $this->assertEquals('5.00', $movimiento->entrada);
        $this->assertEquals('0.00', $movimiento->salida);
        $this->assertEquals(MovimientoStock::TIPO_AJUSTE_MANUAL, $movimiento->tipo);
    }

    /** @test */
    public function crear_movimiento_ajuste_negativo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);

        $movimiento = MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            -3,
            'Ajuste negativo de prueba',
            1
        );

        $this->assertEquals('0.00', $movimiento->entrada);
        $this->assertEquals('3.00', $movimiento->salida);
        $this->assertEquals(MovimientoStock::TIPO_AJUSTE_MANUAL, $movimiento->tipo);
    }

    /** @test */
    public function calcular_stock_suma_entradas_menos_salidas(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);

        // Ajuste +10 (entrada)
        MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            10,
            'Entrada de prueba',
            1
        );

        // Venta -3 (salida) - necesita venta real
        $venta = $this->crearVentaBasica(['_articulo' => $articulo]);
        $ventaDetalle = VentaDetalle::where('venta_id', $venta->id)->first();

        MovimientoStock::crearMovimientoVenta(
            $articulo->id,
            $this->sucursalId,
            3,
            $venta->id,
            $ventaDetalle->id,
            'Venta de prueba',
            1
        );

        $stockCalculado = MovimientoStock::calcularStock($articulo->id, $this->sucursalId);

        // 10 entrada - 3 salida = 7
        $this->assertEquals(7, $stockCalculado);
    }

    /** @test */
    public function calcular_stock_a_fecha(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);

        // Movimiento de ayer
        $movAyer = MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            10,
            'Ajuste de ayer',
            1
        );
        $movAyer->update(['fecha' => now()->subDay()->toDateString()]);

        // Movimiento de hoy
        MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            5,
            'Ajuste de hoy',
            1
        );

        // Stock a ayer solo debe incluir el movimiento de ayer
        $stockAyer = MovimientoStock::calcularStockAFecha(
            $articulo->id,
            $this->sucursalId,
            now()->subDay()->toDateString()
        );
        $this->assertEquals(10, $stockAyer);

        // Stock a hoy debe incluir ambos
        $stockHoy = MovimientoStock::calcularStockAFecha(
            $articulo->id,
            $this->sucursalId,
            now()->toDateString()
        );
        $this->assertEquals(15, $stockHoy);
    }

    /** @test */
    public function crear_contraasiento_invierte_montos(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);
        $venta = $this->crearVentaBasica(['_articulo' => $articulo]);
        $ventaDetalle = VentaDetalle::where('venta_id', $venta->id)->first();

        // Crear movimiento de venta (salida = 5)
        $movOriginal = MovimientoStock::crearMovimientoVenta(
            $articulo->id,
            $this->sucursalId,
            5,
            $venta->id,
            $ventaDetalle->id,
            'Venta de prueba',
            1
        );

        $this->assertEquals('0.00', $movOriginal->entrada);
        $this->assertEquals('5.00', $movOriginal->salida);

        // Crear contraasiento
        $contraasiento = MovimientoStock::crearContraasiento(
            $movOriginal,
            'Anulacion por error',
            1
        );

        // Contraasiento invierte: entrada=5, salida=0
        $this->assertEquals('5.00', $contraasiento->entrada);
        $this->assertEquals('0.00', $contraasiento->salida);
        $this->assertEquals(MovimientoStock::TIPO_ANULACION_VENTA, $contraasiento->tipo);

        // El original queda vinculado al contraasiento
        $movOriginal->refresh();
        $this->assertEquals($contraasiento->id, $movOriginal->anulado_por_movimiento_id);
    }

    /** @test */
    public function scope_activos_excluye_anulados(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100);

        // Crear 2 movimientos activos
        MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            10,
            'Ajuste 1',
            1
        );

        MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            5,
            'Ajuste 2',
            1
        );

        // Crear 1 movimiento y marcarlo como anulado manualmente
        $movAnulado = MovimientoStock::crearMovimientoAjuste(
            $articulo->id,
            $this->sucursalId,
            3,
            'Ajuste anulado',
            1
        );
        $movAnulado->update(['estado' => 'anulado']);

        // Scope activos solo debe traer 2
        $activos = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->activos()
            ->count();

        $this->assertEquals(2, $activos);

        // Scope anulados solo debe traer 1
        $anulados = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->anulados()
            ->count();

        $this->assertEquals(1, $anulados);
    }
}

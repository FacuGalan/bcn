<?php

namespace Tests\Integration\Models;

use App\Models\Stock;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class StockTest extends TestCase
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
     * Obtiene el registro de Stock del artículo recién creado.
     */
    private function obtenerStock(int $articuloId): Stock
    {
        return Stock::where('articulo_id', $articuloId)
            ->where('sucursal_id', $this->sucursalId)
            ->firstOrFail();
    }

    public function test_aumentar_incrementa_stock(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = $this->obtenerStock($articulo->id);

        $resultado = $stock->aumentar(5);

        $this->assertTrue($resultado);
        $stock->refresh();
        $this->assertEquals('15.00', $stock->cantidad);
    }

    public function test_disminuir_decrementa_stock(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = $this->obtenerStock($articulo->id);

        $resultado = $stock->disminuir(3);

        $this->assertTrue($resultado);
        $stock->refresh();
        $this->assertEquals('7.00', $stock->cantidad);
    }

    public function test_disminuir_falla_sin_stock(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $stock = $this->obtenerStock($articulo->id);

        $resultado = $stock->disminuir(10);

        $this->assertFalse($resultado);
        $stock->refresh();
        $this->assertEquals('5.00', $stock->cantidad);
    }

    public function test_disminuir_permite_negativo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $stock = $this->obtenerStock($articulo->id);

        $resultado = $stock->disminuir(10, permitirNegativo: true);

        $this->assertTrue($resultado);
        $stock->refresh();
        $this->assertEquals('-5.00', $stock->cantidad);
    }

    public function test_hay_suficiente_true_con_stock(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = $this->obtenerStock($articulo->id);

        $this->assertTrue($stock->haySuficiente(8));
    }

    public function test_hay_suficiente_false_sin_stock(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $stock = $this->obtenerStock($articulo->id);

        $this->assertFalse($stock->haySuficiente(10));
    }

    public function test_esta_bajo_minimo_true(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 3);
        $stock = $this->obtenerStock($articulo->id);

        $stock->update(['cantidad_minima' => 5]);
        $stock->refresh();

        $this->assertTrue($stock->estaBajoMinimo());
    }

    public function test_esta_bajo_minimo_false_sin_minimo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 3);
        $stock = $this->obtenerStock($articulo->id);

        // cantidad_minima es null por defecto desde crearArticuloConStock
        $this->assertFalse($stock->estaBajoMinimo());
    }

    public function test_esta_sobre_maximo_true(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 15);
        $stock = $this->obtenerStock($articulo->id);

        $stock->update(['cantidad_maxima' => 10]);
        $stock->refresh();

        $this->assertTrue($stock->estaSobreMaximo());
    }

    public function test_scope_bajo_minimo_filtra(): void
    {
        // Articulo bajo minimo: cantidad 3, minimo 5
        $art1 = $this->crearArticuloConStock($this->sucursalId, 3);
        $stock1 = $this->obtenerStock($art1->id);
        $stock1->update(['cantidad_minima' => 5]);

        // Articulo ok: cantidad 10, minimo 5
        $art2 = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock2 = $this->obtenerStock($art2->id);
        $stock2->update(['cantidad_minima' => 5]);

        // Articulo sin minimo definido: no debe aparecer
        $this->crearArticuloConStock($this->sucursalId, 1);

        $bajoMinimo = Stock::bajoMinimo()->count();

        $this->assertEquals(1, $bajoMinimo);
    }
}

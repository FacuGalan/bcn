<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Tests\Traits\WithTenant;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithCaja;
use Tests\Traits\WithVentaHelpers;
use App\Services\StockService;
use App\Models\Stock;
use App\Models\MovimientoStock;
use Exception;

class StockServiceTest extends TestCase
{
    use WithTenant, WithSucursal, WithCaja, WithVentaHelpers;

    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
        $this->stockService = new StockService();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== ajustarStock ====================

    /** @test */
    public function ajustar_stock_positivo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $resultado = $this->stockService->ajustarStock($stock->id, 5, 1, 'Ingreso de mercadería');

        $this->assertEquals(15, (float) $resultado->cantidad);

        // Verificar que se creó el MovimientoStock
        $movimiento = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_AJUSTE_MANUAL)
            ->latest('id')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertEquals(5, (float) $movimiento->entrada);
        $this->assertEquals(0, (float) $movimiento->salida);
    }

    /** @test */
    public function ajustar_stock_negativo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $resultado = $this->stockService->ajustarStock($stock->id, -3, 1, 'Merma');

        $this->assertEquals(7, (float) $resultado->cantidad);
    }

    /** @test */
    public function ajustar_stock_falla_si_queda_negativo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/negativo/');

        $this->stockService->ajustarStock($stock->id, -10, 1, 'Exceso');
    }

    /** @test */
    public function ajustar_stock_registra_movimiento(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        // Ajuste positivo
        $this->stockService->ajustarStock($stock->id, 5, 1, 'Entrada de mercadería');

        $movimiento = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_AJUSTE_MANUAL)
            ->latest('id')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertEquals(MovimientoStock::TIPO_AJUSTE_MANUAL, $movimiento->tipo);
        $this->assertEquals(5, (float) $movimiento->entrada);
        $this->assertEquals(0, (float) $movimiento->salida);
        $this->assertEquals($articulo->id, $movimiento->articulo_id);
        $this->assertEquals($this->sucursalId, $movimiento->sucursal_id);
        $this->assertEquals(1, $movimiento->usuario_id);

        // Ajuste negativo
        $this->stockService->ajustarStock($stock->id, -2, 1, 'Salida por merma');

        $movimientoSalida = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_AJUSTE_MANUAL)
            ->latest('id')
            ->first();

        $this->assertEquals(0, (float) $movimientoSalida->entrada);
        $this->assertEquals(2, (float) $movimientoSalida->salida);
    }

    // ==================== inicializarStockEnSucursal ====================

    /** @test */
    public function inicializar_stock_en_sucursal(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario');

        // Eliminar el stock existente para testear inicialización limpia
        Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->delete();

        $stock = $this->stockService->inicializarStockEnSucursal(
            $articulo->id,
            $this->sucursalId,
            50,
            5,   // cantidad_minima
            200  // cantidad_maxima
        );

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals($articulo->id, $stock->articulo_id);
        $this->assertEquals($this->sucursalId, $stock->sucursal_id);
        $this->assertEquals(50, (float) $stock->cantidad);
        $this->assertEquals(5, (float) $stock->cantidad_minima);
        $this->assertEquals(200, (float) $stock->cantidad_maxima);
    }

    /** @test */
    public function inicializar_stock_falla_modo_ninguno(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'ninguno');

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/no controla stock/');

        $this->stockService->inicializarStockEnSucursal(
            $articulo->id,
            $this->sucursalId,
            50
        );
    }

    // ==================== registrarInventarioFisico ====================

    /** @test */
    public function registrar_inventario_fisico_sobrante(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $resultado = $this->stockService->registrarInventarioFisico(
            $stock->id,
            15,
            1,
            'Inventario mensual'
        );

        $this->assertEquals($stock->id, $resultado['stock_id']);
        $this->assertEquals(10, (float) $resultado['cantidad_anterior']);
        $this->assertEquals(15, (float) $resultado['cantidad_fisica']);
        $this->assertEquals(5, (float) $resultado['diferencia']);
        $this->assertEquals('sobrante', $resultado['tipo_diferencia']);

        // Verificar que se actualizó el stock
        $stockActualizado = Stock::find($stock->id);
        $this->assertEquals(15, (float) $stockActualizado->cantidad);
    }

    /** @test */
    public function registrar_inventario_fisico_faltante(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $resultado = $this->stockService->registrarInventarioFisico(
            $stock->id,
            7,
            1,
            'Faltante detectado'
        );

        $this->assertEquals(10, (float) $resultado['cantidad_anterior']);
        $this->assertEquals(7, (float) $resultado['cantidad_fisica']);
        $this->assertEquals(-3, (float) $resultado['diferencia']);
        $this->assertEquals('faltante', $resultado['tipo_diferencia']);

        // Verificar que se actualizó el stock
        $stockActualizado = Stock::find($stock->id);
        $this->assertEquals(7, (float) $stockActualizado->cantidad);
    }

    /** @test */
    public function registrar_inventario_sin_diferencia(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $resultado = $this->stockService->registrarInventarioFisico(
            $stock->id,
            10,
            1,
            'Inventario OK'
        );

        $this->assertEquals(10, (float) $resultado['cantidad_anterior']);
        $this->assertEquals(10, (float) $resultado['cantidad_fisica']);
        $this->assertEquals(0, (float) $resultado['diferencia']);
        $this->assertEquals('sin_diferencia', $resultado['tipo_diferencia']);
    }

    /** @test */
    public function registrar_inventario_crea_movimiento(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $cantidadMovimientosAntes = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_INVENTARIO_FISICO)
            ->count();

        $this->stockService->registrarInventarioFisico(
            $stock->id,
            15,
            1,
            'Inventario con sobrante'
        );

        $cantidadMovimientosDespues = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_INVENTARIO_FISICO)
            ->count();

        $this->assertEquals($cantidadMovimientosAntes + 1, $cantidadMovimientosDespues);

        $movimiento = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_INVENTARIO_FISICO)
            ->latest('id')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertEquals(MovimientoStock::TIPO_INVENTARIO_FISICO, $movimiento->tipo);
        $this->assertEquals(5, (float) $movimiento->entrada);
        $this->assertEquals(0, (float) $movimiento->salida);
        $this->assertEquals(1, $movimiento->usuario_id);
    }

    // ==================== obtenerStockBajoMinimo ====================

    /** @test */
    public function obtener_stock_bajo_minimo(): void
    {
        // Artículo bajo mínimo: cantidad 5, mínimo 10
        $articuloBajo = $this->crearArticuloConStock($this->sucursalId, 5);
        $stockBajo = Stock::where('articulo_id', $articuloBajo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();
        $stockBajo->update(['cantidad_minima' => 10]);

        // Artículo normal: cantidad 15, mínimo 10
        $articuloNormal = $this->crearArticuloConStock($this->sucursalId, 15);
        $stockNormal = Stock::where('articulo_id', $articuloNormal->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();
        $stockNormal->update(['cantidad_minima' => 10]);

        $resultado = $this->stockService->obtenerStockBajoMinimo($this->sucursalId);

        // Solo debe contener el artículo bajo mínimo
        $articulosBajoMinimo = $resultado->pluck('articulo_id')->toArray();
        $this->assertContains($articuloBajo->id, $articulosBajoMinimo);
        $this->assertNotContains($articuloNormal->id, $articulosBajoMinimo);
    }

    // ==================== obtenerArticulosSinStock ====================

    /** @test */
    public function obtener_articulos_sin_stock(): void
    {
        // Artículo sin stock: cantidad 0
        $articuloSinStock = $this->crearArticuloConStock($this->sucursalId, 0);

        // Artículo con stock: cantidad 10
        $articuloConStock = $this->crearArticuloConStock($this->sucursalId, 10);

        $resultado = $this->stockService->obtenerArticulosSinStock($this->sucursalId);

        $articulosSinStock = $resultado->pluck('articulo_id')->toArray();
        $this->assertContains($articuloSinStock->id, $articulosSinStock);
        $this->assertNotContains($articuloConStock->id, $articulosSinStock);
    }

    // ==================== Transacción y rollback ====================

    /** @test */
    public function ajustar_stock_rollback_en_error(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 5);
        $stock = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->first();

        $cantidadOriginal = (float) $stock->cantidad;

        try {
            $this->stockService->ajustarStock($stock->id, -10, 1, 'Debería fallar');
        } catch (Exception $e) {
            // Se espera la excepción
        }

        // Verificar que el stock no cambió (rollback exitoso)
        $stockDespues = Stock::find($stock->id);
        $this->assertEquals($cantidadOriginal, (float) $stockDespues->cantidad);

        // Verificar que NO se creó movimiento
        $movimiento = MovimientoStock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->where('tipo', MovimientoStock::TIPO_AJUSTE_MANUAL)
            ->first();

        $this->assertNull($movimiento);
    }

    // ==================== firstOrCreate (no duplica) ====================

    /** @test */
    public function inicializar_stock_no_duplica(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario');

        // Eliminar el stock existente para empezar limpio
        Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->delete();

        // Primera inicialización
        $stock1 = $this->stockService->inicializarStockEnSucursal(
            $articulo->id,
            $this->sucursalId,
            50
        );

        // Segunda inicialización (no debería duplicar)
        $stock2 = $this->stockService->inicializarStockEnSucursal(
            $articulo->id,
            $this->sucursalId,
            100  // Valor diferente, pero no debería aplicarse
        );

        // Deben ser el mismo registro
        $this->assertEquals($stock1->id, $stock2->id);

        // La cantidad debe ser la de la primera inicialización (firstOrCreate no actualiza)
        $this->assertEquals(50, (float) $stock2->cantidad);

        // Debe haber un solo registro de stock
        $count = Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->count();

        $this->assertEquals(1, $count);
    }
}

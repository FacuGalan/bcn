<?php

namespace Tests\Integration\Models;

use App\Models\Categoria;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;
use Tests\TestCase;
use Tests\Traits\WithTenant;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithCaja;
use Tests\Traits\WithVentaHelpers;

class ListaPrecioTest extends TestCase
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

    /**
     * Helper: crea una lista de precios de prueba.
     */
    private function crearLista(array $overrides = []): ListaPrecio
    {
        return ListaPrecio::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Lista Test ' . uniqid(),
            'ajuste_porcentaje' => 0,
            'redondeo' => 'ninguno',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => false,
            'prioridad' => 10,
            'activo' => true,
        ], $overrides));
    }

    /** @test */
    public function obtener_precio_por_articulo_especifico(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'precio_base' => 1000,
        ]);

        $lista = $this->crearLista();

        // Detalle con precio fijo para este articulo
        ListaPrecioArticulo::create([
            'lista_precio_id' => $lista->id,
            'articulo_id' => $articulo->id,
            'categoria_id' => null,
            'precio_fijo' => 800,
            'ajuste_porcentaje' => null,
        ]);

        $resultado = $lista->obtenerPrecioArticulo($articulo);

        $this->assertEquals(800, $resultado['precio']);
        $this->assertStringContainsString('articulo', $resultado['origen']);
    }

    /** @test */
    public function obtener_precio_por_categoria(): void
    {
        $categoria = Categoria::create([
            'nombre' => 'Categoria Test',
            'activo' => true,
        ]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'precio_base' => 1000,
            'categoria_id' => $categoria->id,
        ]);

        $lista = $this->crearLista();

        // Detalle por categoria con ajuste +20%
        ListaPrecioArticulo::create([
            'lista_precio_id' => $lista->id,
            'articulo_id' => null,
            'categoria_id' => $categoria->id,
            'precio_fijo' => null,
            'ajuste_porcentaje' => 20,
        ]);

        $resultado = $lista->obtenerPrecioArticulo($articulo);

        // 1000 * 1.20 = 1200
        $this->assertEquals(1200, $resultado['precio']);
        $this->assertStringContainsString('categoria', $resultado['origen']);
    }

    /** @test */
    public function obtener_precio_ajuste_encabezado(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'precio_base' => 1000,
        ]);

        $lista = $this->crearLista([
            'ajuste_porcentaje' => 10,
        ]);

        // Sin detalles, debe usar ajuste del encabezado
        $resultado = $lista->obtenerPrecioArticulo($articulo);

        // 1000 * 1.10 = 1100
        $this->assertEquals(1100, $resultado['precio']);
        $this->assertEquals('encabezado', $resultado['origen']);
        $this->assertEquals(10, $resultado['ajuste_porcentaje']);
    }

    /** @test */
    public function redondeo_entero(): void
    {
        $lista = $this->crearLista(['redondeo' => 'entero']);

        $this->assertEquals(1056, $lista->aplicarRedondeo(1055.67));
    }

    /** @test */
    public function redondeo_decena(): void
    {
        $lista = $this->crearLista(['redondeo' => 'decena']);

        $this->assertEquals(1060, $lista->aplicarRedondeo(1055.67));
    }

    /** @test */
    public function redondeo_centena(): void
    {
        $lista = $this->crearLista(['redondeo' => 'centena']);

        $this->assertEquals(1100, $lista->aplicarRedondeo(1055.67));
    }

    /** @test */
    public function buscar_lista_manual_tiene_prioridad(): void
    {
        $listaBase = ListaPrecio::crearListaBase($this->sucursalId);
        $listaManual = $this->crearLista(['nombre' => 'Lista Manual']);

        $resultado = ListaPrecio::buscarListaAplicable(
            $this->sucursalId,
            [],
            $listaManual->id,
            null
        );

        $this->assertNotNull($resultado);
        $this->assertEquals($listaManual->id, $resultado->id);
    }

    /** @test */
    public function buscar_lista_cliente(): void
    {
        $listaBase = ListaPrecio::crearListaBase($this->sucursalId);
        $listaCliente = $this->crearLista(['nombre' => 'Lista Cliente']);

        $cliente = $this->crearClienteConCC($this->sucursalId);
        $cliente->update(['lista_precio_id' => $listaCliente->id]);

        $resultado = ListaPrecio::buscarListaAplicable(
            $this->sucursalId,
            [],
            null,
            $cliente->id
        );

        $this->assertNotNull($resultado);
        $this->assertEquals($listaCliente->id, $resultado->id);
    }

    /** @test */
    public function buscar_lista_base_como_fallback(): void
    {
        $listaBase = ListaPrecio::crearListaBase($this->sucursalId);

        $resultado = ListaPrecio::buscarListaAplicable(
            $this->sucursalId,
            [],
            null,
            null
        );

        $this->assertNotNull($resultado);
        $this->assertEquals($listaBase->id, $resultado->id);
        $this->assertTrue($resultado->es_lista_base);
    }

    /** @test */
    public function buscar_lista_sin_resultado(): void
    {
        // Sin listas creadas, debe retornar null (no hay lista base)
        $resultado = ListaPrecio::buscarListaAplicable(
            $this->sucursalId,
            [],
            null,
            null
        );

        $this->assertNull($resultado);
    }

    /** @test */
    public function validar_condiciones_sin_condiciones_true(): void
    {
        $lista = $this->crearLista();

        // Cargar condiciones (vacia)
        $lista->load('condiciones');

        $this->assertTrue($lista->validarCondiciones([]));
    }

    /** @test */
    public function obtener_precio_detalle_ajuste_porcentaje(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'precio_base' => 1000,
        ]);

        $lista = $this->crearLista([
            'ajuste_porcentaje' => 5, // encabezado 5%
        ]);

        // Detalle con ajuste_porcentaje=15 (debe pisar al encabezado)
        ListaPrecioArticulo::create([
            'lista_precio_id' => $lista->id,
            'articulo_id' => $articulo->id,
            'categoria_id' => null,
            'precio_fijo' => null,
            'ajuste_porcentaje' => 15,
        ]);

        $resultado = $lista->obtenerPrecioArticulo($articulo);

        // 1000 * 1.15 = 1150
        $this->assertEquals(1150, $resultado['precio']);
        $this->assertEquals(15, $resultado['ajuste_porcentaje']);
        $this->assertStringContainsString('articulo', $resultado['origen']);
    }
}

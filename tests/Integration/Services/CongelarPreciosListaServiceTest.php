<?php

namespace Tests\Integration\Services;

use App\Models\Categoria;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;
use App\Services\CongelarPreciosListaService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class CongelarPreciosListaServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    private CongelarPreciosListaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->service = new CongelarPreciosListaService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearListaEstatica(array $overrides = []): ListaPrecio
    {
        return ListaPrecio::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Lista Estática '.uniqid(),
            'ajuste_porcentaje' => 0,
            'redondeo' => 'ninguno',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => false,
            'estatica' => true,
            'prioridad' => 10,
            'activo' => true,
        ], $overrides));
    }

    public function test_snapshot_aplica_ajuste_header_a_todos_los_articulos(): void
    {
        $a1 = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', ['precio_base' => 1000]);
        $a2 = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', ['precio_base' => 500]);

        $lista = $this->crearListaEstatica(['ajuste_porcentaje' => 20]);

        $cantidad = $this->service->congelar($lista);

        $this->assertEquals(2, $cantidad);

        $fila1 = ListaPrecioArticulo::where('lista_precio_id', $lista->id)
            ->where('articulo_id', $a1->id)->first();
        $fila2 = ListaPrecioArticulo::where('lista_precio_id', $lista->id)
            ->where('articulo_id', $a2->id)->first();

        $this->assertEquals(1200, (float) $fila1->precio_fijo);
        $this->assertEquals(600, (float) $fila2->precio_fijo);
        $this->assertEquals('snapshot', $fila1->origen);
        $this->assertNotNull($lista->fresh()->precios_congelados_at);
    }

    public function test_snapshot_aplica_redondeo(): void
    {
        $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', ['precio_base' => 1055.67]);

        $lista = $this->crearListaEstatica([
            'ajuste_porcentaje' => 0,
            'redondeo' => 'decena',
        ]);

        $this->service->congelar($lista);

        $fila = ListaPrecioArticulo::where('lista_precio_id', $lista->id)->first();
        $this->assertEquals(1060, (float) $fila->precio_fijo);
    }

    public function test_snapshot_respeta_ajuste_de_categoria(): void
    {
        $categoria = Categoria::create(['nombre' => 'Cat '.uniqid(), 'activo' => true]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'precio_base' => 1000,
            'categoria_id' => $categoria->id,
        ]);

        $lista = $this->crearListaEstatica(['ajuste_porcentaje' => 10]); // header 10%

        ListaPrecioArticulo::create([
            'lista_precio_id' => $lista->id,
            'categoria_id' => $categoria->id,
            'ajuste_porcentaje' => 25, // categoría 25% (debe pisar el header)
            'origen' => 'manual',
        ]);

        $this->service->congelar($lista);

        $filaArt = ListaPrecioArticulo::where('lista_precio_id', $lista->id)
            ->where('articulo_id', $articulo->id)->first();
        $this->assertEquals(1250, (float) $filaArt->precio_fijo);
    }

    public function test_snapshot_preserva_precio_manual_sin_porcentaje(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', ['precio_base' => 1000]);

        $lista = $this->crearListaEstatica(['ajuste_porcentaje' => 50]);

        // Precio manual puesto por el usuario (precio_fijo sin ajuste_porcentaje)
        ListaPrecioArticulo::create([
            'lista_precio_id' => $lista->id,
            'articulo_id' => $articulo->id,
            'precio_fijo' => 777,
            'ajuste_porcentaje' => null,
            'origen' => 'manual',
        ]);

        $this->service->congelar($lista);

        $fila = ListaPrecioArticulo::where('lista_precio_id', $lista->id)
            ->where('articulo_id', $articulo->id)->first();
        $this->assertEquals(777, (float) $fila->precio_fijo);
        $this->assertNull($fila->ajuste_porcentaje);
    }

    public function test_re_snapshot_recalcula_con_precio_base_actual(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', ['precio_base' => 1000]);

        $lista = $this->crearListaEstatica(['ajuste_porcentaje' => 20]);
        $this->service->congelar($lista);

        $fila1 = ListaPrecioArticulo::where('lista_precio_id', $lista->id)
            ->where('articulo_id', $articulo->id)->first();
        $this->assertEquals(1200, (float) $fila1->precio_fijo);

        // Cambia el precio base del artículo
        $articulo->update(['precio_base' => 2000]);

        // Re-snapshot
        $this->service->congelar($lista->fresh());

        $fila2 = ListaPrecioArticulo::where('lista_precio_id', $lista->id)
            ->where('articulo_id', $articulo->id)->first();
        $this->assertEquals(2400, (float) $fila2->precio_fijo);
    }

    public function test_congelar_falla_si_lista_no_es_estatica(): void
    {
        $lista = ListaPrecio::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'No estática',
            'ajuste_porcentaje' => 0,
            'redondeo' => 'ninguno',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => false,
            'estatica' => false,
            'prioridad' => 10,
            'activo' => true,
        ]);

        $this->expectException(\Exception::class);
        $this->service->congelar($lista);
    }
}

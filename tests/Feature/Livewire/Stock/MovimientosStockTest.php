<?php

namespace Tests\Feature\Livewire\Stock;

use App\Livewire\Stock\MovimientosStock;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests del componente MovimientosStock.
 *
 * Incluye test reproductor del bug reportado:
 * "Call to undefined method Illuminate\Database\Eloquent\Builder::conStock()"
 * que ocurría al escribir en el search de artículos del modal "Cargar stock".
 */
class MovimientosStockTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create();
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        // El componente usa #[Lazy]: sin esta llamada el render inicial es un placeholder
        // y las interacciones no disparan el código real.
        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_render_component()
    {
        Livewire::test(MovimientosStock::class)
            ->assertOk();
    }

    /**
     * REPRODUCTOR BUG: al tipear en el search del modal de carga, el componente
     * invocaba el scope inexistente conStock() y lanzaba BadMethodCallException.
     * Fix: usar conStockEnSucursal(sucursal_activa()).
     */
    public function test_search_articulo_carga_no_lanza_excepcion()
    {
        $this->crearArticuloConStock($this->sucursalId, 50, 'unitario', [
            'nombre' => 'Coca Cola 500ml',
        ]);

        Livewire::test(MovimientosStock::class)
            ->set('cargaSearchArticulo', 'Coca')
            ->assertOk();
    }

    public function test_search_articulo_descarga_no_lanza_excepcion()
    {
        $this->crearArticuloConStock($this->sucursalId, 50, 'unitario', [
            'nombre' => 'Sprite 1L',
        ]);

        Livewire::test(MovimientosStock::class)
            ->set('descargaSearchArticulo', 'Sprite')
            ->assertOk();
    }

    public function test_search_articulo_inventario_no_lanza_excepcion()
    {
        $this->crearArticuloConStock($this->sucursalId, 50, 'unitario', [
            'nombre' => 'Galletitas',
        ]);

        Livewire::test(MovimientosStock::class)
            ->set('inventarioSearchArticulo', 'Gal')
            ->assertOk();
    }

    public function test_search_articulo_carga_devuelve_resultados_coincidentes()
    {
        $this->crearArticuloConStock($this->sucursalId, 50, 'unitario', [
            'nombre' => 'Pepsi 500ml',
            'codigo' => 'PEPSI-500',
        ]);
        $this->crearArticuloConStock($this->sucursalId, 50, 'unitario', [
            'nombre' => 'Coca Cola 500ml',
            'codigo' => 'COCA-500',
        ]);

        $component = Livewire::test(MovimientosStock::class)
            ->set('cargaSearchArticulo', 'Pepsi');

        $resultados = $component->instance()->articulosCarga;

        $this->assertCount(1, $resultados);
        $this->assertEquals('Pepsi 500ml', $resultados[0]['nombre']);
    }

    public function test_search_vacio_devuelve_array_vacio()
    {
        $this->crearArticuloConStock($this->sucursalId, 50, 'unitario');

        $component = Livewire::test(MovimientosStock::class)
            ->set('cargaSearchArticulo', 'a');

        // Menos de 2 caracteres → array vacío sin ejecutar query
        $this->assertCount(0, $component->instance()->articulosCarga);
    }
}

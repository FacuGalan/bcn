<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoDelivery;
use App\Livewire\Pedidos\NuevoPedidoMostrador;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * RF-05 (spec multi-pago-consistente-y-panel-delivery): la grilla táctil
 * mostraba el `precio_base` GLOBAL del artículo (fallback) aunque la sucursal
 * tuviera precio propio — al tocar, seleccionarArticulo aplicaba el precio
 * correcto y los números no coincidían. El snapshot ahora usa la MISMA
 * cadena (obtenerPrecioConLista): lo que se ve es lo que se agrega.
 */
class CatalogoTactilPrecioTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        // Bypass del cache de SucursalService (mismo patrón que SmokePedidosTest).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        $p = $ref->getProperty('sucursalIdsCache');
        $p->setAccessible(true);
        $p->setValue(null, [0]);

        $this->habilitarDelivery();

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /** Artículo con categoría (la grilla táctil agrupa por categoría) y override de precio en la sucursal. */
    protected function articuloConPrecioDeSucursal(float $precioSucursal): \App\Models\Articulo
    {
        $categoria = \App\Models\Categoria::create(['nombre' => 'Almacén', 'activo' => true]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50, overrides: [
            'categoria_id' => $categoria->id,
        ]);

        \Illuminate\Support\Facades\DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['precio_base' => $precioSucursal]);

        return $articulo;
    }

    protected function precioEnCatalogo(array $catalogo, int $articuloId): ?float
    {
        foreach ($catalogo as $cat) {
            foreach ($cat['articulos'] as $art) {
                if ((int) $art['id'] === $articuloId) {
                    return (float) $art['precio'];
                }
            }
        }

        return null;
    }

    public function test_grilla_tactil_mostrador_muestra_el_precio_de_la_sucursal(): void
    {
        // precio_base global $1000, override de sucursal $800: la grilla debe
        // mostrar $800 (antes mostraba el fallback $1000).
        $articulo = $this->articuloConPrecioDeSucursal(800);

        $componente = Livewire::test(NuevoPedidoMostrador::class);

        $precioGrilla = $this->precioEnCatalogo($componente->get('catalogoTactil'), $articulo->id);
        $this->assertNotNull($precioGrilla, 'El artículo debe estar en la grilla táctil');
        $this->assertEqualsWithDelta(800.0, $precioGrilla, 0.01);

        // Paridad grilla ↔ carrito: al agregarlo, el item usa el MISMO precio.
        $componente->call('seleccionarArticulo', $articulo->id);
        $items = $componente->get('items');
        $this->assertEqualsWithDelta($precioGrilla, (float) $items[0]['precio'], 0.01, 'Lo que se ve es lo que se agrega');
    }

    public function test_grilla_tactil_delivery_muestra_el_precio_de_la_sucursal(): void
    {
        $articulo = $this->articuloConPrecioDeSucursal(750);

        $componente = Livewire::test(NuevoPedidoDelivery::class);

        $precioGrilla = $this->precioEnCatalogo($componente->get('catalogoTactil'), $articulo->id);
        $this->assertNotNull($precioGrilla, 'El artículo debe estar en la grilla táctil');
        $this->assertEqualsWithDelta(750.0, $precioGrilla, 0.01);

        $componente->call('seleccionarArticulo', $articulo->id);
        $items = $componente->get('items');
        $this->assertEqualsWithDelta($precioGrilla, (float) $items[0]['precio'], 0.01, 'Lo que se ve es lo que se agrega');
    }

    public function test_cambio_de_tipo_refresca_los_precios_de_la_grilla(): void
    {
        // El cambio delivery ↔ take-away puede activar otra lista por forma
        // de venta: el server re-cotiza y avisa a Alpine con el mapa de
        // precios (la grilla vive en el navegador).
        $articulo = $this->articuloConPrecioDeSucursal(750);

        Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', \App\Models\PedidoDelivery::TIPO_TAKE_AWAY)
            ->assertDispatched('catalogo-tactil-precios');
    }
}

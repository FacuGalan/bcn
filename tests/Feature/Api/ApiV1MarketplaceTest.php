<?php

namespace Tests\Feature\Api;

use App\Models\Rubro;
use App\Models\Sucursal;
use App\Models\Tienda;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Marketplace público (RF-T4) + cache HTTP del catálogo (RF-T5), Fase 0
 * del spec tienda-online. Los snapshots del marketplace se cachean por
 * tienda: Cache::flush() en setUp para no leer estado de otro test.
 */
class ApiV1MarketplaceTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected ?Tienda $tienda = null;

    protected ?Rubro $rubro = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->habilitarDelivery([
            'georreferenciar_pedidos' => true,
            'radio_entrega_km' => 10,
            'costo_envio_base' => 800,
        ]);

        Cache::flush();

        $this->tienda = Tienda::updateOrCreate(
            ['comercio_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId],
            ['slug' => 'tienda-test', 'habilitada' => true],
        );
    }

    protected function tearDown(): void
    {
        Tienda::where('comercio_id', $this->comercio->id)->delete();
        if ($this->rubro) {
            $this->comercio->update(['rubro_id' => null]);
            $this->rubro->delete();
            $this->rubro = null;
        }
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== RF-T4: GET /v1/tiendas ====================

    public function test_lista_tiendas_sin_ubicacion_con_alcance_desconocido(): void
    {
        $respuesta = $this->getJson('/api/v1/tiendas')->assertOk();

        $card = collect($respuesta->json('data'))->firstWhere('slug', 'tienda-test');
        $this->assertNotNull($card);
        $this->assertSame('desconocido', $card['alcance']);
        $this->assertNull($card['distancia_km']);
        $this->assertArrayHasKey('abierta_ahora', $card);
        $this->assertArrayHasKey('logo_url', $card);
    }

    public function test_logo_de_tienda_prima_sobre_pantalla_cliente(): void
    {
        // RF-T11: con logo propio configurado, la card lo usa (absoluto);
        // el snapshot se cachea ~5 min → flush para verlo ya.
        $this->tienda->update(['logo_path' => 'tiendas/1/logo-market.webp']);
        Cache::flush();

        $card = collect($this->getJson('/api/v1/tiendas')->json('data'))
            ->firstWhere('slug', 'tienda-test');

        $this->assertNotNull($card);
        $this->assertStringEndsWith('/storage/tiendas/1/logo-market.webp', (string) $card['logo_url']);
    }

    public function test_con_ubicacion_dentro_del_radio_devuelve_ok_y_distancia(): void
    {
        // habilitarDelivery deja la sucursal en el Obelisco; punto a ~1 km.
        $respuesta = $this->getJson('/api/v1/tiendas?lat=-34.6100&lng=-58.3850')->assertOk();

        $card = collect($respuesta->json('data'))->firstWhere('slug', 'tienda-test');
        $this->assertNotNull($card);
        $this->assertSame('ok', $card['alcance']);
        $this->assertNotNull($card['distancia_km']);
        $this->assertLessThan(10, $card['distancia_km']);
    }

    public function test_con_ubicacion_fuera_del_radio_la_excluye(): void
    {
        // Rosario está a ~280 km del Obelisco.
        $respuesta = $this->getJson('/api/v1/tiendas?lat=-32.9442&lng=-60.6505')->assertOk();

        $this->assertNull(collect($respuesta->json('data'))->firstWhere('slug', 'tienda-test'));
    }

    public function test_tienda_deshabilitada_no_aparece(): void
    {
        $this->tienda->update(['habilitada' => false]);

        $respuesta = $this->getJson('/api/v1/tiendas')->assertOk();
        $this->assertNull(collect($respuesta->json('data'))->firstWhere('slug', 'tienda-test'));
    }

    public function test_filtro_por_rubro(): void
    {
        $this->rubro = Rubro::create(['nombre' => 'Rubro Test '.uniqid(), 'slug' => 'rubro-test-'.uniqid(), 'activo' => true]);
        $this->comercio->update(['rubro_id' => $this->rubro->id]);

        $conRubro = $this->getJson('/api/v1/tiendas?rubro_id='.$this->rubro->id)->assertOk();
        $card = collect($conRubro->json('data'))->firstWhere('slug', 'tienda-test');
        $this->assertNotNull($card);
        $this->assertSame($this->rubro->nombre, $card['rubro']['nombre']);

        $otroRubro = $this->getJson('/api/v1/tiendas?rubro_id=999999')->assertOk();
        $this->assertNull(collect($otroRubro->json('data'))->firstWhere('slug', 'tienda-test'));
    }

    // ==================== RF-T4: GET /v1/rubros ====================

    public function test_rubros_lista_solo_activos(): void
    {
        $this->rubro = Rubro::create(['nombre' => 'Rubro Activo '.uniqid(), 'slug' => 'rubro-activo-'.uniqid(), 'activo' => true]);
        $inactivo = Rubro::create(['nombre' => 'Rubro Inactivo '.uniqid(), 'slug' => 'rubro-inactivo-'.uniqid(), 'activo' => false]);

        try {
            $respuesta = $this->getJson('/api/v1/rubros')->assertOk();

            $ids = collect($respuesta->json('data'))->pluck('id');
            $this->assertTrue($ids->contains($this->rubro->id));
            $this->assertFalse($ids->contains($inactivo->id));
        } finally {
            $inactivo->delete();
        }
    }

    // ==================== RF-T5: CACHE HTTP DEL CATÁLOGO ====================

    public function test_catalogo_devuelve_etag_y_304_en_revalidacion(): void
    {
        $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $primera = $this->getJson('/api/v1/tiendas/tienda-test/catalogo')->assertOk();
        $etag = $primera->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('max-age=60', (string) $primera->headers->get('Cache-Control'));

        // Revalidación con el mismo catálogo → 304 sin payload.
        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/tiendas/tienda-test/catalogo')
            ->assertStatus(304);

        // Cambia el catálogo → el ETag cambia y vuelve el 200 con data.
        // El armado del catálogo tiene cache server-side de 60s (diseño de
        // RF-T5: staleness ≤ TTL es aceptable); acá simulamos la expiración
        // del TTL para ver el catálogo nuevo sin esperar los 60s reales.
        $this->crearArticuloConStock($this->sucursalId, cantidad: 5);
        Cache::flush();
        $segunda = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/tiendas/tienda-test/catalogo')
            ->assertOk();
        $this->assertNotSame($etag, $segunda->headers->get('ETag'));
    }
}

<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoDelivery;
use App\Models\DeliveryZona;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Spec delivery-burbuja-y-mapa (RF-07..RF-11): el modal de dirección del
 * alta nace con el mapa CERRADO (sin llamadas a la API de Google hasta
 * tocar "Abrir mapa"), sin geolocalización del operador (la dirección es
 * del cliente) y con el contexto de reparto (pin del local + zonas) como
 * JSON para que el picker lo dibuje.
 */
class NuevoPedidoDeliveryMapaTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

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

        Livewire::withoutLazyLoading();
        config(['services.google_maps.key' => 'clave-de-prueba']);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_modal_georreferenciado_nace_con_mapa_cerrado_y_sin_geolocalizacion(): void
    {
        $this->habilitarDelivery(['georreferenciar_pedidos' => true]);

        $html = Livewire::test(NuevoPedidoDelivery::class)
            ->call('abrirModalDireccion')
            ->assertSet('mostrarModalDireccion', true)
            ->html();

        $this->assertStringContainsString('Abrir mapa', $html, 'Lazy: el SDK recién se carga al tocar el botón');
        $this->assertStringContainsString('El mapa se carga solo al abrirlo', $html);
        $this->assertStringNotContainsString("'autoAbrir' =&gt; true", $html);
        $this->assertStringNotContainsString('&quot;autoAbrir&quot;:true', $html, 'Sin autoAbrir en el config del picker');
        $this->assertStringNotContainsString('Usar mi ubicación actual', $html, 'La dirección es del cliente, no del operador');
    }

    public function test_modal_lleva_el_contexto_de_reparto_como_json(): void
    {
        $this->habilitarDelivery(['georreferenciar_pedidos' => true, 'radio_entrega_km' => 8]);
        DeliveryZona::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Zona Centro',
            'centro_lat' => -34.6037,
            'centro_lng' => -58.3816,
            'radio_km' => 0,
            'poligono' => [
                ['lat' => -34.62, 'lng' => -58.40],
                ['lat' => -34.62, 'lng' => -58.36],
                ['lat' => -34.58, 'lng' => -58.36],
                ['lat' => -34.58, 'lng' => -58.40],
            ],
            'costo_envio' => 800,
            'orden' => 0,
            'activo' => true,
        ]);

        $html = Livewire::test(NuevoPedidoDelivery::class)
            ->call('abrirModalDireccion')
            ->html();

        $this->assertStringContainsString('x-ref="mapaContexto"', $html);
        $this->assertStringContainsString('Zona Centro', $html, 'La zona viaja en el JSON del contexto');
        $this->assertStringContainsString('"radioKm":8', $html);
        $this->assertStringContainsString('"centro":{"lat":-34.6037', $html, 'Pin del local desde la sucursal');
    }

    public function test_sin_georreferenciar_no_hay_contexto_ni_mapa(): void
    {
        $this->habilitarDelivery(['georreferenciar_pedidos' => false]);

        $componente = Livewire::test(NuevoPedidoDelivery::class)->call('abrirModalDireccion');

        $this->assertNull($componente->instance()->getMapaContextoEntregaProperty());
        $this->assertStringNotContainsString('Abrir mapa', $componente->html());
    }
}

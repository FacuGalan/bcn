<?php

namespace Tests\Feature\Api;

use App\Models\PedidoDelivery;
use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\Pedidos\PedidoDeliveryService;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * API v1 de pedidos delivery (Fase 6, RF-11/RF-12): rutas públicas por slug
 * de tienda + integración con token Sanctum de comercio (abilities) + D14
 * (aceptación manual/automática de pedidos externos).
 */
class ApiV1DeliveryTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

    protected ?Tienda $tienda = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->habilitarDelivery();
        $this->service = new PedidoDeliveryService;

        $this->tienda = Tienda::updateOrCreate(
            ['comercio_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId],
            ['slug' => 'tienda-test', 'habilitada' => true],
        );
    }

    protected function tearDown(): void
    {
        Tienda::where('comercio_id', $this->comercio->id)->delete();
        \App\Models\PersonalAccessToken::where('tokenable_type', 'Comercio')
            ->where('tokenable_id', $this->comercio->id)
            ->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== PÚBLICO: TIENDA + CATÁLOGO ====================

    public function test_tienda_show_devuelve_datos_publicos(): void
    {
        $this->getJson('/api/v1/tiendas/tienda-test')
            ->assertOk()
            ->assertJsonPath('data.slug', 'tienda-test')
            ->assertJsonStructure(['data' => ['nombre', 'abierta_ahora', 'takeaway_habilitado', 'horarios_atencion']]);
    }

    public function test_tienda_inexistente_o_deshabilitada_da_404(): void
    {
        $this->getJson('/api/v1/tiendas/no-existe')->assertNotFound();

        $this->tienda->update(['habilitada' => false]);
        $this->getJson('/api/v1/tiendas/tienda-test')->assertNotFound();
    }

    public function test_catalogo_respeta_criterio_rf17(): void
    {
        $visible = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $ocultoTienda = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $noDelivery = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $agotado = $this->crearArticuloConStock($this->sucursalId, cantidad: 0);

        \Illuminate\Support\Facades\DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $ocultoTienda->id)->update(['visible_tienda' => false]);
        $noDelivery->update(['disponible_delivery' => false]);

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test/catalogo')->assertOk();

        $ids = collect($respuesta->json('data.articulos'))->pluck('id');
        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($ocultoTienda->id), 'visible_tienda=false no debe aparecer');
        $this->assertFalse($ids->contains($noDelivery->id), 'disponible_delivery=false no debe aparecer en catálogo delivery');

        // Agotado: VISIBLE pero no pedible (RF-17).
        $agotadoJson = collect($respuesta->json('data.articulos'))->firstWhere('id', $agotado->id);
        $this->assertNotNull($agotadoJson, 'El agotado se muestra');
        $this->assertTrue((bool) $agotadoJson['agotado']);
        $this->assertFalse((bool) $agotadoJson['pedible']);
    }

    // ==================== PÚBLICO: COTIZACIONES ====================

    public function test_cotizar_envio_dentro_del_radio(): void
    {
        // habilitarDelivery deja la sucursal en el Obelisco; config con radio.
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'georreferenciar_pedidos' => true,
                'radio_entrega_km' => 10,
                'costo_envio_base' => 800,
            ]),
        ]);

        $this->postJson('/api/v1/tiendas/tienda-test/envios/cotizar', [
            'latitud' => -34.6100,
            'longitud' => -58.3850,
        ])
            ->assertOk()
            ->assertJsonPath('data.pedible', true)
            ->assertJsonPath('data.costo_envio', 800);
    }

    public function test_cotizar_carrito_aplica_motor_de_precios(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [
                ['articulo_id' => $articulo->id, 'cantidad' => 2],
            ],
        ])->assertOk();

        $this->assertGreaterThan(0, (float) $respuesta->json('data.total_final'));
        $this->assertNotNull($respuesta->json('data.desglose_iva'));
    }

    public function test_cotizar_carrito_bloquea_articulo_agotado(): void
    {
        $agotado = $this->crearArticuloConStock($this->sucursalId, cantidad: 0);

        $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $agotado->id, 'cantidad' => 1]],
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'operacion_invalida');
    }

    // ==================== PÚBLICO: PEDIDOS (D14) ====================

    protected function payloadPedido(int $articuloId): array
    {
        return [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articuloId, 'cantidad' => 1]],
            'cliente' => ['nombre' => 'Cliente Tienda', 'telefono' => '1155550000', 'email' => 'c@t.com'],
            'direccion' => ['direccion' => 'Av. Siempreviva 742', 'referencia' => '3B'],
        ];
    }

    public function test_pedido_externo_con_aceptacion_manual_entra_por_aceptar(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))
            ->assertCreated()
            ->assertJsonPath('data.por_aceptar', true)
            ->assertJsonPath('data.origen', 'tienda');

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertNull($pedido->numero, 'Por aceptar: sin número (patrón borrador)');
        $this->assertNotNull($pedido->token_seguimiento);
        // Sin stock descontado (RF-12).
        $stock = \App\Models\Stock::where('articulo_id', $articulo->id)->where('sucursal_id', $this->sucursalId)->value('cantidad');
        $this->assertEqualsWithDelta(10.0, (float) $stock, 0.01);
    }

    public function test_pedido_externo_con_aceptacion_automatica_entra_confirmado(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode(['aceptacion_pedidos_externos' => 'automatica']),
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))
            ->assertCreated()
            ->assertJsonPath('data.por_aceptar', false);

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->numero);
    }

    public function test_seguimiento_publico_por_token(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))->assertCreated();
        $token = $respuesta->json('data.token_seguimiento');

        $this->getJson("/api/v1/tiendas/tienda-test/pedidos/{$token}")
            ->assertOk()
            ->assertJsonPath('data.por_aceptar', true)
            ->assertJsonPath('data.canal_tiempo_real', "pedidos-delivery.seguimiento.{$token}");

        $this->getJson('/api/v1/tiendas/tienda-test/pedidos/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    }

    public function test_consumidor_puede_cancelar_hasta_confirmado(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))->assertCreated();
        $token = $respuesta->json('data.token_seguimiento');

        $this->postJson("/api/v1/tiendas/tienda-test/pedidos/{$token}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelado');

        $this->assertSame(
            PedidoDelivery::ESTADO_CANCELADO,
            PedidoDelivery::find($respuesta->json('data.id'))->estado_pedido,
        );
    }

    // ==================== D14: ACEPTAR / RECHAZAR ====================

    public function test_aceptar_pedido_externo_lo_confirma_con_demora(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        $this->service->aceptarPedidoExterno($pedido, demoraMin: 30);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->numero);
        $this->assertNotNull($pedido->hora_pactada_at);
        $this->assertEqualsWithDelta(30, now()->diffInMinutes($pedido->hora_pactada_at), 2);
    }

    public function test_rechazar_pedido_externo_lo_cancela(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        $resultado = $this->service->rechazarPedidoExterno($pedido, 'Sin stock');

        $this->assertFalse($resultado['a_devolver']);
        $this->assertSame(PedidoDelivery::ESTADO_CANCELADO, $pedido->fresh()->estado_pedido);
    }

    // ==================== INTEGRACIÓN (TOKEN SANCTUM) ====================

    public function test_integracion_sin_token_da_401(): void
    {
        $this->getJson('/api/v1/pedidos-delivery')->assertUnauthorized();
    }

    public function test_integracion_lista_pedidos_con_token_y_ability(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000);
        $token = $this->comercio->createToken('test', ['pedidos:read'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Sucursal-Id' => (string) $this->sucursalId,
        ])
            ->getJson('/api/v1/pedidos-delivery')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pedido->id);
    }

    public function test_integracion_sin_ability_da_403(): void
    {
        $token = $this->comercio->createToken('test', ['catalogo:read'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Sucursal-Id' => (string) $this->sucursalId,
        ])
            ->getJson('/api/v1/pedidos-delivery')
            ->assertForbidden();
    }

    public function test_integracion_patch_cambia_estado(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000);
        $token = $this->comercio->createToken('test', ['pedidos:write'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Sucursal-Id' => (string) $this->sucursalId,
        ])
            ->patchJson("/api/v1/pedidos-delivery/{$pedido->id}", ['estado' => 'en_preparacion'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'en_preparacion');

        $this->assertSame(PedidoDelivery::ESTADO_EN_PREPARACION, $pedido->fresh()->estado_pedido);
    }

    public function test_integracion_config_expone_configuracion_operativa(): void
    {
        $token = $this->comercio->createToken('test', ['config:read'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Sucursal-Id' => (string) $this->sucursalId,
        ])
            ->getJson('/api/v1/delivery/config')
            ->assertOk()
            ->assertJsonPath('data.sucursal_id', $this->sucursalId)
            ->assertJsonPath('data.usa_delivery', true);
    }
}

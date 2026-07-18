<?php

namespace Tests\Feature\Api;

use App\Models\Articulo;
use App\Models\ArticuloGrupoOpcional;
use App\Models\ArticuloGrupoOpcionalOpcion;
use App\Models\GrupoOpcional;
use App\Models\Opcional;
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

    public function test_tienda_show_expone_analytics_tema_y_comportamiento(): void
    {
        // Sin IDs cargados: null explícito (la tienda NO inyecta scripts) y
        // tema = defaults del core (RF-T7 + RF-T6, Principios 10/11).
        $this->getJson('/api/v1/tiendas/tienda-test')
            ->assertOk()
            ->assertJsonPath('data.analytics.ga4_measurement_id', null)
            ->assertJsonPath('data.analytics.meta_pixel_id', null)
            ->assertJsonPath('data.tema.colores.primario', Tienda::TEMA_DEFAULTS['colores']['primario'])
            ->assertJsonPath('data.tema.tipografia.fuente', 'system')
            ->assertJsonPath('data.tema.radios', 'md')
            ->assertJsonPath('data.tema.densidad', 'normal')
            ->assertJsonPath('data.comportamiento', []);

        // Con IDs + tema PARCIAL persistido: los IDs viajan y el tema mergea
        // sobre los defaults (solo pisa lo configurado).
        $this->tienda->update([
            'ga4_measurement_id' => 'G-TEST123',
            'meta_pixel_id' => '111222333',
            'tema' => ['colores' => ['primario' => '#123456']],
        ]);

        $this->getJson('/api/v1/tiendas/tienda-test')
            ->assertOk()
            ->assertJsonPath('data.analytics.ga4_measurement_id', 'G-TEST123')
            ->assertJsonPath('data.analytics.meta_pixel_id', '111222333')
            ->assertJsonPath('data.tema.colores.primario', '#123456')
            ->assertJsonPath('data.tema.colores.acento', Tienda::TEMA_DEFAULTS['colores']['acento'])
            ->assertJsonPath('data.tema.radios', 'md');
    }

    public function test_tienda_show_expone_logo_y_portada_absolutas(): void
    {
        // RF-T11 (aditivo): sin imágenes, null explícito; con paths, URLs
        // ABSOLUTAS (la tienda corre en otro origen — patrón imagen_url).
        $this->getJson('/api/v1/tiendas/tienda-test')
            ->assertOk()
            ->assertJsonPath('data.logo_url', null)
            ->assertJsonPath('data.portada_url', null);

        $this->tienda->update([
            'logo_path' => 'tiendas/1/logo-test.webp',
            'portada_path' => 'tiendas/1/portada-test.webp',
        ]);

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test')->assertOk();
        $this->assertStringStartsWith('http', (string) $respuesta->json('data.logo_url'));
        $this->assertStringEndsWith('/storage/tiendas/1/logo-test.webp', (string) $respuesta->json('data.logo_url'));
        $this->assertStringEndsWith('/storage/tiendas/1/portada-test.webp', (string) $respuesta->json('data.portada_url'));
    }

    public function test_catalogo_etag_y_revalidacion_304(): void
    {
        // Cache HTTP del catálogo (RF-T5): ETag + max-age, y revalidación
        // If-None-Match → 304 sin payload.
        $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $primera = $this->getJson('/api/v1/tiendas/tienda-test/catalogo')->assertOk();
        $etag = $primera->headers->get('ETag');

        $this->assertNotEmpty($etag);
        // Symfony normaliza el orden de las directivas del Cache-Control.
        $cacheControl = (string) $primera->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);

        $revalidacion = $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/api/v1/tiendas/tienda-test/catalogo');

        $revalidacion->assertStatus(304);
        $this->assertSame('', (string) $revalidacion->getContent(), 'El 304 no lleva payload');
        $this->assertSame($etag, $revalidacion->headers->get('ETag'));

        // ETag distinto ⇒ respuesta completa normal.
        $this->withHeaders(['If-None-Match' => '"otro-etag"'])
            ->getJson('/api/v1/tiendas/tienda-test/catalogo')
            ->assertOk();
    }

    public function test_catalogo_devuelve_imagen_url_absoluta(): void
    {
        // La tienda corre en otro origen: una ruta relativa /storage/... se
        // rompe contra su host. La API debe devolver URL absoluta.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10, overrides: [
            'imagen_path' => 'articulos/1/foto-test.webp',
        ]);

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test/catalogo')->assertOk();

        $json = collect($respuesta->json('data.articulos'))->firstWhere('id', $articulo->id);
        $this->assertStringStartsWith('http', $json['imagen_url']);
        $this->assertStringEndsWith('/storage/articulos/1/foto-test.webp', $json['imagen_url']);
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

    /**
     * Combo "Coca + Alfajor a $400" (fixture de regresión del pedido #32):
     * subtotal $2850 → descuento $2450 (~86%, más que el viejo tope del 70%).
     *
     * @return array{0: Articulo, 1: Articulo}
     */
    private function crearComboCocaAlfajor(): array
    {
        $coca = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'nombre' => 'Coca', 'precio_base' => 2400,
        ]);
        $alfajor = $this->crearArticuloConStock($this->sucursalId, 10, 'unitario', [
            'nombre' => 'Alfajor', 'precio_base' => 450,
        ]);

        $combo = \App\Models\PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Coca + Alfajor',
            'tipo' => \App\Models\PromocionEspecial::TIPO_COMBO,
            'precio_tipo' => 'fijo',
            'precio_valor' => 400,
            'prioridad' => 1,
            'modo_aplicacion' => 'automatica',
            'activo' => true, 'usos_actuales' => 0,
        ]);
        foreach ([$coca, $alfajor] as $articulo) {
            $grupo = \App\Models\PromocionEspecialGrupo::create([
                'promocion_especial_id' => $combo->id,
                'nombre' => $articulo->nombre,
                'cantidad' => 1,
                'orden' => 1,
                'es_trigger' => false,
                'es_reward' => false,
            ]);
            $grupo->articulos()->attach($articulo->id);
        }

        return [$coca, $alfajor];
    }

    public function test_cotizar_carrito_combo_fijo_respeta_su_precio_aunque_descuente_mas_del_70(): void
    {
        // Regresión pedido #32 (2026-07-17): el tope silencioso del 70% recortaba
        // total_descuentos (sin tocar la atribución por renglón) y el total
        // cotizado quedaba en el 30% del subtotal en vez del precio configurado
        // del combo. Además la respuesta mapeaba claves inexistentes del
        // resultado del motor (descuento_total/iva_total) → descuento e iva
        // viajaban siempre en 0 y la tienda no tenía nada que mostrar.
        [$coca, $alfajor] = $this->crearComboCocaAlfajor();

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [
                ['articulo_id' => $coca->id, 'cantidad' => 1],
                ['articulo_id' => $alfajor->id, 'cantidad' => 1],
            ],
        ])->assertOk();

        $this->assertEqualsWithDelta(2850.0, (float) $respuesta->json('data.subtotal'), 0.01);
        $this->assertEqualsWithDelta(
            400.0,
            (float) $respuesta->json('data.total_final'),
            0.01,
            'El total debe ser el precio configurado del combo, aunque el descuento supere el 70% del subtotal',
        );
        $this->assertEqualsWithDelta(
            2450.0,
            (float) $respuesta->json('data.descuento'),
            0.01,
            'El descuento agregado debe viajar en la respuesta (mapeaba una clave inexistente y daba 0)',
        );
        $this->assertGreaterThan(0, (float) $respuesta->json('data.iva'), 'El IVA agregado debe viajar en la respuesta');

        $promos = $respuesta->json('data.promociones_especiales_aplicadas');
        $this->assertCount(1, $promos, 'La promo aplicada viaja con nombre para que la tienda la muestre');
        $this->assertSame('Coca + Alfajor', $promos[0]['nombre']);
        $this->assertEqualsWithDelta(2450.0, (float) $promos[0]['descuento'], 0.01);
    }

    public function test_pedido_externo_con_combo_persiste_descuento_en_cabecera_y_renglones(): void
    {
        // Regresión pedido #32: la cabecera quedaba subtotal=2850, descuento=0,
        // total=855 (30% del subtotal) — inconsistente con los renglones, que
        // sí llevaban el descuento del combo bien atribuido.
        [$coca, $alfajor] = $this->crearComboCocaAlfajor();

        $payload = $this->payloadPedido($coca->id);
        $payload['items'] = [
            ['articulo_id' => $coca->id, 'cantidad' => 1],
            ['articulo_id' => $alfajor->id, 'cantidad' => 1],
        ];
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertCreated();

        $pedido = PedidoDelivery::with('detalles')->find($respuesta->json('data.id'));
        $this->assertEqualsWithDelta(2850.0, (float) $pedido->subtotal, 0.01);
        $this->assertEqualsWithDelta(2450.0, (float) $pedido->descuento, 0.01, 'Cabecera con el descuento de promociones');
        $this->assertEqualsWithDelta(400.0, (float) $pedido->total, 0.01, 'Total = precio del combo');
        $this->assertEqualsWithDelta(400.0, (float) $pedido->total_final, 0.01);
        $this->assertGreaterThan(0, (float) $pedido->iva, 'Cabecera con el IVA del desglose');

        // Renglones: precio sin descontar por diseño (paridad panel) + la
        // atribución de la promo en su columna; la conversión a venta resta.
        $this->assertEqualsWithDelta(
            2450.0,
            (float) $pedido->detalles->sum('descuento_promocion_especial'),
            0.01,
            'La suma de la atribución por renglón cierra contra la cabecera',
        );
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

    // ==================== PÚBLICO: OPCIONALES (paridad panel) ====================

    /**
     * Arma grupo global + asignación al artículo en la sucursal + opción con
     * precio OVERRIDE (distinto del global, para detectar cuál se usa).
     */
    protected function asignarGrupoOpcional(
        Articulo $articulo,
        int $sucursalId,
        float $precioGlobal = 100,
        float $precioOverride = 250,
    ): Opcional {
        $grupo = GrupoOpcional::create([
            'nombre' => 'Extras Test',
            'tipo' => 'seleccionable',
            'obligatorio' => false,
            'min_seleccion' => 0,
            'max_seleccion' => 3,
            'activo' => true,
            'orden' => 0,
        ]);
        $opcional = Opcional::create([
            'grupo_opcional_id' => $grupo->id,
            'nombre' => 'Extra Queso Test',
            'precio_extra' => $precioGlobal,
            'activo' => true,
            'orden' => 0,
        ]);
        $asignacion = ArticuloGrupoOpcional::create([
            'articulo_id' => $articulo->id,
            'grupo_opcional_id' => $grupo->id,
            'sucursal_id' => $sucursalId,
            'activo' => true,
            'orden' => 0,
        ]);
        ArticuloGrupoOpcionalOpcion::create([
            'articulo_grupo_opcional_id' => $asignacion->id,
            'opcional_id' => $opcional->id,
            'precio_extra' => $precioOverride,
            'activo' => true,
            'disponible' => true,
            'orden' => 0,
        ]);

        return $opcional;
    }

    public function test_catalogo_publica_opcionales_de_la_sucursal_con_precio_override(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $opcional = $this->asignarGrupoOpcional($articulo, $this->sucursalId, precioGlobal: 100, precioOverride: 250);

        // El mismo artículo con grupo en OTRA sucursal no debe contaminar.
        $otraSucursal = $this->crearSucursalAdicional();
        $this->asignarGrupoOpcional($articulo, $otraSucursal);

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test/catalogo')->assertOk();

        $item = collect($respuesta->json('data.articulos'))->firstWhere('id', $articulo->id);
        $this->assertCount(1, $item['opcionales'], 'Solo los grupos asignados en LA sucursal de la tienda');

        $grupo = $item['opcionales'][0];
        $this->assertSame('Extras Test', $grupo['nombre']);
        $this->assertFalse((bool) $grupo['obligatorio']);
        $this->assertSame(0, $grupo['min']);
        $this->assertSame(3, $grupo['max']);
        $this->assertSame($opcional->id, $grupo['opciones'][0]['opcional_id']);
        $this->assertSame(250.0, (float) $grupo['opciones'][0]['precio_extra'], 'Precio de la ASIGNACIÓN (override), no el global');
    }

    public function test_cotizar_carrito_suma_opcionales_con_precio_de_la_asignacion(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10); // precio_base 1000
        $opcional = $this->asignarGrupoOpcional($articulo, $this->sucursalId, precioGlobal: 100, precioOverride: 250);

        $conOpcional = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [[
                'articulo_id' => $articulo->id,
                'cantidad' => 1,
                'opcionales' => [['opcional_id' => $opcional->id, 'cantidad' => 2]],
            ]],
        ])->assertOk();

        $sinOpcional = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
        ])->assertOk();

        // Paridad panel: el total suma el precio de la ASIGNACIÓN (250 × 2),
        // no el global (100 × 2).
        $this->assertEqualsWithDelta(
            (float) $sinOpcional->json('data.total_final') + 500.0,
            (float) $conOpcional->json('data.total_final'),
            0.01,
        );
    }

    public function test_cotizar_carrito_rechaza_opcional_no_asignado_al_articulo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        // Opcional que existe y está activo, pero asignado a OTRO artículo.
        $otroArticulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $opcionalAjeno = $this->asignarGrupoOpcional($otroArticulo, $this->sucursalId);

        $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [[
                'articulo_id' => $articulo->id,
                'cantidad' => 1,
                'opcionales' => [['opcional_id' => $opcionalAjeno->id, 'cantidad' => 1]],
            ]],
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

    public function test_seguimiento_incluye_items_para_repedir_sin_costo_de_envio(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))->assertCreated();
        $token = $respuesta->json('data.token_seguimiento');
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        // Opcional aplicado a la línea + renglón-concepto del costo de envío
        // (D17): items debe exponer el opcional y EXCLUIR el costo de envío.
        $grupo = \App\Models\GrupoOpcional::create(['nombre' => 'Extras', 'tipo' => 'cuantitativo', 'activo' => true]);
        $opcional = \App\Models\Opcional::create(['grupo_opcional_id' => $grupo->id, 'nombre' => 'Cheddar extra', 'precio_extra' => 500, 'activo' => true]);

        $detalle = $pedido->detalles()->whereNotNull('articulo_id')->first();
        $detalle->opcionales()->create([
            'grupo_opcional_id' => $grupo->id,
            'opcional_id' => $opcional->id,
            'nombre_grupo' => $grupo->nombre,
            'nombre_opcional' => $opcional->nombre,
            'cantidad' => 2,
            'precio_extra' => 500,
            'subtotal_extra' => 1000,
        ]);
        $pedido->detalles()->create([
            'es_concepto' => true,
            'es_costo_envio' => true,
            'concepto_descripcion' => 'Costo de envío',
            'cantidad' => 1,
            'precio_unitario' => 800,
            'subtotal' => 800,
            'total' => 800,
        ]);

        $this->getJson("/api/v1/tiendas/tienda-test/pedidos/{$token}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.articulo_id', $articulo->id)
            ->assertJsonPath('data.items.0.nombre', $articulo->nombre)
            ->assertJsonPath('data.items.0.cantidad', 1)
            ->assertJsonPath('data.items.0.opcionales.0.opcional_id', $opcional->id)
            ->assertJsonPath('data.items.0.opcionales.0.nombre', 'Cheddar extra')
            ->assertJsonPath('data.items.0.opcionales.0.cantidad', 2);
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

    // ==================== CONTRATO DE PROMESA Y PAGO (rev20) ====================

    protected function formaPagoEfectivoEnSucursal(): \App\Models\FormaPago
    {
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $fp->sucursales()->attach($this->sucursalId, ['activo' => true]);

        return $fp;
    }

    public function test_tienda_show_expone_contrato_de_promesa_y_formas_de_pago(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'modo_promesa' => 'automatica',
                'demora_base_min' => 20,
                'demora_min_por_km' => 5,
                'acepta_lo_antes_posible' => true,
            ]),
        ]);
        $fp = $this->formaPagoEfectivoEnSucursal();

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test')
            ->assertOk()
            ->assertJsonPath('data.entrega.modo_promesa', 'automatica')
            ->assertJsonPath('data.entrega.acepta_lo_antes_posible', true)
            ->assertJsonPath('data.entrega.demora_base_min', 20)
            ->assertJsonPath('data.entrega.usa_franjas', false);

        $formasPago = collect($respuesta->json('data.formas_pago'));
        $efectivo = $formasPago->firstWhere('id', $fp->id);
        $this->assertNotNull($efectivo, 'El efectivo habilitado en la sucursal se expone');
        $this->assertTrue((bool) $efectivo['permite_vuelto']);
    }

    public function test_franjas_endpoint_devuelve_horarios_de_la_jornada_por_tipo(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'modo_promesa' => 'franjas',
                'franjas' => [
                    ['hora' => '23:58', 'dias' => [1, 2, 3, 4, 5, 6, 7], 'delivery' => true, 'take_away' => false],
                    ['hora' => '23:59', 'dias' => [1, 2, 3, 4, 5, 6, 7], 'delivery' => true, 'take_away' => true],
                ],
            ]),
        ]);

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test/franjas?tipo=take_away')
            ->assertOk()
            ->assertJsonPath('data.modo_promesa', 'franjas');

        $franjas = collect($respuesta->json('data.franjas'));
        $this->assertCount(1, $franjas, 'Solo la franja habilitada para take-away');
        $this->assertSame('23:59', $franjas->first()['label']);
    }

    public function test_pedido_externo_con_franja_valida_fija_la_hora_pactada(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'modo_promesa' => 'franjas',
                'franjas' => [['hora' => '23:59', 'dias' => [1, 2, 3, 4, 5, 6, 7], 'delivery' => true, 'take_away' => true]],
            ]),
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $franja = $this->getJson('/api/v1/tiendas/tienda-test/franjas')->json('data.franjas.0.hora');
        $this->assertNotNull($franja);

        $payload = $this->payloadPedido($articulo->id) + ['entrega' => ['franja' => $franja]];
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertNotNull($pedido->hora_pactada_at);
        $this->assertSame('23:59', $pedido->hora_pactada_at->format('H:i'));
        $this->assertFalse((bool) $pedido->lo_antes_posible);
    }

    public function test_pedido_externo_con_franja_inventada_es_rechazado(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'modo_promesa' => 'franjas',
                'franjas' => [['hora' => '23:59', 'dias' => [1, 2, 3, 4, 5, 6, 7], 'delivery' => true, 'take_away' => true]],
            ]),
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $payload = $this->payloadPedido($articulo->id) + ['entrega' => ['franja' => now()->addDays(3)->toIso8601String()]];

        $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertStatus(422);
    }

    public function test_pedido_externo_lo_antes_posible_respeta_la_config(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        // Con ASAP ofrecido (default): el pedido nace con el flag.
        $payload = $this->payloadPedido($articulo->id) + ['entrega' => ['lo_antes_posible' => true]];
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertCreated();
        $this->assertTrue((bool) PedidoDelivery::find($respuesta->json('data.id'))->lo_antes_posible);

        // Con ASAP deshabilitado: rechazado con mensaje claro.
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode(['acepta_lo_antes_posible' => false]),
        ]);

        $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertStatus(422);
    }

    public function test_pedido_externo_declara_pago_efectivo_con_vuelto_planificado(): void
    {
        $fp = $this->formaPagoEfectivoEnSucursal();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $payload = $this->payloadPedido($articulo->id) + [
            'pago' => ['forma_pago_id' => $fp->id, 'paga_con' => 20000],
        ];
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertCreated();

        $pedido = PedidoDelivery::with('pagos')->find($respuesta->json('data.id'));
        $pago = $pedido->pagos->firstWhere('forma_pago_id', $fp->id);

        $this->assertNotNull($pago, 'El pago declarado queda planificado en el pedido');
        $this->assertSame('planificado', $pago->estado);
        $this->assertEqualsWithDelta((float) $pedido->total_final, (float) $pago->monto_final, 0.01);
        $this->assertEqualsWithDelta(20000.0, (float) $pago->monto_recibido, 0.01);
        $this->assertEqualsWithDelta(20000.0 - (float) $pedido->total_final, (float) $pago->vuelto, 0.01);
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido, 'Declarar pago NO confirma el borrador');
    }

    public function test_pedido_externo_con_forma_de_pago_no_habilitada_es_rechazado(): void
    {
        // FP sin habilitar en la sucursal (sin pivot).
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $payload = $this->payloadPedido($articulo->id) + [
            'pago' => ['forma_pago_id' => $fp->id],
        ];

        $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertStatus(422);
    }

    public function test_seguimiento_muestra_entregado_cuando_el_pedido_esta_facturado(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $this->payloadPedido($articulo->id))->assertCreated();
        $token = $respuesta->json('data.token_seguimiento');

        PedidoDelivery::find($respuesta->json('data.id'))->update([
            'estado_pedido' => PedidoDelivery::ESTADO_FACTURADO,
            'entregado_at' => now(),
        ]);

        $this->getJson("/api/v1/tiendas/tienda-test/pedidos/{$token}")
            ->assertOk()
            ->assertJsonPath('data.estado', PedidoDelivery::ESTADO_ENTREGADO)
            ->assertJsonPath('data.estado_label', 'Entregado');
    }

    public function test_integracion_post_crea_pedido_con_origen_api(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $token = $this->comercio->createToken('test', ['pedidos:write'])->plainTextToken;

        $respuesta = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Sucursal-Id' => (string) $this->sucursalId,
        ])
            ->postJson('/api/v1/pedidos-delivery', $this->payloadPedido($articulo->id) + [
                'origen_referencia' => 'ext-123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.origen', 'api');

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertSame('ext-123', $pedido->origen_referencia);
    }

    public function test_pedido_publico_fuera_de_alcance_es_rechazado(): void
    {
        // Georref ON + radio 1km; dirección en Rosario (a ~280km del Obelisco).
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'georreferenciar_pedidos' => true,
                'radio_entrega_km' => 1,
            ]),
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $payload = $this->payloadPedido($articulo->id);
        $payload['direccion']['latitud'] = -32.9442;
        $payload['direccion']['longitud'] = -60.6505;

        $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertStatus(422);
    }

    public function test_consumidor_sin_alta_automatica_queda_sin_cliente_tenant(): void
    {
        $consumidor = \App\Models\Consumidor::create([
            'nombre' => 'Con Sumidor',
            'email' => 'consumidor-'.uniqid().'@test.com',
            'password' => bcrypt('secret123'),
            'telefono' => '1144440000',
        ]);
        $this->comercio->update(['tienda_alta_cliente_automatica' => false]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $token = $consumidor->createToken('tienda')->plainTextToken;
        $payload = $this->payloadPedido($articulo->id);
        unset($payload['cliente']);

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertSame($consumidor->id, (int) $pedido->consumidor_id);
        $this->assertNull($pedido->cliente_id, 'Alta automática OFF: sin cliente tenant (D11)');
        $this->assertNotNull($pedido->nombre_cliente_temporal);

        $consumidor->tokens()->delete();
        \App\Models\ConsumidorComercio::where('consumidor_id', $consumidor->id)->delete();
        $consumidor->delete();
    }

    public function test_consumidor_con_alta_automatica_crea_cliente_y_mapping(): void
    {
        $consumidor = \App\Models\Consumidor::create([
            'nombre' => 'Alta Automatica',
            'email' => 'consumidor-'.uniqid().'@test.com',
            'password' => bcrypt('secret123'),
            'telefono' => '1144440001',
        ]);
        $this->comercio->update(['tienda_alta_cliente_automatica' => true]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $token = $consumidor->createToken('tienda')->plainTextToken;
        $payload = $this->payloadPedido($articulo->id);
        unset($payload['cliente']);

        // La API es STATELESS: sin esto, la sesión que setea el setUp de los
        // tests enmascaraba que getComercioId() (sesión) devolvía null en
        // requests reales y el alta automática nunca corría (bug 2026-07-17).
        \Illuminate\Support\Facades\Session::forget('comercio_activo_id');

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertNotNull($pedido->cliente_id, 'Alta automática ON: crea cliente tenant (D11)');

        $mapping = \App\Models\ConsumidorComercio::where('consumidor_id', $consumidor->id)
            ->where('comercio_id', $this->comercio->id)
            ->first();
        $this->assertNotNull($mapping, 'Mapping consumidor↔comercio creado');
        $this->assertSame((int) $pedido->cliente_id, (int) $mapping->cliente_id);

        $this->comercio->update(['tienda_alta_cliente_automatica' => false]);
        $consumidor->tokens()->delete();
        \App\Models\ConsumidorComercio::where('consumidor_id', $consumidor->id)->delete();
        $consumidor->delete();
    }

    public function test_seguimiento_take_away_en_camino_es_para_retirar_sin_repartidor(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $payload = $this->payloadPedido($articulo->id);
        $payload['tipo'] = 'take_away';
        unset($payload['direccion']);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)->assertCreated();
        $token = $respuesta->json('data.token_seguimiento');

        PedidoDelivery::find($respuesta->json('data.id'))->update([
            'estado_pedido' => PedidoDelivery::ESTADO_EN_CAMINO,
            'en_camino_at' => now(),
        ]);

        $this->getJson("/api/v1/tiendas/tienda-test/pedidos/{$token}")
            ->assertOk()
            ->assertJsonPath('data.estado', PedidoDelivery::ESTADO_EN_CAMINO)
            ->assertJsonPath('data.estado_label', 'Para retirar')
            ->assertJsonPath('data.repartidor_en_camino', null);
    }

    // ==================== CUPONES EN LA COTIZACIÓN (fix valid/message) ====================

    protected function crearCuponPorcentaje(float $valor = 10): \App\Models\Cupon
    {
        return \App\Models\Cupon::create([
            'codigo' => 'CUP-'.strtoupper(uniqid()),
            'tipo' => 'promocional',
            'descripcion' => 'Cupón test',
            'modo_descuento' => 'porcentaje',
            'valor_descuento' => $valor,
            'aplica_a' => 'total',
            'uso_maximo' => 100,
            'activo' => true,
            'created_by_usuario_id' => 1,
        ]);
    }

    public function test_cotizar_carrito_con_cupon_valido_aplica_descuento(): void
    {
        // Regresión del bug valido/mensaje vs valid/message: TODO cupón era
        // rechazado como inválido aunque estuviera vigente.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $cupon = $this->crearCuponPorcentaje(10); // artículo $1000 → $100 off

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'cupon_codigo' => $cupon->codigo,
        ])->assertOk();

        $this->assertSame($cupon->codigo, $respuesta->json('data.cupon.codigo'));
        $this->assertEqualsWithDelta(100.0, (float) $respuesta->json('data.cupon.descuento'), 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $respuesta->json('data.total_final'), 0.01);
    }

    public function test_cotizar_carrito_con_cupon_inexistente_da_422_con_motivo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'cupon_codigo' => 'NO-EXISTE',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.message', __('Cupón inválido'));
    }

    public function test_cupon_vencido_devuelve_el_motivo_real(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $cupon = $this->crearCuponPorcentaje();
        $cupon->update(['fecha_vencimiento' => now()->subDay()->toDateString()]);

        $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'cupon_codigo' => $cupon->codigo,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.message', __('Cupón expirado'));
    }

    // ==================== FORMA DE PAGO EN COTIZACIÓN/ALTA (paridad panel) ====================

    /** FP efectivo habilitada en sucursal con descuento del 10%. */
    protected function formaPagoConDescuento(float $ajuste = -10): \App\Models\FormaPago
    {
        $fp = $this->formaPagoEfectivoEnSucursal();
        $fp->update(['ajuste_porcentaje' => $ajuste]);

        return $fp->fresh();
    }

    public function test_tienda_show_expone_ajuste_porcentaje_de_la_fp(): void
    {
        $fp = $this->formaPagoConDescuento(-10);

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-test')->assertOk();

        $efectivo = collect($respuesta->json('data.formas_pago'))->firstWhere('id', $fp->id);
        $this->assertSame(-10.0, (float) $efectivo['ajuste_porcentaje']);
    }

    public function test_cotizar_con_fp_con_descuento_aplica_el_ajuste(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $fp = $this->formaPagoConDescuento(-10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'forma_pago_id' => $fp->id,
        ])->assertOk();

        // Artículo $1000, efectivo -10% → total_a_pagar 900 (total_final sigue
        // siendo el de bienes, paridad con el resultado del panel).
        $this->assertEqualsWithDelta(1000.0, (float) $respuesta->json('data.total_final'), 0.01);
        $this->assertEqualsWithDelta(-100.0, (float) $respuesta->json('data.forma_pago.ajuste_monto'), 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $respuesta->json('data.total_a_pagar'), 0.01);
    }

    public function test_cotizar_con_fp_no_habilitada_da_422(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        // FP existente pero NO habilitada en la sucursal.
        $fp = $this->crearFormaPagoEfectivo()['formaPago'];

        $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'forma_pago_id' => $fp->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'operacion_invalida');
    }

    public function test_promocion_condicionada_a_fp_solo_aplica_con_esa_fp(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $fp = $this->formaPagoEfectivoEnSucursal();

        $promo = \App\Models\Promocion::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Solo efectivo 20%',
            'tipo' => 'descuento_porcentaje',
            'valor' => 20,
            'prioridad' => 1,
            'combinable' => true,
            'activo' => true,
            'usos_actuales' => 0,
        ]);
        \App\Models\PromocionCondicion::create([
            'promocion_id' => $promo->id,
            'tipo_condicion' => 'por_forma_pago',
            'forma_pago_id' => $fp->id,
        ]);

        // Sin FP declarada: la promo NO aplica.
        $sinFp = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
        ])->assertOk();
        $this->assertEqualsWithDelta(1000.0, (float) $sinFp->json('data.total_final'), 0.01);

        // Con la FP de la condición: aplica el 20%.
        $conFp = $this->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'forma_pago_id' => $fp->id,
        ])->assertOk();
        $this->assertEqualsWithDelta(800.0, (float) $conFp->json('data.total_final'), 0.01);
    }

    public function test_pedido_con_fp_con_descuento_cobra_el_total_ajustado(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $fp = $this->formaPagoConDescuento(-10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', array_merge(
            $this->payloadPedido($articulo->id),
            ['pago' => ['forma_pago_id' => $fp->id]],
        ))->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        // total_final = 1000 − 10% = 900; el pago planificado se descompone
        // igual que en el panel (base + ajuste = final).
        $this->assertEqualsWithDelta(900.0, (float) $pedido->total_final, 0.01);
        $this->assertEqualsWithDelta(-100.0, (float) $pedido->ajuste_forma_pago, 0.01);

        $pago = $pedido->pagos()->first();
        $this->assertEqualsWithDelta(1000.0, (float) $pago->monto_base, 0.01);
        $this->assertEqualsWithDelta(-100.0, (float) $pago->monto_ajuste, 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $pago->monto_final, 0.01);
    }

    public function test_el_envio_queda_fuera_de_la_base_del_ajuste_fp(): void
    {
        // D17: efectivo -10% sobre $1000 de productos + $500 de envío = $1400
        // (el descuento no toca el envío) — misma regla que el panel delivery.
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode([
                'georreferenciar_pedidos' => true,
                'radio_entrega_km' => 10,
                'costo_envio_base' => 500,
            ]),
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $fp = $this->formaPagoConDescuento(-10);

        $payload = array_merge($this->payloadPedido($articulo->id), [
            'pago' => ['forma_pago_id' => $fp->id],
        ]);
        $payload['direccion']['latitud'] = -34.6100;
        $payload['direccion']['longitud'] = -58.3850;

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        $this->assertEqualsWithDelta(500.0, (float) $pedido->costo_envio, 0.01);
        $this->assertEqualsWithDelta(-100.0, (float) $pedido->ajuste_forma_pago, 0.01, 'El ajuste es -10% de los productos, sin el envío');
        $this->assertEqualsWithDelta(1400.0, (float) $pedido->total_final, 0.01);
    }

    // ==================== PUNTOS (RF-T8/RF-T9, Fase 3 tienda) ====================

    /** Programa de puntos activo: $100 = 1 punto; 1 punto = $50; mínimo 10. */
    protected function activarProgramaPuntos(): void
    {
        \App\Models\ConfiguracionPuntos::updateOrCreate([], [
            'activo' => true,
            'modo_acumulacion' => 'global',
            'monto_por_punto' => 100,
            'valor_punto_canje' => 50,
            'minimo_canje' => 10,
            'redondeo' => 'floor',
        ]);

        // FP interna del canje (la crea el provisioning real; el comercio de
        // test es mínimo): el alta registra el pago-puntos bajo esta FP.
        if (! \App\Models\FormaPago::where('codigo', 'CANJE_PUNTOS')->exists()) {
            $concepto = \App\Models\ConceptoPago::firstOrCreate(
                ['codigo' => 'canje_puntos'],
                ['nombre' => 'Canje de Puntos', 'permite_cuotas' => false, 'permite_vuelto' => false, 'activo' => true, 'orden' => 8],
            );
            \App\Models\FormaPago::create([
                'nombre' => 'Canje Puntos',
                'codigo' => 'CANJE_PUNTOS',
                'concepto' => 'otro',
                'concepto_pago_id' => $concepto->id,
                'es_mixta' => false,
                'permite_cuotas' => false,
                'ajuste_porcentaje' => 0,
                'activo' => true,
                'solo_sistema' => true,
            ]);
        }
    }

    /** Consumidor con cliente materializado (mapping D11) y saldo de puntos. */
    protected function consumidorConClienteYPuntos(int $saldo = 0): array
    {
        $consumidor = \App\Models\Consumidor::create([
            'nombre' => 'Con Puntos',
            'email' => 'puntos-'.uniqid().'@test.com',
            'password' => bcrypt('secret123'),
        ]);
        $cliente = \App\Models\Cliente::create(['nombre' => 'Cliente Puntos', 'activo' => true]);
        \App\Models\ConsumidorComercio::create([
            'consumidor_id' => $consumidor->id,
            'comercio_id' => $this->comercio->id,
            'cliente_id' => $cliente->id,
        ]);

        if ($saldo > 0) {
            \App\Models\MovimientoPunto::create([
                'cliente_id' => $cliente->id,
                'sucursal_id' => $this->sucursalId,
                'fecha' => now(),
                'tipo' => 'ajuste_manual',
                'puntos' => $saldo,
                'concepto' => 'Saldo inicial de test',
                'estado' => 'activo',
                'usuario_id' => 1,
            ]);
        }

        return [$consumidor, $cliente, $consumidor->createToken('tienda')->plainTextToken];
    }

    public function test_puntos_sin_bearer_da_401(): void
    {
        $this->getJson('/api/v1/tiendas/tienda-test/puntos')->assertStatus(401);
    }

    public function test_puntos_devuelve_saldo_y_reglas_del_programa(): void
    {
        $this->activarProgramaPuntos();
        [, , $token] = $this->consumidorConClienteYPuntos(saldo: 120);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/tiendas/tienda-test/puntos')
            ->assertOk()
            ->assertJsonPath('data.activo', true)
            ->assertJsonPath('data.saldo', 120)
            ->assertJsonPath('data.saldo_en_pesos', 6000)
            ->assertJsonPath('data.valor_punto_canje', 50)
            ->assertJsonPath('data.minimo_canje', 10)
            ->assertJsonPath('data.puede_canjear', true);
    }

    public function test_puntos_sin_cliente_materializado_es_inactivo_honesto(): void
    {
        $this->activarProgramaPuntos();
        $consumidor = \App\Models\Consumidor::create([
            'nombre' => 'Sin Cliente',
            'email' => 'sincliente-'.uniqid().'@test.com',
            'password' => bcrypt('secret123'),
        ]);
        $token = $consumidor->createToken('tienda')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/tiendas/tienda-test/puntos')
            ->assertOk()
            ->assertJsonPath('data.activo', false)
            ->assertJsonPath('data.saldo', 0);
    }

    public function test_cotizar_con_usar_puntos_canjea_el_maximo_y_estima_a_ganar(): void
    {
        $this->activarProgramaPuntos();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10); // $1000
        [, , $token] = $this->consumidorConClienteYPuntos(saldo: 12); // $600 canjeables

        // Sin usar_puntos: el bloque viaja igual (a_ganar como incentivo).
        $sinCanje = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
                'tipo' => 'delivery',
                'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            ])->assertOk();
        $this->assertSame(0, $sinCanje->json('data.puntos.usados'));
        $this->assertSame(10, $sinCanje->json('data.puntos.a_ganar'), '$1000 / $100 por punto');
        $this->assertEqualsWithDelta(1000.0, (float) $sinCanje->json('data.total_a_pagar'), 0.01);

        // Con usar_puntos: canje MÁXIMO = saldo completo ($600 < $1000).
        $conCanje = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
                'tipo' => 'delivery',
                'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
                'usar_puntos' => true,
            ])->assertOk();

        $this->assertSame(12, $conCanje->json('data.puntos.usados'));
        $this->assertEqualsWithDelta(600.0, (float) $conCanje->json('data.puntos.monto'), 0.01);
        $this->assertSame(0, $conCanje->json('data.puntos.saldo_restante'));
        $this->assertEqualsWithDelta(400.0, (float) $conCanje->json('data.total_a_pagar'), 0.01);
        $this->assertSame(4, $conCanje->json('data.puntos.a_ganar'), 'Acumula sobre lo pagado SIN puntos ($400)');
        // total_final NO cambia: el canje es un pago, no un descuento de precio.
        $this->assertEqualsWithDelta(1000.0, (float) $conCanje->json('data.total_final'), 0.01);
    }

    public function test_cotizar_con_saldo_bajo_el_minimo_no_canjea(): void
    {
        $this->activarProgramaPuntos();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        [, , $token] = $this->consumidorConClienteYPuntos(saldo: 5); // < mínimo 10

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/tiendas/tienda-test/carrito/cotizar', [
                'tipo' => 'delivery',
                'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
                'usar_puntos' => true,
            ])->assertOk();

        $this->assertSame(0, $respuesta->json('data.puntos.usados'));
        $this->assertFalse($respuesta->json('data.puntos.puede_canjear'));
        $this->assertEqualsWithDelta(1000.0, (float) $respuesta->json('data.total_a_pagar'), 0.01);
    }

    public function test_pedido_con_usar_puntos_registra_el_pago_puntos_y_el_resto(): void
    {
        $this->activarProgramaPuntos();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10); // $1000
        $fp = $this->formaPagoEfectivoEnSucursal();
        [, , $token] = $this->consumidorConClienteYPuntos(saldo: 12); // $600

        $payload = $this->payloadPedido($articulo->id);
        unset($payload['cliente']);
        $payload['usar_puntos'] = true;
        $payload['pago'] = ['forma_pago_id' => $fp->id];

        $respuesta = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        // Cabecera espejo del panel.
        $this->assertSame(12, (int) $pedido->puntos_usados);
        $this->assertEqualsWithDelta(600.0, (float) $pedido->puntos_usados_monto, 0.01);

        // Pago con puntos (FP interna Canje Puntos) + FP declarada por el resto.
        $pagoPuntos = $pedido->pagos()->where('es_pago_puntos', true)->first();
        $this->assertNotNull($pagoPuntos, 'El canje queda como pago planificado');
        $this->assertEqualsWithDelta(600.0, (float) $pagoPuntos->monto_final, 0.01);
        $this->assertSame(12, (int) $pagoPuntos->puntos_usados);

        $pagoDeclarado = $pedido->pagos()->where('es_pago_puntos', false)->first();
        $this->assertEqualsWithDelta(400.0, (float) $pagoDeclarado->monto_final, 0.01, 'La FP declarada cubre el resto');

        // El saldo NO se descuenta en el alta (lo hace la conversión a venta).
        $this->assertSame(12, \App\Models\MovimientoPunto::calcularSaldo($pedido->cliente_id));
    }

    public function test_pedido_invitado_con_usar_puntos_es_noop(): void
    {
        $this->activarProgramaPuntos();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $payload = $this->payloadPedido($articulo->id);
        $payload['usar_puntos'] = true; // sin Bearer: no hay cliente → no-op

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-test/pedidos', $payload)
            ->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertSame(0, (int) $pedido->puntos_usados);
        $this->assertSame(0, $pedido->pagos()->where('es_pago_puntos', true)->count());
    }
}

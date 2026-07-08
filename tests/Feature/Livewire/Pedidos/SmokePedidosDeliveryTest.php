<?php

namespace Tests\Feature\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoDelivery;
use App\Livewire\Pedidos\PedidosDelivery;
use App\Livewire\Pedidos\Repartidores;
use App\Models\PedidoDelivery;
use App\Models\Repartidor;
use App\Models\User;
use App\Services\Pedidos\PedidoDeliveryService;
use App\Services\Pedidos\RepartidorService;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Smoke tests de la UI de Pedidos Delivery (Fase 4): panel kanban, editor
 * full-screen y ABM de repartidores/fondos. Detecta fallas de mount, Blade
 * inválido, variables indefinidas y dependencias rotas.
 */
class SmokePedidosDeliveryTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

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
        $this->service = new PedidoDeliveryService;

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== SMOKE: MONTAJE ====================

    public function test_pedidos_delivery_monta(): void
    {
        Livewire::test(PedidosDelivery::class)->assertOk();
    }

    public function test_nuevo_pedido_delivery_monta(): void
    {
        Livewire::test(NuevoPedidoDelivery::class)->assertOk();
    }

    public function test_repartidores_monta(): void
    {
        Livewire::test(Repartidores::class)->assertOk();
    }

    public function test_configuracion_delivery_monta(): void
    {
        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDelivery::class)->assertOk();
    }

    public function test_configuracion_delivery_envio_monta(): void
    {
        // Sub-componente con Maps (montado a demanda por el padre).
        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDeliveryEnvio::class)->assertOk();
    }

    public function test_configuracion_delivery_envio_guarda_solo_sus_keys(): void
    {
        // El guardado parcial del sub-componente no pisa las keys del padre.
        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $sucursal->update(['config_delivery' => array_merge(
            is_array($sucursal->config_delivery) ? $sucursal->config_delivery : [],
            ['modo_promesa' => 'automatica'],
        )]);

        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDeliveryEnvio::class)
            ->set('georreferenciarPedidos', true)
            ->set('radioEntregaKm', '8')
            ->set('costoEnvioBase', '700')
            ->call('guardarEnvio')
            ->assertDispatched('toast-success');

        $config = \App\Models\Sucursal::find($this->sucursalId)->getConfigDelivery();
        $this->assertTrue((bool) $config['georreferenciar_pedidos']);
        $this->assertEqualsWithDelta(8.0, (float) $config['radio_entrega_km'], 0.01);
        $this->assertEqualsWithDelta(700.0, (float) $config['costo_envio_base'], 0.01);
        $this->assertSame('automatica', $config['modo_promesa'], 'Las keys del padre no se pisan');
    }

    public function test_api_tokens_monta(): void
    {
        Livewire::test(\App\Livewire\Configuracion\ApiTokens::class)->assertOk();
    }

    public function test_panel_muestra_pedido_externo_por_aceptar_y_lo_acepta(): void
    {
        // D14: un borrador con origen tienda es "por aceptar" — aparece en el
        // strip, se acepta desde el modal (con demora) y queda confirmado.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: 500, overrides: ['origen' => PedidoDelivery::ORIGEN_TIENDA]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 500)],
            esBorrador: true,
        );

        $componente = Livewire::test(PedidosDelivery::class);
        $this->assertTrue(
            $componente->viewData('pedidosPorAceptar')->pluck('id')->contains($pedido->id),
            'El borrador externo debe estar en el strip por aceptar',
        );

        $componente->call('abrirAceptar', $pedido->id)
            ->assertSet('showAceptarModal', true)
            ->call('confirmarAceptar', 20)
            ->assertDispatched('toast-success');

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->hora_pactada_at);
    }

    public function test_panel_rechaza_pedido_externo_con_motivo(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: 500, overrides: ['origen' => PedidoDelivery::ORIGEN_API]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 500)],
            esBorrador: true,
        );

        Livewire::test(PedidosDelivery::class)
            ->call('abrirRechazar', $pedido->id)
            ->set('motivoRechazo', 'Sin stock del artículo')
            ->call('confirmarRechazar')
            ->assertDispatched('toast-success');

        $this->assertSame(PedidoDelivery::ESTADO_CANCELADO, $pedido->fresh()->estado_pedido);
    }

    public function test_configuracion_delivery_guarda_config_core(): void
    {
        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDelivery::class)
            ->set('usaDelivery', true)
            ->set('modoPromesa', 'automatica')
            ->set('convertirVentaAlEntregar', true)
            ->set('alertaAmarillaMin', '20')
            ->set('alertaRojaMin', '45')
            ->call('guardarConfig')
            ->assertDispatched('toast-success');

        $sucursal = \App\Models\Sucursal::find($this->sucursalId);
        $config = $sucursal->getConfigDelivery();
        $this->assertTrue((bool) $sucursal->usa_delivery);
        // rev9: la conversión al entregar es key PROPIA del JSON config_delivery
        // (la columna pedido_conversion_automatica_al_entregar quedó para mostrador).
        $this->assertTrue((bool) $config['conversion_automatica_al_entregar']);
        $this->assertSame(20, (int) $sucursal->pedido_alerta_amarilla_min);
        $this->assertSame(45, (int) $sucursal->pedido_alerta_roja_min);
        $this->assertSame('automatica', $config['modo_promesa']);
        // Las keys de Fase 8 conservan su default (no se pisan).
        $this->assertFalse((bool) $config['acepta_programados']);
    }

    public function test_configuracion_delivery_crea_zona_poligono(): void
    {
        $poligono = [
            ['lat' => -34.60, 'lng' => -58.39],
            ['lat' => -34.60, 'lng' => -58.37],
            ['lat' => -34.62, 'lng' => -58.37],
            ['lat' => -34.62, 'lng' => -58.39],
        ];

        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDeliveryEnvio::class)
            ->call('abrirCrearZona')
            ->assertSet('showZonaModal', true)
            ->assertDispatched('zona-dibujo-iniciar')
            ->set('zonaNombre', 'Centro')
            ->set('zonaCostoEnvio', '600')
            ->set('zonaPoligono', $poligono)
            ->set('zonaRangos', [
                ['dias' => array_fill_keys(range(1, 7), true), 'desde' => '20:00', 'hasta' => '23:30', 'costo' => '900'],
            ])
            ->call('guardarZona')
            ->assertDispatched('toast-success')
            ->assertDispatched('zonas-actualizadas');

        $zona = \App\Models\DeliveryZona::where('nombre', 'Centro')->first();
        $this->assertNotNull($zona);
        $this->assertSame($this->sucursalId, (int) $zona->sucursal_id);
        $this->assertEqualsWithDelta(600.0, (float) $zona->costo_envio, 0.01);
        $this->assertTrue($zona->tienePoligono());
        $this->assertCount(4, $zona->poligono);
        // Franja de costo con el costo persistido.
        $this->assertEqualsWithDelta(900.0, (float) $zona->rangos_horarios[0]['costo'], 0.01);
        // Centroide calculado para centrar el mapa.
        $this->assertEqualsWithDelta(-34.61, (float) $zona->centro_lat, 0.001);
    }

    public function test_configuracion_delivery_zona_sin_poligono_no_guarda(): void
    {
        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDeliveryEnvio::class)
            ->call('abrirCrearZona')
            ->set('zonaNombre', 'Sin dibujo')
            ->set('zonaCostoEnvio', '500')
            ->call('guardarZona')
            ->assertDispatched('toast-error');

        $this->assertNull(\App\Models\DeliveryZona::where('nombre', 'Sin dibujo')->first());
    }

    public function test_configuracion_delivery_reordena_zonas(): void
    {
        $poligono = [
            ['lat' => -34.60, 'lng' => -58.39],
            ['lat' => -34.60, 'lng' => -58.37],
            ['lat' => -34.62, 'lng' => -58.38],
        ];
        $a = \App\Models\DeliveryZona::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'A',
            'centro_lat' => -34.61, 'centro_lng' => -58.38, 'radio_km' => 0,
            'poligono' => $poligono, 'costo_envio' => 500, 'orden' => 0, 'activo' => true,
        ]);
        $b = \App\Models\DeliveryZona::create([
            'sucursal_id' => $this->sucursalId, 'nombre' => 'B',
            'centro_lat' => -34.61, 'centro_lng' => -58.38, 'radio_km' => 0,
            'poligono' => $poligono, 'costo_envio' => 700, 'orden' => 1, 'activo' => true,
        ]);

        Livewire::test(\App\Livewire\Pedidos\ConfiguracionDeliveryEnvio::class)
            ->call('reordenarZonas', [$b->id, $a->id])
            ->assertDispatched('zonas-actualizadas');

        $this->assertSame(0, (int) $b->fresh()->orden);
        $this->assertSame(1, (int) $a->fresh()->orden);
    }

    public function test_pedidos_delivery_abre_modal_alta(): void
    {
        Livewire::test(PedidosDelivery::class)
            ->call('abrirModalNuevoPedido')
            ->assertSet('modalNuevoPedidoAbierto', true)
            ->assertSet('pedidoIdEnEdicion', null);
    }

    // ==================== EDITOR: TIPO + DIRECCIÓN + ENVÍO ====================

    public function test_editor_arranca_en_delivery_y_cambia_a_take_away(): void
    {
        Livewire::test(NuevoPedidoDelivery::class)
            ->assertSet('tipo', PedidoDelivery::TIPO_DELIVERY)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->assertSet('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            // RF-02: take-away limpia el circuito de envío.
            ->assertSet('costoEnvio', 0)
            ->assertSet('zonaEnvioId', null);
    }

    public function test_editor_confirmar_direccion_actualiza_snapshot_y_cotiza(): void
    {
        Livewire::test(NuevoPedidoDelivery::class)
            ->call('abrirModalDireccion')
            ->assertSet('mostrarModalDireccion', true)
            ->set('domDireccion', 'Av. Siempreviva 742')
            ->set('domReferencia', 'Timbre 3B')
            ->call('confirmarDireccion')
            ->assertSet('mostrarModalDireccion', false)
            ->assertSet('direccionEntrega', 'Av. Siempreviva 742')
            ->assertSet('direccionReferencia', 'Timbre 3B');
    }

    public function test_editor_guarda_borrador_delivery_sin_direccion(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('guardarBorrador')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertNotNull($pedido);
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertSame(PedidoDelivery::TIPO_DELIVERY, $pedido->tipo);
        $this->assertSame(PedidoDelivery::ORIGEN_PANEL, $pedido->origen);
    }

    public function test_editor_confirmar_delivery_sin_direccion_pide_direccion(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('confirmarSinCobrar')
            ->assertDispatched('toast-error')
            ->assertSet('mostrarModalDireccion', true);

        $this->assertSame(0, PedidoDelivery::count());
    }

    public function test_editor_confirma_take_away_sin_direccion_y_persiste(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('confirmarSinCobrar')
            ->assertDispatched('pedido-guardado')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertSame(PedidoDelivery::TIPO_TAKE_AWAY, $pedido->tipo);
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
    }

    public function test_editor_costo_envio_manual_se_materializa_en_renglon_d17(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoDelivery::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalDireccion')
            ->set('domDireccion', 'Av. Siempreviva 742')
            ->call('confirmarDireccion')
            ->set('costoEnvio', 500)
            ->assertSet('costoEnvioManual', true)
            ->call('confirmarSinCobrar')
            ->assertDispatched('pedido-guardado');

        $pedido = PedidoDelivery::with('detalles')->first();
        $this->assertNotNull($pedido);
        $this->assertEqualsWithDelta(500.0, (float) $pedido->costo_envio, 0.01);
        $this->assertTrue((bool) $pedido->costo_envio_manual);

        $renglon = $pedido->detalles->firstWhere('es_costo_envio', true);
        $this->assertNotNull($renglon, 'El envío debe materializarse como renglón-concepto (D17)');
        $this->assertEqualsWithDelta(500.0, (float) $renglon->total, 0.01);
        // Total del pedido = artículo + envío (Σ detalles = total, cierra ARCA).
        $this->assertEqualsWithDelta(
            (float) $pedido->detalles->sum('total'),
            (float) $pedido->total,
            0.01,
        );
    }

    // ==================== PANEL: DESPACHO + VUELTA ====================

    protected function pedidoListoConRepartidor(): array
    {
        $repartidor = Repartidor::create(['nombre' => 'Carlos Moto', 'tipo' => 'propio', 'activo' => true]);
        $repartidor->sucursales()->attach($this->sucursalId);

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->crearCajaAbierta($this->sucursalId)->id);
        $this->service->asignarRepartidor($pedido, $repartidor->id);
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);

        return [$pedido->fresh(), $repartidor];
    }

    public function test_despachar_desde_panel_crea_salida_implicita(): void
    {
        [$pedido] = $this->pedidoListoConRepartidor();

        Livewire::test(PedidosDelivery::class)
            ->call('despachar', $pedido->id)
            ->assertNotDispatched('toast-error');

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->salida_id, 'El despacho manual debe crear la salida implícita (RF-08)');
    }

    public function test_drag_a_en_camino_intercepta_y_despacha(): void
    {
        [$pedido] = $this->pedidoListoConRepartidor();

        Livewire::test(PedidosDelivery::class)
            ->call('cambiarEstadoDrag', $pedido->id, PedidoDelivery::ESTADO_EN_CAMINO);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->salida_id);
    }

    public function test_abrir_vuelta_desde_panel_precarga_resultados(): void
    {
        [$pedido] = $this->pedidoListoConRepartidor();
        app(RepartidorService::class)->despacharPedido($pedido);
        $pedido->refresh();

        $componente = Livewire::test(PedidosDelivery::class)
            ->call('abrirVuelta', (int) $pedido->salida_id)
            ->assertSet('showVueltaModal', true);

        $resultados = $componente->get('vueltaResultados');
        $this->assertArrayHasKey($pedido->id, $resultados);
        $this->assertSame('entregado', $resultados[$pedido->id]['resultado']);
    }

    public function test_confirmar_vuelta_entregado_desde_panel(): void
    {
        [$pedido] = $this->pedidoListoConRepartidor();
        app(RepartidorService::class)->despacharPedido($pedido);
        $pedido->refresh();

        Livewire::test(PedidosDelivery::class)
            ->call('abrirVuelta', (int) $pedido->salida_id)
            ->call('confirmarVuelta')
            ->assertDispatched('toast-success');

        $this->assertSame(PedidoDelivery::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_vuelta_de_repartidor_tercero_solo_ofrece_devolver_pedidos(): void
    {
        // Tercero: sin caja chica del comercio — la vuelta arranca directo en
        // "devolver los pedidos" (cobros − envíos) como única rendición.
        $repartidor = Repartidor::create(['nombre' => 'Rappi Juan', 'tipo' => 'tercero', 'activo' => true]);
        $repartidor->sucursales()->attach($this->sucursalId);

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->crearCajaAbierta($this->sucursalId)->id);
        $this->service->asignarRepartidor($pedido, $repartidor->id);
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);
        app(RepartidorService::class)->despacharPedido($pedido->fresh());
        $pedido->refresh();

        $componente = Livewire::test(PedidosDelivery::class)
            ->call('abrirVuelta', (int) $pedido->salida_id)
            ->assertSet('showVueltaModal', true)
            ->assertSet('vueltaRendicionModo', 'devolver_pedidos');

        $this->assertTrue((bool) $componente->get('vueltaInfo')['repartidor_tercero']);
    }

    public function test_asignar_repartidor_desde_panel(): void
    {
        $repartidor = Repartidor::create(['nombre' => 'Ana Bici', 'tipo' => 'propio', 'activo' => true]);
        $repartidor->sucursales()->attach($this->sucursalId);
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 500);

        Livewire::test(PedidosDelivery::class)
            ->call('abrirAsignarRepartidor', $pedido->id)
            ->assertSet('showRepartidorModal', true)
            ->set('repartidorSeleccionadoId', (string) $repartidor->id)
            ->call('confirmarAsignarRepartidor')
            ->assertDispatched('toast-success');

        $this->assertSame($repartidor->id, (int) $pedido->fresh()->repartidor_id);
    }

    public function test_armar_salida_desde_panel_despacha_varios(): void
    {
        $repartidor = Repartidor::create(['nombre' => 'Leo Moto', 'tipo' => 'propio', 'activo' => true]);
        $repartidor->sucursales()->attach($this->sucursalId);

        $p1 = $this->pedidoDeliveryConfirmado(totalFinal: 100);
        $p2 = $this->pedidoDeliveryConfirmado(totalFinal: 200);
        $this->service->cambiarEstado($p1, PedidoDelivery::ESTADO_LISTO);
        $this->service->cambiarEstado($p2, PedidoDelivery::ESTADO_LISTO);

        Livewire::test(PedidosDelivery::class)
            ->call('abrirArmarSalida')
            ->assertSet('showArmarSalidaModal', true)
            ->set('salidaRepartidorId', (string) $repartidor->id)
            ->set('salidaPedidosSeleccionados', [$p1->id => true, $p2->id => true])
            ->call('confirmarArmarSalida')
            ->assertDispatched('toast-success');

        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $p1->fresh()->estado_pedido);
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $p2->fresh()->estado_pedido);
        $this->assertSame((int) $p1->fresh()->salida_id, (int) $p2->fresh()->salida_id);
    }

    // ==================== REPARTIDORES: ABM + FONDO ====================

    public function test_repartidores_crea_repartidor_con_sucursal(): void
    {
        Livewire::test(Repartidores::class)
            ->call('abrirCrear')
            ->assertSet('showModal', true)
            ->set('nombre', 'Nuevo Cadete')
            ->set('tipo', 'tercero')
            ->set('envioEsDelRepartidor', true)
            ->call('guardar')
            ->assertDispatched('toast-success');

        $repartidor = Repartidor::where('nombre', 'Nuevo Cadete')->first();
        $this->assertNotNull($repartidor);
        $this->assertTrue((bool) $repartidor->envio_es_del_repartidor);
        $this->assertTrue($repartidor->sucursales()->where('sucursales.id', $this->sucursalId)->exists());
    }

    public function test_repartidores_abre_y_rinde_fondo(): void
    {
        $repartidor = Repartidor::create(['nombre' => 'Fondo Test', 'tipo' => 'propio', 'activo' => true]);
        $repartidor->sucursales()->attach($this->sucursalId);
        $caja = $this->crearCajaAbierta($this->sucursalId, ['saldo_actual' => 10000]);

        $componente = Livewire::test(Repartidores::class)
            ->call('abrirFondoModal', $repartidor->id)
            ->assertSet('fondoModalModo', 'abrir')
            ->set('fondoMonto', '3000')
            ->set('fondoCajaId', (string) $caja->id)
            ->call('confirmarFondo')
            ->assertDispatched('toast-success');

        $fondo = $repartidor->fondoAbierto($this->sucursalId);
        $this->assertNotNull($fondo);

        $componente->call('abrirRendir', $fondo->id)
            ->assertSet('showRendirModal', true)
            ->set('rendirMontoDeclarado', '3000')
            ->set('rendirCajaId', (string) $caja->id)
            ->call('confirmarRendir')
            ->assertDispatched('toast-success');

        $this->assertSame('rendido', $fondo->fresh()->estado);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MercadoPagoCollectorIndex;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Pago online en tienda — Checkout Pro, Fase 2 (RF-T76..T83).
 *
 * Circuito completo con MP fakeado (Http::fake): alta con FP online (pedido
 * BORRADOR "esperando pago" + preferencia), webhook topic payment
 * (acreditación → materializa pago sin caja + transiciona según D14),
 * expiración (cancela el borrador), re-pago por token, refund automático al
 * rechazar (RF-T82) y propina discriminada (RF-T83).
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md.
 */
class MercadoPagoCheckoutFase2Test extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    private const USER_ID = '888777666';

    private const WEBHOOK_URL = '/api/integraciones/mercadopago/webhook';

    protected ?Tienda $tienda = null;

    protected FormaPago $fpOnline;

    protected IntegracionPagoSucursal $configCheckout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->habilitarDelivery();

        $this->tienda = Tienda::updateOrCreate(
            ['comercio_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId],
            ['slug' => 'tienda-mp', 'habilitada' => true],
        );

        // El retorno_url solo se acepta del dominio de la tienda (revisión
        // 2026-08-07): los fixtures usan tienda.test.
        config(['tienda.url' => 'https://tienda.test']);

        MercadoPagoCollectorIndex::where('user_id_externo', self::USER_ID)->delete();

        $integracion = IntegracionPago::firstOrCreate(
            ['codigo' => IntegracionPago::CODIGO_MERCADOPAGO_CHECKOUT],
            [
                'nombre' => 'Mercado Pago - Checkout Online',
                'modos_disponibles' => [MercadoPagoGateway::MODO_CHECKOUT_PRO],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 3,
            ]
        );

        $this->fpOnline = $this->crearFormaPagoEfectivo()['formaPago'];
        $this->fpOnline->update(['nombre' => 'Mercado Pago Online']);
        $this->fpOnline->sucursales()->attach($this->sucursalId, ['activo' => true]);
        $this->fpOnline->integraciones()->attach($integracion->id, []);

        $this->configCheckout = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $integracion->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-CHECKOUT-TOKEN',
            'user_id_externo' => self::USER_ID,
            'activo' => true,
        ]);
    }

    protected function tearDown(): void
    {
        IntegracionPagoEvento::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        MercadoPagoCollectorIndex::where('user_id_externo', self::USER_ID)->delete();
        Tienda::where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== Helpers ====================

    protected function fakePreferencia(): void
    {
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'PREF-001',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=PREF-001',
            ], 201),
        ]);
    }

    protected function altaPedidoOnline(array $extra = []): \Illuminate\Testing\TestResponse
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $payload = array_merge([
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'cliente' => ['nombre' => 'Cliente Online', 'telefono' => '1155550000', 'email' => 'c@t.com'],
            'direccion' => ['direccion' => 'Av. Siempreviva 742', 'referencia' => '3B'],
            'pago' => ['forma_pago_id' => $this->fpOnline->id, 'retorno_url' => 'https://tienda.test/tienda-mp/pedido/{token}/pago'],
        ], $extra);

        return $this->postJson('/api/v1/tiendas/tienda-mp/pedidos', $payload);
    }

    /**
     * Simula el webhook `payment` aprobado de MP para la tx (fakea la
     * re-consulta autenticada GET /v1/payments/{id}).
     */
    protected function webhookPagoAprobado(IntegracionPagoTransaccion $tx, string $paymentId = 'PAY-001'): \Illuminate\Testing\TestResponse
    {
        Http::fake([
            "api.mercadopago.com/v1/payments/{$paymentId}" => Http::response([
                'id' => $paymentId,
                'status' => 'approved',
                'external_reference' => 'BCN-TX-'.$tx->id,
                'transaction_amount' => (float) $tx->monto,
            ]),
        ]);

        return $this->postJson(self::WEBHOOK_URL, [
            'type' => 'payment',
            'data' => ['id' => $paymentId],
            'user_id' => self::USER_ID,
        ]);
    }

    protected function txDelPedido(int $pedidoId): IntegracionPagoTransaccion
    {
        return IntegracionPagoTransaccion::porCobrable('PedidoDelivery', $pedidoId)->latest('id')->firstOrFail();
    }

    // ==================== RF-T77: alta con FP online ====================

    public function test_fp_online_viaja_con_pago_online_true_en_tienda_show(): void
    {
        $fpComun = $this->formaPagoEfectivoEnSucursalLocal();

        $respuesta = $this->getJson('/api/v1/tiendas/tienda-mp')->assertOk();

        $formasPago = collect($respuesta->json('data.formas_pago'));
        $online = $formasPago->firstWhere('id', $this->fpOnline->id);
        $comun = $formasPago->firstWhere('id', $fpComun->id);

        $this->assertTrue((bool) $online['pago_online']);
        $this->assertSame('checkout_pro', $online['pago_online_modo']);
        $this->assertFalse((bool) $comun['pago_online'], 'FP sin checkout mantiene el shape con pago_online false');
    }

    public function test_alta_con_fp_online_crea_borrador_esperando_pago_con_url(): void
    {
        $this->fakePreferencia();

        $respuesta = $this->altaPedidoOnline()->assertCreated();

        $this->assertStringContainsString('PREF-001', $respuesta->json('pago_online.url_pago'));
        $this->assertSame('pendiente', $respuesta->json('pago_online.estado'));

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertTrue($pedido->esperandoPagoOnline());

        $tx = $this->txDelPedido($pedido->id);
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $tx->estado);
        $this->assertSame(MercadoPagoGateway::MODO_CHECKOUT_PRO, $tx->modo_usado);
        $this->assertNull($tx->usuario_iniciador_id);
        $this->assertEqualsWithDelta((float) $pedido->total_final, (float) $tx->monto, 0.01);
        // Timeout online: 30 min default (config presencial de 300s se eleva).
        $this->assertTrue($tx->expira_en->gt(now()->addMinutes(20)));

        // back_url con el token real reemplazando el placeholder.
        $this->assertSame(
            'https://tienda.test/tienda-mp/pedido/'.$pedido->token_seguimiento.'/pago',
            $tx->metadata['checkout']['back_url'],
        );

        // Preferencia enviada a MP: external_reference + binary_mode.
        Http::assertSent(function ($request) use ($tx) {
            return str_contains($request->url(), '/checkout/preferences')
                && $request['external_reference'] === 'BCN-TX-'.$tx->id
                && $request['binary_mode'] === true;
        });

        // Seguimiento: NO está por aceptar mientras espera el pago.
        $this->getJson('/api/v1/tiendas/tienda-mp/pedidos/'.$pedido->token_seguimiento)
            ->assertOk()
            ->assertJsonPath('data.por_aceptar', false)
            ->assertJsonPath('data.esperando_pago', true);
    }

    public function test_alta_multipago_con_fp_online_es_rechazada(): void
    {
        $fpComun = $this->formaPagoEfectivoEnSucursalLocal();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $this->postJson('/api/v1/tiendas/tienda-mp/pedidos', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'cliente' => ['nombre' => 'Cliente Online'],
            'direccion' => ['direccion' => 'Av. Siempreviva 742'],
            'pagos' => [
                ['forma_pago_id' => $this->fpOnline->id, 'monto' => 500],
                ['forma_pago_id' => $fpComun->id],
            ],
        ])->assertStatus(422);
    }

    public function test_alta_online_con_gateway_caido_no_deja_pedido_huerfano(): void
    {
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['message' => 'internal error'], 500),
        ]);

        $respuesta = $this->altaPedidoOnline()->assertStatus(422);

        // El borrador no puede quedar invisible para siempre: se canceló.
        $this->assertSame(0, PedidoDelivery::where('estado_pedido', PedidoDelivery::ESTADO_BORRADOR)->count());
    }

    // ==================== RF-T78: acreditación por webhook ====================

    public function test_webhook_payment_aprobado_materializa_pago_y_pasa_a_por_aceptar(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);

        $this->webhookPagoAprobado($tx)->assertOk()->assertJsonPath('status', 'ok');

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado);
        $this->assertSame('PAY-001', $tx->paymentIdCheckout());

        $pago = $pedido->pagos()->first();
        $this->assertSame(PedidoDeliveryPago::ESTADO_ACTIVO, $pago->estado);
        $this->assertFalse((bool) $pago->afecta_caja, 'La plata vive en la cuenta MP, no en la caja');
        $this->assertNull($pago->creado_por_usuario_id, 'Sin operador: acreditado por el consumidor');
        $this->assertSame($tx->id, (int) $pago->integracion_pago_transaccion_id);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
        // Aceptación manual (default): sigue borrador pero AHORA sí por aceptar.
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertFalse($pedido->esperandoPagoOnline());

        $this->getJson('/api/v1/tiendas/tienda-mp/pedidos/'.$pedido->token_seguimiento)
            ->assertOk()
            ->assertJsonPath('data.por_aceptar', true)
            ->assertJsonPath('data.esperando_pago', false);

        // Idempotencia: el mismo webhook de nuevo no duplica nada.
        $this->webhookPagoAprobado($tx)->assertOk();
        $this->assertSame(1, $pedido->pagos()->count());
    }

    public function test_webhook_con_aceptacion_automatica_confirma_el_pedido(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode(['aceptacion_pedidos_externos' => 'automatica']),
        ]);

        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));

        // Aun con aceptación automática, con FP online nace BORRADOR (RF-T77).
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido);

        $this->webhookPagoAprobado($this->txDelPedido($pedido->id))->assertOk();

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->numero);
    }

    // ==================== RF-T79: expiración y re-pago ====================

    public function test_tx_expirada_cancela_el_borrador_esperando_pago(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);

        $tx->update(['expira_en' => now()->subMinute()]);

        // Revisión 2026-08-07: el barrido re-consulta el estado vivo antes de
        // expirar una tx de checkout — sin pagos en MP, expira normalmente.
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []]),
        ]);

        app(\App\Services\IntegracionesPago\CobroIntegracionService::class)->expirarPendientesVencidas();

        $this->assertSame(IntegracionPagoTransaccion::ESTADO_EXPIRADO, $tx->fresh()->estado);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_CANCELADO, $pedido->estado_pedido);
        $this->assertStringContainsString('Pago online no completado', (string) $pedido->motivo_cancelacion);
    }

    public function test_re_pago_crea_tx_nueva_sobre_el_mismo_pedido(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $txVieja = $this->txDelPedido($pedido->id);

        // GET estado con la tx pendiente: pendiente + url (sin pagos en MP).
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => []]),
        ]);
        $this->getJson('/api/v1/tiendas/tienda-mp/pedidos/'.$pedido->token_seguimiento.'/pago')
            ->assertOk()
            ->assertJsonPath('data.estado', 'pendiente');

        // La tx muere (expirada) y el consumidor reintenta.
        $txVieja->update(['estado' => IntegracionPagoTransaccion::ESTADO_EXPIRADO]);

        $this->fakePreferencia();
        $reintento = $this->postJson('/api/v1/tiendas/tienda-mp/pedidos/'.$pedido->token_seguimiento.'/pago', [
            'retorno_url' => 'https://tienda.test/tienda-mp/pedido/{token}/pago',
        ])->assertOk();

        $this->assertSame('pendiente', $reintento->json('data.estado'));
        $this->assertNotSame($txVieja->id, $reintento->json('data.transaccion_id'));

        $this->assertSame(2, $pedido->transaccionesIntegracion()->count());
        $this->assertTrue($pedido->fresh()->esperandoPagoOnline());
    }

    // ==================== RF-T82: refund al rechazar ====================

    public function test_rechazar_pedido_pagado_devuelve_automaticamente(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);
        $this->webhookPagoAprobado($tx)->assertOk();

        Http::fake([
            'api.mercadopago.com/v1/payments/PAY-001/refunds' => Http::response(['id' => 'REF-1', 'status' => 'approved'], 201),
        ]);

        $resultado = app(\App\Services\Pedidos\PedidoDeliveryService::class)
            ->rechazarPedidoExterno($pedido->fresh(), 'Sin stock real');

        $this->assertFalse($resultado['a_devolver'], 'El refund automático salió OK: nada queda a devolver');

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_CANCELADO, $pedido->estado_pedido);
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_DEVUELTO, $tx->fresh()->estado);
        $this->assertSame(PedidoDeliveryPago::ESTADO_ANULADO, $pedido->pagos()->first()->estado);

        $this->assertTrue(
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_DEVUELTO)
                ->exists()
        );
    }

    public function test_refund_fallido_no_bloquea_el_rechazo_y_queda_a_devolver(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);
        $this->webhookPagoAprobado($tx)->assertOk();

        // Secuencia: el primer refund falla (queda "a devolver"); el reintento
        // manual sale OK. Http::fake acumula stubs — la secuencia evita que el
        // primer stub gane siempre.
        Http::fake([
            'api.mercadopago.com/v1/payments/PAY-001/refunds' => Http::sequence()
                ->push(['message' => 'insufficient funds'], 400)
                ->push(['id' => 'REF-2', 'status' => 'approved'], 201),
        ]);

        $resultado = app(\App\Services\Pedidos\PedidoDeliveryService::class)
            ->rechazarPedidoExterno($pedido->fresh(), 'Sin stock real');

        $this->assertTrue($resultado['a_devolver']);
        $this->assertSame(PedidoDelivery::ESTADO_CANCELADO, $pedido->fresh()->estado_pedido, 'El rechazo nunca se bloquea por el refund');
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->fresh()->estado, 'La tx sigue confirmada = a devolver');

        $ok = app(\App\Services\Pedidos\PedidoPagoOnlineService::class)->devolver($tx->fresh(), 1);
        $this->assertTrue($ok);
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_DEVUELTO, $tx->fresh()->estado);
    }

    public function test_pedido_pagado_online_no_admite_cambios_de_monto(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline()->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $this->webhookPagoAprobado($this->txDelPedido($pedido->id))->assertOk();

        $this->expectExceptionMessageMatches('/pagado online/');

        app(\App\Services\Pedidos\PedidoDeliveryService::class)->actualizarPedido(
            $pedido->fresh(),
            $this->datosBaseDelivery(5000),
            [$this->detalleDeliveryDe($this->crearArticuloConStock($this->sucursalId, cantidad: 5), 1, 5000)],
        );
    }

    // ==================== RF-T83: propina online ====================

    public function test_propina_suma_a_la_tx_pero_no_al_total_del_pedido(): void
    {
        Sucursal::where('id', $this->sucursalId)->update([
            'config_delivery' => json_encode(['checkout' => ['propina_habilitada' => true]]),
        ]);

        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline(['propina' => 500])->assertCreated();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);

        $this->assertEqualsWithDelta(500.0, (float) $pedido->propina_online, 0.01);
        $this->assertEqualsWithDelta((float) $pedido->total_final + 500.0, (float) $tx->monto, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $tx->metadata['checkout']['propina'], 0.01);

        // El pago del pedido (y la futura venta) NO incluye la propina.
        $pago = $pedido->pagos()->first();
        $this->assertEqualsWithDelta((float) $pedido->total_final, (float) $pago->monto_final, 0.01);

        // La preferencia viaja con DOS ítems: pedido + propina.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/checkout/preferences')
                && count($request['items']) === 2
                && (float) $request['items'][1]['unit_price'] === 500.0;
        });
    }

    public function test_propina_sin_habilitar_es_rechazada(): void
    {
        $this->fakePreferencia();
        $this->altaPedidoOnline(['propina' => 500])->assertStatus(422);
    }

    // ==================== D7: convergencia de CuentaEmpresa ====================

    public function test_checkout_y_qr_con_mismas_credenciales_impactan_la_misma_cuenta(): void
    {
        // Config en PRODUCCIÓN (los movimientos de CuentaEmpresa son solo-prod).
        $this->configCheckout->update([
            'modo' => 'produccion',
            'access_token_produccion' => 'PROD-CHECKOUT-TOKEN',
        ]);

        $qr = IntegracionPago::firstOrCreate(
            ['codigo' => IntegracionPago::CODIGO_MERCADOPAGO_QR],
            [
                'nombre' => 'Mercado Pago - QR',
                'modos_disponibles' => ['qr_dinamico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]
        );
        $configQr = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $qr->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'produccion',
            'access_token_produccion' => 'PROD-QR-TOKEN',
            'user_id_externo' => self::USER_ID, // MISMA cuenta MP
            'activo' => true,
        ]);

        $cobroService = app(\App\Services\IntegracionesPago\CobroIntegracionService::class);

        $txCheckout = IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $this->configCheckout->id,
            'forma_pago_id' => $this->fpOnline->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => null,
            'modo_usado' => MercadoPagoGateway::MODO_CHECKOUT_PRO,
            'monto' => 1500.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'metadata' => ['checkout' => ['propina' => 300.0]],
        ]);
        $cobroService->confirmarCobro($txCheckout);

        $txQr = IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $configQr->id,
            'forma_pago_id' => $this->fpOnline->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => 1,
            'modo_usado' => MercadoPagoGateway::MODO_QR_DINAMICO,
            'monto' => 2000.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
        ]);
        $cobroService->confirmarCobro($txQr);

        $movimientos = \App\Models\MovimientoCuentaEmpresa::where('origen_tipo', 'IntegracionPagoTransaccion')
            ->whereIn('origen_id', [$txCheckout->id, $txQr->id])
            ->get();

        // Checkout: cobro (1200) + propina (300) discriminados; QR: cobro (2000).
        $this->assertCount(3, $movimientos);
        $this->assertSame(1, $movimientos->pluck('cuenta_empresa_id')->unique()->count(), 'Misma cuenta MP ⇒ MISMA CuentaEmpresa');

        // Checkout discriminado: cobro 1200 (1500 − propina) + propina 300.
        $delCheckout = $movimientos->where('origen_id', $txCheckout->id);
        $this->assertEqualsWithDelta(300.0, (float) $delCheckout->min('monto'), 0.01);
        $this->assertEqualsWithDelta(1200.0, (float) $delCheckout->max('monto'), 0.01);
    }

    // ==================== Helpers locales ====================

    protected function formaPagoEfectivoEnSucursalLocal(): FormaPago
    {
        $fp = FormaPago::create([
            'nombre' => 'Efectivo Local',
            'codigo' => 'efec_local',
            'concepto' => 'efectivo',
            'concepto_pago_id' => $this->fpOnline->concepto_pago_id,
            'activo' => true,
        ]);
        $fp->sucursales()->attach($this->sucursalId, ['activo' => true]);

        return $fp;
    }
}

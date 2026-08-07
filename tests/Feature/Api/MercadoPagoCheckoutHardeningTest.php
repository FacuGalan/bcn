<?php

namespace Tests\Feature\Api;

use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MercadoPagoCollectorIndex;
use App\Models\PedidoDelivery;
use App\Models\Tienda;
use App\Services\IntegracionesPago\CobroIntegracionService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Hardening del pago online (revisión adversarial 2026-08-07): carreras
 * webhook/expiración, pagos huérfanos (tx terminal), acreditación fallback
 * sin webhook, devoluciones externas, retorno_url validada y FP checkout
 * sin el tilde de tienda.
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (Fase 3, notas de revisión).
 */
class MercadoPagoCheckoutHardeningTest extends TestCase
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
            'cliente' => ['nombre' => 'Cliente Online', 'telefono' => '1155550000'],
            'direccion' => ['direccion' => 'Av. Siempreviva 742'],
            'pago' => ['forma_pago_id' => $this->fpOnline->id, 'retorno_url' => 'https://tienda.test/tienda-mp/pedido/{token}/pago'],
        ], $extra);

        return $this->postJson('/api/v1/tiendas/tienda-mp/pedidos', $payload);
    }

    protected function txDelPedido(int $pedidoId): IntegracionPagoTransaccion
    {
        return IntegracionPagoTransaccion::porCobrable('PedidoDelivery', $pedidoId)->latest('id')->firstOrFail();
    }

    protected function webhookPayment(IntegracionPagoTransaccion $tx, string $status, string $paymentId = 'PAY-001', array $fakesExtra = []): \Illuminate\Testing\TestResponse
    {
        Http::fake($fakesExtra + [
            "api.mercadopago.com/v1/payments/{$paymentId}" => Http::response([
                'id' => $paymentId,
                'status' => $status,
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

    // ==================== Pagos huérfanos (tx terminal) ====================

    public function test_pago_aprobado_sobre_tx_expirada_se_devuelve_sin_transicionar_el_pedido(): void
    {
        $this->fakePreferencia();
        $pedido = PedidoDelivery::find($this->altaPedidoOnline()->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);

        // La tx murió (expiró); el consumidor pagó el link viejo igual.
        $tx->update(['estado' => IntegracionPagoTransaccion::ESTADO_EXPIRADO]);

        $this->webhookPayment($tx, 'approved', fakesExtra: [
            'api.mercadopago.com/v1/payments/PAY-001/refunds' => Http::response(['id' => 'REF-9', 'status' => 'approved'], 201),
        ])->assertOk()->assertJsonPath('status', 'ok');

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_DEVUELTO, $tx->estado);

        // El pedido NUNCA transicionó: sigue borrador, sin pago materializado.
        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertNotSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments/PAY-001/refunds'));
    }

    public function test_pago_huerfano_con_refund_caido_responde_500_para_que_mp_reintente(): void
    {
        $this->fakePreferencia();
        $pedido = PedidoDelivery::find($this->altaPedidoOnline()->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);
        $tx->update(['estado' => IntegracionPagoTransaccion::ESTADO_EXPIRADO]);

        $this->webhookPayment($tx, 'approved', fakesExtra: [
            'api.mercadopago.com/v1/payments/PAY-001/refunds' => Http::response(['message' => 'boom'], 500),
        ])->assertStatus(500);

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_EXPIRADO, $tx->estado, 'La tx no se marca devuelta sin refund');
        $this->assertTrue(
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_DEVOLUCION_FALLIDA)
                ->exists(),
        );
    }

    // ==================== Barrido de expiración ====================

    public function test_barrido_no_expira_una_tx_cuyo_pago_esta_aprobado_en_mp(): void
    {
        $this->fakePreferencia();
        $pedido = PedidoDelivery::find($this->altaPedidoOnline()->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);
        $tx->update(['expira_en' => now()->subMinute()]);

        // El consumidor pagó pero el webhook NUNCA llegó: el barrido consulta
        // vivo y acredita en vez de expirar (antes cancelaba un pedido PAGADO).
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [[
                'id' => 'PAY-77',
                'status' => 'approved',
                'external_reference' => 'BCN-TX-'.$tx->id,
            ]]]),
        ]);

        app(CobroIntegracionService::class)->expirarPendientesVencidas();

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado);
        $this->assertSame('PAY-77', $tx->paymentIdCheckout());

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
        $this->assertNotSame(PedidoDelivery::ESTADO_CANCELADO, $pedido->estado_pedido);
    }

    public function test_barrido_posterga_la_tx_checkout_si_mp_no_responde(): void
    {
        $this->fakePreferencia();
        $pedido = PedidoDelivery::find($this->altaPedidoOnline()->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);
        $tx->update(['expira_en' => now()->subMinute()]);

        // Sin certeza del estado vivo, expirar podría matar un pedido pagado.
        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['message' => 'down'], 500),
        ]);

        app(CobroIntegracionService::class)->expirarPendientesVencidas();

        $this->assertSame(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $tx->fresh()->estado);
        $this->assertSame(PedidoDelivery::ESTADO_BORRADOR, $pedido->fresh()->estado_pedido);
    }

    // ==================== Acreditación fallback (GET vivo) ====================

    public function test_get_pago_con_aprobado_vivo_acredita_sin_esperar_el_webhook(): void
    {
        $this->fakePreferencia();
        $respuesta = $this->altaPedidoOnline();
        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);

        Http::fake([
            'api.mercadopago.com/v1/payments/search*' => Http::response(['results' => [[
                'id' => 'PAY-55',
                'status' => 'approved',
                'external_reference' => 'BCN-TX-'.$tx->id,
            ]]]),
        ]);

        $this->getJson('/api/v1/tiendas/tienda-mp/pedidos/'.$pedido->token_seguimiento.'/pago')
            ->assertOk()
            ->assertJsonPath('data.estado', 'aprobado');

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado);
        $this->assertSame('PAY-55', $tx->paymentIdCheckout(), 'payment_id persistido para el refund futuro');

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
        $this->assertFalse($pedido->esperandoPagoOnline());
    }

    // ==================== Devolución externa / contracargo ====================

    public function test_webhook_refunded_sobre_tx_confirmada_registra_la_devolucion(): void
    {
        $this->fakePreferencia();
        $pedido = PedidoDelivery::find($this->altaPedidoOnline()->json('data.id'));
        $tx = $this->txDelPedido($pedido->id);

        $this->webhookPayment($tx, 'approved')->assertOk();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->fresh()->estado);

        // Refund hecho desde el panel de MP (o contracargo): antes se
        // ignoraba. (PAY-002: Http::fake no pisa un stub ya registrado para
        // la misma URL — la tx se resuelve igual por external_reference.)
        $this->webhookPayment($tx->fresh(), 'refunded', 'PAY-002')->assertOk()->assertJsonPath('status', 'ok');

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_DEVUELTO, $tx->estado);
        $this->assertTrue(
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_DEVUELTO)
                ->exists(),
        );
    }

    // ==================== retorno_url validada ====================

    public function test_retorno_url_de_otro_dominio_se_ignora(): void
    {
        $this->fakePreferencia();

        $respuesta = $this->altaPedidoOnline([
            'pago' => ['forma_pago_id' => $this->fpOnline->id, 'retorno_url' => 'https://atacante.evil/phish/{token}'],
        ])->assertCreated();

        $tx = $this->txDelPedido((int) $respuesta->json('data.id'));

        // El pago funciona igual, pero SIN back_url: la preferencia real del
        // comercio no puede redirigir post-pago a un dominio ajeno.
        $this->assertArrayNotHasKey('back_url', $tx->metadata['checkout'] ?? []);
    }

    // ==================== FP checkout sin tilde de tienda ====================

    public function test_fp_con_checkout_viaja_aunque_no_este_marcada_disponible_en_tienda(): void
    {
        \App\Models\FormaPagoSucursal::where('forma_pago_id', $this->fpOnline->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['disponible_en_tienda' => false]);

        $formasPago = collect($this->getJson('/api/v1/tiendas/tienda-mp')->assertOk()->json('data.formas_pago'));
        $online = $formasPago->firstWhere('id', $this->fpOnline->id);

        $this->assertNotNull($online, 'Asociar el checkout ES la intención de ofrecerla online (RF-T77)');
        $this->assertTrue((bool) $online['pago_online']);

        // Y el alta con esa FP funciona (borrador esperando pago).
        $this->fakePreferencia();
        $this->altaPedidoOnline()->assertCreated();
    }

    public function test_fp_declarable_sin_tilde_de_tienda_no_viaja(): void
    {
        $fpComun = FormaPago::create([
            'nombre' => 'Efectivo Local',
            'codigo' => 'efec_local',
            'concepto' => 'efectivo',
            'concepto_pago_id' => $this->fpOnline->concepto_pago_id,
            'activo' => true,
        ]);
        $fpComun->sucursales()->attach($this->sucursalId, ['activo' => true]);
        \App\Models\FormaPagoSucursal::where('forma_pago_id', $fpComun->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['disponible_en_tienda' => false]);

        $formasPago = collect($this->getJson('/api/v1/tiendas/tienda-mp')->assertOk()->json('data.formas_pago'));

        $this->assertNull($formasPago->firstWhere('id', $fpComun->id));
    }
}

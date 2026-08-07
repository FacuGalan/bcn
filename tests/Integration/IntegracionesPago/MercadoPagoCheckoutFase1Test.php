<?php

namespace Tests\Integration\IntegracionesPago;

use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MercadoPagoCollectorIndex;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Pago online en tienda — Checkout Pro, Fase 1 (catálogo + esquema).
 *
 * Verifica las bases que la Fase 2 (gateway + circuito) da por sentadas:
 * la integración `mercadopago_checkout` registra el índice colector (sin el
 * guard ampliado el webhook nunca resolvería el tenant), las transacciones
 * aceptan iniciador NULL (checkout sin operador), el pago del pedido se
 * vincula a su transacción (espejo de venta_pagos) y la propina online vive
 * fuera del total del pedido.
 *
 * Ref: .claude/specs/tienda-pago-online-mp.md (Fase 1, RF-T75/RF-T83).
 */
class MercadoPagoCheckoutFase1Test extends TestCase
{
    use WithSucursal, WithTenant;

    private const USER_ID = '777666555';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        MercadoPagoCollectorIndex::where('user_id_externo', self::USER_ID)->delete();
    }

    protected function tearDown(): void
    {
        PedidoDeliveryPago::query()->delete();
        PedidoDelivery::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        MercadoPagoCollectorIndex::where('user_id_externo', self::USER_ID)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function integracionCheckout(): IntegracionPago
    {
        return IntegracionPago::firstOrCreate(
            ['codigo' => IntegracionPago::CODIGO_MERCADOPAGO_CHECKOUT],
            [
                'nombre' => 'Mercado Pago - Checkout Online',
                'modos_disponibles' => [MercadoPagoGateway::MODO_CHECKOUT_PRO],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 3,
            ]
        );
    }

    private function configCheckout(): IntegracionPagoSucursal
    {
        return IntegracionPagoSucursal::create([
            'integracion_pago_id' => $this->integracionCheckout()->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-CHECKOUT-TOKEN',
            'user_id_externo' => self::USER_ID,
            'activo' => true,
        ]);
    }

    public function test_gateway_soporta_modo_checkout_pro(): void
    {
        $this->assertSame('checkout_pro', MercadoPagoGateway::MODO_CHECKOUT_PRO);
        $this->assertContains(MercadoPagoGateway::MODO_CHECKOUT_PRO, (new MercadoPagoGateway)->modosSoportados());
        $this->assertTrue($this->integracionCheckout()->soportaModo(MercadoPagoGateway::MODO_CHECKOUT_PRO));
    }

    public function test_config_checkout_sincroniza_indice_colector(): void
    {
        // Guard ampliado en IntegracionPagoSucursal::sincronizarIndiceColector():
        // sin esto, el webhook `payment` nunca resuelve el tenant del checkout.
        $config = $this->configCheckout();

        $idx = MercadoPagoCollectorIndex::porUserId(self::USER_ID, 'test')->first();
        $this->assertNotNull($idx, 'La config checkout debe registrar el índice colector');
        $this->assertSame($this->comercio->id, $idx->comercio_id);
        $this->assertSame($config->id, $idx->integracion_pago_sucursal_id);
    }

    public function test_transaccion_acepta_usuario_iniciador_null(): void
    {
        $config = $this->configCheckout();
        $formaPago = FormaPago::create([
            'nombre' => 'Mercado Pago Online',
            'codigo' => 'mp_online',
            'concepto' => 'wallet',
            'activo' => true,
        ]);

        $tx = IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => null,
            'modo_usado' => MercadoPagoGateway::MODO_CHECKOUT_PRO,
            'monto' => 2500.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'external_reference' => 'BCN-TX-F1-NULL',
            'expira_en' => now()->addMinutes(30),
        ]);

        $this->assertNull($tx->fresh()->usuario_iniciador_id);
    }

    public function test_pago_de_pedido_se_vincula_a_su_transaccion(): void
    {
        $config = $this->configCheckout();
        $formaPago = FormaPago::create([
            'nombre' => 'Mercado Pago Online',
            'codigo' => 'mp_online',
            'concepto' => 'wallet',
            'activo' => true,
        ]);

        $tx = IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => null,
            'modo_usado' => MercadoPagoGateway::MODO_CHECKOUT_PRO,
            'monto' => 3200.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'external_reference' => 'BCN-TX-F1-PEDIDO',
            'expira_en' => now()->addMinutes(30),
        ]);

        $pedido = PedidoDelivery::create([
            'tipo' => 'take_away',
            'sucursal_id' => $this->sucursalId,
            'nombre_cliente_temporal' => 'Consumidor Tienda',
        ]);

        $pago = PedidoDeliveryPago::create([
            'pedido_delivery_id' => $pedido->id,
            'forma_pago_id' => $formaPago->id,
            'monto_base' => 3200.00,
            'monto_final' => 3200.00,
            'estado' => 'planificado',
            'afecta_caja' => false,
            'integracion_pago_transaccion_id' => $tx->id,
        ]);

        $this->assertSame($tx->id, $pago->fresh()->integracionTransaccion->id);
    }

    public function test_propina_online_default_cero_y_persiste(): void
    {
        $pedido = PedidoDelivery::create([
            'tipo' => 'take_away',
            'sucursal_id' => $this->sucursalId,
            'nombre_cliente_temporal' => 'Consumidor Tienda',
        ]);

        $this->assertSame('0.00', $pedido->fresh()->propina_online);

        $pedido->update(['propina_online' => 500.50]);
        $this->assertSame('500.50', $pedido->fresh()->propina_online);
    }

    public function test_pivote_forma_pago_guarda_config_checkout(): void
    {
        $formaPago = FormaPago::create([
            'nombre' => 'Mercado Pago Online',
            'codigo' => 'mp_online',
            'concepto' => 'wallet',
            'activo' => true,
        ]);

        $formaPago->integraciones()->attach($this->integracionCheckout()->id, [
            'config_checkout' => json_encode(['cuotas_max' => 6]),
        ]);

        $pivot = $formaPago->fresh()->integraciones->first()->pivot;
        $this->assertSame(6, data_get(json_decode($pivot->config_checkout, true), 'cuotas_max'));
    }
}

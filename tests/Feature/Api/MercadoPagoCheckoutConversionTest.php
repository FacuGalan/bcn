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
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\Pedidos\PedidoDeliveryService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Conversión pedido→venta de un pedido PAGADO ONLINE (bug reportado en la
 * validación en vivo 2026-08-07: el pedido figura pagado pero convertir
 * falla "como si faltara cobrar").
 */
class MercadoPagoCheckoutConversionTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected int $cajaId;

    private const USER_ID = '888777666';

    private const WEBHOOK_URL = '/api/integraciones/mercadopago/webhook';

    protected ?Tienda $tienda = null;

    protected FormaPago $fpOnline;

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

        $this->cajaId = $this->crearCajaAbierta($this->sucursalId)->id;

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

        IntegracionPagoSucursal::create([
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

    protected function pedidoPagadoOnline(): PedidoDelivery
    {
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'PREF-001',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=PREF-001',
            ], 201),
        ]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-mp/pedidos', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 1]],
            'cliente' => ['nombre' => 'Cliente Online', 'telefono' => '1155550000'],
            'direccion' => ['direccion' => 'Av. Siempreviva 742'],
            'pago' => ['forma_pago_id' => $this->fpOnline->id],
        ])->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $tx = IntegracionPagoTransaccion::porCobrable('PedidoDelivery', $pedido->id)->latest('id')->firstOrFail();

        Http::fake([
            'api.mercadopago.com/v1/payments/PAY-001' => Http::response([
                'id' => 'PAY-001',
                'status' => 'approved',
                'external_reference' => 'BCN-TX-'.$tx->id,
                'transaction_amount' => (float) $tx->monto,
            ]),
        ]);

        $this->postJson(self::WEBHOOK_URL, [
            'type' => 'payment',
            'data' => ['id' => 'PAY-001'],
            'user_id' => self::USER_ID,
        ])->assertOk();

        return $pedido->fresh();
    }

    public function test_pedido_pagado_online_se_convierte_en_venta(): void
    {
        $pedido = $this->pedidoPagadoOnline();
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);

        // El pedido de tienda no tiene operador: la venta toma el usuario
        // autenticado que convierte (en el panel siempre hay uno).
        $this->actingAs(\App\Models\User::factory()->create());

        $service = app(PedidoDeliveryService::class);
        $service->aceptarPedidoExterno($pedido, demoraMin: 30);

        // Pedido pagado online NO tiene caja (nunca pasó por un cobro del
        // panel): la conversión recibe la caja activa del operador — el bug
        // de la validación en vivo 2026-08-07 era convertir sin cajaId.
        $venta = $service->convertirEnVenta($pedido->fresh(), cajaId: $this->cajaId);

        $this->assertNotNull($venta->id);
        $this->assertSame(PedidoDelivery::ESTADO_FACTURADO, $pedido->fresh()->estado_pedido);

        // El pago migró con el vínculo a la tx (sin doble movimiento).
        $pagoVenta = $venta->pagos()->first();
        $this->assertNotNull($pagoVenta);
        $this->assertNotNull($pagoVenta->integracion_pago_transaccion_id);
    }

    public function test_la_preferencia_lleva_los_renglones_reales_del_pedido(): void
    {
        // Mejora 2026-08-07: el pagador ve QUÉ paga en la pantalla de MP —
        // los renglones viajan como ítems (suma exacta contra el monto).
        // Nota: el assert corre ANTES de fakear el webhook (cada Http::fake
        // resetea lo grabado).
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response([
                'id' => 'PREF-001',
                'init_point' => 'https://www.mercadopago.com.ar/checkout/v1/redirect?pref_id=PREF-001',
            ], 201),
        ]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $respuesta = $this->postJson('/api/v1/tiendas/tienda-mp/pedidos', [
            'tipo' => 'delivery',
            'items' => [['articulo_id' => $articulo->id, 'cantidad' => 2]],
            'cliente' => ['nombre' => 'Cliente Online', 'telefono' => '1155550000'],
            'direccion' => ['direccion' => 'Av. Siempreviva 742'],
            'pago' => ['forma_pago_id' => $this->fpOnline->id],
        ])->assertCreated();

        $pedido = PedidoDelivery::find($respuesta->json('data.id'));
        $detalle = $pedido->detalles()->with('articulo:id,nombre')->first();

        Http::assertSent(function ($request) use ($pedido, $detalle) {
            if (! str_contains($request->url(), '/checkout/preferences')) {
                return false;
            }

            $items = collect($request['items'] ?? []);
            $linea = $items->firstWhere('title', '2 x '.$detalle->articulo->nombre);

            return $linea !== null
                && abs((float) $linea['unit_price'] - (float) $detalle->total) < 0.01
                && abs(round($items->sum('unit_price'), 2) - (float) $pedido->total_final) < 0.01;
        });
    }
}

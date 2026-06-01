<?php

namespace Tests\Integration\IntegracionesPago;

use App\Events\IntegracionesPago\IntegracionPagoActualizado;
use App\Models\Caja;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MercadoPagoCollectorIndex;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 6 — webhook global de Mercado Pago.
 *
 * Verifica la recepción de la notificación de una Order: resolución multi-tenant
 * por el índice colector (DB config), re-chequeo del estado real (Http::fake),
 * confirmación server-side idempotente, broadcast Reverb y auditoría. No pega a
 * MP real; la entrega real del webhook se prueba en el servidor (URL pública).
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 6 — RF-08/RF-14/RF-18).
 */
class MercadoPagoWebhookTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    private const ORDER_ID = 'ORDWEBHOOK01';

    private const USER_ID = '555444333';

    private const WEBHOOK_URL = '/api/integraciones/mercadopago/webhook';

    protected int $cajaId;

    protected IntegracionPagoSucursal $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        // Limpiar índice colector (DB config) para no arrastrar entradas previas.
        MercadoPagoCollectorIndex::where('user_id_externo', self::USER_ID)->delete();

        Caja::where('id', $this->cajaId)->update([
            'estado' => 'abierta',
            'fecha_apertura' => now(),
            'mp_pos_id' => 'POS-1',
            'mp_pos_external_id' => 'EXT-POS-1',
        ]);

        $integracion = IntegracionPago::firstOrCreate(
            ['codigo' => 'mercadopago_qr'],
            [
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]
        );

        // Crear el config dispara el hook que sincroniza el índice colector
        // (DB config) con el comercio del contexto WithTenant.
        $this->config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $integracion->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-12345',
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
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearTransaccionPendiente(): IntegracionPagoTransaccion
    {
        $formaPago = FormaPago::create([
            'nombre' => 'Mercado Pago QR',
            'codigo' => 'mp_qr',
            'concepto' => 'wallet',
            'activo' => true,
        ]);

        return IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $this->config->id,
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
            'usuario_iniciador_id' => 1,
            'modo_usado' => 'qr_dinamico',
            'monto' => 1500.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'external_id' => self::ORDER_ID,
            'external_reference' => 'BCN-TX-999',
            'expira_en' => now()->addMinutes(5),
        ]);
    }

    private function fakeOrderProcesada(): void
    {
        Http::fake([
            '*/v1/orders/'.self::ORDER_ID => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'processed',
            ], 200),
        ]);
    }

    private function payloadOrder(string $orderId = self::ORDER_ID, string $userId = self::USER_ID): array
    {
        return [
            'type' => 'order',
            'action' => 'order.processed',
            'user_id' => $userId,
            'data' => ['id' => $orderId],
        ];
    }

    public function test_webhook_aprobado_confirma_la_transaccion_y_broadcastea(): void
    {
        $this->fakeOrderProcesada();
        Event::fake([IntegracionPagoActualizado::class]);

        $tx = $this->crearTransaccionPendiente();

        $response = $this->postJson(self::WEBHOOK_URL, $this->payloadOrder());

        $response->assertOk()->assertJson(['status' => 'ok']);

        $tx->refresh();
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado, 'La transacción debe quedar confirmada');
        $this->assertNotNull($tx->confirmado_en);

        // Auditoría: quedó el evento de webhook recibido + el de confirmación.
        $eventos = IntegracionPagoEvento::where('transaccion_id', $tx->id)->pluck('evento')->all();
        $this->assertContains(IntegracionPagoEvento::EVENTO_WEBHOOK_RECIBIDO, $eventos);
        $this->assertContains(IntegracionPagoEvento::EVENTO_CONFIRMADO, $eventos);

        // Broadcast en tiempo real al canal de la transacción.
        Event::assertDispatched(IntegracionPagoActualizado::class, function ($e) use ($tx) {
            return $e->transaccionId === $tx->id && $e->estado === 'aprobado';
        });
    }

    public function test_webhook_es_idempotente_si_la_transaccion_ya_esta_confirmada(): void
    {
        $this->fakeOrderProcesada();

        $tx = $this->crearTransaccionPendiente();
        $tx->update(['estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO, 'confirmado_en' => now()]);

        $response = $this->postJson(self::WEBHOOK_URL, $this->payloadOrder());

        $response->assertOk();

        // confirmarCobro es idempotente: no se duplica el evento de confirmación.
        $this->assertEquals(
            0,
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_CONFIRMADO)
                ->count(),
            'No debe registrar una nueva confirmación sobre una transacción ya terminal'
        );
    }

    public function test_webhook_de_collector_desconocido_responde_sin_match(): void
    {
        $tx = $this->crearTransaccionPendiente();

        $response = $this->postJson(self::WEBHOOK_URL, $this->payloadOrder(userId: '000000000'));

        $response->assertOk()->assertJson(['status' => 'sin_match']);

        $this->assertEquals(
            IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            $tx->fresh()->estado,
            'La transacción no debe tocarse si no se resuelve el collector'
        );
    }

    public function test_webhook_con_firma_valida_confirma(): void
    {
        $this->fakeOrderProcesada();
        $secret = 'super-secret-key';
        $this->config->update(['webhook_secret' => $secret]);

        $tx = $this->crearTransaccionPendiente();

        // Firma MP: HMAC-SHA256 del manifest `id:{lower};request-id:{rid};ts:{ts};`.
        $ts = '1717000000';
        $requestId = 'req-abc-1';
        $manifest = 'id:'.strtolower(self::ORDER_ID).';request-id:'.$requestId.';ts:'.$ts.';';
        $v1 = hash_hmac('sha256', $manifest, $secret);

        $response = $this->withHeaders([
            'x-signature' => "ts={$ts},v1={$v1}",
            'x-request-id' => $requestId,
        ])->postJson(self::WEBHOOK_URL, $this->payloadOrder());

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->fresh()->estado);
    }

    public function test_webhook_con_firma_invalida_responde_401(): void
    {
        // Configurar un secret → la firma pasa a ser obligatoria.
        $this->config->update(['webhook_secret' => 'super-secret-key']);

        $tx = $this->crearTransaccionPendiente();

        $response = $this->withHeaders(['x-signature' => 'ts=123,v1=firmafalsa', 'x-request-id' => 'req-1'])
            ->postJson(self::WEBHOOK_URL, $this->payloadOrder());

        $response->assertStatus(401);

        $this->assertEquals(
            IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            $tx->fresh()->estado,
            'Una firma inválida no debe confirmar nada'
        );
    }
}

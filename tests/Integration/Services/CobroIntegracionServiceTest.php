<?php

namespace Tests\Integration\Services;

use App\Events\IntegracionesPago\IntegracionPagoActualizado;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\Sucursal;
use App\Services\IntegracionesPago\CobroIntegracionService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 5 — CobroIntegracionService::asociarCobrable.
 *
 * Cubre la asociación del cobrable (venta/pedido) a una transacción ya
 * confirmada, propia del modelo "cobro primero, venta después": el pago se
 * confirma con el QR y el comprobante se crea después, recién ahí hay cobrable.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 5 de 10).
 */
class CobroIntegracionServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    private CobroIntegracionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        if (! IntegracionPago::porCodigo('mercadopago_qr')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        $this->service = app(CobroIntegracionService::class);
    }

    protected function tearDown(): void
    {
        IntegracionPagoEvento::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearTransaccionConfirmada(): IntegracionPagoTransaccion
    {
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');
        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mpId,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-12345',
            'user_id_externo' => '999888777',
        ]);

        $formaPago = \App\Models\FormaPago::create([
            'nombre' => 'QR Test',
            'codigo' => 'QR_TEST',
            'concepto' => 'wallet',
            'activo' => true,
        ]);

        return IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => 1, // FK cross-DB (config.users): no se valida en tenant
            'modo_usado' => 'qr_dinamico',
            'monto' => 1500.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO,
            'confirmado_en' => now(),
            'expira_en' => now()->addMinutes(5),
        ]);
    }

    public function test_asociar_cobrable_vincula_el_cobrable_y_registra_evento(): void
    {
        $tx = $this->crearTransaccionConfirmada();
        $cobrable = Sucursal::find($this->sucursalId); // stand-in: el service es agnóstico al tipo

        $this->service->asociarCobrable($tx, $cobrable);

        $tx->refresh();
        $this->assertEquals($cobrable->id, $tx->cobrable_id);
        $this->assertNotNull($tx->cobrable_type);
        $this->assertEquals(
            1,
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO)
                ->count()
        );
    }

    public function test_asociar_cobrable_es_idempotente_si_ya_tiene_cobrable(): void
    {
        $tx = $this->crearTransaccionConfirmada();
        $cobrable = Sucursal::find($this->sucursalId);

        $this->service->asociarCobrable($tx, $cobrable);
        // Segunda llamada: no debe re-asociar ni duplicar el evento.
        $this->service->asociarCobrable($tx, $cobrable);

        $this->assertEquals(
            1,
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO)
                ->count()
        );
    }

    // ==================== Fase 7 — iniciar cobro QR estático ====================

    public function test_iniciar_cobro_estatico_guarda_la_imagen_del_qr_del_pos_en_metadata(): void
    {
        // El modo estático no devuelve qr_data: la app muestra el QR impreso del
        // POS (cuya URL se guardó al sincronizar la caja). El service debe
        // persistir esa URL en metadata para que el front la pueda renderizar.
        Http::fake([
            'api.mercadopago.com/v1/orders' => Http::response(['id' => 'ORD-EST-SVC'], 201),
        ]);

        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');
        $config = IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mpId,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-12345',
            'user_id_externo' => '999888777',
        ]);

        $formaPago = \App\Models\FormaPago::create([
            'nombre' => 'QR Estático',
            'codigo' => 'QR_EST',
            'concepto' => 'wallet',
            'activo' => true,
        ]);

        $caja = \App\Models\Caja::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Caja QR',
            'codigo' => 'CQR',
            'tipo' => 'efectivo',
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'estado' => 'cerrada',
            'activo' => true,
            'mp_pos_id' => '999111',
            'mp_pos_external_id' => 'BCN'.$this->comercio->id.'POS999',
            'mp_pos_qr_url' => 'https://mp.com/qr/999111/static.png',
        ]);

        $tx = $this->service->iniciarCobro($config, [
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'usuario_iniciador_id' => 1,
            'modo_usado' => 'qr_estatico',
            'monto' => 2500.00,
        ]);

        $this->assertNull($tx->qr_data);
        $this->assertSame('https://mp.com/qr/999111/static.png', $tx->metadata['qr_image_url'] ?? null);
        $this->assertSame('ORD-EST-SVC', $tx->external_id);
        $this->assertSame('qr_estatico', $tx->modo_usado);
    }

    // ==================== Fase 8 — confirmación manual + expiración ====================

    private function crearTransaccionPendiente(?\Carbon\Carbon $expiraEn = null): IntegracionPagoTransaccion
    {
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');
        $config = IntegracionPagoSucursal::firstOrCreate(
            ['integracion_pago_id' => $mpId, 'sucursal_id' => $this->sucursalId],
            ['modo' => 'test', 'access_token_test' => 'TEST-TOKEN-12345', 'user_id_externo' => '999888777'],
        );

        $formaPago = \App\Models\FormaPago::firstOrCreate(
            ['codigo' => 'QR_PEND'],
            ['nombre' => 'QR Pend', 'concepto' => 'wallet', 'activo' => true],
        );

        return IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => 1,
            'modo_usado' => 'qr_estatico',
            'monto' => 800.00,
            'estado' => IntegracionPagoTransaccion::ESTADO_PENDIENTE,
            'expira_en' => $expiraEn ?? now()->addMinutes(5),
        ]);
    }

    public function test_confirmar_manual_marca_confirmado_manual_y_registra_quien(): void
    {
        $tx = $this->crearTransaccionPendiente();

        $this->service->confirmarManual($tx, usuarioId: 7, motivo: 'cliente mostró comprobante');

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CONFIRMADO_MANUAL, $tx->estado);
        $this->assertNotNull($tx->confirmado_en);

        $evento = IntegracionPagoEvento::where('transaccion_id', $tx->id)
            ->where('evento', IntegracionPagoEvento::EVENTO_CONFIRMADO_MANUAL)
            ->first();
        $this->assertNotNull($evento);
        $this->assertSame(7, $evento->metadata['usuario_id']);
        $this->assertSame('cliente mostró comprobante', $evento->metadata['motivo']);
    }

    public function test_confirmar_manual_es_idempotente_en_estado_terminal(): void
    {
        $tx = $this->crearTransaccionPendiente();
        $tx->update(['estado' => IntegracionPagoTransaccion::ESTADO_CANCELADO]);

        $this->service->confirmarManual($tx, usuarioId: 1);

        $tx->refresh();
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_CANCELADO, $tx->estado);
        $this->assertEquals(
            0,
            IntegracionPagoEvento::where('transaccion_id', $tx->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_CONFIRMADO_MANUAL)->count(),
        );
    }

    public function test_expirar_pendientes_vencidas_marca_expirado_y_respeta_vigentes(): void
    {
        \Illuminate\Support\Facades\Event::fake([IntegracionPagoActualizado::class]);

        $vencida = $this->crearTransaccionPendiente(now()->subMinute());
        $vigente = $this->crearTransaccionPendiente(now()->addMinutes(5));

        $cantidad = $this->service->expirarPendientesVencidas();

        $this->assertSame(1, $cantidad);
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_EXPIRADO, $vencida->fresh()->estado);
        $this->assertSame(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $vigente->fresh()->estado);

        $this->assertEquals(
            1,
            IntegracionPagoEvento::where('transaccion_id', $vencida->id)
                ->where('evento', IntegracionPagoEvento::EVENTO_EXPIRADO)->count(),
        );

        \Illuminate\Support\Facades\Event::assertDispatched(
            IntegracionPagoActualizado::class,
            fn (IntegracionPagoActualizado $e) => $e->transaccionId === $vencida->id && $e->estado === 'expirado',
        );
    }
}

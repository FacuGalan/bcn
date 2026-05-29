<?php

namespace Tests\Integration\Services;

use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\Sucursal;
use App\Services\IntegracionesPago\CobroIntegracionService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
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
}

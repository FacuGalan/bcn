<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\PedidosMostrador;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorPago;
use App\Models\User;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\Pedidos\PedidoMostradorService;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithPedidoMostradorHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 5 — cobro QR de un pago PLANIFICADO con forma de pago integrada, desde
 * el listado de Pedidos Mostrador ("Cobrar pendiente").
 *
 * Escenario: el operario da de alta el pedido y planifica un pago con Mercado
 * Pago sin cobrarlo (queda en estado=planificado). Después, al "Cobrar", ese
 * pago NO debe materializarse directo: debe disparar el QR y esperar la
 * confirmación. Si el pago se aprueba → se materializa (activo, toca caja) y se
 * asocia la transacción al pedido. Si se cancela/expira → queda planificado y
 * editable, sin tocar caja.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 5 de 10).
 */
class CobroQrPagoPlanificadoTest extends TestCase
{
    use WithPedidoMostradorHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    private const QR_DATA = '00020101021243650016com.mercadolibre0201306364TESTQR5204000053039865802AR6304ABCD';

    private const ORDER_ID = 'ORDPLAN0001X';

    protected PedidoMostradorService $service;

    protected int $cajaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->service = new PedidoMostradorService;

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        // Bypass del caché de SucursalService → acceso total (igual que SmokePedidosTest).
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

        Livewire::withoutLazyLoading();

        $caja = $this->crearCajaAbierta($this->sucursalId, [
            'mp_pos_id' => 'POS-1',
            'mp_pos_external_id' => 'EXT-POS-1',
        ]);
        $this->cajaId = $caja->id;

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

        IntegracionPagoSucursal::firstOrCreate(
            ['integracion_pago_id' => $integracion->id, 'sucursal_id' => $this->sucursalId],
            [
                'modo' => 'test',
                'access_token_test' => 'TEST-TOKEN-12345',
                'user_id_externo' => '999888777',
                'activo' => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        IntegracionPagoEvento::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearFormaPagoConIntegracion(): FormaPago
    {
        $concepto = ConceptoPago::firstOrCreate(
            ['codigo' => 'WALLET'],
            ['nombre' => 'Billetera virtual', 'activo' => true, 'orden' => 5]
        );

        $fp = FormaPago::create([
            'nombre' => 'Mercado Pago QR',
            'codigo' => 'mp_qr',
            'concepto' => 'wallet',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ]);

        $integracion = IntegracionPago::porCodigo('mercadopago_qr')->first();
        $fp->integraciones()->attach($integracion->id, [
            'modo_default' => 'qr_dinamico',
            'modos_permitidos' => json_encode(['qr_dinamico']),
            'es_principal' => true,
        ]);

        return $fp;
    }

    /**
     * Crea un pedido confirmado con un pago planificado con la FP integrada por
     * el total. Devuelve [pedido, pago, fp].
     */
    private function pedidoConPagoPlanificadoMp(float $total = 1000): array
    {
        $fp = $this->crearFormaPagoConIntegracion();
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: $total, cajaId: $this->cajaId);

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $fp->id,
            'monto_base' => $total,
            'monto_final' => $total,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        return [$pedido->fresh(), $pago->fresh(), $fp];
    }

    public function test_confirmar_pago_planificado_mp_dispara_qr_y_no_cobra_hasta_aprobar(): void
    {
        Http::fake([
            '*/v1/orders/*' => Http::response(['id' => self::ORDER_ID, 'status' => 'processed'], 200),
            '*/v1/orders' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'created',
                'type_response' => ['qr_data' => self::QR_DATA],
            ], 200),
        ]);

        [$pedido, $pago] = $this->pedidoConPagoPlanificadoMp(1000);

        $component = Livewire::test(PedidosMostrador::class)
            ->call('abrirCobrar', $pedido->id)
            ->assertSet('showCobrarModal', true)
            // Confirmar el pago planificado integrado → debe abrir el QR, NO cobrar.
            ->call('confirmarPagoPlanificado', $pago->id)
            ->assertSet('mostrarModalEsperandoPago', true)
            ->assertSet('showCobrarModal', false)
            ->assertSet('cobroIntegracionPagoPlanificadoId', $pago->id);

        // El pago sigue planificado hasta que el QR se apruebe.
        $this->assertEquals(
            PedidoMostradorPago::ESTADO_PLANIFICADO,
            $pago->fresh()->estado,
            'El pago debe seguir planificado mientras se espera el QR'
        );

        $txId = $component->get('cobroIntegracionTransaccionId');
        $this->assertNotNull($txId, 'Debe haber una transacción QR en curso');
        $tx = IntegracionPagoTransaccion::find($txId);
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $tx->estado);
        $this->assertNull($tx->cobrable_id, 'La transacción nace sin cobrable');

        // Polling detecta el pago aprobado → materializa el planificado.
        $component->call('pollearCobroIntegracion')
            ->assertSet('mostrarModalEsperandoPago', false)
            ->assertSet('cobroIntegracionPagoPlanificadoId', null);

        $pago->refresh();
        $this->assertEquals(PedidoMostradorPago::ESTADO_ACTIVO, $pago->estado, 'El pago debe quedar activo tras aprobar el QR');
        $this->assertNotNull($pago->movimiento_caja_id, 'Debe tocar caja al materializarse');

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->estado_pago, 'El pedido debe quedar pagado');

        $tx->refresh();
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado);
        $this->assertEquals($pedido->id, $tx->cobrable_id, 'La transacción debe asociarse al pedido');
        $this->assertStringContainsString('Pedido', (string) $tx->cobrable_type);

        $eventos = IntegracionPagoEvento::where('transaccion_id', $txId)->pluck('evento')->all();
        $this->assertContains(IntegracionPagoEvento::EVENTO_CONFIRMADO, $eventos);
        $this->assertContains(IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO, $eventos);
    }

    public function test_cancelar_qr_deja_el_pago_planificado_y_editable(): void
    {
        Http::fake([
            '*/v1/orders/*/cancel' => Http::response(['id' => self::ORDER_ID, 'status' => 'canceled'], 200),
            '*/v1/orders/*' => Http::response(['id' => self::ORDER_ID, 'status' => 'created'], 200),
            '*/v1/orders' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'created',
                'type_response' => ['qr_data' => self::QR_DATA],
            ], 200),
        ]);

        [$pedido, $pago] = $this->pedidoConPagoPlanificadoMp(1000);

        $component = Livewire::test(PedidosMostrador::class)
            ->call('abrirCobrar', $pedido->id)
            ->call('confirmarPagoPlanificado', $pago->id)
            ->assertSet('mostrarModalEsperandoPago', true)
            // Cancelar el QR → reabre "Cobrar pendiente", no materializa nada.
            ->call('cancelarCobroIntegracion')
            ->assertSet('mostrarModalEsperandoPago', false)
            ->assertSet('cobroIntegracionPagoPlanificadoId', null)
            ->assertSet('showCobrarModal', true);

        $this->assertEquals(
            PedidoMostradorPago::ESTADO_PLANIFICADO,
            $pago->fresh()->estado,
            'El pago debe seguir planificado tras cancelar el QR'
        );
        $this->assertNull($pago->fresh()->movimiento_caja_id, 'No debe tocar caja');
    }

    public function test_pago_planificado_sin_integracion_se_confirma_directo_sin_qr(): void
    {
        $efectivo = $this->crearFormaPagoEfectivo();
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $this->cajaId);
        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'afecta_caja' => true,
            'planificado' => true,
        ]);

        Livewire::test(PedidosMostrador::class)
            ->call('abrirCobrar', $pedido->id)
            ->call('confirmarPagoPlanificado', $pago->id)
            // FP sin integración → se materializa directo, sin abrir el QR.
            ->assertSet('mostrarModalEsperandoPago', false);

        $this->assertEquals(PedidoMostradorPago::ESTADO_ACTIVO, $pago->fresh()->estado);
    }
}

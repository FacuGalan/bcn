<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoMostrador;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorPago;
use App\Services\CuponService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\OpcionalService;
use App\Services\Pedidos\PedidoMostradorService;
use App\Services\PuntosService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithPedidoMostradorHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 5 — cobro QR dinámico desde el COBRO RÁPIDO del listado de pedidos.
 *
 * Regresión: el "Cobrar" del listado abre el desglose (NuevoPedidoMostrador en
 * modoCobroRapido) y procesa directo en confirmarPago(), sin pasar por
 * verificarPuntoVentaYProcesar() como NuevaVenta. Eso salteaba el enganche del
 * QR y materializaba el cobro sin pedir el pago. Ahora confirmarPago() invoca el
 * punto único compartido interceptarCobroPorIntegracion(), de modo que el QR se
 * dispara igual que en cualquier otro flujo de cobro.
 *
 * Análogo a CobroQrFlujoFelizTest (NuevaVenta) pero sobre el pedido.
 *
 * Ref: .claude/specs/integraciones-pago-mercadopago.md (Fase 5 de 10).
 */
class CobroQrPedidoMostradorTest extends TestCase
{
    use WithPedidoMostradorHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    private const QR_DATA = '00020101021243650016com.mercadolibre0201306364TESTQR5204000053039865802AR6304ABCD';

    private const ORDER_ID = 'ORDPEDIDO01X';

    protected PedidoMostradorService $service;

    protected int $cajaId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->service = new PedidoMostradorService;

        $this->actingAs(\App\Models\User::factory()->create());

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

    private function prepararCobroRapido(PedidoMostrador $pedido, FormaPago $fp, float $saldo): NuevoPedidoMostrador
    {
        $component = new NuevoPedidoMostrador;
        $component->boot(
            app(PedidoMostradorService::class),
            app(OpcionalService::class),
            app(CuponService::class),
            app(PuntosService::class),
        );

        // Estado equivalente al que deja iniciarCobroRapido(): el pedido ya
        // existe y solo se cobra el saldo pendiente con un único pago a la FP
        // integrada. No montamos el componente completo para no arrastrar el
        // catálogo táctil; sí replicamos las props que el cobro necesita.
        $component->modoCobroRapido = true;
        $component->modoEdicion = true;
        $component->pedidoId = $pedido->id;
        $component->sucursalId = $this->sucursalId;
        $component->cajaSeleccionada = $this->cajaId;
        $component->modalPagoEnModoCobro = true;
        $component->mostrarModalPago = true;
        $component->resultado = ['total_final' => $saldo];
        $component->montoPendienteDesglose = 0;
        $component->desglosePagos = [[
            'forma_pago_id' => $fp->id,
            'nombre' => $fp->nombre,
            'concepto_pago_id' => $fp->concepto_pago_id,
            'monto_base' => $saldo,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => $saldo,
            'monto_recibido' => $saldo,
            'vuelto' => 0,
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'factura_fiscal' => false,
            'es_moneda_extranjera' => false,
            'moneda_id' => null,
            'tipo_cambio_id' => null,
            'tipo_cambio_tasa' => null,
            'monto_moneda_original' => null,
        ]];

        return $component;
    }

    public function test_cobro_rapido_con_fp_integrada_dispara_qr_y_no_cobra_hasta_confirmar(): void
    {
        Http::fake([
            '*/v1/orders/*' => Http::response(['id' => self::ORDER_ID, 'status' => 'processed'], 200),
            '*/v1/orders' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'created',
                'type_response' => ['qr_data' => self::QR_DATA],
            ], 200),
        ]);

        $fp = $this->crearFormaPagoConIntegracion();
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $this->cajaId);
        $saldo = 1000.0;

        $component = $this->prepararCobroRapido($pedido, $fp, $saldo);

        // --- Paso 1: confirmar el desglose → debe abrir el QR, SIN cobrar ---
        $component->confirmarPago();

        $this->assertTrue($component->mostrarModalEsperandoPago, 'Debe abrirse el modal de espera del QR');
        $this->assertFalse($component->mostrarModalPago, 'El modal de desglose debe cerrarse para no superponerse');
        $this->assertNotNull($component->cobroIntegracionTransaccionId, 'Debe haber una transacción en curso');
        $this->assertSame(self::QR_DATA, $component->cobroIntegracionQrData, 'Debe tener el QR del gateway');

        $txId = $component->cobroIntegracionTransaccionId;
        $tx = IntegracionPagoTransaccion::find($txId);
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_PENDIENTE, $tx->estado);
        $this->assertNull($tx->cobrable_id, 'La transacción nace sin cobrable');
        $this->assertEquals(0, PedidoMostradorPago::where('pedido_mostrador_id', $pedido->id)->count(), 'No debe registrarse el pago todavía (cobro primero)');

        // --- Paso 2: polling detecta el pago aprobado → cobra el pedido ---
        $component->pollearCobroIntegracion();

        $pago = PedidoMostradorPago::where('pedido_mostrador_id', $pedido->id)->first();
        $this->assertNotNull($pago, 'El pago debe registrarse al confirmar el QR');
        $this->assertEquals(PedidoMostradorPago::ESTADO_ACTIVO, $pago->estado, 'El pago debe quedar activo (no planificado)');
        $this->assertEquals($fp->id, $pago->forma_pago_id);

        $pedido->refresh();
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PAGADO, $pedido->estado_pago, 'El pedido debe quedar pagado');

        $tx->refresh();
        $this->assertEquals(IntegracionPagoTransaccion::ESTADO_CONFIRMADO, $tx->estado, 'La transacción debe quedar confirmada');
        $this->assertEquals($pedido->id, $tx->cobrable_id, 'La transacción debe asociarse al pedido cobrado');
        $this->assertStringContainsString('Pedido', (string) $tx->cobrable_type, 'cobrable_type debe apuntar al pedido');

        // Estado del componente limpio tras el cobro.
        $this->assertFalse($component->mostrarModalEsperandoPago, 'El modal de espera debe cerrarse');
        $this->assertNull($component->cobroIntegracionTransaccionId, 'El estado del cobro debe resetearse');

        // Auditoría mínima.
        $eventos = IntegracionPagoEvento::where('transaccion_id', $txId)->pluck('evento')->all();
        $this->assertContains(IntegracionPagoEvento::EVENTO_CONFIRMADO, $eventos);
        $this->assertContains(IntegracionPagoEvento::EVENTO_COBRABLE_ASOCIADO, $eventos);
    }

    public function test_cancelar_qr_reabre_el_desglose_para_reintentar(): void
    {
        Http::fake([
            // El estado se consulta como cancelado tras pedir el QR.
            '*/v1/orders/*/cancel' => Http::response(['id' => self::ORDER_ID, 'status' => 'canceled'], 200),
            '*/v1/orders/*' => Http::response(['id' => self::ORDER_ID, 'status' => 'created'], 200),
            '*/v1/orders' => Http::response([
                'id' => self::ORDER_ID,
                'status' => 'created',
                'type_response' => ['qr_data' => self::QR_DATA],
            ], 200),
        ]);

        $fp = $this->crearFormaPagoConIntegracion();
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 1000, cajaId: $this->cajaId);

        $component = $this->prepararCobroRapido($pedido, $fp, 1000.0);

        $component->confirmarPago();
        $this->assertTrue($component->mostrarModalEsperandoPago);
        $this->assertFalse($component->mostrarModalPago);

        // Cancelar el cobro → el concern resetea y, vía hook alCancelarCobroIntegracion,
        // reabre el desglose para reintentar (modalPagoEnModoCobro sigue true).
        $component->cancelarCobroIntegracion();
        $this->assertFalse($component->mostrarModalEsperandoPago, 'El modal de espera debe cerrarse al cancelar');
        $this->assertTrue($component->mostrarModalPago, 'El desglose debe reabrirse para reintentar');

        // No se cobró nada.
        $this->assertEquals(0, PedidoMostradorPago::where('pedido_mostrador_id', $pedido->id)->count());
    }
}

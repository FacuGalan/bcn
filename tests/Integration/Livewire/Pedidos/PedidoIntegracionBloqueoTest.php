<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoEvento;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorPago;
use App\Services\IntegracionesPago\IntegracionPagoSucursalService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\Pedidos\PedidoMostradorService;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithPedidoMostradorHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Paridad del bloqueo de Fase 9 en Pedidos Mostrador: un pedido cobrado por
 * integración (QR MercadoPago) ya confirmada NO puede cancelarse, ni se puede
 * anular su pago de integración (la plata ya entró al proveedor; no hay refund
 * real todavía). Un pago en efectivo del mismo pedido sí se puede anular.
 *
 * Además: la config de integración por sucursal rechaza reutilizar una cuenta MP
 * (user_id_externo + modo) ya tomada por otra sucursal, para no pisar el índice
 * global que resuelve el webhook.
 */
class PedidoIntegracionBloqueoTest extends TestCase
{
    use WithCaja, WithPedidoMostradorHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoMostradorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
        $this->service = new PedidoMostradorService;

        $this->actingAs(\App\Models\User::factory()->create());

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

        IntegracionPagoSucursal::create([
            'integracion_pago_id' => $integracion->id,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-12345',
            'user_id_externo' => '999888777',
            'activo' => true,
        ]);
    }

    protected function tearDown(): void
    {
        IntegracionPagoEvento::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function formaPagoConIntegracion(): FormaPago
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
     * Crea un pedido confirmado con un pago en efectivo + un pago de integración,
     * y una transacción QR confirmada asociada al pedido.
     *
     * @return array{pedido: PedidoMostrador, pagoQr: PedidoMostradorPago, pagoEfectivo: PedidoMostradorPago}
     */
    private function pedidoConCobroIntegracionConfirmado(): array
    {
        $pedido = $this->pedidoConfirmadoSimple(totalFinal: 100, cajaId: $this->cajaId);

        $efectivo = $this->crearFormaPagoEfectivo();
        $fpQr = $this->formaPagoConIntegracion();
        $config = IntegracionPagoSucursal::where('sucursal_id', $this->sucursalId)->first();

        IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $fpQr->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
            'usuario_iniciador_id' => \Illuminate\Support\Facades\Auth::id(),
            'modo_usado' => 'qr_dinamico',
            'monto' => 60,
            'estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO,
            'confirmado_en' => now(),
            'cobrable_type' => PedidoMostrador::class,
            'cobrable_id' => $pedido->id,
        ]);

        $pagoEfectivo = PedidoMostradorPago::create([
            'pedido_mostrador_id' => $pedido->id,
            'forma_pago_id' => $efectivo['formaPago']->id,
            'concepto_pago_id' => $efectivo['concepto']->id,
            'monto_base' => 40,
            'monto_final' => 40,
            'afecta_caja' => true,
            'estado' => PedidoMostradorPago::ESTADO_ACTIVO,
            'creado_por_usuario_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        $pagoQr = PedidoMostradorPago::create([
            'pedido_mostrador_id' => $pedido->id,
            'forma_pago_id' => $fpQr->id,
            'concepto_pago_id' => $fpQr->concepto_pago_id,
            'monto_base' => 60,
            'monto_final' => 60,
            'afecta_caja' => false,
            'estado' => PedidoMostradorPago::ESTADO_ACTIVO,
            'creado_por_usuario_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return compact('pedido', 'pagoQr', 'pagoEfectivo');
    }

    public function test_no_se_puede_cancelar_pedido_con_cobro_integracion_confirmado(): void
    {
        ['pedido' => $pedido] = $this->pedidoConCobroIntegracionConfirmado();

        $this->assertTrue($pedido->tieneIntegracionPagoConfirmada());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cobro por integración');

        $this->service->cancelarPedido($pedido, 'prueba');
    }

    public function test_no_se_puede_anular_el_pago_de_integracion(): void
    {
        ['pagoQr' => $pagoQr] = $this->pedidoConCobroIntegracionConfirmado();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('integración');

        $this->service->anularPago($pagoQr, 'prueba');
    }

    public function test_se_puede_anular_un_pago_en_efectivo_del_mismo_pedido(): void
    {
        ['pagoEfectivo' => $pagoEfectivo] = $this->pedidoConCobroIntegracionConfirmado();

        // El efectivo NO es de integración: su anulación no está bloqueada.
        $this->service->anularPago($pagoEfectivo, 'prueba');

        $this->assertEquals(
            PedidoMostradorPago::ESTADO_ANULADO,
            $pagoEfectivo->fresh()->estado,
        );
    }

    public function test_config_rechaza_cuenta_mp_ya_usada_por_otra_sucursal(): void
    {
        // El setUp ya registró user_id 999888777 (modo test) para esta sucursal,
        // sincronizando el collector_index. Otra sucursal que intente la MISMA
        // cuenta+modo debe ser rechazada ANTES de persistir (la validación corre
        // previo a la transacción, por eso un sucursal_id cualquiera alcanza).
        $integracion = IntegracionPago::porCodigo('mercadopago_qr')->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ya está configurada en otra sucursal');

        IntegracionPagoSucursalService::crear([
            'integracion_pago_id' => $integracion->id,
            'sucursal_id' => $this->sucursalId + 999, // otra sucursal
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN-OTRA',
            'user_id_externo' => '999888777', // mismo que la sucursal del setUp
            'activo' => true,
        ]);
    }
}

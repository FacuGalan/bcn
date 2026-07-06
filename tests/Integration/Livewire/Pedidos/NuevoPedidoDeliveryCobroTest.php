<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoDelivery;
use App\Models\FormaPago;
use App\Models\PedidoDelivery;
use App\Models\User;
use App\Services\Pedidos\PedidoDeliveryService;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Regresiones del cobro en el editor delivery (revisión post-Fase 6):
 *
 * 1. BUG envío no cobrado: calcularVenta() corría el ajuste de forma de pago
 *    ANTES de sumar el envío al resultado, dejando total_con_ajuste sin el
 *    envío. Todo cobro (directo, con vuelto, cobro rápido) nacía corto por
 *    exactamente el costo de envío y el pedido quedaba estado_pago=parcial.
 * 2. Consumidor final: el cobro con desglose exigía nombre+teléfono aunque
 *    la confirmación sin cobrar ya no lo hace (paridad mostrador).
 * 3. Promesa de entrega (RF-15 core): hora_pactada_at desde el alta (botones
 *    en modo manual / demora base o por km en modo automática).
 */
class NuevoPedidoDeliveryCobroTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);

        // Bypass del cache de SucursalService (mismo patrón que SmokePedidosTest).
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

        $this->habilitarDelivery();
        $this->service = new PedidoDeliveryService;

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== BUG: ENVÍO EN EL COBRO ====================

    public function test_ajuste_forma_pago_incluye_el_envio_en_total_con_ajuste(): void
    {
        ['formaPago' => $fp] = $this->crearFormaPagoEfectivo();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoDelivery::class)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalDireccion')
            ->set('domDireccion', 'Av. Siempreviva 742')
            ->call('confirmarDireccion')
            ->set('costoEnvio', 500)
            ->set('formaPagoId', (string) $fp->id);

        $resultado = $componente->get('resultado');
        $ajusteInfo = $componente->get('ajusteFormaPagoInfo');

        // El total en memoria incluye el envío…
        $this->assertEqualsWithDelta(1500.0, (float) $resultado['total_final'], 0.01);
        // …y la base del monto de los pagos también (acá vivía el bug).
        $this->assertEqualsWithDelta(1500.0, (float) $ajusteInfo['total_con_ajuste'], 0.01);
    }

    public function test_ajuste_forma_pago_no_aplica_sobre_el_envio(): void
    {
        // El envío es un valor FIJO: el -10% de efectivo aplica solo sobre los
        // productos. $1000 de productos + $500 de envío → 1000*0.9 + 500 = 1400.
        ['concepto' => $concepto] = $this->crearFormaPagoEfectivo();
        $fp = FormaPago::create([
            'nombre' => 'Efectivo 10% off',
            'codigo' => 'efectivo_desc',
            'concepto' => 'efectivo',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => -10,
            'activo' => true,
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $componente = Livewire::test(NuevoPedidoDelivery::class)
            ->set('cajaSeleccionada', $caja->id)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalDireccion')
            ->set('domDireccion', 'Av. Siempreviva 742')
            ->call('confirmarDireccion')
            ->set('costoEnvio', 500)
            ->set('formaPagoId', (string) $fp->id);

        $ajusteInfo = $componente->get('ajusteFormaPagoInfo');
        $this->assertEqualsWithDelta(-100.0, (float) $ajusteInfo['monto'], 0.01);
        $this->assertEqualsWithDelta(1400.0, (float) $ajusteInfo['total_con_ajuste'], 0.01);

        $componente->call('confirmarPedido')
            ->call('confirmarPagoConVuelto')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertEqualsWithDelta(1400.0, (float) $pedido->total_final, 0.01);
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
    }

    public function test_cobro_directo_efectivo_con_envio_deja_pedido_pagado(): void
    {
        ['formaPago' => $fp] = $this->crearFormaPagoEfectivo();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $componente = Livewire::test(NuevoPedidoDelivery::class)
            ->set('cajaSeleccionada', $caja->id)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalDireccion')
            ->set('domDireccion', 'Av. Siempreviva 742')
            ->call('confirmarDireccion')
            ->set('costoEnvio', 500)
            ->set('formaPagoId', (string) $fp->id)
            ->call('confirmarPedido');

        // Efectivo permite vuelto → modal de vuelto con el TOTAL CON envío.
        $pagoConVuelto = $componente->get('pagoConVuelto');
        $this->assertEqualsWithDelta(1500.0, (float) $pagoConVuelto['total_a_pagar'], 0.01);

        $componente->call('confirmarPagoConVuelto')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::with('pagos')->first();
        $this->assertNotNull($pedido);
        $this->assertEqualsWithDelta(1500.0, (float) $pedido->total_final, 0.01);
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
        $this->assertEqualsWithDelta(
            (float) $pedido->total_final,
            (float) $pedido->pagos->where('estado', '!=', 'anulado')->sum('monto_final'),
            0.01,
        );
    }

    public function test_cobro_rapido_cubre_el_saldo_completo_con_envio(): void
    {
        ['formaPago' => $fp] = $this->crearFormaPagoEfectivo();
        $caja = $this->crearCajaAbierta($this->sucursalId);

        // Pedido confirmado sin cobrar con envío (total 1000 + 500 = 1500).
        $pedido = $this->pedidoDeliveryConfirmado(
            totalFinal: 1000,
            cajaId: $caja->id,
            overrides: ['costo_envio' => 500],
        );
        $this->assertEqualsWithDelta(1500.0, (float) $pedido->total_final, 0.01);

        $componente = Livewire::test(NuevoPedidoDelivery::class, [
            'pedidoId' => $pedido->id,
            'modoCobroRapido' => true,
        ]);

        $this->assertEqualsWithDelta(1500.0, (float) $componente->get('saldoCobroRapido'), 0.01);
        $this->assertEqualsWithDelta(1500.0, (float) $componente->get('montoPendienteDesglose'), 0.01);

        // El saldo sobrevive a un recálculo (updatedFormaPagoId lo pisaba).
        $componente->set('formaPagoId', (string) $fp->id);
        $this->assertEqualsWithDelta(1500.0, (float) $componente->get('resultado')['total_final'], 0.01);

        // Cobrar todo el saldo en una sola forma de pago desde el desglose.
        $componente->set('cajaSeleccionada', $caja->id)
            ->set('nuevoPago.forma_pago_id', (string) $fp->id)
            ->call('agregarAlDesglose')
            ->call('confirmarPago')
            ->assertNotDispatched('toast-error');

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
    }

    // ==================== CONSUMIDOR FINAL EN EL COBRO ====================

    public function test_cobro_con_desglose_sin_cliente_queda_como_consumidor_final(): void
    {
        ['formaPago' => $fp] = $this->crearFormaPagoEfectivo();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);
        $caja = $this->crearCajaAbierta($this->sucursalId);

        Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->set('cajaSeleccionada', $caja->id)
            ->call('seleccionarArticulo', $articulo->id)
            ->set('formaPagoId', (string) $fp->id)
            ->call('confirmarPedido')
            ->call('confirmarPagoConVuelto')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertNotNull($pedido, 'El cobro sin cliente ni nombre temporal debe persistir el pedido');
        $this->assertSame('Consumidor final', $pedido->nombre_cliente_temporal);
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->estado_pago);
    }

    // ==================== PROMESA DE ENTREGA (RF-15 CORE) ====================

    public function test_promesa_manual_con_boton_persiste_hora_pactada(): void
    {
        $this->habilitarDelivery(['modo_promesa' => 'manual', 'botones_demora' => [0, 10, 20, 30]]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('seleccionarDemora', 20)
            ->call('confirmarSinCobrar')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertNotNull($pedido->hora_pactada_at);
        $this->assertEqualsWithDelta(20, now()->diffInMinutes($pedido->hora_pactada_at), 2);
    }

    public function test_promesa_manual_sin_boton_queda_sin_hora_pactada(): void
    {
        $this->habilitarDelivery(['modo_promesa' => 'manual']);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('confirmarSinCobrar')
            ->assertNotDispatched('toast-error');

        $this->assertNull(PedidoDelivery::first()->hora_pactada_at);
    }

    public function test_promesa_automatica_take_away_usa_demora_base(): void
    {
        $this->habilitarDelivery(['modo_promesa' => 'automatica', 'demora_base_min' => 25]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('confirmarSinCobrar')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertNotNull($pedido->hora_pactada_at);
        $this->assertEqualsWithDelta(25, now()->diffInMinutes($pedido->hora_pactada_at), 2);
    }

    public function test_promesa_franjas_persiste_el_horario_elegido(): void
    {
        // Los horarios se dan de alta A MANO: hora + días + tipo que sirven.
        \Illuminate\Support\Carbon::setTestNow(now()->setTime(11, 10));
        $todos = [1, 2, 3, 4, 5, 6, 7];
        $this->habilitarDelivery([
            'modo_promesa' => 'franjas',
            'franjas' => [
                ['hora' => '11:00', 'dias' => $todos, 'delivery' => true, 'take_away' => true],
                ['hora' => '11:30', 'dias' => $todos, 'delivery' => true, 'take_away' => true],
                ['hora' => '12:00', 'dias' => $todos, 'delivery' => true, 'take_away' => true],
            ],
        ]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevoPedidoDelivery::class)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->call('seleccionarArticulo', $articulo->id);

        $franjas = $componente->get('franjasDisponibles');
        $this->assertCount(2, $franjas, 'El horario de las 11:00 ya pasó (son las 11:10)');
        $this->assertSame('11:30', $franjas[0]['label']);

        $componente->call('seleccionarFranja', $franjas[1]['iso'])
            ->call('confirmarSinCobrar')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertSame($franjas[1]['iso'], $pedido->hora_pactada_at->toDateTimeString());

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_promesa_franjas_filtra_por_tipo_y_dia(): void
    {
        \Illuminate\Support\Carbon::setTestNow(now()->setTime(10, 0));
        $todos = [1, 2, 3, 4, 5, 6, 7];
        $this->habilitarDelivery([
            'modo_promesa' => 'franjas',
            'franjas' => [
                ['hora' => '20:00', 'dias' => $todos, 'delivery' => true, 'take_away' => false],
                ['hora' => '21:00', 'dias' => $todos, 'delivery' => false, 'take_away' => true],
                // Un horario que NO aplica hoy (día siguiente ISO).
                ['hora' => '22:00', 'dias' => [now()->addDay()->isoWeekday()], 'delivery' => true, 'take_away' => true],
            ],
        ]);

        $componente = Livewire::test(NuevoPedidoDelivery::class);

        // Tipo delivery (default): solo el horario de delivery de hoy.
        $this->assertSame(['20:00'], array_column($componente->get('franjasDisponibles'), 'label'));

        // Elegida la franja de delivery, al pasar a take-away se descarta y
        // quedan solo los horarios de take-away.
        $componente->call('seleccionarFranja', $componente->get('franjasDisponibles')[0]['iso'])
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->assertSet('franjaSeleccionada', null);
        $this->assertSame(['21:00'], array_column($componente->get('franjasDisponibles'), 'label'));

        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_promesa_franjas_lo_antes_posible_queda_sin_hora(): void
    {
        $this->habilitarDelivery(['modo_promesa' => 'franjas']);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        Livewire::test(NuevoPedidoDelivery::class)
            ->assertSet('aceptaLoAntesPosible', true)
            ->set('tipo', PedidoDelivery::TIPO_TAKE_AWAY)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('seleccionarFranja', 'asap')
            ->call('confirmarSinCobrar')
            ->assertNotDispatched('toast-error');

        $pedido = PedidoDelivery::first();
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertNull($pedido->hora_pactada_at, '"Lo antes posible" no fija hora pactada');
    }

    public function test_aceptar_pedido_externo_con_franja_horaria(): void
    {
        $this->habilitarDelivery(['modo_promesa' => 'franjas']);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: 500, overrides: ['origen' => PedidoDelivery::ORIGEN_TIENDA]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 500)],
            esBorrador: true,
        );

        $franja = now()->addHours(2)->startOfHour();

        Livewire::test(\App\Livewire\Pedidos\PedidosDelivery::class)
            ->call('abrirAceptar', $pedido->id)
            ->assertSet('showAceptarModal', true)
            ->call('confirmarAceptarFranja', $franja->toDateTimeString())
            ->assertDispatched('toast-success');

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_CONFIRMADO, $pedido->estado_pedido);
        $this->assertTrue($pedido->hora_pactada_at->equalTo($franja));
    }

    public function test_promesa_existente_se_preserva_al_editar_sin_cambiarla(): void
    {
        $this->habilitarDelivery(['modo_promesa' => 'manual', 'botones_demora' => [10, 20]]);
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $horaPactada = now()->addMinutes(45)->startOfSecond();
        $pedido = $this->pedidoDeliveryConfirmado(
            totalFinal: 1000,
            cajaId: $caja->id,
            overrides: ['hora_pactada_at' => $horaPactada],
        );

        Livewire::test(NuevoPedidoDelivery::class, ['pedidoId' => $pedido->id])
            ->set('observaciones', 'Sin cambios en la promesa')
            ->call('confirmarSinCobrar')
            ->assertNotDispatched('toast-error');

        $this->assertTrue(
            $pedido->fresh()->hora_pactada_at->equalTo($horaPactada),
            'La promesa existente no debe recalcularse al editar sin tocarla',
        );
    }
}

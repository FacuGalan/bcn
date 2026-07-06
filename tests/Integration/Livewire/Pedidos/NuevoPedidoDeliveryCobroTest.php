<?php

namespace Tests\Integration\Livewire\Pedidos;

use App\Livewire\Pedidos\NuevoPedidoDelivery;
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

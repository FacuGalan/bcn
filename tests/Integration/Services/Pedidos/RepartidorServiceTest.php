<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\Caja;
use App\Models\ConceptoPago;
use App\Models\DeliverySalida;
use App\Models\DeliverySalidaPedido;
use App\Models\FormaPago;
use App\Models\MovimientoCaja;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Models\Repartidor;
use App\Models\RepartidorFondo;
use App\Models\RepartidorFondoMovimiento;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\Pedidos\PedidoDeliveryService;
use App\Services\Pedidos\RepartidorService;
use Exception;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 3 spec pedidos-delivery: RepartidorService — salidas/vueltas (RF-08)
 * y fondo del repartidor (RF-09, D4/D13). Es dinero: ledger append-only,
 * rendición con diferencia y liquidación de envíos de terceros.
 */
class RepartidorServiceTest extends TestCase
{
    use WithCaja, WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

    protected RepartidorService $repartidorService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        $this->cajaId = $this->crearCajaAbierta($this->sucursalId, ['saldo_actual' => 50000])->id;
        session(['caja_id' => $this->cajaId]);
        $this->habilitarDelivery();
        $this->service = new PedidoDeliveryService;
        $this->repartidorService = new RepartidorService($this->service);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== HELPERS ====================

    private function crearRepartidorHabilitado(array $overrides = []): Repartidor
    {
        $repartidor = Repartidor::create(array_merge([
            'nombre' => 'Carlos Moto',
            'tipo' => 'propio',
            'activo' => true,
        ], $overrides));
        $repartidor->sucursales()->attach($this->sucursalId);

        return $repartidor;
    }

    private ?int $formaPagoEfectivoId = null;

    private function formaPagoEfectivo(): int
    {
        return $this->formaPagoEfectivoId ??= (int) $this->crearFormaPagoEfectivo()['formaPago']->id;
    }

    private function formaPagoTransferencia(): int
    {
        $concepto = ConceptoPago::firstOrCreate(
            ['codigo' => 'TRANSFERENCIA'],
            ['nombre' => 'Transferencia', 'permite_cuotas' => false, 'permite_vuelto' => false, 'activo' => true, 'orden' => 5],
        );

        return (int) FormaPago::create([
            'nombre' => 'Transferencia',
            'codigo' => 'transferencia-'.uniqid(),
            'concepto' => 'transferencia',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ])->id;
    }

    /**
     * Pedido delivery LISTO con repartidor asignado y pago planificado.
     */
    private function pedidoListoConPagoPlanificado(Repartidor $repartidor, float $total = 1000, ?int $formaPagoId = null, ?int $cajaId = null): PedidoDelivery
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: $total, cajaId: $cajaId ?? $this->cajaId);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $formaPagoId ?? $this->formaPagoEfectivo(),
            'monto_base' => $total,
            'monto_final' => $total,
            'planificado' => true,
        ]);
        $this->service->asignarRepartidor($pedido, $repartidor->id);
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);

        return $pedido->fresh();
    }

    private function abrirFondoDe(Repartidor $repartidor, float $monto = 5000): RepartidorFondo
    {
        return $this->repartidorService->abrirFondo(
            repartidorId: $repartidor->id,
            sucursalId: $this->sucursalId,
            cajaOrigenId: $this->cajaId,
            monto: $monto,
            usuarioId: 1,
        );
    }

    // ==================== FONDO: APERTURA / REFUERZO ====================

    public function test_abrir_fondo_crea_egreso_de_caja_y_movimiento_inicial(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $saldoAntes = (float) Caja::find($this->cajaId)->saldo_actual;

        $fondo = $this->abrirFondoDe($repartidor, monto: 5000);

        $this->assertSame(RepartidorFondo::ESTADO_ABIERTO, $fondo->estado);
        $this->assertEqualsWithDelta(5000.0, (float) $fondo->monto_inicial, 0.01);
        $this->assertEqualsWithDelta(5000.0, $this->repartidorService->saldoTeorico($fondo), 0.01);

        $inicial = $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_ENTREGA_INICIAL)->first();
        $this->assertNotNull($inicial);
        $this->assertEqualsWithDelta(5000.0, (float) $inicial->monto, 0.01);

        $egreso = MovimientoCaja::find($inicial->movimiento_caja_id);
        $this->assertNotNull($egreso);
        $this->assertSame(MovimientoCaja::TIPO_EGRESO, $egreso->tipo);
        $this->assertSame(MovimientoCaja::REF_FONDO_REPARTIDOR, $egreso->referencia_tipo);
        $this->assertSame($fondo->id, (int) $egreso->referencia_id);
        $this->assertEqualsWithDelta($saldoAntes - 5000, (float) Caja::find($this->cajaId)->saldo_actual, 0.01);
    }

    public function test_no_permite_segundo_fondo_abierto_por_repartidor_y_sucursal(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $this->abrirFondoDe($repartidor);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/ya tiene un fondo abierto/');
        $this->abrirFondoDe($repartidor);
    }

    public function test_abrir_fondo_en_cero_no_toca_la_caja(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $movimientosAntes = MovimientoCaja::where('caja_id', $this->cajaId)->count();

        $fondo = $this->abrirFondoDe($repartidor, monto: 0);

        $this->assertSame(RepartidorFondo::ESTADO_ABIERTO, $fondo->estado);
        $this->assertSame(0, $fondo->movimientos()->count());
        $this->assertSame($movimientosAntes, MovimientoCaja::where('caja_id', $this->cajaId)->count());
    }

    public function test_reforzar_fondo_suma_saldo_y_egresa_de_caja(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor, monto: 2000);

        $this->repartidorService->reforzarFondo($fondo, monto: 1500, usuarioId: 1);

        $this->assertEqualsWithDelta(3500.0, $this->repartidorService->saldoTeorico($fondo), 0.01);
        $refuerzo = $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_REFUERZO)->first();
        $this->assertNotNull($refuerzo->movimiento_caja_id);
        $this->assertSame(MovimientoCaja::TIPO_EGRESO, MovimientoCaja::find($refuerzo->movimiento_caja_id)->tipo);
    }

    // ==================== SALIDAS ====================

    public function test_crear_y_registrar_salida_pasa_pedidos_a_en_camino(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $p1 = $this->pedidoListoConPagoPlanificado($repartidor);
        $p2 = $this->pedidoListoConPagoPlanificado($repartidor);

        $salida = $this->repartidorService->crearSalida(
            sucursalId: $this->sucursalId,
            repartidorId: $repartidor->id,
            pedidoIds: [$p1->id, $p2->id],
            usuarioId: 1,
        );

        $this->assertSame(DeliverySalida::ESTADO_ARMANDO, $salida->estado);
        $this->assertSame(2, $salida->salidaPedidos()->count());
        $this->assertSame($salida->id, (int) $p1->fresh()->salida_id);

        $salida = $this->repartidorService->registrarSalida($salida, usuarioId: 1);

        $this->assertSame(DeliverySalida::ESTADO_EN_CAMINO, $salida->estado);
        $this->assertNotNull($salida->salida_at);
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $p1->fresh()->estado_pedido);
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $p2->fresh()->estado_pedido);
    }

    public function test_salida_rechaza_pedidos_no_listos_y_take_away(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $confirmado = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);

        try {
            $this->repartidorService->crearSalida($this->sucursalId, $repartidor->id, [$confirmado->id]);
            $this->fail('Debió rechazar el pedido no listo');
        } catch (Exception $e) {
            $this->assertStringContainsString('no está listo', $e->getMessage());
        }

        $takeAway = $this->pedidoDeliveryConfirmado(totalFinal: 500, cajaId: $this->cajaId, overrides: [
            'tipo' => PedidoDelivery::TIPO_TAKE_AWAY,
            'direccion_entrega' => null,
        ]);
        $this->service->cambiarEstado($takeAway, PedidoDelivery::ESTADO_LISTO);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/take-away/');
        $this->repartidorService->crearSalida($this->sucursalId, $repartidor->id, [$takeAway->id]);
    }

    public function test_despachar_pedido_crea_salida_implicita_de_uno(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor);

        $salida = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $this->assertSame(DeliverySalida::ESTADO_EN_CAMINO, $salida->estado);
        $this->assertSame(1, $salida->salidaPedidos()->count());
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $pedido->fresh()->estado_pedido);
        $this->assertSame($salida->id, (int) $pedido->fresh()->salida_id);
    }

    public function test_despachar_sin_repartidor_falla(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/repartidor/');
        $this->repartidorService->despacharPedido($pedido->fresh());
    }

    // ==================== VUELTA: COBROS + FONDO (D13) ====================

    public function test_vuelta_con_cobro_efectivo_al_fondo_sin_movimiento_caja(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor, monto: 5000);
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor, total: 1000);
        $pago = $pedido->pagos()->first();
        $salida = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $movimientosAntes = MovimientoCaja::where('caja_id', $this->cajaId)->count();

        $salida = $this->repartidorService->registrarVuelta($salida, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id, 'monto_recibido' => 1500]],
            ],
        ], usuarioId: 1);

        // Salida finalizada, pedido entregado, pivot con resultado
        $this->assertSame(DeliverySalida::ESTADO_FINALIZADA, $salida->estado);
        $this->assertNotNull($salida->vuelta_at);
        $this->assertContains($pedido->fresh()->estado_pedido, [PedidoDelivery::ESTADO_ENTREGADO, PedidoDelivery::ESTADO_FACTURADO]);
        $this->assertSame(
            DeliverySalidaPedido::RESULTADO_ENTREGADO,
            $salida->salidaPedidos()->where('pedido_id', $pedido->id)->value('resultado'),
        );

        // Pago confirmado al fondo SIN MovimientoCaja (D13)
        $pago->refresh();
        $this->assertSame(PedidoDeliveryPago::ESTADO_ACTIVO, $pago->estado);
        $this->assertTrue((bool) $pago->destino_fondo);
        $this->assertSame($fondo->id, (int) $pago->repartidor_fondo_id);
        $this->assertNull($pago->movimiento_caja_id);
        $this->assertSame($movimientosAntes, MovimientoCaja::where('caja_id', $this->cajaId)->count());

        // Ledger del fondo: +1500 cobro, -500 vuelto → saldo 5000+1000
        $cobro = $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_COBRO_PEDIDO)->first();
        $vuelto = $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_VUELTO)->first();
        $this->assertEqualsWithDelta(1500.0, (float) $cobro->monto, 0.01);
        $this->assertEqualsWithDelta(-500.0, (float) $vuelto->monto, 0.01);
        $this->assertEqualsWithDelta(6000.0, $this->repartidorService->saldoTeorico($fondo), 0.01);
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->fresh()->estado_pago);
    }

    public function test_vuelta_cobro_efectivo_sin_fondo_abierto_falla_claro(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor, total: 1000);
        $pago = $pedido->pagos()->first();
        $salida = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/fondo abierto/');
        $this->repartidorService->registrarVuelta($salida, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id]],
            ],
        ]);
    }

    public function test_vuelta_cobro_no_efectivo_va_por_circuito_normal(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor);
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor, total: 1000, formaPagoId: $this->formaPagoTransferencia());
        $pago = $pedido->pagos()->first();
        $salida = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $this->repartidorService->registrarVuelta($salida, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id, 'referencia' => 'TRF-001']],
            ],
        ], usuarioId: 1);

        $pago->refresh();
        $this->assertSame(PedidoDeliveryPago::ESTADO_ACTIVO, $pago->estado);
        $this->assertFalse((bool) $pago->destino_fondo);
        $this->assertNotNull($pago->movimiento_caja_id, 'No-efectivo con caja: MovimientoCaja normal');
        $this->assertSame(0, $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_COBRO_PEDIDO)->count());
    }

    public function test_vuelta_no_entregado_vuelve_a_listo_y_permite_re_despacho(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $this->abrirFondoDe($repartidor);
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor, total: 1000);
        $salida1 = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $this->repartidorService->registrarVuelta($salida1, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_NO_ENTREGADO,
                'motivo' => 'Cliente ausente',
            ],
        ], usuarioId: 1);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_LISTO, $pedido->estado_pedido);
        $this->assertNull($pedido->salida_id);
        // El pago planificado persiste para el re-despacho
        $this->assertSame(1, $pedido->pagos()->where('estado', PedidoDeliveryPago::ESTADO_PLANIFICADO)->count());
        // El intento queda en el historial append-only
        $intento = $salida1->salidaPedidos()->where('pedido_id', $pedido->id)->first();
        $this->assertSame(DeliverySalidaPedido::RESULTADO_NO_ENTREGADO, $intento->resultado);
        $this->assertSame('Cliente ausente', $intento->motivo);

        // Re-despacho en OTRA salida: el pivot conserva ambos intentos
        $salida2 = $this->repartidorService->despacharPedido($pedido->fresh(), usuarioId: 1);
        $this->assertNotSame($salida1->id, $salida2->id);
        $this->assertSame(2, DeliverySalidaPedido::where('pedido_id', $pedido->id)->count());
    }

    public function test_vuelta_exige_resultado_de_todos_los_pedidos(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $this->abrirFondoDe($repartidor);
        $p1 = $this->pedidoListoConPagoPlanificado($repartidor);
        $p2 = $this->pedidoListoConPagoPlanificado($repartidor);
        $salida = $this->repartidorService->crearSalida($this->sucursalId, $repartidor->id, [$p1->id, $p2->id], 1);
        $salida = $this->repartidorService->registrarSalida($salida, 1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Falta el resultado/');
        $this->repartidorService->registrarVuelta($salida, [
            $p1->id => ['resultado' => DeliverySalidaPedido::RESULTADO_NO_ENTREGADO, 'motivo' => 'x'],
        ]);
    }

    public function test_vuelta_con_conversion_automatica_factura_post_vuelta(): void
    {
        Sucursal::where('id', $this->sucursalId)->update(['pedido_conversion_automatica_al_entregar' => true]);
        $repartidor = $this->crearRepartidorHabilitado();
        $this->abrirFondoDe($repartidor);
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor, total: 1000);
        $pago = $pedido->pagos()->first();
        $salida = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $this->repartidorService->registrarVuelta($salida, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id]],
            ],
        ], usuarioId: 1);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_FACTURADO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->venta_id);
        $this->assertSame('PedidoDelivery', Venta::find($pedido->venta_id)->origen_type);
        // El pago del fondo migró a la venta sin duplicar movimientos de caja
        $this->assertTrue((bool) $pedido->pagos()->first()->destino_fondo);
    }

    // ==================== RENDICION (RF-09) ====================

    public function test_rendir_fondo_exacto_ingresa_neto_a_caja_y_cierra_ledger(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor, monto: 5000);
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor, total: 1000);
        $pago = $pedido->pagos()->first();
        $salida = $this->repartidorService->despacharPedido($pedido, usuarioId: 1);
        $this->repartidorService->registrarVuelta($salida, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id]],
            ],
        ], usuarioId: 1);

        $saldoCajaAntes = (float) Caja::find($this->cajaId)->saldo_actual;

        // Teórico: 5000 inicial + 1000 cobro = 6000
        $fondo = $this->repartidorService->rendirFondo($fondo, montoDeclarado: 6000, cajaRendicionId: $this->cajaId, usuarioId: 1);

        $this->assertSame(RepartidorFondo::ESTADO_RENDIDO, $fondo->estado);
        $this->assertEqualsWithDelta(6000.0, (float) $fondo->monto_rendido, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $fondo->diferencia, 0.01);
        $this->assertSame($this->cajaId, (int) $fondo->caja_rendicion_id);
        // UN ingreso neto a la caja receptora
        $ingreso = MovimientoCaja::where('caja_id', $this->cajaId)
            ->where('tipo', MovimientoCaja::TIPO_INGRESO)
            ->where('referencia_tipo', MovimientoCaja::REF_FONDO_REPARTIDOR)
            ->where('referencia_id', $fondo->id)
            ->get();
        $this->assertCount(1, $ingreso);
        $this->assertEqualsWithDelta(6000.0, (float) $ingreso->first()->monto, 0.01);
        $this->assertEqualsWithDelta($saldoCajaAntes + 6000, (float) Caja::find($this->cajaId)->saldo_actual, 0.01);
        // Ledger cerrado en cero
        $this->assertEqualsWithDelta(0.0, $this->repartidorService->saldoTeorico($fondo), 0.01);
    }

    public function test_rendir_con_faltante_registra_diferencia_negativa(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor, monto: 5000);

        // Declara 4800 contra 5000 teórico → faltante de 200
        $fondo = $this->repartidorService->rendirFondo($fondo, montoDeclarado: 4800, cajaRendicionId: $this->cajaId, usuarioId: 1);

        $this->assertEqualsWithDelta(-200.0, (float) $fondo->diferencia, 0.01);
        $ajuste = $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_AJUSTE)->first();
        $this->assertEqualsWithDelta(-200.0, (float) $ajuste->monto, 0.01);
        $this->assertStringContainsString('Faltante', $ajuste->detalle);
        $this->assertEqualsWithDelta(0.0, $this->repartidorService->saldoTeorico($fondo), 0.01);
        // La caja recibe lo DECLARADO (arqueo físico), no el teórico
        $ingreso = MovimientoCaja::where('referencia_tipo', MovimientoCaja::REF_FONDO_REPARTIDOR)
            ->where('referencia_id', $fondo->id)
            ->where('tipo', MovimientoCaja::TIPO_INGRESO)
            ->first();
        $this->assertEqualsWithDelta(4800.0, (float) $ingreso->monto, 0.01);
    }

    public function test_rendicion_liquida_envios_de_terceros(): void
    {
        $repartidor = $this->crearRepartidorHabilitado([
            'nombre' => 'Cadete Externo',
            'tipo' => 'tercero',
            'envio_es_del_repartidor' => true,
        ]);
        $fondo = $this->abrirFondoDe($repartidor, monto: 0);

        // Pedido de 1000 + 300 de envío (renglón D17) cobrado en efectivo
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId, overrides: ['costo_envio' => 300]);
        $pedido->refresh(); // total_final ahora incluye el envío (1300)
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => (float) $pedido->total_final,
            'monto_final' => (float) $pedido->total_final,
            'planificado' => true,
        ]);
        $this->service->asignarRepartidor($pedido, $repartidor->id);
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);
        $pago = $pedido->pagos()->first();
        $salida = $this->repartidorService->despacharPedido($pedido->fresh(), usuarioId: 1);
        $this->repartidorService->registrarVuelta($salida, [
            $pedido->id => [
                'resultado' => DeliverySalidaPedido::RESULTADO_ENTREGADO,
                'cobros' => [['pago_id' => $pago->id]],
            ],
        ], usuarioId: 1);

        // Teórico pre-liquidación: 1300 cobrado. Liquidación: -300 del envío
        // → neto del comercio 1000, que es lo que declara y entra a caja.
        $fondo = $this->repartidorService->rendirFondo($fondo, montoDeclarado: 1000, cajaRendicionId: $this->cajaId, usuarioId: 1);

        $liquidacion = $fondo->movimientos()->where('tipo', RepartidorFondoMovimiento::TIPO_LIQUIDACION_ENVIOS)->first();
        $this->assertNotNull($liquidacion, 'La rendición debe liquidar los envíos del tercero');
        $this->assertEqualsWithDelta(-300.0, (float) $liquidacion->monto, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $fondo->diferencia, 0.01);
        $this->assertEqualsWithDelta(0.0, $this->repartidorService->saldoTeorico($fondo), 0.01);
    }

    public function test_no_rinde_con_salida_en_camino(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor);
        $pedido = $this->pedidoListoConPagoPlanificado($repartidor);
        $this->repartidorService->despacharPedido($pedido, usuarioId: 1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/salida en camino/');
        $this->repartidorService->rendirFondo($fondo, 5000, $this->cajaId, 1);
    }

    public function test_no_rinde_dos_veces(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = $this->abrirFondoDe($repartidor, monto: 1000);
        $fondo = $this->repartidorService->rendirFondo($fondo, 1000, $this->cajaId, 1);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/ya fue rendido/');
        $this->repartidorService->rendirFondo($fondo, 1000, $this->cajaId, 1);
    }

    // ==================== VISIBILIDAD (D13) ====================

    public function test_advertencia_de_fondos_abiertos_por_caja_origen(): void
    {
        $repartidor = $this->crearRepartidorHabilitado();

        $this->assertNull($this->repartidorService->advertenciaFondosAbiertos([$this->cajaId]));

        $fondo = $this->abrirFondoDe($repartidor, monto: 5000);

        $advertencia = $this->repartidorService->advertenciaFondosAbiertos([$this->cajaId]);
        $this->assertNotNull($advertencia);
        $this->assertStringContainsString('Carlos Moto', $advertencia);

        $this->repartidorService->rendirFondo($fondo, 5000, $this->cajaId, 1);
        $this->assertNull($this->repartidorService->advertenciaFondosAbiertos([$this->cajaId]));
    }

    public function test_total_en_fondos_abiertos_de_la_sucursal(): void
    {
        $r1 = $this->crearRepartidorHabilitado(['nombre' => 'R1']);
        $r2 = $this->crearRepartidorHabilitado(['nombre' => 'R2']);
        $this->abrirFondoDe($r1, monto: 3000);
        $fondo2 = $this->abrirFondoDe($r2, monto: 2000);

        $this->assertEqualsWithDelta(5000.0, $this->repartidorService->totalEnFondosAbiertos($this->sucursalId), 0.01);

        // Rendido deja de contar
        $this->repartidorService->rendirFondo($fondo2, 2000, $this->cajaId, 1);
        $this->assertEqualsWithDelta(3000.0, $this->repartidorService->totalEnFondosAbiertos($this->sucursalId), 0.01);
    }
}

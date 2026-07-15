<?php

namespace Tests\Integration\Services\Pedidos;

use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\GrupoOpcional;
use App\Models\MovimientoCaja;
use App\Models\Opcional;
use App\Models\PedidoDelivery;
use App\Models\PedidoDeliveryPago;
use App\Models\Repartidor;
use App\Models\RepartidorFondo;
use App\Models\RepartidorFondoMovimiento;
use App\Models\Sucursal;
use App\Services\Pedidos\PedidoDeliveryService;
use Exception;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Fase 2 spec pedidos-delivery: núcleo del service espejo — alta con renglón
 * de envío (D17), transiciones con en_camino, cobro al fondo (D13) y
 * conversión a venta con origen polimórfico (D20) + fixes D19.
 */
class PedidoDeliveryServiceTest extends TestCase
{
    use WithCaja, WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    protected PedidoDeliveryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
        // Caja ABIERTA (la conversión a venta la exige) en lugar de setUpCaja.
        $this->cajaId = $this->crearCajaAbierta($this->sucursalId)->id;
        session(['caja_id' => $this->cajaId]);
        $this->habilitarDelivery();
        $this->service = new PedidoDeliveryService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearRepartidorHabilitado(): Repartidor
    {
        $repartidor = Repartidor::create(['nombre' => 'Carlos Moto', 'tipo' => 'propio', 'activo' => true]);
        $repartidor->sucursales()->attach($this->sucursalId);

        return $repartidor;
    }

    private ?int $formaPagoEfectivoId = null;

    private function formaPagoEfectivo(): int
    {
        return $this->formaPagoEfectivoId ??= (int) $this->crearFormaPagoEfectivo()['formaPago']->id;
    }

    // ==================== ALTA (D17) ====================

    public function test_crear_delivery_materializa_renglon_de_envio_y_ajusta_totales(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: 1000, overrides: ['costo_envio' => 500]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 1000)],
        );

        $renglon = $pedido->detalles->firstWhere('es_costo_envio', true);
        $this->assertNotNull($renglon, 'Debe existir el renglón-concepto del envío');
        $this->assertTrue((bool) $renglon->es_concepto);
        $this->assertSame('Costo de envío', $renglon->concepto_descripcion);
        $this->assertEqualsWithDelta(500.0, (float) $renglon->total, 0.01);
        $this->assertEqualsWithDelta(86.78, (float) $renglon->iva_monto, 0.01); // 500×21/121

        // Totales del pedido incluyen el envío (Σitems = total, sin rechazo ARCA)
        $this->assertEqualsWithDelta(1500.0, (float) $pedido->total, 0.01);
        $this->assertEqualsWithDelta(1500.0, (float) $pedido->total_final, 0.01);
        $this->assertEqualsWithDelta(
            (float) $pedido->total,
            (float) $pedido->detalles->sum('total'),
            0.01
        );

        // Numeración propia + token de seguimiento
        $this->assertSame(1, $pedido->numero);
        $this->assertNotEmpty($pedido->token_seguimiento);
        $this->assertSame(1, (int) Sucursal::find($this->sucursalId)->pedido_delivery_ultimo_numero);
    }

    public function test_take_away_ignora_costo_envio_y_no_exige_direccion(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: 1000, overrides: [
                'tipo' => PedidoDelivery::TIPO_TAKE_AWAY,
                'direccion_entrega' => null,
                'costo_envio' => 500,
            ]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 1000)],
        );

        $this->assertSame(PedidoDelivery::TIPO_TAKE_AWAY, $pedido->tipo);
        $this->assertNull($pedido->detalles->firstWhere('es_costo_envio', true));
        $this->assertEqualsWithDelta(1000.0, (float) $pedido->total_final, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $pedido->costo_envio, 0.01);
    }

    public function test_delivery_confirmado_sin_direccion_es_rechazado(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('dirección de entrega');

        $this->service->crearPedido(
            data: $this->datosBaseDelivery(overrides: ['direccion_entrega' => null]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 1000)],
        );
    }

    public function test_sucursal_sin_delivery_rechaza_el_alta(): void
    {
        Sucursal::where('id', $this->sucursalId)->update(['usa_delivery' => false]);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);

        $this->expectException(Exception::class);

        $this->service->crearPedido(
            data: $this->datosBaseDelivery(),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 1000)],
        );
    }

    public function test_establecer_costo_envio_actualiza_renglon_por_delta(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, overrides: ['costo_envio' => 300]);
        $this->assertEqualsWithDelta(1300.0, (float) $pedido->total_final, 0.01);

        $pedido = $this->service->establecerCostoEnvio($pedido, 800, manual: true, usuarioId: 1);

        $renglones = $pedido->detalles->where('es_costo_envio', true);
        $this->assertCount(1, $renglones, 'El renglón se actualiza, no se duplica');
        $this->assertEqualsWithDelta(800.0, (float) $renglones->first()->total, 0.01);
        $this->assertEqualsWithDelta(1800.0, (float) $pedido->total_final, 0.01);
        $this->assertTrue((bool) $pedido->costo_envio_manual);
        $this->assertSame(1, (int) $pedido->costo_envio_usuario_id);

        // Bajarlo a 0 elimina el renglón
        $pedido = $this->service->establecerCostoEnvio($pedido, 0, manual: true, usuarioId: 1);
        $this->assertNull($pedido->detalles->firstWhere('es_costo_envio', true));
        $this->assertEqualsWithDelta(1000.0, (float) $pedido->total_final, 0.01);
    }

    // ==================== DIRECCION (D6/D18) ====================

    public function test_establecer_direccion_persiste_entrega_en_cliente_sin_pisar_fiscal(): void
    {
        $clienteId = DB::connection('pymes_tenant')->table('clientes')->insertGetId([
            'nombre' => 'Cliente Delivery',
            'direccion' => 'Domicilio Fiscal 123', // fiscal: NUNCA se pisa (D18)
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pedido = $this->pedidoDeliveryConfirmado(overrides: [
            'cliente_id' => $clienteId,
            'nombre_cliente_temporal' => null,
            'telefono_cliente_temporal' => null,
        ]);

        $this->service->establecerDireccion($pedido, [
            'direccion_entrega' => 'Casa de la novia 456',
            'direccion_referencia' => 'Timbre 3B',
            'latitud' => -34.6100000,
            'longitud' => -58.4000000,
        ], actualizarCliente: true);

        $cliente = DB::connection('pymes_tenant')->table('clientes')->find($clienteId);
        $this->assertSame('Casa de la novia 456', $cliente->direccion_entrega);
        $this->assertSame('Timbre 3B', $cliente->direccion_entrega_referencia);
        $this->assertSame('Domicilio Fiscal 123', $cliente->direccion, 'El domicilio fiscal no se toca');

        // "Entregar en otra dirección" (actualizarCliente=false) no pisa nada
        $pedido->refresh();
        $this->service->establecerDireccion($pedido, [
            'direccion_entrega' => 'Otra direccion 789',
            'latitud' => -34.62,
            'longitud' => -58.41,
        ], actualizarCliente: false);

        $cliente = DB::connection('pymes_tenant')->table('clientes')->find($clienteId);
        $this->assertSame('Casa de la novia 456', $cliente->direccion_entrega);
        $this->assertSame('Otra direccion 789', $pedido->fresh()->direccion_entrega);
    }

    // ==================== ESTADOS (RF-03/RF-08) ====================

    public function test_listo_a_en_camino_exige_repartidor_y_setea_timestamp(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado();
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);
        $pedido->refresh();

        // Sin repartidor (exigir_repartidor default true) → rechaza
        try {
            $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_EN_CAMINO);
            $this->fail('Debió exigir repartidor');
        } catch (Exception $e) {
            $this->assertStringContainsString('repartidor', $e->getMessage());
        }

        $repartidor = $this->crearRepartidorHabilitado();
        $this->service->asignarRepartidor($pedido->fresh(), $repartidor->id);

        $pedido = $pedido->fresh();
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_EN_CAMINO);
        $pedido->refresh();

        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $pedido->estado_pedido);
        $this->assertNotNull($pedido->en_camino_at);

        // Vuelta fallida: en_camino → listo es transición válida (re-despacho)
        $this->service->cambiarEstado($pedido->fresh(), PedidoDelivery::ESTADO_LISTO);
        $this->assertSame(PedidoDelivery::ESTADO_LISTO, $pedido->fresh()->estado_pedido);
    }

    public function test_take_away_pasa_a_en_camino_como_para_retirar(): void
    {
        // rev9: en_camino es compartido — para take-away significa "listo para
        // retirar" (sin exigir repartidor ni salida de reparto).
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(overrides: [
                'tipo' => PedidoDelivery::TIPO_TAKE_AWAY,
                'direccion_entrega' => null,
            ]),
            detalles: [$this->detalleDeliveryDe($articulo, 1, 1000)],
        );
        $this->service->cambiarEstado($pedido, PedidoDelivery::ESTADO_LISTO);

        $this->service->cambiarEstado($pedido->fresh(), PedidoDelivery::ESTADO_EN_CAMINO);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_EN_CAMINO, $pedido->estado_pedido);
        $this->assertNull($pedido->repartidor_id);
        $this->assertSame(__('Para retirar'), $pedido->estado_label);

        // Y de ahí se entrega directo (sin vuelta: no hay salida).
        $this->service->cambiarEstado($pedido->fresh(), PedidoDelivery::ESTADO_ENTREGADO, convertirAutomatico: false);
        $this->assertSame(PedidoDelivery::ESTADO_ENTREGADO, $pedido->fresh()->estado_pedido);
    }

    public function test_reasignar_repartidor_bloqueado_en_camino(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado();
        $repartidor = $this->crearRepartidorHabilitado();
        $this->service->asignarRepartidor($pedido, $repartidor->id);
        $this->service->cambiarEstado($pedido->fresh(), PedidoDelivery::ESTADO_LISTO);
        $this->service->cambiarEstado($pedido->fresh(), PedidoDelivery::ESTADO_EN_CAMINO);

        $otro = Repartidor::create(['nombre' => 'Otro', 'tipo' => 'propio', 'activo' => true]);
        $otro->sucursales()->attach($this->sucursalId);

        $this->expectException(Exception::class);

        $this->service->asignarRepartidor($pedido->fresh(), $otro->id);
    }

    // ==================== FONDO DEL REPARTIDOR (D13) ====================

    public function test_confirmar_pago_planificado_al_fondo_no_crea_movimiento_caja(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = RepartidorFondo::create([
            'repartidor_id' => $repartidor->id,
            'sucursal_id' => $this->sucursalId,
            'caja_origen_id' => $this->cajaId,
            'estado' => RepartidorFondo::ESTADO_ABIERTO,
            'monto_inicial' => 5000,
            'usuario_apertura_id' => 1,
            'abierto_at' => now(),
        ]);

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        $movimientosAntes = MovimientoCaja::where('caja_id', $this->cajaId)->count();

        $pago = $this->service->confirmarPagoPlanificado($pago, [], [
            'destino_fondo' => true,
            'repartidor_fondo_id' => $fondo->id,
        ]);

        $this->assertSame(PedidoDeliveryPago::ESTADO_ACTIVO, $pago->estado);
        $this->assertTrue((bool) $pago->destino_fondo);
        $this->assertSame($fondo->id, (int) $pago->repartidor_fondo_id);
        $this->assertNull($pago->movimiento_caja_id, 'El cobro al fondo NO toca la caja (D13)');
        $this->assertSame($movimientosAntes, MovimientoCaja::where('caja_id', $this->cajaId)->count());
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PAGADO, $pedido->fresh()->estado_pago);
    }

    public function test_anular_pago_del_fondo_genera_movimiento_inverso(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $repartidor = $this->crearRepartidorHabilitado();
        $fondo = RepartidorFondo::create([
            'repartidor_id' => $repartidor->id,
            'sucursal_id' => $this->sucursalId,
            'caja_origen_id' => $this->cajaId,
            'estado' => RepartidorFondo::ESTADO_ABIERTO,
            'monto_inicial' => 5000,
            'usuario_apertura_id' => 1,
            'abierto_at' => now(),
        ]);

        $pago = $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);
        $pago = $this->service->confirmarPagoPlanificado($pago, [], [
            'destino_fondo' => true,
            'repartidor_fondo_id' => $fondo->id,
        ]);

        $this->service->anularPago($pago, 'Cliente rechazó');

        $inverso = RepartidorFondoMovimiento::where('fondo_id', $fondo->id)
            ->where('tipo', RepartidorFondoMovimiento::TIPO_AJUSTE)
            ->first();

        $this->assertNotNull($inverso);
        $this->assertEqualsWithDelta(-1000.0, (float) $inverso->monto, 0.01);
        $this->assertSame(PedidoDelivery::ESTADO_PAGO_PENDIENTE, $pedido->fresh()->estado_pago);
    }

    // ==================== CONVERSION (D19/D20) ====================

    public function test_convertir_en_venta_persiste_origen_polimorfico(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 1000,
            'monto_final' => 1000,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertSame('PedidoDelivery', $venta->fresh()->origen_type);
        $this->assertSame($pedido->id, (int) $venta->fresh()->origen_id);
        $this->assertSame($pedido->id, $venta->origen()->first()?->id);

        $pedido->refresh();
        $this->assertSame(PedidoDelivery::ESTADO_FACTURADO, $pedido->estado_pedido);
        $this->assertSame($venta->id, (int) $pedido->venta_id);
    }

    public function test_convertir_marca_pagos_de_fp_fiscal_para_facturar(): void
    {
        // FP marcada "factura fiscal": la conversión intenta emitir el
        // comprobante POST-commit. En tests ARCA no está configurado → el pago
        // queda `pendiente_de_facturar` (reintentable desde Cajas), y la
        // conversión NUNCA se revierte por un fallo fiscal.
        $fpId = $this->formaPagoEfectivo();
        \App\Models\FormaPago::where('id', $fpId)->update(['factura_fiscal' => true]);

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $fpId,
            'monto_base' => 1000,
            'monto_final' => 1000,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $pago = $venta->pagos()->first();
        $this->assertSame(\App\Models\VentaPago::ESTADO_FACT_PENDIENTE, $pago->estado_facturacion);
        $this->assertSame(PedidoDelivery::ESTADO_FACTURADO, $pedido->fresh()->estado_pedido, 'El fallo de ARCA no revierte la conversión');
    }

    public function test_convertir_sin_fp_fiscal_no_marca_pagos(): void
    {
        $fpId = $this->formaPagoEfectivo();
        \App\Models\FormaPago::where('id', $fpId)->update(['factura_fiscal' => false]);

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $fpId,
            'monto_base' => 1000,
            'monto_final' => 1000,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertNotSame(
            \App\Models\VentaPago::ESTADO_FACT_PENDIENTE,
            $venta->pagos()->first()->estado_facturacion,
        );
    }

    public function test_convertir_sin_caja_exige_caja_o_falla_claro(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: null, overrides: [
            'origen' => PedidoDelivery::ORIGEN_TIENDA,
        ]);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        // Sin caja → excepción clara ("por facturar")
        try {
            $this->service->convertirEnVenta($pedido->fresh());
            $this->fail('Debió exigir caja');
        } catch (Exception $e) {
            $this->assertStringContainsString('caja', $e->getMessage());
        }

        // Con la caja de quien convierte, funciona
        $venta = $this->service->convertirEnVenta($pedido->fresh(), null, $this->cajaId);
        $this->assertSame($this->cajaId, (int) $venta->caja_id);
    }

    public function test_convertir_registra_uso_del_cupon(): void
    {
        $cupon = Cupon::create([
            'codigo' => 'CUP-'.strtoupper(uniqid()),
            'tipo' => 'promocional',
            'modo_descuento' => 'monto_fijo',
            'valor_descuento' => 200,
            'aplica_a' => 'total',
            'uso_maximo' => 10,
            'uso_actual' => 0,
            'activo' => true,
            'created_by_usuario_id' => 1,
        ]);

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 800, cajaId: $this->cajaId, overrides: [
            'cupon_id' => $cupon->id,
            'cupon_codigo_snapshot' => $cupon->codigo,
            'monto_cupon' => 200,
        ]);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 800,
            'monto_final' => 800,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $uso = CuponUso::where('cupon_id', $cupon->id)->where('venta_id', $venta->id)->first();
        $this->assertNotNull($uso, 'D19: la conversión registra CuponUso');
        $this->assertEqualsWithDelta(200.0, (float) $uso->monto_descontado, 0.01);
        $this->assertSame(1, (int) $cupon->fresh()->uso_actual);
    }

    public function test_convertir_migra_opcionales_a_venta_detalle_opcionales(): void
    {
        $grupo = GrupoOpcional::create([
            'nombre' => 'Aderezos',
            'tipo' => 'seleccionable',
            'obligatorio' => false,
            'min_seleccion' => 0,
            'max_seleccion' => 3,
            'activo' => true,
            'orden' => 0,
        ]);
        $opcional = Opcional::create([
            'grupo_opcional_id' => $grupo->id,
            'nombre' => 'Mayonesa',
            'precio_extra' => 50,
            'activo' => true,
            'orden' => 0,
        ]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 10);
        $detalle = $this->detalleDeliveryDe($articulo, 1, 1000);
        $detalle['opcionales'] = [[
            'grupo_id' => $grupo->id,
            'grupo_nombre' => $grupo->nombre,
            'selecciones' => [[
                'opcional_id' => $opcional->id,
                'nombre' => $opcional->nombre,
                'cantidad' => 1,
                'precio_extra' => 50,
            ]],
        ]];

        $pedido = $this->service->crearPedido(
            data: $this->datosBaseDelivery(total: 1000, cajaId: $this->cajaId),
            detalles: [$detalle],
        );
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 1000,
            'monto_final' => 1000,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $migrados = DB::connection('pymes_tenant')
            ->table('venta_detalle_opcionales')
            ->join('ventas_detalle', 'ventas_detalle.id', '=', 'venta_detalle_opcionales.venta_detalle_id')
            ->where('ventas_detalle.venta_id', $venta->id)
            ->select('venta_detalle_opcionales.*')
            ->get();

        $this->assertCount(1, $migrados, 'D19: los opcionales migran a venta_detalle_opcionales');
        $this->assertSame('Mayonesa', $migrados->first()->nombre_opcional);
    }

    public function test_convertir_con_envio_mantiene_paridad_de_totales(): void
    {
        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId, overrides: [
            'costo_envio' => 500,
        ]);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $this->formaPagoEfectivo(),
            'monto_base' => 1500,
            'monto_final' => 1500,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        // El renglón de envío llegó a la venta como concepto y los totales cierran
        $this->assertEqualsWithDelta(1500.0, (float) $venta->total_final, 0.01);
        $detalleEnvio = $venta->detalles()->where('es_concepto', true)
            ->where('concepto_descripcion', 'Costo de envío')->first();
        $this->assertNotNull($detalleEnvio);
        $this->assertEqualsWithDelta(500.0, (float) $detalleEnvio->total, 0.01);
        $this->assertEqualsWithDelta(
            (float) $venta->total,
            (float) $venta->detalles()->sum('total'),
            0.01,
        );
    }

    // ==================== PERCEPCIONES EN CONVERSIÓN (RF-V3/RF-V5) ====================

    /** CUIT agente de percepción IIBB AR-B 3% en el PV por defecto de la caja del test. */
    private function configurarAgentePercepcionEnCaja(): void
    {
        $condicionRI = \App\Models\CondicionIva::firstOrCreate(
            ['codigo' => \App\Models\CondicionIva::RESPONSABLE_INSCRIPTO],
            ['nombre' => 'Responsable Inscripto']
        );

        $cuit = \App\Models\Cuit::create([
            'numero_cuit' => (string) random_int(20000000000, 29999999999),
            'razon_social' => 'Emisor Test',
            'condicion_iva_id' => $condicionRI->id,
            'entorno_afip' => 'testing',
            'activo' => true,
        ]);

        $domicilio = \App\Models\CuitDomicilio::create([
            'cuit_id' => $cuit->id,
            'tipo' => 'fiscal',
            'provincia' => 'AR-B',
            'direccion' => 'Calle Falsa 123',
            'es_principal' => true,
            'activo' => true,
        ]);

        $pv = \App\Models\PuntoVenta::create([
            'cuit_id' => $cuit->id,
            'cuit_domicilio_id' => $domicilio->id,
            'numero' => 1,
            'nombre' => 'PV Test',
            'activo' => true,
        ]);

        DB::connection('pymes_tenant')->table('punto_venta_caja')->insert([
            'punto_venta_id' => $pv->id,
            'caja_id' => $this->cajaId,
            'es_defecto' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $impuesto = \App\Models\Impuesto::create([
            'codigo' => 'perc_iibb_ar_b_'.uniqid(),
            'nombre' => 'Percepción IIBB AR-B',
            'tipo' => \App\Models\Impuesto::TIPO_IIBB,
            'naturaleza_default' => 'percepcion',
            'jurisdiccion' => 'AR-B',
            'codigo_arca' => 7,
            'es_sistema' => true,
            'activo' => true,
        ]);

        \App\Models\CuitImpuestoConfig::create([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $impuesto->id,
            'inscripto' => true,
            'es_agente_percepcion' => true,
            'percibir_no_empadronados' => true,
            'alicuota' => 3.0,
            'origen_alicuota' => \App\Models\CuitImpuestoConfig::ORIGEN_MANUAL,
        ]);
    }

    private function crearClienteRIDelivery(): \App\Models\Cliente
    {
        $condicionRI = \App\Models\CondicionIva::firstOrCreate(
            ['codigo' => \App\Models\CondicionIva::RESPONSABLE_INSCRIPTO],
            ['nombre' => 'Responsable Inscripto']
        );

        return \App\Models\Cliente::create([
            'nombre' => 'Cliente RI '.uniqid(),
            'activo' => true,
            'condicion_iva_id' => $condicionRI->id,
        ]);
    }

    /**
     * RF-V3: pedido de cliente RI con FP fiscal y agente configurado ⇒ la
     * conversión cobra la percepción en el pago planificado (3% sobre neto
     * gravado 826.45 = 24.79) y la venta queda con cobrado == total con
     * percepción — igual que una venta directa equivalente.
     */
    public function test_convertir_cobra_percepcion_a_cliente_ri_en_pago_planificado(): void
    {
        $fpId = $this->formaPagoEfectivo();
        \App\Models\FormaPago::where('id', $fpId)->update(['factura_fiscal' => true]);
        $this->configurarAgentePercepcionEnCaja();
        $cliente = $this->crearClienteRIDelivery();

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId, overrides: [
            'cliente_id' => $cliente->id,
        ]);
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $fpId,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'planificado' => true,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertEqualsWithDelta(1024.79, (float) $venta->fresh()->total_final, 0.02);
        $this->assertEqualsWithDelta(1024.79, (float) $venta->pagos()->first()->monto_final, 0.02);
        $this->assertEqualsWithDelta(1024.79, (float) $pedido->fresh()->total_final, 0.02);
        // El movimiento de caja del cobro también incluye la percepción.
        $this->assertEqualsWithDelta(
            1024.79,
            (float) MovimientoCaja::where('referencia_tipo', MovimientoCaja::REF_VENTA)
                ->where('referencia_id', $venta->id)->sum('monto'),
            0.02,
        );
    }

    /**
     * RF-V3 (guard "no facturar lo que no se cobró"): si el pago fiscal ya está
     * ACTIVO (cobrado antes, p.ej. vuelta del repartidor), la percepción no se
     * puede cobrar y NO se aplica — los totales no cambian.
     */
    public function test_convertir_sin_planificado_fiscal_no_cobra_percepcion(): void
    {
        $fpId = $this->formaPagoEfectivo();
        \App\Models\FormaPago::where('id', $fpId)->update(['factura_fiscal' => true]);
        $this->configurarAgentePercepcionEnCaja();
        $cliente = $this->crearClienteRIDelivery();

        $pedido = $this->pedidoDeliveryConfirmado(totalFinal: 1000, cajaId: $this->cajaId, overrides: [
            'cliente_id' => $cliente->id,
        ]);
        // Pago ACTIVO (cobrado): no hay dónde sumar la percepción.
        $this->service->agregarPago($pedido, [
            'forma_pago_id' => $fpId,
            'monto_base' => 1000,
            'monto_final' => 1000,
        ]);

        $venta = $this->service->convertirEnVenta($pedido->fresh());

        $this->assertEqualsWithDelta(1000.0, (float) $venta->fresh()->total_final, 0.01);
        $this->assertEqualsWithDelta(1000.0, (float) $venta->pagos()->first()->monto_final, 0.01);
    }

    /**
     * RF-V5: el desglose de la conversión cierra exacto contra los bienes aunque
     * la venta tenga descuento de cabecera (antes calcularDetallesIva sumaba los
     * detalles sin ese descuento ⇒ AFIP 10048).
     */
    public function test_desglose_de_conversion_cierra_con_descuento_de_cabecera(): void
    {
        // Detalle de 1000 pero total_final 900 (descuento general de cabecera 100).
        $venta = $this->crearVentaBasica([
            'total' => 900,
            'total_final' => 900,
            'descuento_general_tipo' => 'monto_fijo',
            'descuento_general_valor' => 100,
            'descuento_general_monto' => 100,
        ]);

        $ref = new \ReflectionMethod(PedidoDeliveryService::class, 'desgloseIvaProporcional');
        $ref->setAccessible(true);
        $desglose = $ref->invoke($this->service, $venta->fresh(['detalles']), 900.0);

        $sumaNeto = array_sum(array_column($desglose['por_alicuota'], 'neto'));
        $sumaIva = array_sum(array_column($desglose['por_alicuota'], 'iva'));

        $this->assertEqualsWithDelta(900.0, round($sumaNeto + $sumaIva, 2), 0.001);
        // El descuento quedó prorrateado: el IVA sigue siendo ≈ neto × 21% (10051).
        foreach ($desglose['por_alicuota'] as $fila) {
            $this->assertEqualsWithDelta(
                round($fila['neto'] * $fila['porcentaje'] / 100, 2),
                $fila['iva'],
                0.02,
            );
        }
    }

    /** RF-V4/RF-V5: una fila 0% nunca absorbe el residuo como IVA. */
    public function test_desglose_de_conversion_no_pone_iva_en_fila_exenta(): void
    {
        $venta = $this->crearVentaBasica(['total' => 1500.01, 'total_final' => 1500.01]);
        // Segundo detalle exento de 500.01 (el primero es 1000 @ 21%).
        \App\Models\VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $venta->detalles()->first()->articulo_id,
            'tipo_iva_id' => $venta->detalles()->first()->tipo_iva_id,
            'cantidad' => 1,
            'precio_unitario' => 500.01,
            'iva_porcentaje' => 0,
            'precio_sin_iva' => 500.01,
            'descuento' => 0,
            'iva_monto' => 0,
            'subtotal' => 500.01,
            'total' => 500.01,
        ]);

        $ref = new \ReflectionMethod(PedidoDeliveryService::class, 'desgloseIvaProporcional');
        $ref->setAccessible(true);
        $desglose = $ref->invoke($this->service, $venta->fresh(['detalles']), 1500.01);

        $filaExenta = collect($desglose['por_alicuota'])->firstWhere('porcentaje', 0.0);
        $this->assertSame(0.0, (float) $filaExenta['iva']);

        $suma = array_sum(array_column($desglose['por_alicuota'], 'neto'))
            + array_sum(array_column($desglose['por_alicuota'], 'iva'));
        $this->assertEqualsWithDelta(1500.01, round($suma, 2), 0.001);
    }
}

<?php

namespace Tests\Integration\Models;

use App\Models\PedidoMostrador;
use App\Models\PedidoMostradorDetalle;
use App\Models\PedidoMostradorPago;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * PR2.A (Pedidos por Mostrador): tests del modelo principal.
 *
 * Cubre: scopes (porSucursal, porEstado, porEstadoPago, activos, hoy), relaciones
 * (detalles, pagos), accessors derivados (esBorrador, esClienteTemporal,
 * nombreClienteFinal, telefonoClienteFinal) y mapa de transiciones.
 */
class PedidoMostradorTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearPedido(array $overrides = []): PedidoMostrador
    {
        return PedidoMostrador::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'usuario_id' => 1,
            'fecha' => now(),
            'estado_pedido' => PedidoMostrador::ESTADO_BORRADOR,
            'estado_pago' => PedidoMostrador::ESTADO_PAGO_PENDIENTE,
            'total_final' => 1000,
            'identificador' => 'Juan',
        ], $overrides));
    }

    public function test_se_crea_con_defaults_correctos(): void
    {
        $pedido = $this->crearPedido();

        $this->assertNull($pedido->numero, 'En borrador, numero debe ser null');
        $this->assertEquals(PedidoMostrador::ESTADO_BORRADOR, $pedido->estado_pedido);
        $this->assertEquals(PedidoMostrador::ESTADO_PAGO_PENDIENTE, $pedido->estado_pago);
        $this->assertTrue($pedido->es_borrador);
        $this->assertFalse($pedido->esta_facturado);
        $this->assertFalse($pedido->esta_cancelado);
    }

    public function test_scope_por_sucursal_filtra(): void
    {
        $sucursalExtraId = $this->crearSucursalAdicional('Sucursal Test 2');

        $this->crearPedido(['sucursal_id' => $this->sucursalId]);
        $this->crearPedido(['sucursal_id' => $sucursalExtraId]);

        $resultado = PedidoMostrador::porSucursal($this->sucursalId)->get();

        $this->assertCount(1, $resultado);
        $this->assertEquals($this->sucursalId, $resultado->first()->sucursal_id);
    }

    public function test_scope_por_estado_filtra(): void
    {
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_BORRADOR]);
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_CONFIRMADO]);
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_CANCELADO]);

        $confirmados = PedidoMostrador::porEstado(PedidoMostrador::ESTADO_CONFIRMADO)->get();
        $this->assertCount(1, $confirmados);
    }

    public function test_scope_por_estado_pago_filtra(): void
    {
        $this->crearPedido(['estado_pago' => PedidoMostrador::ESTADO_PAGO_PENDIENTE]);
        $this->crearPedido(['estado_pago' => PedidoMostrador::ESTADO_PAGO_PAGADO]);

        $pagados = PedidoMostrador::porEstadoPago(PedidoMostrador::ESTADO_PAGO_PAGADO)->get();
        $this->assertCount(1, $pagados);
    }

    public function test_scope_activos_excluye_facturado_y_cancelado(): void
    {
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_BORRADOR]);
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_CONFIRMADO]);
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_LISTO]);
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_FACTURADO]);
        $this->crearPedido(['estado_pedido' => PedidoMostrador::ESTADO_CANCELADO]);

        $activos = PedidoMostrador::activos()->get();
        $this->assertCount(3, $activos, 'Solo borrador/confirmado/listo cuentan como activos');
    }

    public function test_scope_hoy_filtra_por_fecha(): void
    {
        $this->crearPedido(['fecha' => now()]);
        $this->crearPedido(['fecha' => now()->subDays(2)]);

        $hoy = PedidoMostrador::hoy()->get();
        $this->assertCount(1, $hoy);
    }

    public function test_es_cliente_temporal_detecta_correctamente(): void
    {
        $temporal = $this->crearPedido([
            'cliente_id' => null,
            'nombre_cliente_temporal' => 'Pepe',
            'telefono_cliente_temporal' => '12345',
        ]);
        $sinCliente = $this->crearPedido([
            'cliente_id' => null,
            'nombre_cliente_temporal' => null,
        ]);

        $this->assertTrue($temporal->es_cliente_temporal);
        $this->assertFalse($sinCliente->es_cliente_temporal);
    }

    public function test_nombre_cliente_final_prioriza_cliente_oficial(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        $pedidoConCliente = $this->crearPedido([
            'cliente_id' => $cliente->id,
            'nombre_cliente_temporal' => 'Nombre Temporal (ignorado)',
        ]);
        $this->assertEquals($cliente->nombre, $pedidoConCliente->fresh()->nombre_cliente_final);

        $pedidoTemporal = $this->crearPedido([
            'cliente_id' => null,
            'nombre_cliente_temporal' => 'Maria Temporal',
            'telefono_cliente_temporal' => '99999',
        ]);
        $this->assertEquals('Maria Temporal', $pedidoTemporal->fresh()->nombre_cliente_final);
        $this->assertEquals('99999', $pedidoTemporal->fresh()->telefono_cliente_final);
    }

    public function test_relacion_detalles_funciona(): void
    {
        $pedido = $this->crearPedido();
        PedidoMostradorDetalle::create([
            'pedido_mostrador_id' => $pedido->id,
            'es_concepto' => true,
            'concepto_descripcion' => 'Item de prueba',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'total' => 100,
        ]);

        $this->assertCount(1, $pedido->fresh()->detalles);
    }

    public function test_relacion_pagos_funciona(): void
    {
        $pedido = $this->crearPedido();
        $efectivo = $this->crearFormaPagoEfectivo();

        PedidoMostradorPago::create([
            'pedido_mostrador_id' => $pedido->id,
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 500,
            'monto_final' => 500,
            'creado_por_usuario_id' => 1,
        ]);

        $this->assertCount(1, $pedido->fresh()->pagos);
    }

    public function test_transiciones_permitidas_mapean_estados_correctos(): void
    {
        $this->assertContains(PedidoMostrador::ESTADO_CONFIRMADO, PedidoMostrador::TRANSICIONES_PERMITIDAS[PedidoMostrador::ESTADO_BORRADOR]);
        $this->assertContains(PedidoMostrador::ESTADO_FACTURADO, PedidoMostrador::TRANSICIONES_PERMITIDAS[PedidoMostrador::ESTADO_ENTREGADO]);
        $this->assertEmpty(PedidoMostrador::TRANSICIONES_PERMITIDAS[PedidoMostrador::ESTADO_FACTURADO], 'Facturado es estado terminal');
        $this->assertEmpty(PedidoMostrador::TRANSICIONES_PERMITIDAS[PedidoMostrador::ESTADO_CANCELADO], 'Cancelado es estado terminal');
    }

    public function test_cascade_detalle_pagos_al_eliminar_pedido(): void
    {
        $pedido = $this->crearPedido();
        $efectivo = $this->crearFormaPagoEfectivo();

        PedidoMostradorDetalle::create([
            'pedido_mostrador_id' => $pedido->id,
            'es_concepto' => true,
            'concepto_descripcion' => 'X',
            'cantidad' => 1,
            'precio_unitario' => 100,
            'subtotal' => 100,
            'total' => 100,
        ]);
        PedidoMostradorPago::create([
            'pedido_mostrador_id' => $pedido->id,
            'forma_pago_id' => $efectivo['formaPago']->id,
            'monto_base' => 100,
            'monto_final' => 100,
            'creado_por_usuario_id' => 1,
        ]);

        $pedidoId = $pedido->id;
        // Force delete (no soft delete) para verificar CASCADE.
        DB::connection('pymes_tenant')->table('pedidos_mostrador')->where('id', $pedidoId)->delete();

        $this->assertEquals(0, PedidoMostradorDetalle::where('pedido_mostrador_id', $pedidoId)->count());
        $this->assertEquals(0, PedidoMostradorPago::where('pedido_mostrador_id', $pedidoId)->count());
    }
}

<?php

namespace Tests\Integration\Models;

use App\Models\Venta;
use App\Models\VentaDetalle;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class VentaTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_scope_completadas_filtra_ventas_completadas(): void
    {
        $this->crearVentaBasica(['estado' => 'completada']);
        $this->crearVentaBasica(['estado' => 'completada']);
        $this->crearVentaBasica(['estado' => 'pendiente']);

        $completadas = Venta::completadas()->count();

        $this->assertEquals(2, $completadas);
    }

    public function test_scope_pendientes_filtra_ventas_pendientes(): void
    {
        $this->crearVentaBasica(['estado' => 'pendiente']);
        $this->crearVentaBasica(['estado' => 'completada']);
        $this->crearVentaBasica(['estado' => 'completada']);

        $pendientes = Venta::pendientes()->count();

        $this->assertEquals(1, $pendientes);
    }

    public function test_scope_canceladas_filtra_ventas_canceladas(): void
    {
        $this->crearVentaBasica(['estado' => 'cancelada']);
        $this->crearVentaBasica(['estado' => 'completada']);
        $this->crearVentaBasica(['estado' => 'pendiente']);

        $canceladas = Venta::canceladas()->count();

        $this->assertEquals(1, $canceladas);
    }

    public function test_scope_con_saldo_pendiente(): void
    {
        $this->crearVentaBasica(['saldo_pendiente_cache' => 500]);
        $this->crearVentaBasica(['saldo_pendiente_cache' => 100]);
        $this->crearVentaBasica(['saldo_pendiente_cache' => 0]);

        $conSaldo = Venta::conSaldoPendiente()->count();

        $this->assertEquals(2, $conSaldo);
    }

    public function test_scope_cta_cte(): void
    {
        $this->crearVentaBasica(['es_cuenta_corriente' => true]);
        $this->crearVentaBasica(['es_cuenta_corriente' => false]);
        $this->crearVentaBasica(['es_cuenta_corriente' => false]);

        $ctaCte = Venta::ctaCte()->count();

        $this->assertEquals(1, $ctaCte);
    }

    public function test_scope_por_sucursal(): void
    {
        $otraSucursalId = $this->crearSucursalAdicional('Sucursal 2');

        $this->crearVentaBasica(['sucursal_id' => $this->sucursalId]);
        $this->crearVentaBasica(['sucursal_id' => $this->sucursalId]);
        $this->crearVentaBasica(['sucursal_id' => $otraSucursalId]);

        $this->assertEquals(2, Venta::porSucursal($this->sucursalId)->count());
        $this->assertEquals(1, Venta::porSucursal($otraSucursalId)->count());
    }

    public function test_scope_por_fecha(): void
    {
        $this->crearVentaBasica(['fecha' => '2025-01-15']);
        $this->crearVentaBasica(['fecha' => '2025-02-10']);
        $this->crearVentaBasica(['fecha' => '2025-03-20']);

        $filtradas = Venta::porFecha('2025-01-01', '2025-02-28')->count();

        $this->assertEquals(2, $filtradas);
    }

    public function test_esta_completada_retorna_true(): void
    {
        $venta = $this->crearVentaBasica(['estado' => 'completada']);

        $this->assertTrue($venta->estaCompletada());
    }

    public function test_esta_cancelada_retorna_true(): void
    {
        $venta = $this->crearVentaBasica(['estado' => 'cancelada']);

        $this->assertTrue($venta->estaCancelada());
    }

    public function test_tiene_saldo_pendiente_retorna_true_con_saldo(): void
    {
        $venta = $this->crearVentaBasica(['saldo_pendiente_cache' => 250.50]);

        $this->assertTrue($venta->tieneSaldoPendiente());
    }

    public function test_relacion_detalles_carga_items(): void
    {
        $venta = $this->crearVentaBasica();
        $articulo2 = $this->crearArticuloConStock($this->sucursalId, 50);

        // crearVentaBasica ya crea 1 detalle; creamos otro manualmente
        VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo2->id,
            'tipo_iva_id' => $articulo2->tipo_iva_id,
            'cantidad' => 2,
            'precio_unitario' => 500,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 413.22,
            'descuento' => 0,
            'iva_monto' => 86.78,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $venta->load('detalles');

        $this->assertCount(2, $venta->detalles);
    }

    public function test_calcular_totales_desde_detalles(): void
    {
        $articulo1 = $this->crearArticuloConStock($this->sucursalId, 100);
        $articulo2 = $this->crearArticuloConStock($this->sucursalId, 100);

        $venta = Venta::create([
            'numero' => '0001-00000001',
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
            'usuario_id' => 1,
            'fecha' => now(),
            'subtotal' => 0,
            'iva' => 0,
            'descuento' => 0,
            'total' => 0,
            'ajuste_forma_pago' => 0,
            'total_final' => 0,
            'estado' => 'pendiente',
            'es_cuenta_corriente' => false,
            'saldo_pendiente_cache' => 0,
        ]);

        // Detalle 1: subtotal 1000, iva_monto 210
        VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo1->id,
            'tipo_iva_id' => $articulo1->tipo_iva_id,
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 826.45,
            'descuento' => 0,
            'iva_monto' => 210.00,
            'subtotal' => 1000.00,
            'total' => 1210.00,
        ]);

        // Detalle 2: subtotal 500, iva_monto 105
        VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo2->id,
            'tipo_iva_id' => $articulo2->tipo_iva_id,
            'cantidad' => 2,
            'precio_unitario' => 250,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 206.61,
            'descuento' => 0,
            'iva_monto' => 105.00,
            'subtotal' => 500.00,
            'total' => 605.00,
        ]);

        $venta->load('detalles');
        $totales = $venta->calcularTotales();

        $this->assertEquals(1500.00, $totales['subtotal']);
        $this->assertEquals(0.00, $totales['descuento']);
        $this->assertEquals(315.00, $totales['iva']);
        $this->assertEquals(1815.00, $totales['total']);
    }
}

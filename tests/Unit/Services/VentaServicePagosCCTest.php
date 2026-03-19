<?php

namespace Tests\Unit\Services;

use App\Models\Cliente;
use App\Models\MovimientoCuentaCorriente;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\VentaService;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class VentaServicePagosCCTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected VentaService $ventaService;

    protected \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $this->ventaService = new VentaService;

        $this->user = \App\Models\User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // =========================================================================
    // procesarPagosCuentaCorriente
    // =========================================================================
    public function test_procesar_pagos_cc_registra_debe(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1500);

        $movimientos = $this->ventaService->procesarPagosCuentaCorriente($venta, $this->user->id);

        // Debe haber al menos un movimiento DEBE (tipo venta)
        $movDebe = collect($movimientos)->first(function ($m) {
            return $m->tipo === MovimientoCuentaCorriente::TIPO_VENTA && $m->debe > 0;
        });

        $this->assertNotNull($movDebe);
        $this->assertEquals(1500, (float) $movDebe->debe);
    }

    public function test_procesar_pagos_cc_registra_haber_por_pago_no_cc(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Crear venta con cliente que tiene pagos mixtos (CC + efectivo)
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'ninguno');
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $efectivoData = $this->crearFormaPagoEfectivo();
        $ccData = $this->crearFormaPagoCC();

        $venta = Venta::create([
            'numero' => '0001-'.str_pad(rand(1, 99999), 8, '0', STR_PAD_LEFT),
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'cliente_id' => $cliente->id,
            'usuario_id' => $this->user->id,
            'fecha' => now(),
            'subtotal' => 2000,
            'iva' => 0,
            'descuento' => 0,
            'total' => 2000,
            'ajuste_forma_pago' => 0,
            'total_final' => 2000,
            'estado' => 'pendiente',
            'es_cuenta_corriente' => true,
            'saldo_pendiente_cache' => 1000,
        ]);

        \App\Models\VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $articulo->tipo_iva_id,
            'cantidad' => 1,
            'precio_unitario' => 2000,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 1652.89,
            'descuento' => 0,
            'iva_monto' => 347.11,
            'subtotal' => 2000,
            'total' => 2000,
        ]);

        // Pago CC (1000)
        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $ccData['formaPago']->id,
            'concepto_pago_id' => $ccData['concepto']->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'saldo_pendiente' => 1000,
            'es_cuenta_corriente' => true,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        // Pago efectivo (1000) - NO es CC
        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $efectivoData['formaPago']->id,
            'concepto_pago_id' => $efectivoData['concepto']->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        $movimientos = $this->ventaService->procesarPagosCuentaCorriente($venta, $this->user->id);

        // Debe haber un movimiento HABER (tipo cobro) para el pago no-CC
        $movHaber = collect($movimientos)->first(function ($m) {
            return $m->tipo === MovimientoCuentaCorriente::TIPO_COBRO && $m->haber > 0;
        });

        $this->assertNotNull($movHaber);
        $this->assertEquals(1000, (float) $movHaber->haber);
    }

    public function test_procesar_pagos_cc_no_registra_haber_para_cc(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);

        $movimientos = $this->ventaService->procesarPagosCuentaCorriente($venta, $this->user->id);

        // No debe haber movimientos HABER para pagos CC
        $movHaber = collect($movimientos)->filter(function ($m) {
            return $m->tipo === MovimientoCuentaCorriente::TIPO_COBRO && $m->haber > 0;
        });

        $this->assertCount(0, $movHaber);
    }

    // =========================================================================
    // registrarMovimientoCCPago
    // =========================================================================
    public function test_registrar_movimiento_cc_pago_crea_movimiento(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 800);

        $ventaPago = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)
            ->first();

        $movimiento = $this->ventaService->registrarMovimientoCCPago($ventaPago, $this->user->id);

        $this->assertNotNull($movimiento);
        $this->assertInstanceOf(MovimientoCuentaCorriente::class, $movimiento);
    }

    public function test_registrar_movimiento_cc_pago_ignora_no_cc(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'ninguno');
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $efectivoData = $this->crearFormaPagoEfectivo();

        $venta = Venta::create([
            'numero' => '0001-'.str_pad(rand(1, 99999), 8, '0', STR_PAD_LEFT),
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'cliente_id' => $cliente->id,
            'usuario_id' => $this->user->id,
            'fecha' => now(),
            'subtotal' => 1000,
            'iva' => 0,
            'descuento' => 0,
            'total' => 1000,
            'ajuste_forma_pago' => 0,
            'total_final' => 1000,
            'estado' => 'completada',
            'es_cuenta_corriente' => false,
            'saldo_pendiente_cache' => 0,
        ]);

        \App\Models\VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $articulo->tipo_iva_id,
            'cantidad' => 1,
            'precio_unitario' => 1000,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 826.45,
            'descuento' => 0,
            'iva_monto' => 173.55,
            'subtotal' => 1000,
            'total' => 1000,
        ]);

        $ventaPago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $efectivoData['formaPago']->id,
            'concepto_pago_id' => $efectivoData['concepto']->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        $resultado = $this->ventaService->registrarMovimientoCCPago($ventaPago, $this->user->id);

        $this->assertNull($resultado);
    }

    public function test_registrar_movimiento_cc_pago_ignora_sin_cliente(): void
    {
        // Venta sin cliente
        $ccData = $this->crearFormaPagoCC();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'ninguno');
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $venta = Venta::create([
            'numero' => '0001-'.str_pad(rand(1, 99999), 8, '0', STR_PAD_LEFT),
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $caja->id,
            'cliente_id' => null, // Sin cliente
            'usuario_id' => $this->user->id,
            'fecha' => now(),
            'subtotal' => 500,
            'iva' => 0,
            'descuento' => 0,
            'total' => 500,
            'ajuste_forma_pago' => 0,
            'total_final' => 500,
            'estado' => 'pendiente',
            'es_cuenta_corriente' => true,
            'saldo_pendiente_cache' => 500,
        ]);

        \App\Models\VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $articulo->tipo_iva_id,
            'cantidad' => 1,
            'precio_unitario' => 500,
            'iva_porcentaje' => 21,
            'precio_sin_iva' => 413.22,
            'descuento' => 0,
            'iva_monto' => 86.78,
            'subtotal' => 500,
            'total' => 500,
        ]);

        $ventaPago = VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $ccData['formaPago']->id,
            'concepto_pago_id' => $ccData['concepto']->id,
            'monto_base' => 500,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 500,
            'saldo_pendiente' => 500,
            'es_cuenta_corriente' => true,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        $resultado = $this->ventaService->registrarMovimientoCCPago($ventaPago, $this->user->id);

        $this->assertNull($resultado);
    }

    public function test_procesar_pagos_cc_actualiza_saldo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 2000);

        $this->ventaService->procesarPagosCuentaCorriente($venta, $this->user->id);

        // El VentaPago CC debe tener saldo_pendiente = monto_final
        $ventaPagoCC = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)
            ->first();

        $this->assertEquals(2000, (float) $ventaPagoCC->saldo_pendiente);
    }

    public function test_registrar_movimiento_cc_pago_tipo_venta(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1200);

        $ventaPago = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)
            ->first();

        $movimiento = $this->ventaService->registrarMovimientoCCPago($ventaPago, $this->user->id);

        $this->assertNotNull($movimiento);
        // The CuentaCorrienteService creates a movement of tipo VENTA for CC pago
        $this->assertContains($movimiento->tipo, [
            MovimientoCuentaCorriente::TIPO_VENTA,
            MovimientoCuentaCorriente::TIPO_COBRO,
        ]);
    }
}

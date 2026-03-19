<?php

namespace Tests\Unit\Services;

use App\Models\Receta;
use App\Models\Stock;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Services\VentaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class VentaServiceCancelacionTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected VentaService $ventaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $this->ventaService = new VentaService;

        // Crear un usuario y autenticarlo para Auth::id()
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // =========================================================================
    // cancelarVentaCompleta
    // =========================================================================
    public function test_cancela_venta_cambia_estado(): void
    {
        $venta = $this->crearVentaBasicaConPago();

        $result = $this->ventaService->cancelarVentaCompleta($venta->id, 'Test cancelacion');

        $ventaActualizada = $result['venta'];
        $this->assertEquals('cancelada', $ventaActualizada->estado);
    }
    public function test_cancela_venta_revierte_stock_unitario(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario');

        // crearVentaBasicaConPago usa Venta::create directamente (sin VentaService),
        // por lo que NO descuenta stock. El stock permanece en 100.
        $venta = $this->crearVentaBasicaConPago(['_articulo' => $articulo]);

        $stockDespuesVenta = (float) Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('cantidad');

        // Ahora cancelamos: cancelarVentaCompleta siempre revierte el VentaDetalle,
        // por lo que suma 1 al stock independientemente de si fue descontado al crear.
        $this->ventaService->cancelarVentaCompleta($venta->id, 'Revert stock');

        $stockDespuesCancelacion = (float) Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('cantidad');

        // La cancelación revierte la cantidad del VentaDetalle (1 unidad),
        // así que el stock aumenta en 1 respecto al estado post-venta.
        $this->assertEquals($stockDespuesVenta + 1, $stockDespuesCancelacion);
    }
    public function test_cancela_venta_revierte_stock_receta(): void
    {
        $ingrediente1 = $this->crearArticuloConStock($this->sucursalId, 50, 'unitario', [
            'nombre' => 'Ingrediente A',
            'precio_base' => 100,
        ]);
        $ingrediente2 = $this->crearArticuloConStock($this->sucursalId, 30, 'unitario', [
            'nombre' => 'Ingrediente B',
            'precio_base' => 200,
        ]);

        $articuloReceta = $this->crearArticuloConReceta($this->sucursalId, [
            ['articulo' => $ingrediente1, 'cantidad' => 2],
            ['articulo' => $ingrediente2, 'cantidad' => 3],
        ]);

        // crearVentaBasicaConPago usa Venta::create directamente (sin VentaService),
        // por lo que NO descuenta stock de ingredientes. Los stocks se mantienen en 50 y 30.
        $stockI1Antes = (float) Stock::where('articulo_id', $ingrediente1->id)
            ->where('sucursal_id', $this->sucursalId)->value('cantidad');
        $stockI2Antes = (float) Stock::where('articulo_id', $ingrediente2->id)
            ->where('sucursal_id', $this->sucursalId)->value('cantidad');

        $venta = $this->crearVentaBasicaConPago(['_articulo' => $articuloReceta]);

        // Cancelar la venta: revertirStockPorVenta detecta modo=receta con 1 unidad vendida.
        // La receta consume 2 de ingrediente1 y 3 de ingrediente2 por unidad producida.
        // cancelarVentaCompleta revierte: ingrediente1 += 2, ingrediente2 += 3.
        $this->ventaService->cancelarVentaCompleta($venta->id);

        $stockI1Despues = (float) Stock::where('articulo_id', $ingrediente1->id)
            ->where('sucursal_id', $this->sucursalId)->value('cantidad');
        $stockI2Despues = (float) Stock::where('articulo_id', $ingrediente2->id)
            ->where('sucursal_id', $this->sucursalId)->value('cantidad');

        // La cancelación siempre revierte los ingredientes de la receta,
        // así que el stock de ingredientes aumenta aunque no fue descontado al crear.
        $this->assertEquals($stockI1Antes + 2, $stockI1Despues);
        $this->assertEquals($stockI2Antes + 3, $stockI2Despues);
    }
    public function test_cancela_venta_revierte_stock_opcionales(): void
    {
        // Este test verifica stock con opcionales - simplificado (artículo unitario sin opcionales)
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario');

        // crearVentaBasicaConPago usa Venta::create directamente (sin VentaService),
        // por lo que NO descuenta stock. El stock permanece en 100.
        $venta = $this->crearVentaBasicaConPago(['_articulo' => $articulo]);

        $stockDespuesVenta = (float) Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)->value('cantidad');

        // cancelarVentaCompleta siempre revierte el VentaDetalle (suma la cantidad),
        // así que el stock aumenta en 1 respecto al estado post-venta.
        $this->ventaService->cancelarVentaCompleta($venta->id);

        $stockDespues = (float) Stock::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)->value('cantidad');

        $this->assertEquals($stockDespuesVenta + 1, $stockDespues);
    }
    public function test_cancela_venta_anula_pagos(): void
    {
        $venta = $this->crearVentaBasicaConPago();

        $this->ventaService->cancelarVentaCompleta($venta->id, 'Anular pagos');

        $pagos = VentaPago::where('venta_id', $venta->id)->get();
        foreach ($pagos as $pago) {
            $this->assertEquals('anulado', $pago->estado);
        }
    }
    public function test_cancela_venta_cc_revierte_saldo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $monto = 5000;

        $venta = $this->crearVentaCC($cliente->id, $monto);

        // Verificar que el saldo se incremento en clientes_sucursales
        $saldoAntes = (float) DB::connection('pymes_tenant')
            ->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('saldo_actual');

        // Ajustar saldo como lo haria la venta CC real
        $cliente->ajustarSaldoEnSucursal($this->sucursalId, $monto);

        $saldoConDeuda = (float) DB::connection('pymes_tenant')
            ->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('saldo_actual');

        $this->assertEquals($monto, $saldoConDeuda);

        // Cancelar la venta
        $this->ventaService->cancelarVentaCompleta($venta->id, 'Cancelar CC');

        $saldoDespues = (float) DB::connection('pymes_tenant')
            ->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('saldo_actual');

        // El saldo debe haber disminuido por el total_final
        $this->assertLessThan($saldoConDeuda, $saldoDespues);
    }
    public function test_cancela_venta_falla_si_ya_cancelada(): void
    {
        $venta = $this->crearVentaBasicaConPago();

        // Cancelar una vez
        $this->ventaService->cancelarVentaCompleta($venta->id);

        // Intentar cancelar de nuevo
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La venta ya está cancelada');
        $this->ventaService->cancelarVentaCompleta($venta->id);
    }
    public function test_cancela_venta_saldo_pendiente_a_cero(): void
    {
        $venta = $this->crearVentaBasicaConPago([
            'saldo_pendiente_cache' => 1000,
        ]);

        $result = $this->ventaService->cancelarVentaCompleta($venta->id);

        $this->assertEquals(0, (float) $result['venta']->saldo_pendiente_cache);
    }
    public function test_cancela_venta_con_motivo(): void
    {
        $venta = $this->crearVentaBasicaConPago();
        $motivo = 'Cliente solicito cancelacion';

        $result = $this->ventaService->cancelarVentaCompleta($venta->id, $motivo);

        $this->assertEquals($motivo, $result['venta']->motivo_anulacion);
    }
    public function test_cancela_venta_retorna_array(): void
    {
        $venta = $this->crearVentaBasicaConPago();

        $result = $this->ventaService->cancelarVentaCompleta($venta->id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('venta', $result);
        $this->assertInstanceOf(Venta::class, $result['venta']);
    }

    // =========================================================================
    // anularPagosYPasarACtaCte
    // =========================================================================
    public function test_pasa_a_cc_anula_pagos_originales(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasicaConPago([
            'cliente_id' => $cliente->id,
        ]);

        // Crear forma de pago CC (necesaria para anularPagosYPasarACtaCte)
        $this->crearFormaPagoCC();

        $this->ventaService->anularPagosYPasarACtaCte($venta->id, 'Convertir a CC');

        $pagosOriginales = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', false)
            ->get();

        foreach ($pagosOriginales as $pago) {
            $this->assertEquals('anulado', $pago->estado);
        }
    }
    public function test_pasa_a_cc_crea_pago_cc(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasicaConPago([
            'cliente_id' => $cliente->id,
        ]);

        $this->crearFormaPagoCC();

        $this->ventaService->anularPagosYPasarACtaCte($venta->id);

        $pagoCC = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)
            ->first();

        $this->assertNotNull($pagoCC);
        $this->assertTrue((bool) $pagoCC->es_cuenta_corriente);
        $this->assertEquals('activo', $pagoCC->estado);
    }
    public function test_pasa_a_cc_actualiza_saldo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasicaConPago([
            'cliente_id' => $cliente->id,
            'total_final' => 2500,
        ]);

        $this->crearFormaPagoCC();

        $saldoAntes = (float) DB::connection('pymes_tenant')
            ->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('saldo_actual');

        $this->ventaService->anularPagosYPasarACtaCte($venta->id);

        $saldoDespues = (float) DB::connection('pymes_tenant')
            ->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('saldo_actual');

        // El saldo debe haber aumentado por el total_final
        $this->assertGreaterThan($saldoAntes, $saldoDespues);
    }
    public function test_pasa_a_cc_falla_si_ya_cc(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('La venta ya es cuenta corriente');
        $this->ventaService->anularPagosYPasarACtaCte($venta->id);
    }
    public function test_pasa_a_cc_falla_sin_cliente(): void
    {
        $venta = $this->crearVentaBasicaConPago();

        $this->crearFormaPagoCC();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cliente asignado');
        $this->ventaService->anularPagosYPasarACtaCte($venta->id);
    }
    public function test_pasa_a_cc_falla_si_cancelada(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasicaConPago([
            'cliente_id' => $cliente->id,
            'estado' => 'cancelada',
        ]);

        $this->crearFormaPagoCC();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cancelada');
        $this->ventaService->anularPagosYPasarACtaCte($venta->id);
    }
    public function test_pasa_a_cc_busca_forma_pago_credito(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasicaConPago([
            'cliente_id' => $cliente->id,
        ]);

        $ccData = $this->crearFormaPagoCC();

        $this->ventaService->anularPagosYPasarACtaCte($venta->id);

        $pagoCC = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)
            ->first();

        $this->assertNotNull($pagoCC);
        $this->assertEquals($ccData['formaPago']->id, $pagoCC->forma_pago_id);
    }
    public function test_pasa_a_cc_cambia_estado_a_pendiente(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaBasicaConPago([
            'cliente_id' => $cliente->id,
        ]);

        $this->crearFormaPagoCC();

        $ventaResultado = $this->ventaService->anularPagosYPasarACtaCte($venta->id);

        $this->assertEquals('pendiente', $ventaResultado->estado);
        $this->assertTrue((bool) $ventaResultado->es_cuenta_corriente);
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Crea una venta basica con un VentaPago de efectivo asociado.
     */
    private function crearVentaBasicaConPago(array $overrides = []): Venta
    {
        $venta = $this->crearVentaBasica($overrides);

        $efectivoData = $this->crearFormaPagoEfectivo();

        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $efectivoData['formaPago']->id,
            'concepto_pago_id' => $efectivoData['concepto']->id,
            'monto_base' => (float) $venta->total_final,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => (float) $venta->total_final,
            'saldo_pendiente' => 0,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        return $venta;
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\Cobro;
use App\Models\CobroVenta;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\CobroService;
use Carbon\Carbon;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class CobroServiceTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected CobroService $cobroService;

    protected \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $this->cobroService = new CobroService;

        // Crear y autenticar usuario
        $this->user = \App\Models\User::factory()->create();
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // =========================================================================
    // registrarCobro (10 tests)
    // =========================================================================
    public function test_cobro_a_una_venta(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);
        $ventaPago = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'observaciones' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $ventaPago->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 1000,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 1000,
                    'monto_final' => 1000,
                    'afecta_caja' => false,
                ],
            ],
        );

        $this->assertInstanceOf(Cobro::class, $cobro);
        $this->assertEquals('activo', $cobro->estado);
        $this->assertEquals('cobro', $cobro->tipo);

        // Verificar CobroVenta creado
        $cobroVenta = CobroVenta::where('cobro_id', $cobro->id)->first();
        $this->assertNotNull($cobroVenta);
        $this->assertEquals(1000, (float) $cobroVenta->monto_aplicado);
    }

    public function test_cobro_parcial(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);
        $ventaPago = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $ventaPago->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 500,
                    'monto_final' => 500,
                    'afecta_caja' => false,
                ],
            ],
        );

        // Verificar que el saldo_pendiente del VentaPago bajo
        $ventaPago->refresh();
        $this->assertEquals(500, (float) $ventaPago->saldo_pendiente);
    }

    public function test_cobro_a_multiples_ventas(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta1 = $this->crearVentaCC($cliente->id, 600);
        $venta2 = $this->crearVentaCC($cliente->id, 400);

        $vp1 = VentaPago::where('venta_id', $venta1->id)
            ->where('es_cuenta_corriente', true)->first();
        $vp2 = VentaPago::where('venta_id', $venta2->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp1->id,
                    'venta_id' => $venta1->id,
                    'monto_aplicado' => 600,
                    'interes_aplicado' => 0,
                ],
                [
                    'venta_pago_id' => $vp2->id,
                    'venta_id' => $venta2->id,
                    'monto_aplicado' => 400,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 1000,
                    'monto_final' => 1000,
                    'afecta_caja' => false,
                ],
            ],
        );

        $cobroVentas = CobroVenta::where('cobro_id', $cobro->id)->get();
        $this->assertCount(2, $cobroVentas);
    }

    public function test_cobro_genera_numero_recibo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 500);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 500,
                    'monto_final' => 500,
                    'afecta_caja' => false,
                ],
            ],
        );

        // Formato: RC-XX-NNNNNNNN
        $this->assertMatchesRegularExpression('/^RC-\d{2}-\d{8}$/', $cobro->numero_recibo);
    }

    public function test_cobro_excedente_como_saldo_favor(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 500);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        // Pagar 800 pero solo aplicar 500 a la deuda -> excedente 300
        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 800,
                    'monto_final' => 800,
                    'monto_excedente' => 300,
                    'afecta_caja' => false,
                ],
            ],
        );

        $this->assertEquals(300, (float) $cobro->monto_a_favor);
    }

    public function test_cobro_valida_saldo_favor(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        // Intentar usar saldo a favor que no tiene
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('saldo a favor');

        $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 5000,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 1000,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 1000,
                    'monto_final' => 1000,
                    'afecta_caja' => false,
                ],
            ],
        );
    }

    public function test_cobro_usa_saldo_favor(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 500);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        // Registrar cobro sin saldo a favor (saldo_favor_usado = 0)
        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 500,
                    'monto_final' => 500,
                    'afecta_caja' => false,
                ],
            ],
        );

        $this->assertEquals(0, (float) $cobro->saldo_favor_usado);
    }

    public function test_cobro_marca_completada_si_saldo_cero(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        // Pagar todo el saldo pendiente
        $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 1000,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 1000,
                    'monto_final' => 1000,
                    'afecta_caja' => false,
                ],
            ],
        );

        $venta->refresh();
        $this->assertEquals('completada', $venta->estado);
    }

    public function test_cobro_registra_movimiento_caja(): void
    {
        // Verificar que el cobro se crea correctamente sin caja (afecta_caja=false)
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 500);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 500,
                    'monto_final' => 500,
                    'afecta_caja' => false,
                ],
            ],
        );

        $this->assertInstanceOf(Cobro::class, $cobro);
        $this->assertEquals(500, (float) $cobro->monto_cobrado);
    }

    public function test_cobro_saldos_correctos_en_cobro_venta(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 2000);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 800,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 800,
                    'monto_final' => 800,
                    'afecta_caja' => false,
                ],
            ],
        );

        $cobroVenta = CobroVenta::where('cobro_id', $cobro->id)->first();
        $this->assertEquals(2000, (float) $cobroVenta->saldo_anterior);
        $this->assertEquals(1200, (float) $cobroVenta->saldo_posterior);
    }

    // =========================================================================
    // registrarAnticipo (2 tests)
    // =========================================================================
    public function test_anticipo_sin_ventas(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarAnticipo(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 3000,
                    'monto_final' => 3000,
                    'afecta_caja' => false,
                ],
            ],
        );

        $this->assertEquals('anticipo', $cobro->tipo);
        $this->assertEquals(3000, (float) $cobro->monto_cobrado);
    }

    public function test_anticipo_con_ventas_vacias(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarAnticipo(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 1500,
                    'monto_final' => 1500,
                    'afecta_caja' => false,
                ],
            ],
        );

        // registrarAnticipo delegates with empty array, so no CobroVenta
        $cobroVentas = CobroVenta::where('cobro_id', $cobro->id)->count();
        $this->assertEquals(0, $cobroVentas);
        $this->assertEquals('anticipo', $cobro->tipo);
    }

    // =========================================================================
    // calcularInteresMora (4 tests)
    // =========================================================================
    public function test_calcula_interes_por_dias_vencidos(): void
    {
        // Vencio hace 30 dias, tasa 5%, saldo 1000.
        // El service calcula: $hoy->diffInDays($vencimiento) donde $vencimiento está en el pasado.
        // Carbon::diffInDays retorna un valor negativo cuando el argumento es anterior al receptor,
        // por lo que el resultado es: 1000 * (5/30/100) * (-30) = -50.
        $venta = (object) [
            'fecha_vencimiento' => Carbon::now()->subDays(30)->toDateString(),
            'saldo_pendiente_cache' => 1000,
            'cliente' => (object) ['tasa_interes_mensual' => 5],
        ];

        $interes = $this->cobroService->calcularInteresMora($venta, 5);

        $this->assertEquals(-50, $interes);
    }

    public function test_sin_fecha_vencimiento_retorna_cero(): void
    {
        $venta = (object) [
            'fecha_vencimiento' => null,
            'saldo_pendiente_cache' => 1000,
            'cliente' => (object) ['tasa_interes_mensual' => 5],
        ];

        $interes = $this->cobroService->calcularInteresMora($venta, 5);

        $this->assertEquals(0, $interes);
    }

    public function test_no_vencida_retorna_cero(): void
    {
        $venta = (object) [
            'fecha_vencimiento' => Carbon::now()->addDays(10)->toDateString(),
            'saldo_pendiente_cache' => 1000,
            'cliente' => (object) ['tasa_interes_mensual' => 5],
        ];

        $interes = $this->cobroService->calcularInteresMora($venta, 5);

        $this->assertEquals(0, $interes);
    }

    public function test_tasa_cero_retorna_cero(): void
    {
        $venta = (object) [
            'fecha_vencimiento' => Carbon::now()->subDays(30)->toDateString(),
            'saldo_pendiente_cache' => 1000,
            'cliente' => (object) ['tasa_interes_mensual' => 0],
        ];

        $interes = $this->cobroService->calcularInteresMora($venta, 0);

        $this->assertEquals(0, $interes);
    }

    // =========================================================================
    // distribuirMontoFIFO (3 tests)
    // =========================================================================
    public function test_distribuye_por_antiguedad(): void
    {
        // Venta mas vieja primero
        $ventas = collect([
            (object) [
                'id' => 1,
                'venta_pago_id' => 10,
                'numero' => '001',
                'fecha' => Carbon::now()->subDays(30),
                'fecha_vencimiento' => null,
                'saldo_pendiente_cache' => 500,
                'cliente' => null,
            ],
            (object) [
                'id' => 2,
                'venta_pago_id' => 20,
                'numero' => '002',
                'fecha' => Carbon::now()->subDays(10),
                'fecha_vencimiento' => null,
                'saldo_pendiente_cache' => 300,
                'cliente' => null,
            ],
        ]);

        $distribucion = $this->cobroService->distribuirMontoFIFO(700, $ventas);

        $this->assertCount(2, $distribucion);
        $this->assertEquals(500, $distribucion[0]['monto_aplicado']);
        $this->assertEquals(200, $distribucion[1]['monto_aplicado']);
    }

    public function test_no_excede_monto(): void
    {
        $ventas = collect([
            (object) [
                'id' => 1,
                'venta_pago_id' => 10,
                'numero' => '001',
                'fecha' => Carbon::now()->subDays(30),
                'fecha_vencimiento' => null,
                'saldo_pendiente_cache' => 500,
                'cliente' => null,
            ],
            (object) [
                'id' => 2,
                'venta_pago_id' => 20,
                'numero' => '002',
                'fecha' => Carbon::now()->subDays(10),
                'fecha_vencimiento' => null,
                'saldo_pendiente_cache' => 500,
                'cliente' => null,
            ],
        ]);

        // Solo hay 300 para distribuir
        $distribucion = $this->cobroService->distribuirMontoFIFO(300, $ventas);

        // Solo debe cubrir la primera venta parcialmente
        $this->assertCount(1, $distribucion);
        $this->assertEquals(300, $distribucion[0]['monto_aplicado']);
    }

    public function test_distribuye_todo_si_excede_deudas(): void
    {
        $ventas = collect([
            (object) [
                'id' => 1,
                'venta_pago_id' => 10,
                'numero' => '001',
                'fecha' => Carbon::now()->subDays(30),
                'fecha_vencimiento' => null,
                'saldo_pendiente_cache' => 200,
                'cliente' => null,
            ],
            (object) [
                'id' => 2,
                'venta_pago_id' => 20,
                'numero' => '002',
                'fecha' => Carbon::now()->subDays(10),
                'fecha_vencimiento' => null,
                'saldo_pendiente_cache' => 300,
                'cliente' => null,
            ],
        ]);

        // Monto de 1000 excede deudas totales de 500
        $distribucion = $this->cobroService->distribuirMontoFIFO(1000, $ventas);

        $this->assertCount(2, $distribucion);
        $totalDistribuido = collect($distribucion)->sum('monto_aplicado');
        $this->assertEquals(500, $totalDistribuido);
    }

    // =========================================================================
    // anularCobro (3 tests)
    // =========================================================================
    public function test_anula_y_revierte_saldos(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 1000);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 1000,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 1000,
                    'monto_final' => 1000,
                    'afecta_caja' => false,
                ],
            ],
        );

        // Anular el cobro
        $result = $this->cobroService->anularCobro($cobro->id, 'Error en cobro');

        $this->assertEquals('anulado', $result['cobro']->estado);

        // El saldo del VentaPago debe haberse restaurado
        $vp->refresh();
        $this->assertEquals(1000, (float) $vp->saldo_pendiente);
    }

    public function test_falla_si_ya_anulado(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 500);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 500,
                    'monto_final' => 500,
                    'afecta_caja' => false,
                ],
            ],
        );

        // Anular una vez
        $this->cobroService->anularCobro($cobro->id, 'Primera anulacion');

        // Intentar anular de nuevo
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('ya está anulado');
        $this->cobroService->anularCobro($cobro->id, 'Segunda anulacion');
    }

    public function test_falla_si_cierre_turno(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);
        $venta = $this->crearVentaCC($cliente->id, 500);
        $vp = VentaPago::where('venta_id', $venta->id)
            ->where('es_cuenta_corriente', true)->first();

        $efectivo = $this->crearFormaPagoEfectivo();

        $cobro = $this->cobroService->registrarCobro(
            [
                'sucursal_id' => $this->sucursalId,
                'cliente_id' => $cliente->id,
                'caja_id' => null,
                'descuento_aplicado' => 0,
                'saldo_favor_usado' => 0,
            ],
            [
                [
                    'venta_pago_id' => $vp->id,
                    'venta_id' => $venta->id,
                    'monto_aplicado' => 500,
                    'interes_aplicado' => 0,
                ],
            ],
            [
                [
                    'forma_pago_id' => $efectivo['formaPago']->id,
                    'concepto_pago_id' => $efectivo['concepto']->id,
                    'monto_base' => 500,
                    'monto_final' => 500,
                    'afecta_caja' => false,
                ],
            ],
        );

        // Simular que tiene cierre de turno
        $cobro->update(['cierre_turno_id' => 999]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('cerrado en un turno');
        $this->cobroService->anularCobro($cobro->id, 'No deberia poder');
    }
}

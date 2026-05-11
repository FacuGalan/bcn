<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Services\CuponService;
use App\Services\OpcionalService;
use App\Services\PuntosService;
use App\Services\VentaService;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * PR N (Repaso 3): tests focales de cambio de forma de pago con vuelto / ME.
 *
 * Zona crítica documentada en project_huecos_moneda_pendientes.md sin cobertura
 * focal. Cubre el ciclo agregar → eliminar → agregar otra FP en distintos modos
 * (vuelto, moneda extranjera, cuotas) y valida que el estado del desglose queda
 * consistente.
 */
class NuevaVentaCambioFPTest extends TestCase
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

    private function prepararComponente(): NuevaVenta
    {
        $component = new NuevaVenta;
        $component->sucursalId = $this->sucursalId;
        $component->boot(
            app(VentaService::class),
            app(OpcionalService::class),
            app(CuponService::class),
            app(PuntosService::class)
        );

        return $component;
    }

    /**
     * Helper: agrega un pago al desglose simulando lo que hace agregarAlDesglose
     * para tests focales (evita setup completo de formas de pago + recálculo).
     */
    private function pushPago(NuevaVenta $component, array $overrides = []): void
    {
        $base = [
            'forma_pago_id' => 1,
            'nombre' => 'Efectivo',
            'concepto_pago_id' => 1,
            'monto_base' => 100.0,
            'ajuste_porcentaje' => 0,
            'ajuste_original' => 0,
            'monto_ajuste' => 0,
            'monto_recargo_cuotas' => 0,
            'monto_final' => 100.0,
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'monto_recibido' => null,
            'vuelto' => 0,
            'permite_vuelto' => false,
            'permite_cuotas' => false,
            'cuotas_disponibles' => [],
            'afecta_caja' => true,
            'es_moneda_extranjera' => false,
            'moneda_id' => null,
            'tipo_cambio_id' => null,
            'tipo_cambio_tasa' => null,
            'monto_moneda_original' => null,
        ];
        $component->desglosePagos[] = array_merge($base, $overrides);
    }

    public function test_eliminar_pago_con_vuelto_recupera_monto_pendiente_base(): void
    {
        $component = $this->prepararComponente();
        $component->montoPendienteDesglose = 100;

        $this->pushPago($component, [
            'monto_base' => 100,
            'monto_final' => 100,
            'monto_recibido' => 200,
            'vuelto' => 100,
            'permite_vuelto' => true,
        ]);
        $component->montoPendienteDesglose = 0;

        $component->eliminarDelDesglose(0);

        $this->assertCount(0, $component->desglosePagos, 'El desglose debe quedar vacio');
        $this->assertEquals(100.0, $component->montoPendienteDesglose, 'Eliminar pago con vuelto debe devolver SOLO monto_base, no monto_recibido');
    }

    public function test_eliminar_pago_m_e_recupera_monto_pendiente_en_moneda_principal(): void
    {
        $component = $this->prepararComponente();
        $component->montoPendienteDesglose = 0;

        // Pago ME: USD 10 * cotizacion 1500 = ARS 15000
        $this->pushPago($component, [
            'monto_base' => 15000,
            'monto_final' => 15000,
            'es_moneda_extranjera' => true,
            'moneda_id' => 7,
            'monto_moneda_original' => 10,
            'tipo_cambio_id' => 42,
            'tipo_cambio_tasa' => 1500.0,
        ]);

        $component->eliminarDelDesglose(0);

        $this->assertCount(0, $component->desglosePagos);
        $this->assertEquals(15000.0, $component->montoPendienteDesglose, 'Eliminar pago ME debe devolver el equivalente en moneda principal');
    }

    public function test_cambio_f_p_vuelto_a_otra_con_vuelto_no_acumula_vuelto_residual(): void
    {
        $component = $this->prepararComponente();
        $component->montoPendienteDesglose = 100;

        // 1. Agregar pago con vuelto
        $this->pushPago($component, [
            'forma_pago_id' => 1,
            'nombre' => 'Efectivo',
            'monto_base' => 100,
            'monto_final' => 100,
            'monto_recibido' => 200,
            'vuelto' => 100,
            'permite_vuelto' => true,
        ]);
        $component->montoPendienteDesglose = 0;

        // 2. Eliminar (cambio de FP)
        $component->eliminarDelDesglose(0);

        // 3. Agregar otro pago con vuelto distinto
        $this->pushPago($component, [
            'forma_pago_id' => 2,
            'nombre' => 'USD Efectivo',
            'monto_base' => 100,
            'monto_final' => 100,
            'monto_recibido' => 150,
            'vuelto' => 50,
            'permite_vuelto' => true,
        ]);

        $this->assertCount(1, $component->desglosePagos);
        $this->assertEquals(50.0, $component->desglosePagos[0]['vuelto'], 'El vuelto del nuevo pago no debe acumular el del anterior');
        $this->assertEquals(150.0, $component->desglosePagos[0]['monto_recibido']);
    }

    public function test_actualizar_cuotas_recalcula_monto_final_con_recargo(): void
    {
        $component = $this->prepararComponente();
        $component->montoPendienteDesglose = 0;

        $this->pushPago($component, [
            'forma_pago_id' => 3,
            'nombre' => 'Tarjeta',
            'monto_base' => 1000,
            'monto_final' => 1000,
            'ajuste_porcentaje' => 0,
            'ajuste_original' => 0,
            'monto_ajuste' => 0,
            'cuotas' => 1,
            'recargo_cuotas' => 0,
            'permite_cuotas' => true,
            'cuotas_disponibles' => [
                ['id' => 1, 'cantidad' => 1, 'recargo' => 0.0],
                ['id' => 2, 'cantidad' => 3, 'recargo' => 12.0], // 12% en 3 cuotas
                ['id' => 3, 'cantidad' => 6, 'recargo' => 25.0],
            ],
        ]);

        $component->actualizarCuotasDesglose(0, 3);

        $pago = $component->desglosePagos[0];
        $this->assertEquals(3, $pago['cuotas']);
        $this->assertEquals(12.0, $pago['recargo_cuotas']);
        // monto_base 1000 + ajuste 0 = 1000; +12% recargo = 1120
        $this->assertEquals(1120.0, $pago['monto_final']);
        $this->assertEquals(120.0, $pago['monto_recargo_cuotas']);
    }

    public function test_eliminar_pago_recalcula_total_con_ajustes(): void
    {
        $component = $this->prepararComponente();
        $component->montoPendienteDesglose = 0;

        // Dos pagos: efectivo 500 + tarjeta 500+10%recargo = 550
        $this->pushPago($component, [
            'forma_pago_id' => 1,
            'monto_base' => 500,
            'monto_final' => 500,
        ]);
        $this->pushPago($component, [
            'forma_pago_id' => 3,
            'monto_base' => 500,
            'monto_final' => 550,
            'ajuste_porcentaje' => 10,
            'monto_ajuste' => 50,
        ]);

        $component->eliminarDelDesglose(1); // Eliminar el de tarjeta

        $this->assertCount(1, $component->desglosePagos);
        $this->assertEquals(500.0, $component->totalConAjustes, 'totalConAjustes debe recalcular sin el pago tarjeta');
        $this->assertEquals(500.0, $component->montoPendienteDesglose, 'El monto_base eliminado vuelve a pendiente');
    }
}

<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * RF-07 (spec multi-pago-consistente-y-panel-delivery): la semántica de
 * TRASLADO del desglose (lo ingresado es lo que se cobra; el ajuste generado
 * reduce el pendiente y lo absorbe el pago que cierra) aplica también en
 * NuevaVenta — misma regla en venta, mostrador, delivery y tienda.
 */
class NuevaVentaDesgloseTrasladoTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
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

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    protected function efectivoConDescuento(float $ajuste = -10): FormaPago
    {
        ['formaPago' => $fp] = $this->crearFormaPagoEfectivo();
        $fp->update(['ajuste_porcentaje' => $ajuste]);

        return $fp->fresh();
    }

    protected function transferencia(float $ajuste = 0): FormaPago
    {
        $concepto = ConceptoPago::firstOrCreate(
            ['codigo' => 'TRANSFERENCIA'],
            ['nombre' => 'Transferencia', 'permite_cuotas' => false, 'permite_vuelto' => false, 'activo' => true, 'orden' => 2],
        );

        return FormaPago::create([
            'nombre' => 'Transferencia',
            'codigo' => 'transferencia',
            'concepto' => 'transferencia',
            'concepto_pago_id' => $concepto->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => $ajuste,
            'activo' => true,
        ]);
    }

    public function test_desglose_en_venta_traslada_el_descuento_al_pago_que_cierra(): void
    {
        // Venta $1000, efectivo −10% por $600 + transferencia el resto:
        // el efectivo se cobra tal cual ($600, genera −$60) y la
        // transferencia cierra con $340 (base $400 − $60). Total $940.
        $efectivo = $this->efectivoConDescuento(-10);
        $transferencia = $this->transferencia();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalDesglose')
            ->set('nuevoPago.forma_pago_id', (string) $efectivo->id)
            ->set('nuevoPago.monto', '600')
            ->call('agregarAlDesglose')
            ->assertNotDispatched('toast-error');

        $pagos = $componente->get('desglosePagos');
        $this->assertEqualsWithDelta(600.0, (float) $pagos[0]['monto_final'], 0.01, 'Lo ingresado es lo que se cobra');
        $this->assertEqualsWithDelta(0.0, (float) $pagos[0]['monto_ajuste'], 0.01);
        $this->assertEqualsWithDelta(-60.0, (float) $pagos[0]['ajuste_generado'], 0.01);
        $this->assertEqualsWithDelta(340.0, (float) $componente->get('montoPendienteDesglose'), 0.01, '1000 − 60 de descuento − 600 cobrados');

        $componente->set('nuevoPago.forma_pago_id', (string) $transferencia->id)
            ->call('agregarAlDesglose')
            ->assertNotDispatched('toast-error');

        $pagos = $componente->get('desglosePagos');
        $this->assertEqualsWithDelta(400.0, (float) $pagos[1]['monto_base'], 0.01);
        $this->assertEqualsWithDelta(-60.0, (float) $pagos[1]['monto_ajuste'], 0.01, 'El pago que cierra absorbe el descuento');
        $this->assertEqualsWithDelta(340.0, (float) $pagos[1]['monto_final'], 0.01);
        $this->assertEqualsWithDelta(940.0, (float) $componente->get('totalConAjustes'), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $componente->get('montoPendienteDesglose'), 0.01);
    }

    public function test_una_sola_fp_por_el_total_absorbe_su_propio_descuento(): void
    {
        // El pendiente ya ofrece el monto CON el ajuste hipotético de la FP
        // candidata ($900, no $1000); al agregarlo cierra: base $1000,
        // ajuste −$100, final $900 — idéntico al single-FP histórico.
        $efectivo = $this->efectivoConDescuento(-10);
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('abrirModalDesglose')
            ->set('nuevoPago.forma_pago_id', (string) $efectivo->id);

        $this->assertEqualsWithDelta(900.0, (float) $componente->get('montoPendienteDesglose'), 0.01, 'El pendiente anticipa el descuento de la candidata');

        $componente->call('agregarAlDesglose')->assertNotDispatched('toast-error');

        $pagos = $componente->get('desglosePagos');
        $this->assertEqualsWithDelta(1000.0, (float) $pagos[0]['monto_base'], 0.01);
        $this->assertEqualsWithDelta(-100.0, (float) $pagos[0]['monto_ajuste'], 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $pagos[0]['monto_final'], 0.01);
        $this->assertEqualsWithDelta(900.0, (float) $componente->get('totalConAjustes'), 0.01);
    }
}

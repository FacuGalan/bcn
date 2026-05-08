<?php

namespace Tests\Integration\Livewire\Cajas;

use App\Livewire\Cajas\TurnoActual;
use App\Models\Caja;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\Venta;
use App\Models\VentaPago;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * PR E — Repaso 2 — Cierre de turno separa pagos que afectan caja de los que no.
 *
 * Verifica que `calcularDesglosesCaja()` particiona los VentaPagos según
 * `afecta_caja`: los que afectan suman al desglose principal y a `total_ingresos`;
 * los que no (canje puntos / FPs solo_sistema) van a un desglose paralelo
 * `internos` que se muestra como trazabilidad pero NO contamina el cobrado real.
 */
class CierreTurnoCanjePuntosTest extends TestCase
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

    public function test_split_separa_canje_puntos_del_desglose_principal(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);

        // FormaPago Efectivo (afecta caja)
        $fpData = $this->crearFormaPagoEfectivo();
        $fpEfectivo = $fpData['formaPago'];

        // FormaPago Canje Puntos (solo_sistema=true, afecta caja en VentaPago será false)
        $conceptoCanje = ConceptoPago::create([
            'codigo' => 'canje_puntos',
            'nombre' => 'Canje Puntos',
            'permite_cuotas' => false,
            'permite_vuelto' => false,
            'activo' => true,
            'orden' => 99,
        ]);
        $fpCanjePuntos = FormaPago::create([
            'nombre' => 'Canje Puntos',
            'codigo' => 'CANJE_PUNTOS',
            'concepto' => 'otro',
            'concepto_pago_id' => $conceptoCanje->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
            'solo_sistema' => true,
        ]);

        // Venta A: $1000 cobrados con Efectivo (afecta_caja=true)
        $ventaA = $this->crearVentaBasica([
            'caja_id' => $caja->id,
            'subtotal' => 1000,
            'total' => 1000,
            'total_final' => 1000,
        ]);
        VentaPago::create([
            'venta_id' => $ventaA->id,
            'forma_pago_id' => $fpEfectivo->id,
            'concepto_pago_id' => $fpData['concepto']->id,
            'monto_base' => 1000,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 1000,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        // Venta B: $500 con Canje Puntos (afecta_caja=false, es_pago_puntos=true)
        $ventaB = $this->crearVentaBasica([
            'caja_id' => $caja->id,
            'subtotal' => 500,
            'total' => 500,
            'total_final' => 500,
            'puntos_usados' => 500,
            'puntos_canjeados_pago' => 500,
        ]);
        VentaPago::create([
            'venta_id' => $ventaB->id,
            'forma_pago_id' => $fpCanjePuntos->id,
            'concepto_pago_id' => $conceptoCanje->id,
            'monto_base' => 500,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 500,
            'es_pago_puntos' => true,
            'puntos_usados' => 500,
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        // Llamar al método protected via reflexión
        $componente = new TurnoActual;
        $metodo = new ReflectionMethod($componente, 'calcularDesglosesCaja');
        $metodo->setAccessible(true);
        $desgloses = $metodo->invoke($componente, $caja->id);

        // formas_pago solo tiene Efectivo
        $this->assertArrayHasKey('Efectivo', $desgloses['formas_pago']);
        $this->assertEquals(1000.00, $desgloses['formas_pago']['Efectivo']);
        $this->assertArrayNotHasKey('Canje Puntos', $desgloses['formas_pago']);

        // internos tiene Canje Puntos
        $this->assertArrayHasKey('Canje Puntos', $desgloses['internos']);
        $this->assertEquals(500.00, $desgloses['internos']['Canje Puntos']);
        $this->assertArrayNotHasKey('Efectivo', $desgloses['internos']);

        // conceptos solo agrupa los que afectan caja
        $this->assertArrayHasKey('Efectivo', $desgloses['conceptos']);
        $this->assertEquals(1000.00, $desgloses['conceptos']['Efectivo']);
        $this->assertArrayNotHasKey('Canje Puntos', $desgloses['conceptos']);
    }

    public function test_split_sin_canje_devuelve_internos_vacio(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $fpData = $this->crearFormaPagoEfectivo();

        $venta = $this->crearVentaBasica([
            'caja_id' => $caja->id,
            'total_final' => 750,
        ]);
        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fpData['formaPago']->id,
            'concepto_pago_id' => $fpData['concepto']->id,
            'monto_base' => 750,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 750,
            'es_cuenta_corriente' => false,
            'afecta_caja' => true,
            'estado' => 'activo',
        ]);

        $componente = new TurnoActual;
        $metodo = new ReflectionMethod($componente, 'calcularDesglosesCaja');
        $metodo->setAccessible(true);
        $desgloses = $metodo->invoke($componente, $caja->id);

        $this->assertEquals(750.00, $desgloses['formas_pago']['Efectivo']);
        $this->assertEmpty($desgloses['internos']);
    }

    public function test_solo_canje_genera_formas_pago_vacio_y_internos_completo(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);

        $conceptoCanje = ConceptoPago::create([
            'codigo' => 'canje_puntos',
            'nombre' => 'Canje Puntos',
            'permite_cuotas' => false,
            'permite_vuelto' => false,
            'activo' => true,
            'orden' => 99,
        ]);
        $fpCanjePuntos = FormaPago::create([
            'nombre' => 'Canje Puntos',
            'codigo' => 'CANJE_PUNTOS',
            'concepto' => 'otro',
            'concepto_pago_id' => $conceptoCanje->id,
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
            'solo_sistema' => true,
        ]);

        $venta = $this->crearVentaBasica([
            'caja_id' => $caja->id,
            'total_final' => 300,
            'puntos_usados' => 300,
            'puntos_canjeados_pago' => 300,
        ]);
        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fpCanjePuntos->id,
            'concepto_pago_id' => $conceptoCanje->id,
            'monto_base' => 300,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => 300,
            'es_pago_puntos' => true,
            'puntos_usados' => 300,
            'afecta_caja' => false,
            'estado' => 'activo',
        ]);

        $componente = new TurnoActual;
        $metodo = new ReflectionMethod($componente, 'calcularDesglosesCaja');
        $metodo->setAccessible(true);
        $desgloses = $metodo->invoke($componente, $caja->id);

        $this->assertEmpty($desgloses['formas_pago']);
        $this->assertEmpty($desgloses['conceptos']);
        $this->assertEquals(300.00, $desgloses['internos']['Canje Puntos']);
    }
}

<?php

namespace Tests\Integration\Services;

use App\Models\MovimientoCaja;
use App\Models\MovimientoTesoreria;
use App\Models\ProvisionFondo;
use App\Models\Tesoreria;
use App\Services\TesoreriaService;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * PR M del Repaso 3: snapshot id+tasa de moneda en MovimientoTesoreria y ProvisionFondo.
 *
 * Verifica que cuando se opera con moneda extranjera, los movimientos de tesorería
 * y las provisiones de fondo persistan el snapshot (tipo_cambio_id + tipo_cambio_tasa)
 * para reconstruir conversiones históricas.
 */
class TesoreriaSnapshotMonedaTest extends TestCase
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
        // Limpiar saldos de monedas en tesorerias para no contaminar SmokeTesoreriaTest
        // que carga TesoreriaSaldoMoneda con eager-load 'moneda' y falla si la moneda
        // creada en este test no existe en otra ejecucion del fixture.
        \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('tesoreria_saldos_moneda')->delete();
        \Illuminate\Support\Facades\DB::connection('pymes_tenant')
            ->table('tesorerias')->where('nombre', 'Tesoreria Test')->delete();

        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Helper: crea o reutiliza una tesorería de prueba para la sucursal.
     */
    private function crearTesoreria(float $saldoInicial = 0): Tesoreria
    {
        return Tesoreria::firstOrCreate(
            ['sucursal_id' => $this->sucursalId, 'nombre' => 'Tesoreria Test'],
            ['saldo_actual' => $saldoInicial, 'activo' => true]
        );
    }

    public function test_ingreso_moneda_extranjera_persiste_snapshot_id_y_tasa(): void
    {
        $tesoreria = $this->crearTesoreria();

        $mov = $tesoreria->ingresoMonedaExtranjera(
            montoOriginal: 100,
            concepto: 'Ingreso USD',
            usuarioId: 1,
            monedaId: 7,
            referenciaTipo: null,
            referenciaId: null,
            observaciones: null,
            tipoCambioId: 42,
            tipoCambioTasa: 1500.000000,
        );

        $this->assertEquals(7, $mov->moneda_id);
        $this->assertEquals('100.00', $mov->monto_moneda_original);
        $this->assertEquals(42, $mov->tipo_cambio_id);
        $this->assertEquals('1500.000000', $mov->tipo_cambio_tasa);
    }

    public function test_egreso_moneda_extranjera_persiste_snapshot_id_y_tasa(): void
    {
        $tesoreria = $this->crearTesoreria();
        // Pre-poblar saldo USD para no quedar negativo (informativo, no valida)
        $tesoreria->ingresoMonedaExtranjera(500, 'Inicial', 1, 7, null, null, null, 42, 1500.0);

        $mov = $tesoreria->egresoMonedaExtranjera(
            montoOriginal: 100,
            concepto: 'Egreso USD',
            usuarioId: 1,
            monedaId: 7,
            referenciaTipo: null,
            referenciaId: null,
            observaciones: null,
            tipoCambioId: 50,
            tipoCambioTasa: 1550.500000,
        );

        $this->assertEquals(7, $mov->moneda_id);
        $this->assertEquals('100.00', $mov->monto_moneda_original);
        $this->assertEquals(50, $mov->tipo_cambio_id);
        $this->assertEquals('1550.500000', $mov->tipo_cambio_tasa);
    }

    public function test_ingreso_sin_parametros_de_tasa_deja_campos_null(): void
    {
        $tesoreria = $this->crearTesoreria();

        $mov = $tesoreria->ingresoMonedaExtranjera(
            montoOriginal: 100,
            concepto: 'Ingreso sin tasa',
            usuarioId: 1,
            monedaId: 7,
        );

        $this->assertNull($mov->tipo_cambio_id);
        $this->assertNull($mov->tipo_cambio_tasa);
    }

    public function test_provisionar_fondo_caja_en_me_propaga_snapshot_a_provision_y_mov_caja(): void
    {
        // Crear tipo de cambio real para que TesoreriaService::provisionarFondoCaja lo encuentre
        $monedaPrincipal = \App\Models\Moneda::firstOrCreate(
            ['codigo' => 'ARS'],
            ['nombre' => 'Peso Argentino', 'simbolo' => '$', 'es_principal' => true, 'activo' => true]
        );
        $usd = \App\Models\Moneda::firstOrCreate(
            ['codigo' => 'USD'],
            ['nombre' => 'Dolar', 'simbolo' => 'US$', 'es_principal' => false, 'activo' => true]
        );
        $tc = \App\Models\TipoCambio::create([
            'moneda_origen_id' => $usd->id,
            'moneda_destino_id' => $monedaPrincipal->id,
            'tasa_venta' => 1500.000000,
            'tasa_compra' => 1490.000000,
            'fecha' => now()->toDateString(),
            'usuario_id' => 1,
        ]);

        // Tesoreria con saldo USD suficiente para egresar
        $tesoreria = $this->crearTesoreria();
        $tesoreria->ingresoMonedaExtranjera(500, 'Inicial', 1, $usd->id, null, null, null, $tc->id, 1500.0);

        $caja = $this->crearCajaAbierta($this->sucursalId);

        $provision = TesoreriaService::provisionarFondo(
            tesoreria: $tesoreria,
            caja: $caja,
            monto: 1500, // ARS equivalente
            usuarioId: 1,
            observaciones: 'Test ME',
            monedaId: $usd->id,
            montoOriginal: 1.00,
        );

        // ProvisionFondo persiste snapshot
        $this->assertEquals($usd->id, $provision->moneda_id);
        $this->assertEquals($tc->id, $provision->tipo_cambio_id, 'ProvisionFondo debe persistir tipo_cambio_id');
        $this->assertEquals('1500.000000', $provision->tipo_cambio_tasa);
        $this->assertEquals('1.00', $provision->monto_moneda_original);

        // MovimientoCaja del ingreso persiste tasa (antes solo guardaba id)
        $movCaja = MovimientoCaja::find($provision->movimiento_caja_id);
        $this->assertEquals($tc->id, $movCaja->tipo_cambio_id);
        $this->assertEquals('1500.000000', $movCaja->tipo_cambio_tasa, 'MovimientoCaja debe persistir tasa');

        // MovimientoTesoreria del egreso ME también persiste snapshot
        $movTes = MovimientoTesoreria::find($provision->movimiento_tesoreria_id);
        $this->assertEquals($tc->id, $movTes->tipo_cambio_id);
        $this->assertEquals('1500.000000', $movTes->tipo_cambio_tasa);
    }
}

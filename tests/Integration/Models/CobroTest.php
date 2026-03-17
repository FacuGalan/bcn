<?php

namespace Tests\Integration\Models;

use App\Models\Cobro;
use App\Models\CobroVenta;
use App\Models\Venta;
use Tests\TestCase;
use Tests\Traits\WithTenant;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithCaja;
use Tests\Traits\WithVentaHelpers;

class CobroTest extends TestCase
{
    use WithTenant, WithSucursal, WithCaja, WithVentaHelpers;

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

    /**
     * Crea un Cobro con los datos indicados.
     */
    private function crearCobro(array $overrides = []): Cobro
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        return Cobro::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
            'cliente_id' => $cliente->id,
            'numero_recibo' => 'REC-' . uniqid(),
            'tipo' => 'cobro',
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
            'monto_cobrado' => 1000,
            'interes_aplicado' => 0,
            'descuento_aplicado' => 0,
            'monto_aplicado_a_deuda' => 1000,
            'monto_a_favor' => 0,
            'saldo_favor_usado' => 0,
            'estado' => 'activo',
            'usuario_id' => 1,
        ], $overrides));
    }

    /** @test */
    public function scope_activos(): void
    {
        $this->crearCobro(['estado' => 'activo']);
        $this->crearCobro(['estado' => 'activo']);
        $this->crearCobro(['estado' => 'anulado']);

        $activos = Cobro::activos()->count();

        $this->assertEquals(2, $activos);
    }

    /** @test */
    public function scope_cobros(): void
    {
        $this->crearCobro(['tipo' => 'cobro']);
        $this->crearCobro(['tipo' => 'cobro']);
        $this->crearCobro(['tipo' => 'anticipo']);

        $cobros = Cobro::cobros()->count();

        $this->assertEquals(2, $cobros);
    }

    /** @test */
    public function scope_anticipos(): void
    {
        $this->crearCobro(['tipo' => 'anticipo']);
        $this->crearCobro(['tipo' => 'cobro']);
        $this->crearCobro(['tipo' => 'cobro']);

        $anticipos = Cobro::anticipos()->count();

        $this->assertEquals(1, $anticipos);
    }

    /** @test */
    public function es_anticipo_true(): void
    {
        $cobro = $this->crearCobro(['tipo' => 'anticipo']);

        $this->assertTrue($cobro->esAnticipo());
    }

    /** @test */
    public function esta_anulado_true(): void
    {
        $cobro = $this->crearCobro(['estado' => 'anulado']);

        $this->assertTrue($cobro->estaAnulado());
    }

    /** @test */
    public function relacion_cobro_ventas(): void
    {
        $cobro = $this->crearCobro();
        $venta1 = $this->crearVentaBasica();
        $venta2 = $this->crearVentaBasica();

        CobroVenta::create([
            'cobro_id' => $cobro->id,
            'venta_id' => $venta1->id,
            'monto_aplicado' => 500,
            'interes_aplicado' => 0,
            'saldo_anterior' => 1000,
            'saldo_posterior' => 500,
        ]);

        CobroVenta::create([
            'cobro_id' => $cobro->id,
            'venta_id' => $venta2->id,
            'monto_aplicado' => 300,
            'interes_aplicado' => 0,
            'saldo_anterior' => 800,
            'saldo_posterior' => 500,
        ]);

        $cobro->load('cobroVentas');

        $this->assertCount(2, $cobro->cobroVentas);
        $this->assertEquals('500.00', $cobro->cobroVentas[0]->monto_aplicado);
        $this->assertEquals('300.00', $cobro->cobroVentas[1]->monto_aplicado);
    }
}

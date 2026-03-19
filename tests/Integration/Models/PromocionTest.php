<?php

namespace Tests\Integration\Models;

use App\Models\Promocion;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class PromocionTest extends TestCase
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

    /**
     * Helper: crea una promocion de prueba.
     */
    private function crearPromocion(array $overrides = []): Promocion
    {
        return Promocion::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Promo Test '.uniqid(),
            'tipo' => 'descuento_porcentaje',
            'valor' => 10,
            'prioridad' => 1,
            'combinable' => true,
            'activo' => true,
            'usos_actuales' => 0,
        ], $overrides));
    }

    public function test_calcular_ajuste_descuento_porcentaje(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'descuento_porcentaje',
            'valor' => 20,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        $this->assertEquals(20, $ajuste['porcentaje']);
        $this->assertEquals(200, $ajuste['valor']);
    }

    public function test_calcular_ajuste_descuento_monto(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'descuento_monto',
            'valor' => 500,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        $this->assertNull($ajuste['porcentaje']);
        $this->assertEquals(500, $ajuste['valor']);
    }

    public function test_calcular_ajuste_precio_fijo(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'precio_fijo',
            'valor' => 800,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        $this->assertNull($ajuste['porcentaje']);
        // valor = max(0, 1000 - 800) = 200
        $this->assertEquals(200, $ajuste['valor']);
    }

    public function test_calcular_ajuste_recargo_porcentaje(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'recargo_porcentaje',
            'valor' => 10,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('recargo', $ajuste['tipo']);
        $this->assertEquals(10, $ajuste['porcentaje']);
        $this->assertEquals(100, $ajuste['valor']);
    }

    public function test_calcular_ajuste_recargo_monto(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'recargo_monto',
            'valor' => 200,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('recargo', $ajuste['tipo']);
        $this->assertNull($ajuste['porcentaje']);
        $this->assertEquals(200, $ajuste['valor']);
    }

    public function test_calcular_ajuste_descuento_monto_no_supera_monto(): void
    {
        $promo = $this->crearPromocion([
            'tipo' => 'descuento_monto',
            'valor' => 1500,
        ]);

        $ajuste = $promo->calcularAjuste(1000);

        $this->assertEquals('descuento', $ajuste['tipo']);
        // min(1500, 1000) = 1000
        $this->assertEquals(1000, $ajuste['valor']);
    }

    public function test_vigencia_por_fecha_dentro_de_rango(): void
    {
        $promo = $this->crearPromocion([
            'vigencia_desde' => now()->subDay()->toDateString(),
            'vigencia_hasta' => now()->addDay()->toDateString(),
        ]);

        $this->assertTrue($promo->estaVigentePorFecha(now()));
    }

    public function test_vigencia_por_fecha_fuera_de_rango(): void
    {
        $promo = $this->crearPromocion([
            'vigencia_desde' => now()->subDays(5)->toDateString(),
            'vigencia_hasta' => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($promo->estaVigentePorFecha(now()));
    }

    public function test_aplica_en_dia_semana(): void
    {
        $promo = $this->crearPromocion([
            'dias_semana' => [1, 2, 3],
        ]);

        // Dia 2 (Martes) debe aplicar
        $this->assertTrue($promo->aplicaEnDiaSemana(2));

        // Dia 5 (Viernes) no debe aplicar
        $this->assertFalse($promo->aplicaEnDiaSemana(5));
    }

    public function test_aplica_en_horario(): void
    {
        $promo = $this->crearPromocion([
            'hora_desde' => '08:00:00',
            'hora_hasta' => '20:00:00',
        ]);

        // 10:00 dentro de rango
        $this->assertTrue($promo->aplicaEnHorario('10:00:00'));

        // 22:00 fuera de rango
        $this->assertFalse($promo->aplicaEnHorario('22:00:00'));
    }

    public function test_tiene_usos_disponibles(): void
    {
        $promo = $this->crearPromocion([
            'usos_maximos' => 5,
            'usos_actuales' => 3,
        ]);

        $this->assertTrue($promo->tieneUsosDisponibles());

        // Alcanzar el limite
        $promo->update(['usos_actuales' => 5]);
        $promo->refresh();

        $this->assertFalse($promo->tieneUsosDisponibles());
    }

    public function test_scope_vigentes_filtra_por_fecha(): void
    {
        // 2 promociones vigentes
        $this->crearPromocion([
            'vigencia_desde' => now()->subDay()->toDateString(),
            'vigencia_hasta' => now()->addDay()->toDateString(),
        ]);

        $this->crearPromocion([
            'vigencia_desde' => null,
            'vigencia_hasta' => null,
        ]);

        // 1 promocion expirada
        $this->crearPromocion([
            'vigencia_desde' => now()->subDays(10)->toDateString(),
            'vigencia_hasta' => now()->subDays(5)->toDateString(),
        ]);

        $vigentes = Promocion::where('sucursal_id', $this->sucursalId)
            ->vigentes(now())
            ->count();

        $this->assertEquals(2, $vigentes);
    }
}

<?php

namespace Tests\Integration\Models;

use App\Models\ConceptoPago;
use Tests\TestCase;
use Tests\Traits\WithTenant;

class ConceptoPagoTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_permite_integracion_se_castea_a_boolean(): void
    {
        $concepto = ConceptoPago::create([
            'codigo' => 'wallet_'.uniqid(),
            'nombre' => 'Wallet Test',
            'permite_integracion' => 1,
            'activo' => true,
            'orden' => 1,
        ]);

        $fresh = $concepto->fresh();

        $this->assertIsBool($fresh->permite_integracion);
        $this->assertTrue($fresh->permite_integracion);
    }

    public function test_permite_integracion_false_por_defecto(): void
    {
        $concepto = ConceptoPago::create([
            'codigo' => 'efectivo_'.uniqid(),
            'nombre' => 'Efectivo Test',
            'activo' => true,
            'orden' => 1,
        ]);

        $this->assertFalse($concepto->fresh()->permite_integracion);
    }
}

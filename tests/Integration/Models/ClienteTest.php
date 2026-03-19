<?php

namespace Tests\Integration\Models;

use App\Models\Cliente;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class ClienteTest extends TestCase
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

    public function test_obtener_saldo_en_sucursal(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Establecer saldo en el pivot
        DB::connection('pymes_tenant')->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['saldo_actual' => 5000]);

        $saldo = $cliente->obtenerSaldoEnSucursal($this->sucursalId);

        $this->assertEquals(5000, $saldo);
    }

    public function test_ajustar_saldo_positivo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Establecer saldo inicial
        DB::connection('pymes_tenant')->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['saldo_actual' => 1000]);

        $cliente->ajustarSaldoEnSucursal($this->sucursalId, 500);

        $saldoActualizado = $cliente->obtenerSaldoEnSucursal($this->sucursalId);
        $this->assertEquals(1500, $saldoActualizado);
    }

    public function test_ajustar_saldo_negativo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Establecer saldo inicial
        DB::connection('pymes_tenant')->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['saldo_actual' => 1000]);

        $cliente->ajustarSaldoEnSucursal($this->sucursalId, -300);

        $saldoActualizado = $cliente->obtenerSaldoEnSucursal($this->sucursalId);
        $this->assertEquals(700, $saldoActualizado);
    }

    public function test_ajustar_saldo_no_permite_negativo(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId);

        // Establecer saldo inicial
        DB::connection('pymes_tenant')->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['saldo_actual' => 100]);

        $cliente->ajustarSaldoEnSucursal($this->sucursalId, -500);

        $saldoActualizado = $cliente->obtenerSaldoEnSucursal($this->sucursalId);
        // max(0, 100 - 500) = 0
        $this->assertEquals(0, $saldoActualizado);
    }

    public function test_tiene_disponibilidad_credito_con_limite(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId, 10000);

        // Establecer saldo actual
        DB::connection('pymes_tenant')->table('clientes_sucursales')
            ->where('cliente_id', $cliente->id)
            ->where('sucursal_id', $this->sucursalId)
            ->update(['saldo_actual' => 3000]);

        // Disponible = 10000 - 3000 = 7000, pedir 5000 debe ser true
        $this->assertTrue($cliente->tieneDisponibilidadCredito(5000, $this->sucursalId));

        // Pedir 8000 debe ser false (excede disponibilidad de 7000)
        $this->assertFalse($cliente->tieneDisponibilidadCredito(8000, $this->sucursalId));
    }

    public function test_tiene_disponibilidad_credito_sin_limite(): void
    {
        // Limite 0 = sin limite = credito ilimitado
        $cliente = $this->crearClienteConCC($this->sucursalId, 0);

        // Con limite 0 en pivot y cliente, retorna null en obtenerLimiteCreditoEnSucursal
        // lo que significa credito ilimitado -> siempre true
        $this->assertTrue($cliente->tieneDisponibilidadCredito(999999, $this->sucursalId));
    }

    public function test_puede_operar_a_credito(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId, 10000, [
            'tiene_cuenta_corriente' => true,
            'bloqueado_por_mora' => false,
        ]);

        $this->assertTrue($cliente->puedeOperarACredito());
    }

    public function test_puede_operar_a_credito_bloqueado(): void
    {
        $cliente = $this->crearClienteConCC($this->sucursalId, 10000, [
            'tiene_cuenta_corriente' => true,
            'bloqueado_por_mora' => true,
        ]);

        $this->assertFalse($cliente->puedeOperarACredito());
    }
}

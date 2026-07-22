<?php

namespace Tests\Integration\Models;

use App\Models\FormaPago;
use App\Models\IntegracionPago;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class FormaPagoTest extends TestCase
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
     * Crea una FormaPago con los datos indicados.
     */
    private function crearFormaPago(array $overrides = []): FormaPago
    {
        return FormaPago::create(array_merge([
            'nombre' => 'Forma Pago Test '.uniqid(),
            'codigo' => 'test_'.uniqid(),
            'concepto' => 'efectivo',
            'es_mixta' => false,
            'permite_cuotas' => false,
            'ajuste_porcentaje' => 0,
            'activo' => true,
        ], $overrides));
    }

    public function test_calcular_ajuste_recargo(): void
    {
        $fp = $this->crearFormaPago(['ajuste_porcentaje' => 10]);

        $resultado = $fp->calcularAjuste(1000);

        $this->assertEquals('recargo', $resultado['tipo']);
        $this->assertEquals(10, $resultado['porcentaje']);
        $this->assertEquals(100, $resultado['monto']);
    }

    public function test_calcular_ajuste_descuento(): void
    {
        $fp = $this->crearFormaPago(['ajuste_porcentaje' => -5]);

        $resultado = $fp->calcularAjuste(1000);

        $this->assertEquals('descuento', $resultado['tipo']);
        $this->assertEquals(5, $resultado['porcentaje']);
        $this->assertEquals(50, $resultado['monto']);
    }

    public function test_calcular_ajuste_ninguno(): void
    {
        $fp = $this->crearFormaPago(['ajuste_porcentaje' => 0]);

        $resultado = $fp->calcularAjuste(1000);

        $this->assertEquals('ninguno', $resultado['tipo']);
        $this->assertEquals(0, $resultado['porcentaje']);
        $this->assertEquals(0, $resultado['monto']);
    }

    public function test_esta_habilitada_en_sucursal_true(): void
    {
        $fp = $this->crearFormaPago();

        // Insertar registro pivot con activo=true
        DB::connection('pymes_tenant')->table('formas_pago_sucursales')->insert([
            'forma_pago_id' => $fp->id,
            'sucursal_id' => $this->sucursalId,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($fp->estaHabilitadaEnSucursal($this->sucursalId));
    }

    public function test_esta_habilitada_en_sucursal_false(): void
    {
        $fp = $this->crearFormaPago();

        // Sin registro pivot
        $this->assertFalse($fp->estaHabilitadaEnSucursal($this->sucursalId));
    }

    public function test_es_efectivo_true(): void
    {
        $fp = $this->crearFormaPago(['concepto' => 'efectivo']);

        $this->assertTrue($fp->esEfectivo());
    }

    public function test_es_mixta_true(): void
    {
        $fp = $this->crearFormaPago(['es_mixta' => true]);

        $this->assertTrue($fp->esMixta());
    }

    public function test_scope_activas(): void
    {
        $this->crearFormaPago(['activo' => true]);
        $this->crearFormaPago(['activo' => true]);
        $this->crearFormaPago(['activo' => false]);

        $activas = FormaPago::activas()->count();

        $this->assertEquals(2, $activas);
    }

    public function test_relacion_integraciones_con_pivote(): void
    {
        $fp = $this->crearFormaPago(['concepto' => 'wallet']);
        $mp = IntegracionPago::create([
            'codigo' => 'mercadopago_qr',
            'nombre' => 'Mercado Pago',
            'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
            'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
            'activo' => true,
            'orden' => 1,
        ]);

        $fp->integraciones()->sync([$mp->id => [
            'modo_default' => 'qr_dinamico',
            'modos_permitidos' => json_encode(['qr_dinamico', 'qr_estatico']),
            'es_principal' => 1,
        ]]);

        $fp->load('integraciones');

        $this->assertCount(1, $fp->integraciones);
        $pivot = $fp->integraciones->first()->pivot;
        $this->assertSame('qr_dinamico', $pivot->modo_default);
        $this->assertSame(['qr_dinamico', 'qr_estatico'], json_decode($pivot->modos_permitidos, true));
        $this->assertTrue($fp->tieneIntegracion());
        $this->assertSame($mp->id, $fp->integracionPrincipal()->id);
    }

    public function test_es_declarable_en_tienda_respeta_disponible_en_tienda(): void
    {
        $fp = $this->crearFormaPago();
        $fp->sucursales()->attach($this->sucursalId, ['activo' => true]);

        $this->assertTrue($fp->esDeclarableEnTienda($this->sucursalId), 'Habilitada y disponible (default) es declarable');

        $fp->sucursales()->updateExistingPivot($this->sucursalId, ['disponible_en_tienda' => false]);

        $this->assertFalse($fp->esDeclarableEnTienda($this->sucursalId), 'Con disponible_en_tienda=false deja de ser declarable');

        $fp->sucursales()->updateExistingPivot($this->sucursalId, ['disponible_en_tienda' => true, 'activo' => false]);

        $this->assertFalse($fp->esDeclarableEnTienda($this->sucursalId), 'Disponible pero no habilitada en la sucursal tampoco es declarable');
    }
}

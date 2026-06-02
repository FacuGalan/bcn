<?php

namespace Tests\Feature\Livewire\Configuracion;

use App\Livewire\Configuracion\GestionarFormasPago;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 4 — asignación de integraciones de pago (N:M) a una FormaPago.
 */
class GestionarFormasPagoTest extends TestCase
{
    use WithSucursal, WithTenant;

    private int $walletId;

    private int $efectivoId;

    private int $mpId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        // Reset cache estático de SucursalService (ver SmokeConfiguracionTest).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            $p = $ref->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, null);
        }

        Livewire::withoutLazyLoading();

        $this->walletId = ConceptoPago::create([
            'codigo' => 'wallet', 'nombre' => 'Billetera Virtual',
            'permite_integracion' => true, 'activo' => true, 'orden' => 5,
        ])->id;

        $this->efectivoId = ConceptoPago::create([
            'codigo' => 'efectivo', 'nombre' => 'Efectivo',
            'permite_integracion' => false, 'permite_vuelto' => true, 'activo' => true, 'orden' => 1,
        ])->id;

        $this->mpId = IntegracionPago::create([
            'codigo' => 'mercadopago_qr', 'nombre' => 'Mercado Pago',
            'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
            'gateway_class' => 'App\\Services\\IntegracionesPago\\MercadoPagoGateway',
            'activo' => true, 'orden' => 1,
        ])->id;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_guardar_fp_simple_con_integracion_mp(): void
    {
        Livewire::test(GestionarFormasPago::class)
            ->call('crear')
            ->set('nombre', 'MP QR')
            ->set('es_mixta', false)
            ->set('concepto_pago_id', $this->walletId)
            ->set('integraciones_fp', [[
                'integracion_pago_id' => $this->mpId,
                'modo_default' => 'qr_estatico',
                'es_principal' => true,
            ]])
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $fp = FormaPago::where('nombre', 'MP QR')->first();
        $this->assertNotNull($fp);
        $this->assertTrue($fp->tieneIntegracion());

        $pivot = $fp->integraciones->first()->pivot;
        $this->assertSame('qr_estatico', $pivot->modo_default);
        // Un solo modo por integración: modos_permitidos espeja el modo elegido.
        $this->assertSame(['qr_estatico'], json_decode($pivot->modos_permitidos, true));
        $this->assertEquals(1, (int) $pivot->es_principal);
    }

    public function test_concepto_sin_permite_integracion_no_persiste_integraciones(): void
    {
        $component = Livewire::test(GestionarFormasPago::class)
            ->call('crear')
            ->set('nombre', 'Efectivo X')
            ->set('es_mixta', false)
            ->set('concepto_pago_id', $this->efectivoId)
            ->set('integraciones_fp', [[
                'integracion_pago_id' => $this->mpId,
                'modo_default' => 'qr_dinamico',
                'modos_permitidos' => ['qr_dinamico'],
                'es_principal' => true,
            ]]);

        $this->assertFalse($component->instance()->conceptoPermiteIntegracion());

        $component->call('guardar')->assertHasNoErrors();

        $fp = FormaPago::where('nombre', 'Efectivo X')->first();
        $this->assertNotNull($fp);
        $this->assertFalse($fp->tieneIntegracion());
    }

    public function test_no_permite_integracion_duplicada(): void
    {
        Livewire::test(GestionarFormasPago::class)
            ->call('crear')
            ->set('nombre', 'MP Dup')
            ->set('es_mixta', false)
            ->set('concepto_pago_id', $this->walletId)
            ->set('integraciones_fp', [
                ['integracion_pago_id' => $this->mpId, 'modo_default' => 'qr_dinamico', 'modos_permitidos' => ['qr_dinamico'], 'es_principal' => true],
                ['integracion_pago_id' => $this->mpId, 'modo_default' => 'qr_estatico', 'modos_permitidos' => ['qr_estatico'], 'es_principal' => false],
            ])
            ->call('guardar')
            ->assertHasErrors('integraciones_fp.1.integracion_pago_id');

        $this->assertNull(FormaPago::where('nombre', 'MP Dup')->first());
    }
}

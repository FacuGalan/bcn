<?php

namespace Tests\Feature\Livewire\Fiscal;

use App\Livewire\Fiscal\LibrosIva;
use App\Livewire\Fiscal\MovimientosFiscales;
use App\Livewire\Fiscal\PosicionFiscal;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Models\User;
use App\Services\Fiscal\ImpuestoService;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests del módulo Fiscal (Fase 7): que los componentes monten y
 * respondan a los cambios de filtro / export sin error.
 */
class SmokeFiscalTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        // is_system_admin=true bypasa el check de permisos del mount().
        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    protected function cuit(): Cuit
    {
        $cond = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'RI']);

        return Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Emisor SA', 'condicion_iva_id' => $cond->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
    }

    public function test_posicion_fiscal_monta(): void
    {
        Livewire::test(PosicionFiscal::class)->assertOk();
    }

    public function test_posicion_fiscal_monta_con_cuit_y_periodo(): void
    {
        $this->cuit();

        Livewire::test(PosicionFiscal::class)
            ->assertOk()
            ->assertSet('periodo', now()->format('Y-m'));
    }

    public function test_libros_iva_monta(): void
    {
        Livewire::test(LibrosIva::class)->assertOk();
    }

    public function test_libros_iva_cambia_de_tab(): void
    {
        $this->cuit();

        Livewire::test(LibrosIva::class)
            ->assertSet('tab', 'ventas')
            ->call('setTab', 'compras')
            ->assertSet('tab', 'compras')
            ->call('setTab', 'ventas')
            ->assertSet('tab', 'ventas');
    }

    protected function impuesto(): Impuesto
    {
        return Impuesto::firstOrCreate(
            ['codigo' => 'ret_iibb_ar_b'],
            [
                'nombre' => 'Retención IIBB Buenos Aires',
                'tipo' => Impuesto::TIPO_IIBB,
                'naturaleza_default' => MovimientoFiscal::NATURALEZA_RETENCION,
                'jurisdiccion' => 'AR-B',
                'es_sistema' => true,
                'activo' => true,
            ]
        );
    }

    public function test_movimientos_fiscales_monta(): void
    {
        Livewire::test(MovimientosFiscales::class)->assertOk();
    }

    public function test_movimientos_fiscales_alta_manual_registra(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto();

        Livewire::test(MovimientosFiscales::class)
            ->call('abrirModalAlta')
            ->assertSet('mostrarModalAlta', true)
            ->set('formCuitId', $cuit->id)
            ->set('formImpuestoId', $imp->id)
            ->set('formSentido', MovimientoFiscal::SENTIDO_SUFRIDO)
            ->set('formNaturaleza', MovimientoFiscal::NATURALEZA_RETENCION)
            ->set('formFecha', now()->format('Y-m-d'))
            ->set('formMonto', '150.75')
            ->call('registrarMovimiento')
            ->assertSet('mostrarModalAlta', false)
            ->assertHasNoErrors();

        $this->assertTrue(
            MovimientoFiscal::where('cuit_id', $cuit->id)
                ->where('impuesto_id', $imp->id)
                ->where('naturaleza', MovimientoFiscal::NATURALEZA_RETENCION)
                ->where('monto', 150.75)
                ->exists()
        );
    }

    public function test_movimientos_fiscales_alta_manual_valida_monto(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto();

        Livewire::test(MovimientosFiscales::class)
            ->call('abrirModalAlta')
            ->set('formCuitId', $cuit->id)
            ->set('formImpuestoId', $imp->id)
            ->set('formFecha', now()->format('Y-m-d'))
            ->set('formMonto', '0')
            ->call('registrarMovimiento')
            ->assertHasErrors('formMonto')
            ->assertSet('mostrarModalAlta', true);
    }

    public function test_movimientos_fiscales_anula_por_contraasiento(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto();

        $mov = app(ImpuestoService::class)->registrarMovimientoFiscal([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_SUFRIDO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_RETENCION,
            'fecha' => now()->format('Y-m-d'),
            'monto' => 200,
        ]);

        Livewire::test(MovimientosFiscales::class)
            ->call('abrirModalAnulacion', $mov->id)
            ->assertSet('mostrarModalAnulacion', true)
            ->set('motivoAnulacion', 'Cargado por error')
            ->call('confirmarAnulacion')
            ->assertSet('mostrarModalAnulacion', false)
            ->assertHasNoErrors();

        $this->assertSame(MovimientoFiscal::ESTADO_ANULADO, $mov->fresh()->estado);
        $this->assertTrue(
            MovimientoFiscal::where('movimiento_anulado_id', $mov->id)->exists()
        );
    }
}

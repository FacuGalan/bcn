<?php

namespace Tests\Feature\Livewire\Fiscal;

use App\Livewire\Fiscal\LibrosIva;
use App\Livewire\Fiscal\MovimientosFiscales;
use App\Livewire\Fiscal\PadronImport;
use App\Livewire\Fiscal\PosicionFiscal;
use App\Models\Cliente;
use App\Models\ClienteImpuestoConfig;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Models\User;
use App\Services\Fiscal\ImpuestoService;
use Illuminate\Http\UploadedFile;
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

    public function test_movimientos_fiscales_agrupa_impuestos_configurados(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto();

        CuitImpuestoConfig::create([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $imp->id,
            'inscripto' => true,
            'es_agente_percepcion' => false,
            'alicuota' => 1.5,
            'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
        ]);

        Livewire::test(MovimientosFiscales::class)
            ->call('abrirModalAlta')
            ->set('formCuitId', $cuit->id)
            ->assertSee(__('Configurados para este CUIT'))
            ->assertSee(__('Otros impuestos del catálogo'));
    }

    public function test_padron_import_monta(): void
    {
        Livewire::test(PadronImport::class)
            ->assertOk()
            ->assertSet('agencia', 'arba');
    }

    public function test_padron_import_actualiza_cliente_que_matchea(): void
    {
        $cond = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'RI']);
        $cliente = Cliente::create([
            'nombre' => 'Cliente padrón',
            'cuit' => '20123456789',
            'condicion_iva_id' => $cond->id,
            'activo' => true,
        ]);
        $imp = Impuesto::firstOrCreate(
            ['codigo' => 'perc_iibb_ar_b'],
            [
                'nombre' => 'Percepción IIBB Buenos Aires',
                'tipo' => Impuesto::TIPO_IIBB,
                'naturaleza_default' => MovimientoFiscal::NATURALEZA_PERCEPCION,
                'jurisdiccion' => 'AR-B',
                'es_sistema' => true,
                'activo' => true,
            ]
        );

        $contenido = "P;01062026;01062026;30062026;20123456789;D;S;N;1,50;00;\n";

        // El padrón se sube comprimido (.zip), como en producción.
        $base = tempnam(sys_get_temp_dir(), 'padron');
        $zipPath = $base.'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('PadronRGSPer062026.txt', $contenido);
        $zip->close();
        $zipBinario = file_get_contents($zipPath);
        @unlink($zipPath);
        @unlink($base);

        Livewire::test(PadronImport::class)
            ->set('agencia', 'arba')
            ->set('archivo', UploadedFile::fake()->createWithContent('PadronRGSPer062026.zip', $zipBinario))
            ->call('importar')
            ->assertHasNoErrors();

        $this->assertSame(
            ClienteImpuestoConfig::ORIGEN_PADRON,
            ClienteImpuestoConfig::where('cliente_id', $cliente->id)->where('impuesto_id', $imp->id)->value('origen_alicuota')
        );
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

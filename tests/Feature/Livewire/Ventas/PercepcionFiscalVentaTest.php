<?php

namespace Tests\Feature\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\Cliente;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitDomicilio;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\PuntoVenta;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Reactividad de la percepción fiscal aplicada (Fase 5b) en NuevaVenta: cuando el
 * punto de venta de la caja pertenece a un CUIT agente de percepción IIBB y se
 * factura a un cliente Responsable Inscripto, la percepción debe calcularse sola
 * al tildar el checkbox de factura. Si no hay cliente (consumidor final), no se
 * percibe. Valida el cableado component → calcularTributosFiscales → ImpuestoService
 * (el motor en sí ya está cubierto por ImpuestoServiceTest).
 */
class PercepcionFiscalVentaTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
        $this->crearFormaPagoEfectivo();

        $user = User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        $this->actingAs($user);
        session([
            'comercio_activo_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
            'caja_id' => $this->cajaId,
        ]);

        // Bypass del cache de SucursalService (mismo patrón que SmokeVentasTest).
        $ref = new \ReflectionClass(\App\Services\SucursalService::class);
        foreach (['sucursalesCache', 'sucursalActivaCache', 'esMultiSucursalCache'] as $prop) {
            if ($ref->hasProperty($prop)) {
                $p = $ref->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
            }
        }
        $p = $ref->getProperty('sucursalIdsCache');
        $p->setAccessible(true);
        $p->setValue(null, [0]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function condicionRI(): CondicionIva
    {
        return CondicionIva::firstOrCreate(
            ['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO],
            ['nombre' => 'Responsable Inscripto']
        );
    }

    /**
     * Configura el PV por defecto de la caja como agente de percepción IIBB AR-B 3%.
     */
    private function configurarAgentePercepcion(): void
    {
        $cuit = Cuit::create([
            'numero_cuit' => (string) random_int(20000000000, 29999999999),
            'razon_social' => 'Emisor Test',
            'condicion_iva_id' => $this->condicionRI()->id,
            'entorno_afip' => 'testing',
            'activo' => true,
        ]);

        $domicilio = CuitDomicilio::create([
            'cuit_id' => $cuit->id,
            'tipo' => 'fiscal',
            'provincia' => 'AR-B',
            'direccion' => 'Calle Falsa 123',
            'es_principal' => true,
            'activo' => true,
        ]);

        $pv = PuntoVenta::create([
            'cuit_id' => $cuit->id,
            'cuit_domicilio_id' => $domicilio->id,
            'numero' => 1,
            'nombre' => 'PV Test',
            'activo' => true,
        ]);

        DB::connection('pymes_tenant')->table('punto_venta_caja')->insert([
            'punto_venta_id' => $pv->id,
            'caja_id' => $this->cajaId,
            'es_defecto' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $impuesto = Impuesto::create([
            'codigo' => 'perc_iibb_ar_b_'.uniqid(),
            'nombre' => 'Percepción IIBB AR-B',
            'tipo' => Impuesto::TIPO_IIBB,
            'naturaleza_default' => 'percepcion',
            'jurisdiccion' => 'AR-B',
            'codigo_arca' => 7,
            'es_sistema' => true,
            'activo' => true,
        ]);

        CuitImpuestoConfig::create([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $impuesto->id,
            'inscripto' => true,
            'es_agente_percepcion' => true,
            'alicuota' => 3.0,
            'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
        ]);
    }

    private function crearClienteRI(): Cliente
    {
        $cliente = Cliente::create([
            'nombre' => 'Cliente RI '.uniqid(),
            'activo' => true,
            'condicion_iva_id' => $this->condicionRI()->id,
        ]);

        DB::connection('pymes_tenant')->table('clientes_sucursales')->insert([
            'cliente_id' => $cliente->id,
            'sucursal_id' => $this->sucursalId,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $cliente;
    }

    public function test_percepcion_se_calcula_al_facturar_a_cliente_ri(): void
    {
        $this->configurarAgentePercepcion();
        $cliente = $this->crearClienteRI();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('seleccionarCliente', $cliente->id)
            ->set('emitirFacturaFiscal', true);

        $this->assertGreaterThan(0, (float) $componente->get('percepcionMonto'));

        $tributos = $componente->get('percepcionTributos');
        $this->assertNotEmpty($tributos);
        $this->assertSame(7, $tributos[0]['codigo_arca']);
        $this->assertSame('AR-B', $tributos[0]['jurisdiccion']);
        $this->assertSame(3.0, (float) $tributos[0]['alicuota']);
    }

    public function test_sin_cliente_consumidor_final_no_percibe(): void
    {
        $this->configurarAgentePercepcion();
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->call('seleccionarArticulo', $articulo->id)
            ->set('emitirFacturaFiscal', true);

        $this->assertSame(0.0, round((float) $componente->get('percepcionMonto'), 2));
        $this->assertEmpty($componente->get('percepcionTributos'));
    }
}

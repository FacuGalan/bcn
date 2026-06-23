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
    private function configurarAgentePercepcion(float $alicuota = 3.0): void
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
            // El agente percibe a RI aunque el cliente no tenga perfil fiscal
            // cargado (D7, Fase 10); si no, un RI sin config no se percibe.
            'percibir_no_empadronados' => true,
            'alicuota' => $alicuota,
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

    /**
     * Reproductor del bug 10051: la percepción NO debe inflar el neto gravado del
     * desglose fiscal (la base de la percepción son los bienes, no un valor que ya
     * la incluye). Antes: neto = 826.45 / (1 - 0.10) = 918.28 (punto fijo de la
     * recursión) y el IVA quedaba deformado → AlicIVA inválido para AFIP.
     */
    public function test_la_percepcion_no_infla_el_neto_gravado_del_desglose(): void
    {
        $this->configurarAgentePercepcion(10.0); // 10% IIBB AR-B
        $cliente = $this->crearClienteRI();
        // Artículo $1000 IVA 21% incluido → neto 826.45, IVA 173.55.
        $articulo = $this->crearArticuloConStock($this->sucursalId, cantidad: 50);

        $componente = Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->call('seleccionarArticulo', $articulo->id)
            ->call('seleccionarCliente', $cliente->id)
            ->set('emitirFacturaFiscal', true);

        $desglose = $componente->get('desgloseIvaFiscal');
        $netoGravado = (float) ($desglose['total_neto'] ?? 0);

        // El neto gravado debe ser el de los BIENES (826.45), nunca inflado por la percepción.
        $this->assertEqualsWithDelta(826.45, $netoGravado, 0.02, 'El neto gravado se infló con la percepción');

        // La percepción es 10% del neto de bienes (no de un neto inflado).
        $this->assertEqualsWithDelta(82.65, (float) $componente->get('percepcionMonto'), 0.02);

        // Invariante AFIP: en cada alícuota, IVA == neto × % (lo que valida 10051).
        foreach ($desglose['por_alicuota'] ?? [] as $ali) {
            $esperado = round($ali['neto'] * $ali['alicuota'] / 100, 2);
            $this->assertEqualsWithDelta($esperado, (float) $ali['iva'], 0.02,
                "AlicIVA inconsistente: neto {$ali['neto']} × {$ali['alicuota']}% != IVA {$ali['iva']}");
        }

        // Recursión: recalcular varias veces (simula los ciclos reactivos del modal
        // de cobro/vuelto). El neto y la percepción NO deben crecer cada vuelta.
        for ($i = 0; $i < 5; $i++) {
            $componente->call('calcularMontoFacturaFiscal');
        }

        $desglose2 = $componente->get('desgloseIvaFiscal');
        $this->assertEqualsWithDelta(826.45, (float) ($desglose2['total_neto'] ?? 0), 0.02,
            'El neto gravado se infló tras recálculos reactivos repetidos (recursión)');
        $this->assertEqualsWithDelta(82.65, (float) $componente->get('percepcionMonto'), 0.02,
            'La percepción se infló tras recálculos repetidos');
    }

    /**
     * Regresión del bug AFIP 10051 (datos reales del log 2026-06-19): una factura
     * fiscal con DESCUENTO por forma de pago debe armar el AlicIVA con el neto YA
     * ajustado (neto_con_ajuste_fp), no con el neto pelado. Antes:
     * formatearDesgloseParaAFIP tomaba neto=538.48 mientras montoFacturaFiscal=586.40
     * → el residuo deformaba el IVA a 47.92 (≠ 538.48×21%) → AFIP 10051.
     */
    public function test_desglose_fiscal_usa_el_neto_con_ajuste_de_forma_de_pago(): void
    {
        // resultado tal como lo deja recalcularTotales con un descuento FP del 10%
        // (réplica exacta del log que rechazó AFIP).
        $resultado = [
            'total_final' => 651.56,
            'subtotal' => 685.85,
            'total_descuentos' => 34.29,
            'items' => [],
            'articulos_canjeados_monto' => 0,
            'promociones_comunes_aplicadas' => [],
            'promociones_especiales_aplicadas' => [],
            'desglose_iva' => [
                'por_alicuota' => [[
                    'codigo' => 5,
                    'nombre' => 'IVA 21%',
                    'porcentaje' => 21,
                    'neto' => 538.479,
                    'iva' => 113.081,
                    'subtotal' => 651.56,
                    'neto_con_ajuste_fp' => 484.628,
                    'iva_con_ajuste_fp' => 101.772,
                    'subtotal_con_ajuste_fp' => 586.40,
                ]],
                'total_neto' => 538.479,
                'total_iva' => 113.081,
                'total' => 651.56,
                'descuento_aplicado' => 34.29,
                'ajuste_forma_pago' => -65.16,
                'recargo_cuotas' => 0,
                'total_neto_con_ajuste_fp' => 484.628,
                'total_iva_con_ajuste_fp' => 101.772,
                'total_con_ajuste_fp' => 586.40,
            ],
        ];

        $componente = Livewire::test(NuevaVenta::class)
            ->set('cajaSeleccionada', $this->cajaId)
            ->set('resultado', $resultado)
            ->set('emitirFacturaFiscal', true)
            ->call('calcularMontoFacturaFiscal');

        $desglose = $componente->get('desgloseIvaFiscal');

        // El neto del desglose fiscal debe ser el de bienes CON el descuento FP (484.63).
        $this->assertEqualsWithDelta(484.63, (float) ($desglose['total_neto'] ?? 0), 0.02);

        // Invariante AFIP 10051: en cada alícuota IVA == neto × % (lo que antes fallaba).
        foreach ($desglose['por_alicuota'] ?? [] as $ali) {
            $esperado = round($ali['neto'] * $ali['alicuota'] / 100, 2);
            $this->assertEqualsWithDelta($esperado, (float) $ali['iva'], 0.02,
                "AlicIVA inconsistente: neto {$ali['neto']} × {$ali['alicuota']}% != IVA {$ali['iva']}");
        }
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

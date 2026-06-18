<?php

namespace Tests\Unit\Services\Fiscal;

use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Models\Sucursal;
use App\Services\Fiscal\PosicionFiscalService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests de la posición fiscal y libros de IVA (Fase 7, RF-09).
 *
 * Verifica la semántica de signos de la posición de IVA (débito − crédito −
 * percep/ret sufridas), el agrupamiento de IIBB por jurisdicción, y que el
 * libro de ventas tome solo comprobantes autorizados del CUIT/período.
 *
 * Ref: .claude/specs/sistema-impositivo.md (Fase 7).
 */
class PosicionFiscalServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected PosicionFiscalService $service;

    protected string $periodo = '2026-06';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        // comprobantes_fiscales / puntos_venta no están en el cleanup de WithTenant
        // y tienen UNIQUE por (punto_venta, tipo, número): aislamos el test.
        DB::connection('pymes_tenant')->table('comprobantes_fiscales')->delete();
        DB::connection('pymes_tenant')->table('puntos_venta')->delete();

        $this->service = new PosicionFiscalService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== Helpers ====================

    protected function cuit(): Cuit
    {
        $cond = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'RI']);

        return Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            ['razon_social' => 'Emisor SA', 'condicion_iva_id' => $cond->id, 'entorno_afip' => 'testing', 'activo' => true]
        );
    }

    protected function impuesto(string $codigo, string $tipo, string $naturaleza, ?string $jurisdiccion = null): Impuesto
    {
        return Impuesto::firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => $codigo, 'tipo' => $tipo, 'naturaleza_default' => $naturaleza, 'jurisdiccion' => $jurisdiccion, 'es_sistema' => true, 'activo' => true]
        );
    }

    protected function mov(Cuit $cuit, Impuesto $imp, string $sentido, string $naturaleza, float $monto, array $extra = []): MovimientoFiscal
    {
        $fecha = $extra['fecha'] ?? '2026-06-15';

        return MovimientoFiscal::create(array_merge([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $imp->id,
            'sentido' => $sentido,
            'naturaleza' => $naturaleza,
            'fecha' => $fecha,
            'periodo_fiscal' => substr($fecha, 0, 7),
            'monto' => $monto,
            'estado' => MovimientoFiscal::ESTADO_ACTIVO,
        ], $extra));
    }

    protected function comprobante(Cuit $cuit, array $extra = []): int
    {
        $puntoVentaId = DB::connection('pymes_tenant')->table('puntos_venta')->where('cuit_id', $cuit->id)->value('id')
            ?? DB::connection('pymes_tenant')->table('puntos_venta')->insertGetId([
                'cuit_id' => $cuit->id,
                'numero' => 1,
                'nombre' => 'PV Test',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return DB::connection('pymes_tenant')->table('comprobantes_fiscales')->insertGetId(array_merge([
            'sucursal_id' => $this->sucursalId,
            'cuit_id' => $cuit->id,
            'punto_venta_id' => $puntoVentaId,
            'tipo' => 'factura_a',
            'letra' => 'A',
            'punto_venta_numero' => 1,
            'numero_comprobante' => 1,
            'condicion_iva_id' => $cuit->condicion_iva_id,
            'fecha_emision' => '2026-06-10',
            'receptor_nombre' => 'Cliente RI',
            'receptor_documento_numero' => '0',
            'usuario_id' => 1,
            'neto_gravado' => 1000,
            'neto_no_gravado' => 0,
            'neto_exento' => 0,
            'iva_total' => 210,
            'tributos' => 0,
            'total' => 1210,
            'moneda' => 'ARS',
            'cotizacion' => 1,
            'estado' => 'autorizado',
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    // ==================== posicionIva ====================

    public function test_posicion_iva_aplica_la_semantica_de_signos(): void
    {
        $cuit = $this->cuit();
        $ivaDeb = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');
        $ivaCred = $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $percIva = $this->impuesto('perc_iva', Impuesto::TIPO_IVA, 'percepcion', 'AR');
        $retIva = $this->impuesto('ret_iva', Impuesto::TIPO_IVA, 'retencion', 'AR');

        $this->mov($cuit, $ivaDeb, MovimientoFiscal::SENTIDO_APLICADO, 'debito_fiscal', 1000);
        $this->mov($cuit, $ivaCred, MovimientoFiscal::SENTIDO_SUFRIDO, 'credito_fiscal', 300);
        $this->mov($cuit, $percIva, MovimientoFiscal::SENTIDO_SUFRIDO, 'percepcion', 50);
        $this->mov($cuit, $retIva, MovimientoFiscal::SENTIDO_SUFRIDO, 'retencion', 20);
        // Como agente (deuda a depositar): NO integra la posición.
        $this->mov($cuit, $percIva, MovimientoFiscal::SENTIDO_APLICADO, 'percepcion', 15);
        // Anulado: ignorado.
        $this->mov($cuit, $ivaDeb, MovimientoFiscal::SENTIDO_APLICADO, 'debito_fiscal', 500, ['estado' => MovimientoFiscal::ESTADO_ANULADO]);
        // Otro período: ignorado.
        $this->mov($cuit, $ivaDeb, MovimientoFiscal::SENTIDO_APLICADO, 'debito_fiscal', 777, ['fecha' => '2026-05-15']);

        $pos = $this->service->posicionIva($cuit, $this->periodo);

        $this->assertEquals(1000, $pos['debito_fiscal']);
        $this->assertEquals(300, $pos['credito_fiscal']);
        $this->assertEquals(700, $pos['saldo_tecnico']);
        $this->assertEquals(50, $pos['percepciones_iva_sufridas']);
        $this->assertEquals(20, $pos['retenciones_iva_sufridas']);
        $this->assertEquals(70, $pos['a_cuenta']);
        $this->assertEquals(630, $pos['saldo']);
        $this->assertEquals(630, $pos['a_pagar']);
        $this->assertEquals(0, $pos['saldo_a_favor']);
        $this->assertEquals(15, $pos['percepciones_iva_aplicadas']);
    }

    public function test_posicion_iva_saldo_a_favor_cuando_credito_supera_debito(): void
    {
        $cuit = $this->cuit();
        $ivaDeb = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');
        $ivaCred = $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');

        $this->mov($cuit, $ivaDeb, MovimientoFiscal::SENTIDO_APLICADO, 'debito_fiscal', 100);
        $this->mov($cuit, $ivaCred, MovimientoFiscal::SENTIDO_SUFRIDO, 'credito_fiscal', 400);

        $pos = $this->service->posicionIva($cuit, $this->periodo);

        $this->assertEquals(-300, $pos['saldo']);
        $this->assertEquals(0, $pos['a_pagar']);
        $this->assertEquals(300, $pos['saldo_a_favor']);
    }

    // ==================== posicionIibb ====================

    public function test_posicion_iibb_agrupa_por_jurisdiccion(): void
    {
        $cuit = $this->cuit();
        $percB = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $retC = $this->impuesto('ret_iibb_ar_c', Impuesto::TIPO_IIBB, 'retencion', 'AR-C');

        $this->mov($cuit, $percB, MovimientoFiscal::SENTIDO_SUFRIDO, 'percepcion', 40);
        $this->mov($cuit, $retC, MovimientoFiscal::SENTIDO_SUFRIDO, 'retencion', 10);
        $this->mov($cuit, $percB, MovimientoFiscal::SENTIDO_APLICADO, 'percepcion', 5);

        // Base imponible de ventas en AR-B (provincia de la sucursal del comprobante).
        Sucursal::find($this->sucursalId)->update(['provincia' => 'AR-B']);
        $this->comprobante($cuit, ['neto_gravado' => 2000, 'total' => 2420, 'iva_total' => 420]);

        $iibb = $this->service->posicionIibb($cuit, $this->periodo);

        $porIso = collect($iibb)->keyBy('jurisdiccion');

        $this->assertEquals(40, $porIso['AR-B']['percepciones_sufridas']);
        $this->assertEquals(40, $porIso['AR-B']['a_cuenta']);
        $this->assertEquals(5, $porIso['AR-B']['percepciones_aplicadas']);
        $this->assertEquals(2000, $porIso['AR-B']['base_imponible']);

        $this->assertEquals(10, $porIso['AR-C']['retenciones_sufridas']);
        $this->assertEquals(10, $porIso['AR-C']['a_cuenta']);
    }

    public function test_posicion_iibb_usa_la_jurisdiccion_del_domicilio_del_pv(): void
    {
        // RF-11, Fase 9: la jurisdicción de la base imponible sale del domicilio
        // fiscal del PV, NO de la sucursal física.
        $cuit = $this->cuit();

        // Sucursal física en AR-C, pero el domicilio fiscal del PV está en AR-B.
        Sucursal::find($this->sucursalId)->update(['provincia' => 'AR-C']);

        $compId = $this->comprobante($cuit, ['neto_gravado' => 3000, 'total' => 3630, 'iva_total' => 630]);
        $comp = \App\Models\ComprobanteFiscal::find($compId);

        $dom = \App\Models\CuitDomicilio::create([
            'cuit_id' => $cuit->id, 'tipo' => 'fiscal', 'provincia' => 'AR-B',
            'direccion' => 'Dom B', 'es_principal' => true, 'activo' => true,
        ]);
        \App\Models\PuntoVenta::where('id', $comp->punto_venta_id)->update(['cuit_domicilio_id' => $dom->id]);

        $porIso = collect($this->service->posicionIibb($cuit, $this->periodo))->keyBy('jurisdiccion');

        // La base cae en AR-B (domicilio del PV), no en AR-C (sucursal física).
        $this->assertEquals(3000, $porIso['AR-B']['base_imponible'] ?? 0);
        $this->assertArrayNotHasKey('AR-C', $porIso->all());
    }

    // ==================== libros ====================

    public function test_libro_iva_ventas_toma_solo_comprobantes_autorizados_del_periodo(): void
    {
        $cuit = $this->cuit();

        $this->comprobante($cuit, ['numero_comprobante' => 1]);
        $this->comprobante($cuit, ['numero_comprobante' => 2]);
        // Pendiente: no entra.
        $this->comprobante($cuit, ['numero_comprobante' => 3, 'estado' => 'pendiente']);
        // Otro período: no entra.
        $this->comprobante($cuit, ['numero_comprobante' => 4, 'fecha_emision' => '2026-05-10']);

        $libro = $this->service->libroIvaVentas($cuit, $this->periodo);

        $this->assertCount(2, $libro);

        $totales = $this->service->totalesLibroVentas($libro);
        $this->assertEquals(2000, $totales['neto_gravado']);
        $this->assertEquals(420, $totales['iva']);
    }

    public function test_libro_iva_compras_agrupa_movimientos_por_compra(): void
    {
        $cuit = $this->cuit();
        $ivaCred = $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $percIibb = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');

        $this->mov($cuit, $ivaCred, MovimientoFiscal::SENTIDO_SUFRIDO, 'credito_fiscal', 200, ['origen_tipo' => 'Compra', 'origen_id' => 1]);
        $this->mov($cuit, $percIibb, MovimientoFiscal::SENTIDO_SUFRIDO, 'percepcion', 30, ['origen_tipo' => 'Compra', 'origen_id' => 1]);

        $libro = $this->service->libroIvaCompras($cuit, $this->periodo);

        $this->assertCount(1, $libro);
        $this->assertEquals(1, $libro[0]['origen_id']);
        $this->assertEquals(200, $libro[0]['credito_fiscal']);
        $this->assertEquals(30, $libro[0]['percepciones']);
    }
}

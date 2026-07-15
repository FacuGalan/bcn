<?php

namespace Tests\Unit\Services;

use App\Models\Cliente;
use App\Models\ComprobanteFiscal;
use App\Models\ComprobanteFiscalTributo;
use App\Models\ComprobanteFiscalVenta;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitDomicilio;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\PuntoVenta;
use App\Models\Venta;
use App\Services\ARCA\ComprobanteFiscalService;
use App\Services\Ventas\CambioFormaPagoService;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * RF-V2 + RF-V6 (hardening fiscal saliente, tanda 2).
 *
 * RF-V2: una FC RE-emitida (cambio de forma de pago / reintento de facturación)
 * debe conservar los tributos que la venta cobró — antes salía con ImpTrib=0 y
 * calcularDesgloseIvaProporcional repartía la percepción como si fuera bienes
 * (neto/IVA inflados, débito fiscal de más). Se cubren las dos fuentes:
 * snapshot del comprobante original y recálculo vía ImpuestoService.
 *
 * RF-V6: monto_fiscal_cache refleja lo REALMENTE facturado (saldo fiscal de las
 * facturas autorizadas netas de NC), no total_final incondicional.
 *
 * Los métodos son privados: se invocan por reflection (no hay AFIP en tests;
 * la emisión completa se valida en homologación).
 */
class CambioFormaPagoReemisionTest extends TestCase
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

    private function invocar(string $metodo, ...$args)
    {
        $ref = new ReflectionMethod(CambioFormaPagoService::class, $metodo);
        $ref->setAccessible(true);

        return $ref->invoke(new CambioFormaPagoService, ...$args);
    }

    private function condicionRI(): CondicionIva
    {
        return CondicionIva::firstOrCreate(
            ['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO],
            ['nombre' => 'Responsable Inscripto']
        );
    }

    /** Crea CUIT + PV vinculado a una caja (es_defecto) y devuelve ambos. */
    private function crearPuntoVentaParaCaja(int $cajaId): PuntoVenta
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
            'caja_id' => $cajaId,
            'es_defecto' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $pv;
    }

    private function crearImpuestoPercepcion(): Impuesto
    {
        return Impuesto::create([
            'codigo' => 'perc_iibb_ar_b_'.uniqid(),
            'nombre' => 'Percepción IIBB AR-B',
            'tipo' => Impuesto::TIPO_IIBB,
            'naturaleza_default' => 'percepcion',
            'jurisdiccion' => 'AR-B',
            'codigo_arca' => 7,
            'es_sistema' => true,
            'activo' => true,
        ]);
    }

    /**
     * Crea una factura B autorizada asociada a la venta, opcionalmente con un
     * tributo de percepción (base 826.45, 3% = 24.79).
     */
    private function crearFacturaAutorizada(Venta $venta, PuntoVenta $pv, float $total, array $overrides = [], ?Impuesto $impuesto = null): ComprobanteFiscal
    {
        $comprobante = ComprobanteFiscal::create(array_merge([
            'sucursal_id' => $this->sucursalId,
            'punto_venta_id' => $pv->id,
            'cuit_id' => $pv->cuit_id,
            'tipo' => 'factura_b',
            'letra' => 'B',
            'punto_venta_numero' => $pv->numero,
            'numero_comprobante' => random_int(1, 99999),
            'fecha_emision' => now()->toDateString(),
            'condicion_iva_id' => $this->condicionRI()->id,
            'receptor_nombre' => 'CONSUMIDOR FINAL',
            'receptor_documento_tipo' => '99',
            'receptor_documento_numero' => '0',
            'neto_gravado' => round(($total - ($overrides['tributos'] ?? 0)) / 1.21, 2),
            'neto_no_gravado' => 0,
            'neto_exento' => 0,
            'iva_total' => round($total - ($overrides['tributos'] ?? 0) - ($total - ($overrides['tributos'] ?? 0)) / 1.21, 2),
            'tributos' => 0,
            'total' => $total,
            'estado' => 'autorizado',
            'usuario_id' => 1,
            'es_total_venta' => true,
        ], $overrides));

        if ($impuesto) {
            ComprobanteFiscalTributo::create([
                'comprobante_fiscal_id' => $comprobante->id,
                'impuesto_id' => $impuesto->id,
                'base_imponible' => 826.45,
                'alicuota' => 3.0,
                'monto' => 24.79,
                'codigo_arca' => 7,
            ]);
        }

        ComprobanteFiscalVenta::create([
            'comprobante_fiscal_id' => $comprobante->id,
            'venta_id' => $venta->id,
            'monto' => $total,
            'es_anulacion' => false,
        ]);

        return $comprobante;
    }

    // =========================================================================
    // RF-V2 — tributosParaReemision
    // =========================================================================

    public function test_reemision_toma_snapshot_de_tributos_del_comprobante_original(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);
        $impuesto = $this->crearImpuestoPercepcion();

        // Venta cobrada con percepción: bienes 1000 + percepción 24.79.
        $venta = $this->crearVentaBasica(['_caja' => $caja, 'total' => 1024.79, 'total_final' => 1024.79]);
        $this->crearFacturaAutorizada($venta, $pv, 1024.79, ['tributos' => 24.79], $impuesto);

        $reemision = $this->invocar('tributosParaReemision', $venta->fresh(), 1024.79);

        $this->assertCount(1, $reemision['tributos']);
        $this->assertSame(24.79, $reemision['imp_trib_porcion']);
        $this->assertSame(24.79, $reemision['imp_trib_venta']);
        $this->assertSame(24.79, (float) $reemision['tributos'][0]['monto']);
        $this->assertSame(7, (int) $reemision['tributos'][0]['codigo_arca']);
        $this->assertEqualsWithDelta(826.45, (float) $reemision['tributos'][0]['base_imponible'], 0.01);
    }

    public function test_reemision_prorratea_el_snapshot_a_la_porcion_facturada(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);
        $impuesto = $this->crearImpuestoPercepcion();

        $venta = $this->crearVentaBasica(['_caja' => $caja, 'total' => 1024.79, 'total_final' => 1024.79]);
        $this->crearFacturaAutorizada($venta, $pv, 1024.79, ['tributos' => 24.79], $impuesto);

        // Se re-factura la mitad (512.40 de 1024.79).
        $reemision = $this->invocar('tributosParaReemision', $venta->fresh(), 512.40);

        $this->assertEqualsWithDelta(12.40, $reemision['imp_trib_porcion'], 0.01);
        $this->assertSame(24.79, $reemision['imp_trib_venta']);
    }

    public function test_reemision_recalcula_tributos_si_no_hay_comprobante_previo(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);
        $impuesto = $this->crearImpuestoPercepcion();

        CuitImpuestoConfig::create([
            'cuit_id' => $pv->cuit_id,
            'impuesto_id' => $impuesto->id,
            'inscripto' => true,
            'es_agente_percepcion' => true,
            'percibir_no_empadronados' => true,
            'alicuota' => 3.0,
            'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Cliente RI '.uniqid(),
            'activo' => true,
            'condicion_iva_id' => $this->condicionRI()->id,
        ]);

        // Venta cobrada con percepción cuya FC nunca salió (p.ej. QR post-commit):
        // el pago activo evidencia la percepción (monto_final = base + 24.79).
        $venta = $this->crearVentaBasica([
            '_caja' => $caja,
            'cliente_id' => $cliente->id,
            'total' => 1024.79,
            'total_final' => 1024.79,
        ]);
        $this->crearPagoConPercepcion($venta, base: 1000.0, percepcion: 24.79);

        $reemision = $this->invocar('tributosParaReemision', $venta->fresh(), 1024.79);

        // Recalcula por config vigente: 3% sobre neto gravado 826.45 = 24.79.
        $this->assertCount(1, $reemision['tributos']);
        $this->assertEqualsWithDelta(24.79, $reemision['imp_trib_porcion'], 0.01);
        $this->assertEqualsWithDelta(826.45, (float) $reemision['tributos'][0]['base_imponible'], 0.01);
    }

    /**
     * Guard "no autopercibir": aunque el cliente sea RI y el CUIT agente, si los
     * pagos de la venta NO evidencian percepción cobrada (monto_final == base +
     * ajustes), el reintento no debe inventar ImpTrib — se facturaría un tributo
     * que el cliente nunca pagó.
     */
    public function test_recalculo_no_inventa_tributos_si_la_venta_no_cobro_percepcion(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);
        $impuesto = $this->crearImpuestoPercepcion();

        CuitImpuestoConfig::create([
            'cuit_id' => $pv->cuit_id,
            'impuesto_id' => $impuesto->id,
            'inscripto' => true,
            'es_agente_percepcion' => true,
            'percibir_no_empadronados' => true,
            'alicuota' => 3.0,
            'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
        ]);

        $cliente = Cliente::create([
            'nombre' => 'Cliente RI '.uniqid(),
            'activo' => true,
            'condicion_iva_id' => $this->condicionRI()->id,
        ]);

        // Venta que NO cobró percepción: pago plano de 1000.
        $venta = $this->crearVentaBasica(['_caja' => $caja, 'cliente_id' => $cliente->id]);
        $this->crearPagoConPercepcion($venta, base: 1000.0, percepcion: 0.0);

        $reemision = $this->invocar('tributosParaReemision', $venta->fresh(), 1000.0);

        $this->assertSame([], $reemision['tributos']);
        $this->assertSame(0.0, $reemision['imp_trib_porcion']);
    }

    /** Pago activo cuyo monto_final excede la base en $percepcion (evidencia de cobro). */
    private function crearPagoConPercepcion(Venta $venta, float $base, float $percepcion): void
    {
        DB::connection('pymes_tenant')->table('venta_pagos')->insert([
            'venta_id' => $venta->id,
            'forma_pago_id' => $this->crearFormaPagoEfectivo()['formaPago']->id,
            'monto_base' => $base,
            'ajuste_porcentaje' => 0,
            'monto_ajuste' => 0,
            'monto_final' => round($base + $percepcion, 2),
            'es_cuenta_corriente' => false,
            'afecta_caja' => false,
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_reemision_sin_percepcion_no_inventa_tributos(): void
    {
        $venta = $this->crearVentaBasica();

        $reemision = $this->invocar('tributosParaReemision', $venta->fresh(), 1000.0);

        $this->assertSame([], $reemision['tributos']);
        $this->assertSame(0.0, $reemision['imp_trib_porcion']);
    }

    // =========================================================================
    // RF-V2 — calcularDesgloseIvaProporcional con tributos
    // =========================================================================

    public function test_desglose_proporcional_excluye_la_percepcion_de_la_base(): void
    {
        $venta = $this->crearVentaBasica(['total' => 1024.79, 'total_final' => 1024.79]);

        // Con percepción informada: el neto/IVA se arma SOLO sobre los bienes (1000).
        $desglose = $this->invocar('calcularDesgloseIvaProporcional', $venta->fresh(), 1024.79, 24.79, 24.79);

        $sumaNeto = array_sum(array_column($desglose['por_alicuota'], 'neto'));
        $sumaIva = array_sum(array_column($desglose['por_alicuota'], 'iva'));

        // Cierre contra bienes: neto + IVA = 1000 exacto (ImpTotal = 1000 + 24.79).
        $this->assertEqualsWithDelta(1000.0, round($sumaNeto + $sumaIva, 2), 0.001);
        // El bug corregido: el IVA quedaba inflado con la percepción (198.34).
        $this->assertEqualsWithDelta(173.55, $sumaIva, 0.02);
    }

    public function test_desglose_proporcional_sin_tributos_no_cambia(): void
    {
        $venta = $this->crearVentaBasica();

        $desglose = $this->invocar('calcularDesgloseIvaProporcional', $venta->fresh(), 1000.0);

        $sumaNeto = array_sum(array_column($desglose['por_alicuota'], 'neto'));
        $sumaIva = array_sum(array_column($desglose['por_alicuota'], 'iva'));

        $this->assertEqualsWithDelta(1000.0, round($sumaNeto + $sumaIva, 2), 0.001);
        $this->assertEqualsWithDelta(826.45, $sumaNeto, 0.02);
    }

    // =========================================================================
    // RF-V6 — montoFiscalFacturado (fuente del cache monto_fiscal_cache)
    // =========================================================================

    public function test_monto_fiscal_facturado_suma_solo_lo_facturado(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);

        $venta = $this->crearVentaBasica(['_caja' => $caja]);

        // Facturación PARCIAL: una FC de 400 sobre una venta de 1000.
        $this->crearFacturaAutorizada($venta, $pv, 400.0, ['es_total_venta' => false]);

        $service = new ComprobanteFiscalService;
        $this->assertSame(400.0, $service->montoFiscalFacturado($venta->fresh()));
    }

    public function test_monto_fiscal_facturado_descuenta_notas_de_credito(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);

        $venta = $this->crearVentaBasica(['_caja' => $caja]);
        $factura = $this->crearFacturaAutorizada($venta, $pv, 1000.0);

        // NC autorizada que anula la factura completa.
        ComprobanteFiscal::create([
            'sucursal_id' => $this->sucursalId,
            'punto_venta_id' => $pv->id,
            'cuit_id' => $pv->cuit_id,
            'tipo' => 'nota_credito_b',
            'letra' => 'B',
            'punto_venta_numero' => $pv->numero,
            'numero_comprobante' => random_int(1, 99999),
            'fecha_emision' => now()->toDateString(),
            'condicion_iva_id' => $this->condicionRI()->id,
            'receptor_nombre' => 'CONSUMIDOR FINAL',
            'receptor_documento_tipo' => '99',
            'receptor_documento_numero' => '0',
            'neto_gravado' => 826.45,
            'neto_no_gravado' => 0,
            'neto_exento' => 0,
            'iva_total' => 173.55,
            'tributos' => 0,
            'total' => 1000.0,
            'estado' => 'autorizado',
            'usuario_id' => 1,
            'comprobante_asociado_id' => $factura->id,
            'es_total_venta' => true,
        ]);

        $service = new ComprobanteFiscalService;
        $this->assertSame(0.0, $service->montoFiscalFacturado($venta->fresh()));
    }

    public function test_monto_fiscal_facturado_topea_en_total_final(): void
    {
        $caja = $this->crearCajaAbierta($this->sucursalId);
        $pv = $this->crearPuntoVentaParaCaja($caja->id);

        $venta = $this->crearVentaBasica(['_caja' => $caja]);
        $this->crearFacturaAutorizada($venta, $pv, 1000.0);

        $service = new ComprobanteFiscalService;
        $this->assertSame(1000.0, $service->montoFiscalFacturado($venta->fresh()));
    }
}

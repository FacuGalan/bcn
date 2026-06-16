<?php

namespace Tests\Unit\Services\IntegracionesPago;

use App\Services\IntegracionesPago\MercadoPagoGateway;
use Tests\TestCase;

/**
 * Mapeo TAX_DETAIL del reporte de MP → impuesto del catálogo (RF-06, Fase 4a).
 *
 * Códigos reales de la doc oficial de MP (ver memoria
 * reference-mp-reporte-columnas-impuestos).
 */
class MercadoPagoGatewayMapeoImpuestoTest extends TestCase
{
    private MercadoPagoGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new MercadoPagoGateway;
    }

    public function test_percepcion_iibb_por_jurisdiccion(): void
    {
        $r = $this->gateway->mapearImpuestoReporte('tax_payment_iibb_cre_santa_fe');

        $this->assertSame('perc_iibb_ar_s', $r['codigo']);
        $this->assertSame('percepcion', $r['naturaleza']);
        $this->assertSame('AR-S', $r['jurisdiccion']);
    }

    public function test_percepcion_iibb_sin_variante_cre(): void
    {
        $r = $this->gateway->mapearImpuestoReporte('tax_payment_iibb_cordoba');

        $this->assertSame('perc_iibb_ar_x', $r['codigo']);
        $this->assertSame('AR-X', $r['jurisdiccion']);
    }

    public function test_percepcion_iibb_provincia_compuesta(): void
    {
        $r = $this->gateway->mapearImpuestoReporte('tax_payment_iibb_cre_santiago_del_estero');

        $this->assertSame('perc_iibb_ar_g', $r['codigo']);
        $this->assertSame('AR-G', $r['jurisdiccion']);
    }

    public function test_percepcion_iva(): void
    {
        foreach (['tax_iva', 'tax_iva_cre'] as $code) {
            $r = $this->gateway->mapearImpuestoReporte($code);
            $this->assertSame('perc_iva', $r['codigo']);
            $this->assertSame('percepcion', $r['naturaleza']);
            $this->assertSame('AR', $r['jurisdiccion']);
        }
    }

    public function test_impuesto_creditos_debitos(): void
    {
        foreach (['tax_withholding_payer', 'tax_withholding_collector', 'tax_withholding_payout', 'tax_withholding_shipping'] as $code) {
            $r = $this->gateway->mapearImpuestoReporte($code);
            $this->assertSame('imp_creditos_debitos', $r['codigo'], "para {$code}");
            $this->assertSame('tributo', $r['naturaleza']);
            $this->assertSame('AR', $r['jurisdiccion']);
        }
    }

    public function test_tax_detail_pelado_nombre_de_provincia(): void
    {
        // La doc oficial documenta que TAX_DETAIL puede traer solo la jurisdicción.
        $r = $this->gateway->mapearImpuestoReporte('santa_fe');
        $this->assertSame('perc_iibb_ar_s', $r['codigo']);
        $this->assertSame('percepcion', $r['naturaleza']);
        $this->assertSame('AR-S', $r['jurisdiccion']);

        $r2 = $this->gateway->mapearImpuestoReporte('cordoba');
        $this->assertSame('perc_iibb_ar_x', $r2['codigo']);
        $this->assertSame('AR-X', $r2['jurisdiccion']);
    }

    public function test_retencion_iibb_generica_no_se_mapea_aun(): void
    {
        // Sin jurisdicción en el código → queda genérico (resolución en 4b).
        $this->assertNull($this->gateway->mapearImpuestoReporte('tax_withholding'));
        $this->assertNull($this->gateway->mapearImpuestoReporte('tax_withdholding')); // typo MP
        $this->assertNull($this->gateway->mapearImpuestoReporte('tax_withholding_cancel'));
    }

    public function test_jurisdiccion_desconocida_no_rompe(): void
    {
        $this->assertNull($this->gateway->mapearImpuestoReporte('tax_payment_iibb_atlantida'));
        $this->assertNull($this->gateway->mapearImpuestoReporte(''));
        $this->assertNull($this->gateway->mapearImpuestoReporte('cobro_normal'));
    }
}

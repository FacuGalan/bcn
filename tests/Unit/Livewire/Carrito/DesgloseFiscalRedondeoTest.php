<?php

namespace Tests\Unit\Livewire\Carrito;

use App\Livewire\Concerns\Carrito\WithPagosDesglose;
use Tests\TestCase;

/**
 * Cubre la política de redondeo del desglose fiscal (Fase 5b): el comprobante
 * debe cerrar exacto ImpNeto + ImpIVA == total (y con percepción,
 * ImpNeto + ImpIVA + ImpTrib == ImpTotal), porque AFIP rechaza con error 10048
 * cuando no cierran. El IVA de la última alícuota absorbe el residuo de redondeo
 * manteniendo el neto.
 *
 * Son tests de lógica pura sobre el trait WithPagosDesglose: no tocan BD ni AFIP,
 * por eso usan un harness anónimo en vez del componente Livewire completo.
 */
class DesgloseFiscalRedondeoTest extends TestCase
{
    /**
     * Harness mínimo que expone los métodos protegidos del trait. Solo declara
     * las propiedades que provee el host (NuevaVenta); desglosePagos/percepcionMonto
     * ya las declara el trait.
     */
    private function harness(): object
    {
        return new class
        {
            use WithPagosDesglose;

            public $resultado = null;

            public $montoFacturaFiscal = 0;

            public $desgloseIvaFiscal = [];

            public function llamarFormatear(): void
            {
                $this->formatearDesgloseParaAFIP();
            }

            public function llamarRecalcular(): void
            {
                $this->recalcularDesgloseIvaFiscal();
            }

            public function llamarDistribuir(): void
            {
                $this->distribuirPercepcionEnDesglose();
            }
        };
    }

    /**
     * Caso real #194: total 600.94 @ 21%. Con el algoritmo viejo (ajustar el neto)
     * quedaba neto 496.64 + iva 104.29 = 600.93 (un centavo corto). Ahora el IVA
     * absorbe el residuo → 496.64 + 104.30 = 600.94 exacto.
     */
    public function test_formatear_cierra_exacto_una_alicuota(): void
    {
        $h = $this->harness();
        $h->montoFacturaFiscal = 600.94;
        $h->resultado = [
            'desglose_iva' => [
                'por_alicuota' => [
                    ['alicuota' => 21, 'neto' => 600.94 / 1.21],
                ],
            ],
        ];

        $h->llamarFormatear();

        $d = $h->desgloseIvaFiscal;
        $this->assertSame(496.64, $d['total_neto']);
        $this->assertSame(104.30, $d['total_iva']);
        $this->assertSame(600.94, round($d['total_neto'] + $d['total_iva'], 2));
        $this->assertSame(600.94, $d['total']);
    }

    /** El IVA ajustado no se aparta más de 0.01 de neto×alícuota (tolerancia AFIP). */
    public function test_formatear_iva_dentro_de_tolerancia_afip(): void
    {
        $h = $this->harness();
        $h->montoFacturaFiscal = 600.94;
        $h->resultado = [
            'desglose_iva' => [
                'por_alicuota' => [
                    ['alicuota' => 21, 'neto' => 600.94 / 1.21],
                ],
            ],
        ];

        $h->llamarFormatear();

        $ultima = $h->desgloseIvaFiscal['por_alicuota'][0];
        $ivaTeorico = round($ultima['neto'] * 0.21, 2);
        $this->assertLessThanOrEqual(0.01, abs($ultima['iva'] - $ivaTeorico));
    }

    /** Varias alícuotas (21% + 10.5%): la suma debe cerrar exacto al total. */
    public function test_formatear_cierra_exacto_multiples_alicuotas(): void
    {
        $h = $this->harness();
        $h->montoFacturaFiscal = 300.07;
        $h->resultado = [
            'desglose_iva' => [
                'por_alicuota' => [
                    ['alicuota' => 21, 'neto' => 123.456],
                    ['alicuota' => 10.5, 'neto' => 150.123],
                ],
            ],
        ];

        $h->llamarFormatear();

        $d = $h->desgloseIvaFiscal;
        $this->assertSame(300.07, round($d['total_neto'] + $d['total_iva'], 2));
        $this->assertSame(300.07, $d['total']);
    }

    /** Caso sin percepción (preexistente): cualquier monto debe cerrar exacto. */
    public function test_formatear_cierra_exacto_sin_percepcion(): void
    {
        $h = $this->harness();
        $h->montoFacturaFiscal = 1234.57;
        $h->resultado = [
            'desglose_iva' => [
                'por_alicuota' => [
                    ['alicuota' => 21, 'neto' => 1234.57 / 1.21],
                ],
            ],
        ];

        $h->llamarFormatear();

        $d = $h->desgloseIvaFiscal;
        $this->assertSame(1234.57, round($d['total_neto'] + $d['total_iva'], 2));
    }

    /** Mixto/parcial: el desglose proporcional también cierra exacto. */
    public function test_recalcular_proporcional_cierra_exacto(): void
    {
        $h = $this->harness();
        $h->montoFacturaFiscal = 360.28;
        $h->resultado = [
            'desglose_iva' => [
                'total' => 1000.0,
                'por_alicuota' => [
                    ['alicuota' => 21, 'neto' => 1000.0 / 1.21],
                ],
            ],
        ];

        $h->llamarRecalcular();

        $d = $h->desgloseIvaFiscal;
        $this->assertSame(360.28, round($d['total_neto'] + $d['total_iva'], 2));
        $this->assertSame(360.28, $d['total']);
    }

    /**
     * La percepción se reparte sobre los pagos fiscales (proporcional a bienes),
     * el último absorbe el redondeo, y los no fiscales quedan intactos.
     */
    public function test_distribuir_percepcion_reparte_en_pagos_fiscales(): void
    {
        $h = $this->harness();
        $h->desglosePagos = [
            ['factura_fiscal' => true, 'monto_final' => 200.0, 'percepcion' => 0.0],
            ['factura_fiscal' => true, 'monto_final' => 100.0, 'percepcion' => 0.0],
            ['factura_fiscal' => false, 'monto_final' => 50.0, 'percepcion' => 0.0],
        ];
        $h->percepcionMonto = 8.72;

        $h->llamarDistribuir();

        $sumaPercepcion = round(array_sum(array_column($h->desglosePagos, 'percepcion')), 2);
        $this->assertSame(8.72, $sumaPercepcion);
        // El no fiscal no recibe percepción ni cambia su monto.
        $this->assertSame(0.0, (float) $h->desglosePagos[2]['percepcion']);
        $this->assertSame(50.0, (float) $h->desglosePagos[2]['monto_final']);
        // Los fiscales cobran bienes + su parte de percepción.
        $this->assertSame(
            308.72,
            round($h->desglosePagos[0]['monto_final'] + $h->desglosePagos[1]['monto_final'], 2)
        );
    }

    /** Distribuir es idempotente: recalcular no acumula la percepción. */
    public function test_distribuir_percepcion_es_idempotente(): void
    {
        $h = $this->harness();
        $h->desglosePagos = [
            ['factura_fiscal' => true, 'monto_final' => 200.0, 'percepcion' => 0.0],
            ['factura_fiscal' => true, 'monto_final' => 100.0, 'percepcion' => 0.0],
        ];
        $h->percepcionMonto = 8.72;

        $h->llamarDistribuir();
        $primeraVez = array_column($h->desglosePagos, 'monto_final');

        $h->llamarDistribuir();
        $segundaVez = array_column($h->desglosePagos, 'monto_final');

        $this->assertEquals($primeraVez, $segundaVez);
        $this->assertSame(8.72, round(array_sum(array_column($h->desglosePagos, 'percepcion')), 2));
    }
}

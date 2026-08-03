<?php

namespace Tests\Unit\Services;

use App\Services\PuntosService;
use PHPUnit\Framework\TestCase;

/**
 * Matriz de costo del canje por artículo (RF-T59, spec
 * tienda-canje-puntos-avanzado): costo (fijo/derivado) × modo de opcionales.
 * Cálculo puro, sin BD. Valores por unidad: artículo $1000, opcionales $500,
 * valor del punto $50.
 */
class PuntosServiceCostoCanjeTest extends TestCase
{
    protected function costo(?int $fijos, ?string $modo, ?float $valorPunto = 50.0, float $opcionales = 500.0): ?array
    {
        return (new PuntosService)->costoCanjeArticulo($fijos, $modo, 1000.0, $opcionales, $valorPunto);
    }

    public function test_derivado_incluidos_deriva_del_precio_con_opcionales(): void
    {
        $this->assertSame(['puntos' => 30, 'monto_canjeado' => 1500.0], $this->costo(null, 'incluidos'));
    }

    public function test_derivado_en_plata_deriva_del_pelado_y_deja_los_opcionales_a_pagar(): void
    {
        $this->assertSame(['puntos' => 20, 'monto_canjeado' => 1000.0], $this->costo(null, 'en_plata'));
    }

    public function test_derivado_en_puntos_suma_ambas_derivaciones(): void
    {
        $this->assertSame(['puntos' => 30, 'monto_canjeado' => 1500.0], $this->costo(null, 'en_puntos'));
    }

    public function test_fijo_incluidos_vale_lo_mismo_con_cualquier_opcional(): void
    {
        $this->assertSame(['puntos' => 7, 'monto_canjeado' => 1500.0], $this->costo(7, 'incluidos'));
    }

    public function test_fijo_en_plata_cobra_los_opcionales(): void
    {
        $this->assertSame(['puntos' => 7, 'monto_canjeado' => 1000.0], $this->costo(7, 'en_plata'));
    }

    public function test_fijo_en_puntos_suma_los_opcionales_derivados(): void
    {
        // 7 + ceil(500 / 50) = 17.
        $this->assertSame(['puntos' => 17, 'monto_canjeado' => 1500.0], $this->costo(7, 'en_puntos'));
    }

    public function test_modo_desconocido_o_null_cae_a_incluidos(): void
    {
        $this->assertSame(['puntos' => 30, 'monto_canjeado' => 1500.0], $this->costo(null, null));
        $this->assertSame(['puntos' => 30, 'monto_canjeado' => 1500.0], $this->costo(null, 'cualquiera'));
    }

    public function test_derivar_sin_valor_de_punto_no_es_resoluble(): void
    {
        $this->assertNull($this->costo(null, 'incluidos', valorPunto: null));
        $this->assertNull($this->costo(null, 'incluidos', valorPunto: 0.0));
        // Fijo + en_puntos con opcionales tampoco: la parte opcional deriva.
        $this->assertNull($this->costo(7, 'en_puntos', valorPunto: null));
    }

    public function test_fijo_sin_opcionales_no_necesita_valor_de_punto(): void
    {
        $this->assertSame(['puntos' => 7, 'monto_canjeado' => 1000.0], $this->costo(7, 'incluidos', valorPunto: null, opcionales: 0.0));
        $this->assertSame(['puntos' => 7, 'monto_canjeado' => 1000.0], $this->costo(7, 'en_puntos', valorPunto: null, opcionales: 0.0));
    }
}

<?php

namespace Tests\Unit\Services\Fiscal;

use App\Services\Fiscal\Padron\AgipPadronParser;
use App\Services\Fiscal\Padron\ArbaPadronParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit del parseo de padrones ARBA/AGIP (Fase 10b, RF-14). Sin BD: valida el
 * mapeo posicional de cada layout y el criterio de exención conservador
 * (alícuota 0,00 o baja ⇒ exento).
 */
class PadronParserTest extends TestCase
{
    // ==================== ARBA ====================

    public function test_arba_parsea_percepcion_con_alicuota(): void
    {
        $parser = new ArbaPadronParser;
        // Régimen P ; pub ; desde ; hasta ; cuit ; tipo ; alta ; cambio ; alícuota ; grupo
        $fila = $parser->parseLinea('P;01062026;01062026;30062026;20123456789;D;S;N;1,50;00;');

        $this->assertNotNull($fila);
        $this->assertSame('20123456789', $fila->cuit);
        $this->assertFalse($fila->exento);
        $this->assertSame(1.5, $fila->alicuota);
        $this->assertSame('2026-06-01', $fila->vigenteDesde);
        $this->assertSame('2026-06-30', $fila->vigenteHasta);
    }

    public function test_arba_parsea_fecha_con_cero_inicial_como_espacio(): void
    {
        $parser = new ArbaPadronParser;
        // ARBA rinde el cero inicial del campo fecha (ancho 8) como espacio:
        // " 1102014" = 01/10/2014. Formato real del padrón de percepción.
        $fila = $parser->parseLinea('P;23092014; 1102014;31102014;20000000028;D;N;N;6,00;15;');

        $this->assertNotNull($fila);
        $this->assertSame('2014-10-01', $fila->vigenteDesde);
        $this->assertSame('2014-10-31', $fila->vigenteHasta);
    }

    public function test_arba_ignora_regimen_retencion(): void
    {
        $parser = new ArbaPadronParser;

        $this->assertNull($parser->parseLinea('R;01062026;01062026;30062026;20123456789;D;S;N;2,00;00;'));
    }

    public function test_arba_alicuota_cero_es_exento(): void
    {
        $parser = new ArbaPadronParser;
        $fila = $parser->parseLinea('P;01062026;01062026;30062026;20123456789;D;S;N;0,00;00;');

        $this->assertNotNull($fila);
        $this->assertTrue($fila->exento);
        $this->assertNull($fila->alicuota);
    }

    public function test_arba_marca_baja_es_exento(): void
    {
        $parser = new ArbaPadronParser;
        // Marca de baja "B" aunque traiga alícuota positiva ⇒ exento.
        $fila = $parser->parseLinea('P;01062026;01062026;30062026;20123456789;D;B;N;3,00;00;');

        $this->assertNotNull($fila);
        $this->assertTrue($fila->exento);
        $this->assertNull($fila->alicuota);
    }

    public function test_arba_descarta_cuit_invalido_y_linea_vacia(): void
    {
        $parser = new ArbaPadronParser;

        $this->assertNull($parser->parseLinea('P;01062026;01062026;30062026;123;D;S;N;1,00;00;'));
        $this->assertNull($parser->parseLinea(''));
        $this->assertNull($parser->parseLinea('basura sin separadores'));
    }

    // ==================== AGIP ====================

    public function test_agip_parsea_percepcion_e_ignora_retencion(): void
    {
        $parser = new AgipPadronParser;
        // pub ; desde ; hasta ; cuit ; tipo ; alta ; cambioAlic ; alícPerc ; alícRet ; gPerc ; gRet ; razón
        $fila = $parser->parseLinea('01062026;01062026;30062026;20123456789;D;S;N;2,00;1,00;00;00;EMPRESA SA');

        $this->assertNotNull($fila);
        $this->assertSame('20123456789', $fila->cuit);
        $this->assertFalse($fila->exento);
        $this->assertSame(2.0, $fila->alicuota); // usa percepción (2,00), no retención (1,00)
        $this->assertSame('2026-06-01', $fila->vigenteDesde);
    }

    public function test_agip_alicuota_percepcion_cero_es_exento(): void
    {
        $parser = new AgipPadronParser;
        $fila = $parser->parseLinea('01062026;01062026;30062026;20123456789;D;S;N;0,00;1,00;00;00;EXENTO SA');

        $this->assertNotNull($fila);
        $this->assertTrue($fila->exento);
        $this->assertNull($fila->alicuota);
    }

    public function test_agip_marca_baja_es_exento(): void
    {
        $parser = new AgipPadronParser;
        $fila = $parser->parseLinea('01062026;01062026;30062026;20123456789;D;B;N;2,50;0,00;00;00;BAJA SA');

        $this->assertNotNull($fila);
        $this->assertTrue($fila->exento);
    }

    public function test_codigos_de_impuesto_por_agencia(): void
    {
        $this->assertSame('perc_iibb_ar_b', (new ArbaPadronParser)->impuestoCodigo());
        $this->assertSame('perc_iibb_ar_c', (new AgipPadronParser)->impuestoCodigo());
    }
}

<?php

namespace Tests\Feature\Services\Fiscal;

use App\Models\Cliente;
use App\Models\ClienteImpuestoConfig;
use App\Models\CondicionIva;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Services\Fiscal\PadronImportService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Integración del importador de padrón ARBA/AGIP (Fase 10b, RF-14): match por
 * CUIT contra clientes del comercio, upsert idempotente, precedencia del
 * override manual y exención conservadora.
 */
class PadronImportServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    private array $archivos = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
    }

    protected function tearDown(): void
    {
        foreach ($this->archivos as $a) {
            @unlink($a);
        }

        $this->tearDownTenant();
        parent::tearDown();
    }

    private function service(): PadronImportService
    {
        return app(PadronImportService::class);
    }

    private function impuestoArba(): Impuesto
    {
        return Impuesto::firstOrCreate(
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
    }

    private function cliente(string $cuit): Cliente
    {
        $cond = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'RI']);

        return Cliente::create([
            'nombre' => 'Cliente '.$cuit,
            'cuit' => $cuit,
            'condicion_iva_id' => $cond->id,
            'activo' => true,
        ]);
    }

    /** Escribe un archivo temporal de padrón ARBA con las líneas dadas. */
    private function archivoArba(array $lineas): string
    {
        $path = tempnam(sys_get_temp_dir(), 'padron');
        file_put_contents($path, implode("\n", $lineas)."\n");
        $this->archivos[] = $path;

        return $path;
    }

    /** Escribe un .gz temporal con las líneas dadas (padrón comprimido). */
    private function archivoArbaGz(array $lineas): string
    {
        $base = tempnam(sys_get_temp_dir(), 'padron');
        $path = $base.'.gz';
        file_put_contents($path, gzencode(implode("\n", $lineas)."\n"));
        $this->archivos[] = $base;
        $this->archivos[] = $path;

        return $path;
    }

    /** Escribe un .zip temporal con el .txt del padrón adentro. */
    private function archivoArbaZip(array $lineas): string
    {
        $base = tempnam(sys_get_temp_dir(), 'padron');
        $path = $base.'.zip';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('PadronRGSPer.txt', implode("\n", $lineas)."\n");
        $zip->close();
        $this->archivos[] = $base;
        $this->archivos[] = $path;

        return $path;
    }

    private function lineaArba(string $cuit, string $alicuota, string $marca = 'S'): string
    {
        return "P;01062026;01062026;30062026;{$cuit};D;{$marca};N;{$alicuota};00;";
    }

    public function test_importa_crea_config_padron_para_cliente_que_matchea(): void
    {
        $imp = $this->impuestoArba();
        $cliente = $this->cliente('20123456789');

        $path = $this->archivoArba([
            $this->lineaArba('20123456789', '1,50'),
            $this->lineaArba('27999999990', '2,00'), // no es cliente
        ]);

        $resumen = $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertSame(1, $resumen->creadas);
        $this->assertSame(1, $resumen->sinMatch);

        $config = ClienteImpuestoConfig::where('cliente_id', $cliente->id)
            ->where('impuesto_id', $imp->id)
            ->first();

        $this->assertNotNull($config);
        $this->assertSame(ClienteImpuestoConfig::ORIGEN_PADRON, $config->origen_alicuota);
        $this->assertFalse($config->exento);
        $this->assertSame('1.5000', $config->alicuota);
    }

    public function test_alicuota_cero_y_baja_dejan_exento(): void
    {
        $imp = $this->impuestoArba();
        $cero = $this->cliente('20111111112');
        $baja = $this->cliente('20222222223');

        $path = $this->archivoArba([
            $this->lineaArba('20111111112', '0,00'),
            $this->lineaArba('20222222223', '3,00', 'B'),
        ]);

        $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertTrue(ClienteImpuestoConfig::where('cliente_id', $cero->id)->value('exento'));
        $this->assertTrue(ClienteImpuestoConfig::where('cliente_id', $baja->id)->value('exento'));
        $this->assertNull(ClienteImpuestoConfig::where('cliente_id', $baja->id)->value('alicuota'));
    }

    public function test_no_pisa_el_override_manual(): void
    {
        $imp = $this->impuestoArba();
        $cliente = $this->cliente('20123456789');

        // Override manual previo, sin vigencia (la del padrón será 2026-06-01).
        ClienteImpuestoConfig::create([
            'cliente_id' => $cliente->id,
            'impuesto_id' => $imp->id,
            'exento' => false,
            'alicuota' => 5.0,
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_MANUAL,
            'vigente_desde' => '2026-06-01',
        ]);

        $path = $this->archivoArba([$this->lineaArba('20123456789', '1,50')]);

        $resumen = $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertSame(1, $resumen->omitidasManual);
        $this->assertSame(0, $resumen->creadas);

        $config = ClienteImpuestoConfig::where('cliente_id', $cliente->id)->first();
        $this->assertSame(ClienteImpuestoConfig::ORIGEN_MANUAL, $config->origen_alicuota);
        $this->assertSame('5.0000', $config->alicuota); // intacto
    }

    public function test_es_idempotente_por_cliente_impuesto_vigencia(): void
    {
        $imp = $this->impuestoArba();
        $cliente = $this->cliente('20123456789');

        $path = $this->archivoArba([$this->lineaArba('20123456789', '1,50')]);

        $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);
        $segunda = $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertSame(0, $segunda->creadas);
        $this->assertSame(1, $segunda->actualizadas);
        $this->assertSame(
            1,
            ClienteImpuestoConfig::where('cliente_id', $cliente->id)->where('impuesto_id', $imp->id)->count()
        );
    }

    public function test_importa_desde_zip_comprimido(): void
    {
        $imp = $this->impuestoArba();
        $cliente = $this->cliente('20123456789');

        // El usuario sube el .zip tal cual lo baja de ARBA (sin descomprimir).
        $path = $this->archivoArbaZip([
            $this->lineaArba('20123456789', '1,50'),
            $this->lineaArba('27999999990', '2,00'),
        ]);

        $resumen = $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertSame(1, $resumen->creadas);
        $this->assertSame('1.5000', ClienteImpuestoConfig::where('cliente_id', $cliente->id)->value('alicuota'));
    }

    public function test_importa_desde_gz_comprimido(): void
    {
        $imp = $this->impuestoArba();
        $cliente = $this->cliente('20123456789');

        $path = $this->archivoArbaGz([$this->lineaArba('20123456789', '2,50')]);

        $resumen = $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertSame(1, $resumen->creadas);
        $this->assertSame('2.5000', ClienteImpuestoConfig::where('cliente_id', $cliente->id)->value('alicuota'));
    }

    public function test_matchea_normalizando_cuit_con_guiones(): void
    {
        $imp = $this->impuestoArba();
        $cliente = $this->cliente('20-12345678-9'); // cliente guarda con guiones

        $path = $this->archivoArba([$this->lineaArba('20123456789', '2,50')]);

        $resumen = $this->service()->importar($path, PadronImportService::AGENCIA_ARBA);

        $this->assertSame(1, $resumen->creadas);
        $this->assertSame('2.5000', ClienteImpuestoConfig::where('cliente_id', $cliente->id)->value('alicuota'));
    }
}

<?php

namespace Tests\Unit\Services\Fiscal;

use App\Models\Cliente;
use App\Models\ClienteImpuestoConfig;
use App\Models\Compra;
use App\Models\CompraPercepcion;
use App\Models\ComprobanteFiscal;
use App\Models\ComprobanteFiscalIva;
use App\Models\ConciliacionFila;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Services\Fiscal\ImpuestoService;
use Exception;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests del núcleo del sistema impositivo (Fase 2).
 *
 * Cubre la matriz condición IVA × agente × receptor de calcularTributos
 * (v1 conservador) + el ledger fiscal (registrar/anular/configVigente).
 *
 * Ref: .claude/specs/sistema-impositivo.md (Fase 2).
 */
class ImpuestoServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected ImpuestoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->service = new ImpuestoService;
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== Helpers ====================

    protected function condicion(int $codigo): CondicionIva
    {
        return CondicionIva::firstOrCreate(
            ['codigo' => $codigo],
            ['nombre' => "Cond {$codigo}"]
        );
    }

    protected function cuit(int $condicionCodigo = CondicionIva::RESPONSABLE_INSCRIPTO): Cuit
    {
        return Cuit::firstOrCreate(
            ['numero_cuit' => '20111111113'],
            [
                'razon_social' => 'Emisor SA',
                'condicion_iva_id' => $this->condicion($condicionCodigo)->id,
                'entorno_afip' => 'testing',
                'activo' => true,
            ]
        );
    }

    /**
     * Cliente receptor con la condición de IVA dada (RF-15, Fase 10: el receptor
     * de calcularTributos pasó de CondicionIva a Cliente).
     */
    protected function cliente(int $condicionCodigo = CondicionIva::RESPONSABLE_INSCRIPTO): Cliente
    {
        return Cliente::create([
            'nombre' => 'Cliente '.$condicionCodigo,
            'condicion_iva_id' => $this->condicion($condicionCodigo)->id,
            'activo' => true,
        ]);
    }

    /**
     * Perfil fiscal del cliente para un impuesto (cliente_impuesto_configs).
     */
    protected function clienteConfig(Cliente $cliente, Impuesto $imp, array $extra = []): ClienteImpuestoConfig
    {
        return ClienteImpuestoConfig::create(array_merge([
            'cliente_id' => $cliente->id,
            'impuesto_id' => $imp->id,
            'exento' => false,
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_MANUAL,
        ], $extra));
    }

    protected function impuesto(string $codigo, string $tipo, string $naturaleza, ?string $jurisdiccion): Impuesto
    {
        return Impuesto::create([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'tipo' => $tipo,
            'naturaleza_default' => $naturaleza,
            'jurisdiccion' => $jurisdiccion,
            'es_sistema' => true,
            'activo' => true,
        ]);
    }

    protected function config(Cuit $cuit, Impuesto $imp, array $extra = []): CuitImpuestoConfig
    {
        return CuitImpuestoConfig::create(array_merge([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $imp->id,
            'inscripto' => true,
            'es_agente_percepcion' => true,
            // Por defecto el agente percibe a RI aunque no tengan perfil fiscal
            // (D7): así los tests de "el agente percibe IIBB" siguen valiendo. Los
            // tests específicos de D7 lo bajan a false explícitamente.
            'percibir_no_empadronados' => true,
            'alicuota' => 3.0,
            'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
        ], $extra));
    }

    // ==================== calcularTributos: percepción IIBB ====================

    public function test_percepcion_iibb_a_ri_en_la_jurisdiccion_de_la_sucursal(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertCount(1, $tributos);
        $this->assertEquals($imp->id, $tributos[0]['impuesto_id']);
        $this->assertEquals(1000.0, $tributos[0]['base_imponible']);
        $this->assertEquals(3.0, $tributos[0]['alicuota']);
        $this->assertEquals(30.0, $tributos[0]['monto']);
    }

    public function test_percepcion_iibb_no_aplica_si_la_jurisdiccion_de_la_sucursal_difiere(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-C' // CABA, distinta jurisdicción
        );

        $this->assertSame([], $tributos);
    }

    public function test_percepcion_iibb_no_aplica_sin_sucursal(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            null
        );

        $this->assertSame([], $tributos);
    }

    // ==================== calcularTributos: condición del receptor ====================

    public function test_no_percibe_a_consumidor_final(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::CONSUMIDOR_FINAL),
            1000.0,
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    public function test_no_percibe_a_monotributo(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_MONOTRIBUTO),
            1000.0,
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    public function test_no_percibe_si_el_receptor_es_null(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $tributos = $this->service->calcularTributos($emisor, null, 1000.0, 'AR-B');

        $this->assertSame([], $tributos);
    }

    // ==================== calcularTributos: condición del emisor ====================

    public function test_no_percibe_si_el_emisor_no_es_agente_de_percepcion(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['es_agente_percepcion' => false]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    public function test_no_percibe_si_el_emisor_no_es_responsable_inscripto(): void
    {
        // Un monotributo NO puede ser agente de percepción aunque la config lo diga.
        $emisor = Cuit::firstOrCreate(
            ['numero_cuit' => '20222222223'],
            [
                'razon_social' => 'Monotributo SA',
                'condicion_iva_id' => $this->condicion(CondicionIva::RESPONSABLE_MONOTRIBUTO)->id,
                'entorno_afip' => 'testing',
                'activo' => true,
            ]
        );
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp); // es_agente_percepcion = true (mal cargado)

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    public function test_respeta_la_fecha_de_la_operacion_para_la_vigencia(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0, 'vigente_hasta' => '2026-03-31']);

        $receptor = $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO);
        $sucursal = 'AR-B';

        // Operación dentro de la vigencia → percibe.
        $dentro = $this->service->calcularTributos($emisor, $receptor, 1000.0, $sucursal, \Carbon\Carbon::parse('2026-03-15'));
        $this->assertCount(1, $dentro);

        // Operación posterior al vencimiento de la config → no percibe.
        $fuera = $this->service->calcularTributos($emisor, $receptor, 1000.0, $sucursal, \Carbon\Carbon::parse('2026-12-15'));
        $this->assertSame([], $fuera);
    }

    public function test_no_percibe_si_el_emisor_no_tiene_config_para_el_impuesto(): void
    {
        $emisor = $this->cuit();
        $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B'); // sin config

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    // ==================== calcularTributos: percepción IVA (nacional) ====================

    public function test_percepcion_iva_nacional_aplica_a_ri_sin_condicionar_jurisdiccion(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iva', Impuesto::TIPO_IVA, 'percepcion', 'AR');
        $this->config($emisor, $imp, ['alicuota' => 1.5]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            2000.0,
            null // sin jurisdicción: IVA nacional igual aplica
        );

        $this->assertCount(1, $tributos);
        $this->assertEquals('perc_iva', $tributos[0]['codigo']);
        $this->assertEquals(30.0, $tributos[0]['monto']); // 2000 * 1.5%
    }

    // ==================== calcularTributos: mínimos, base y multiplicidad ====================

    public function test_no_percibe_si_el_neto_es_menor_a_la_base_minima(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota_minimo_base' => 5000.0]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0, // < 5000
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    public function test_no_percibe_con_neto_cero_o_negativo(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $this->assertSame([], $this->service->calcularTributos(
            $emisor, $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO), 0.0, 'AR-B'
        ));
        $this->assertSame([], $this->service->calcularTributos(
            $emisor, $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO), -100.0, 'AR-B'
        ));
    }

    public function test_no_percibe_si_la_config_no_esta_vigente(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['vigente_hasta' => now()->subDay()->toDateString()]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertSame([], $tributos);
    }

    public function test_devuelve_multiples_percepciones_iva_e_iibb(): void
    {
        $emisor = $this->cuit();
        $iibb = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $iva = $this->impuesto('perc_iva', Impuesto::TIPO_IVA, 'percepcion', 'AR');
        $this->config($emisor, $iibb, ['alicuota' => 3.0]);
        $this->config($emisor, $iva, ['alicuota' => 1.5]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertCount(2, $tributos);
        $montosPorCodigo = collect($tributos)->pluck('monto', 'codigo');
        $this->assertEquals(30.0, $montosPorCodigo['perc_iibb_ar_b']);
        $this->assertEquals(15.0, $montosPorCodigo['perc_iva']);
    }

    // ==================== Fase 10: perfil fiscal del cliente (RF-15) ====================

    public function test_cliente_exento_no_se_le_percibe_iibb(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]);

        $cliente = $this->cliente();
        $this->clienteConfig($cliente, $imp, ['exento' => true]);

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertSame([], $tributos);
    }

    public function test_usa_la_alicuota_del_cliente_si_tiene_override(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]); // fija del agente

        $cliente = $this->cliente();
        $this->clienteConfig($cliente, $imp, [
            'alicuota' => 1.5, // alícuota de padrón menor a la fija
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_PADRON,
        ]);

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertCount(1, $tributos);
        $this->assertEquals(1.5, $tributos[0]['alicuota']);
        $this->assertEquals(15.0, $tributos[0]['monto']); // 1000 * 1.5%, no la fija 3%
    }

    public function test_d7_no_percibe_a_no_empadronado_si_el_agente_no_lo_habilita(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0, 'percibir_no_empadronados' => false]);

        $cliente = $this->cliente(); // sin perfil fiscal para este impuesto

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertSame([], $tributos);
    }

    public function test_d7_percibe_a_no_empadronado_si_el_agente_lo_habilita(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0, 'percibir_no_empadronados' => true]);

        $cliente = $this->cliente(); // sin perfil fiscal → aplica la fija del agente

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertCount(1, $tributos);
        $this->assertEquals(30.0, $tributos[0]['monto']); // 1000 * 3%
    }

    public function test_percepcion_iva_no_depende_del_perfil_del_cliente(): void
    {
        // Un cliente exento de IIBB igual sufre la percepción de IVA (automática).
        $emisor = $this->cuit();
        $iibb = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $iva = $this->impuesto('perc_iva', Impuesto::TIPO_IVA, 'percepcion', 'AR');
        $this->config($emisor, $iibb, ['alicuota' => 3.0]);
        $this->config($emisor, $iva, ['alicuota' => 1.5]);

        $cliente = $this->cliente();
        $this->clienteConfig($cliente, $iibb, ['exento' => true]);

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertCount(1, $tributos);
        $this->assertEquals('perc_iva', $tributos[0]['codigo']);
        $this->assertEquals(15.0, $tributos[0]['monto']);
    }

    public function test_config_manual_del_cliente_gana_sobre_la_del_padron(): void
    {
        // Revisión Fable 2026-07-01: si coexisten vigentes una config manual
        // (contador) y una de padrón para el mismo impuesto, gana la manual —
        // antes el keyBy se quedaba con la última por orden de PK (el padrón).
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]);

        $cliente = $this->cliente();
        // Manual del contador: exento (vigente_desde null, creada primero).
        $this->clienteConfig($cliente, $imp, ['exento' => true]);
        // Padrón importado después: alícuota 1.5 con vigencia fechada.
        $this->clienteConfig($cliente, $imp, [
            'alicuota' => 1.5,
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_PADRON,
            'vigente_desde' => now()->subMonth()->toDateString(),
        ]);

        // Gana la manual (exento) → no se percibe.
        $this->assertSame([], $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B'));
    }

    public function test_configs_de_padron_solapadas_gana_la_vigencia_mas_reciente(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]);

        $cliente = $this->cliente();
        $this->clienteConfig($cliente, $imp, [
            'alicuota' => 2.0,
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_PADRON,
            'vigente_desde' => now()->subMonths(2)->toDateString(),
        ]);
        $this->clienteConfig($cliente, $imp, [
            'alicuota' => 1.0,
            'origen_alicuota' => ClienteImpuestoConfig::ORIGEN_PADRON,
            'vigente_desde' => now()->subMonth()->toDateString(),
        ]);

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertCount(1, $tributos);
        $this->assertEquals(1.0, $tributos[0]['alicuota']); // la más reciente
    }

    public function test_configs_solapadas_del_emisor_no_duplican_la_percepcion(): void
    {
        // Dos configs vigentes del mismo impuesto (vigencias solapadas): se
        // percibe UNA sola vez, con la de vigente_desde más reciente.
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 2.0, 'vigente_desde' => '2026-01-01']);
        $this->config($emisor, $imp, ['alicuota' => 3.5, 'vigente_desde' => '2026-06-01']);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertCount(1, $tributos);
        $this->assertEquals(3.5, $tributos[0]['alicuota']);
        $this->assertEquals(35.0, $tributos[0]['monto']);
    }

    public function test_cliente_con_certificado_de_exclusion_no_sufre_percepcion_iva(): void
    {
        // Revisión Fable 2026-07-01 (RG 2226): perfil fiscal exento sobre el
        // impuesto de percepción de IVA ⇒ no se percibe IVA (el IIBB no cambia).
        $emisor = $this->cuit();
        $iva = $this->impuesto('perc_iva', Impuesto::TIPO_IVA, 'percepcion', 'AR');
        $iibb = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $iva, ['alicuota' => 1.5]);
        $this->config($emisor, $iibb, ['alicuota' => 3.0]);

        $cliente = $this->cliente();
        $this->clienteConfig($cliente, $iva, ['exento' => true]);

        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertCount(1, $tributos);
        $this->assertEquals('perc_iibb_ar_b', $tributos[0]['codigo']);
    }

    public function test_no_percibe_si_el_importe_no_alcanza_el_monto_minimo_de_percepcion(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iva', Impuesto::TIPO_IVA, 'percepcion', 'AR');
        $this->config($emisor, $imp, ['alicuota' => 1.5, 'monto_minimo_percepcion' => 100.0]);

        $receptor = $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO);

        // 1000 * 1.5% = 15 < 100 → no se practica.
        $this->assertSame([], $this->service->calcularTributos($emisor, $receptor, 1000.0, null));

        // 10000 * 1.5% = 150 ≥ 100 → se practica.
        $tributos = $this->service->calcularTributos($emisor, $receptor, 10000.0, null);
        $this->assertCount(1, $tributos);
        $this->assertEquals(150.0, $tributos[0]['monto']);
    }

    public function test_respeta_la_base_minima_del_cliente_sobre_la_del_agente(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]); // agente sin base mínima

        $cliente = $this->cliente();
        $this->clienteConfig($cliente, $imp, ['alicuota' => 3.0, 'alicuota_minimo_base' => 5000.0]);

        // Neto 1000 < base mínima del cliente (5000) → no percibe.
        $tributos = $this->service->calcularTributos($emisor, $cliente, 1000.0, 'AR-B');

        $this->assertSame([], $tributos);
    }

    // ==================== registrarMovimientoFiscal ====================

    public function test_registrar_calcula_periodo_fiscal_desde_la_fecha(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $mov = $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'base_imponible' => 1000.0,
            'alicuota' => 21.0,
            'monto' => 210.0,
        ]);

        $this->assertEquals('2026-03', $mov->periodo_fiscal);
        $this->assertEquals(MovimientoFiscal::ESTADO_ACTIVO, $mov->estado);
        $this->assertEquals(210.0, (float) $mov->monto);
        $this->assertDatabaseHas('movimientos_fiscales', ['id' => $mov->id, 'periodo_fiscal' => '2026-03'], 'pymes_tenant');
    }

    public function test_registrar_falla_si_falta_un_campo_requerido(): void
    {
        $this->expectException(Exception::class);

        $this->service->registrarMovimientoFiscal([
            'cuit_id' => $this->cuit()->id,
            // falta impuesto_id, sentido, etc.
            'monto' => 100.0,
        ]);
    }

    public function test_registrar_falla_con_monto_no_positivo(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $this->expectException(Exception::class);

        $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'monto' => 0.0,
        ]);
    }

    public function test_registrar_falla_con_monto_negativo_sin_permitir_negativo(): void
    {
        // El monto negativo queda reservado a las reversas de NC (flag interno).
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $this->expectException(Exception::class);

        $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'monto' => -100.0,
        ]);
    }

    public function test_registrar_falla_con_sentido_invalido(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $this->expectException(Exception::class);

        $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => 'inexistente',
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'monto' => 100.0,
        ]);
    }

    // ==================== anularMovimientoFiscal ====================

    public function test_anular_genera_contraasiento_y_marca_el_original_anulado(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $original = $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'monto' => 210.0,
        ]);

        $contra = $this->service->anularMovimientoFiscal($original, usuarioId: 7, motivo: 'Error de carga');

        $this->assertEquals($original->id, $contra->movimiento_anulado_id);
        $this->assertEquals(MovimientoFiscal::ESTADO_ANULADO, $contra->estado);
        $this->assertEquals(210.0, (float) $contra->monto); // monto siempre positivo
        $this->assertEquals(7, $contra->usuario_id);
        $this->assertTrue($contra->esContraasiento());

        $this->assertEquals(MovimientoFiscal::ESTADO_ANULADO, $original->fresh()->estado);

        // La posición (solo activos) no cuenta ni el original ni el contraasiento.
        $activos = MovimientoFiscal::activos()->deCuit($emisor->id)->count();
        $this->assertEquals(0, $activos);
    }

    public function test_no_se_puede_anular_dos_veces(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $original = $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'monto' => 210.0,
        ]);

        $this->service->anularMovimientoFiscal($original, 1);

        $this->expectException(Exception::class);
        $this->service->anularMovimientoFiscal($original->fresh(), 1);
    }

    public function test_no_se_puede_anular_un_contraasiento(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $original = $this->service->registrarMovimientoFiscal([
            'cuit_id' => $emisor->id,
            'impuesto_id' => $imp->id,
            'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
            'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            'fecha' => '2026-03-15',
            'monto' => 210.0,
        ]);

        $contra = $this->service->anularMovimientoFiscal($original, 1);

        $this->expectException(Exception::class);
        $this->service->anularMovimientoFiscal($contra, 1);
    }

    // ==================== registrarDesdeComprobante (RF-04, Fase 5a) ====================

    protected function comprobante(int $id, array $ivas, ?int $asociadoId = null, string $fecha = '2026-04-10', ?int $cuitId = null): ComprobanteFiscal
    {
        $c = new ComprobanteFiscal([
            'cuit_id' => $cuitId ?? $this->cuit()->id,
            'sucursal_id' => $this->sucursalId,
            'fecha_emision' => $fecha,
            'comprobante_asociado_id' => $asociadoId,
            'usuario_id' => 1,
        ]);
        $c->id = $id; // origen_id (la factura no se persiste en este unit test).
        $c->setRelation('detallesIva', collect($ivas)->map(fn ($i) => new ComprobanteFiscalIva($i)));

        return $c;
    }

    public function test_registrar_desde_comprobante_genera_debito_fiscal_por_alicuota(): void
    {
        $iva = $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $c = $this->comprobante(100, [
            ['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210],
            ['base_imponible' => 500, 'alicuota' => 10.5, 'importe' => 52.5],
            ['base_imponible' => 300, 'alicuota' => 0, 'importe' => 0], // exento: no genera
        ]);

        $this->service->registrarDesdeComprobante($c, 7);

        $movs = MovimientoFiscal::where('origen_tipo', 'ComprobanteFiscal')->where('origen_id', 100)->get();

        $this->assertCount(2, $movs);
        $this->assertTrue($movs->every(fn ($m) => $m->sentido === MovimientoFiscal::SENTIDO_APLICADO
            && $m->naturaleza === MovimientoFiscal::NATURALEZA_DEBITO_FISCAL
            && $m->impuesto_id === $iva->id));
        $this->assertEquals('2026-04', $movs->first()->periodo_fiscal);
        $this->assertEqualsCanonicalizing([210.0, 52.5], $movs->pluck('monto')->map(fn ($m) => (float) $m)->all());
    }

    public function test_registrar_desde_comprobante_es_idempotente(): void
    {
        $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');
        $c = $this->comprobante(100, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]]);

        $this->service->registrarDesdeComprobante($c, 7);
        $this->service->registrarDesdeComprobante($c, 7);

        $this->assertEquals(1, MovimientoFiscal::where('origen_tipo', 'ComprobanteFiscal')->where('origen_id', 100)->count());
    }

    public function test_no_genera_debito_fiscal_si_el_emisor_es_monotributo(): void
    {
        $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $monotributo = Cuit::firstOrCreate(
            ['numero_cuit' => '20222222223'],
            [
                'razon_social' => 'Monotributo SA',
                'condicion_iva_id' => $this->condicion(CondicionIva::RESPONSABLE_MONOTRIBUTO)->id,
                'entorno_afip' => 'testing',
                'activo' => true,
            ]
        );

        $c = $this->comprobante(200, [['base_imponible' => 1000, 'alicuota' => 0, 'importe' => 0]], cuitId: $monotributo->id);
        // Aún si viniera con importe (config errónea), no debe generar débito.
        $c->setRelation('detallesIva', collect([new ComprobanteFiscalIva(['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210])]));

        $this->service->registrarDesdeComprobante($c, 7);

        $this->assertEquals(0, MovimientoFiscal::where('origen_tipo', 'ComprobanteFiscal')->where('origen_id', 200)->count());
    }

    public function test_nota_de_credito_registra_debito_negativo_en_su_propio_periodo(): void
    {
        // Revisión Fable 2026-07-01: la NC NO anula retroactivamente el original
        // (su período puede estar ya declarado). Registra movimientos propios
        // negativos imputados al período de la NC.
        $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $factura = $this->comprobante(100, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]], fecha: '2026-04-10');
        $this->service->registrarDesdeComprobante($factura, 7);

        // NC emitida al mes siguiente (venta de abril anulada en mayo).
        $nc = $this->comprobante(101, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]], asociadoId: 100, fecha: '2026-05-02');
        $this->service->registrarDesdeComprobante($nc, 7);

        // El débito de abril queda INTACTO (abril puede estar declarado).
        $original = MovimientoFiscal::activos()->where('origen_tipo', 'ComprobanteFiscal')->where('origen_id', 100)->first();
        $this->assertNotNull($original);
        $this->assertEquals('2026-04', $original->periodo_fiscal);
        $this->assertEquals(210.0, (float) $original->monto);

        // La NC genera su propio débito NEGATIVO activo en MAYO.
        $reversa = MovimientoFiscal::activos()->where('origen_tipo', 'ComprobanteFiscal')->where('origen_id', 101)->first();
        $this->assertNotNull($reversa);
        $this->assertEquals('2026-05', $reversa->periodo_fiscal);
        $this->assertEquals(-210.0, (float) $reversa->monto);
        $this->assertEquals(-1000.0, (float) $reversa->base_imponible);

        // Neto de ambos períodos: abril +210, mayo −210 (la suma de activos da 0).
        $suma = MovimientoFiscal::activos()->where('origen_tipo', 'ComprobanteFiscal')->sum('monto');
        $this->assertEquals(0.0, (float) $suma);
    }

    public function test_nota_de_credito_es_idempotente(): void
    {
        $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');

        $factura = $this->comprobante(100, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]]);
        $this->service->registrarDesdeComprobante($factura, 7);

        $nc = $this->comprobante(101, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]], asociadoId: 100);
        $this->service->registrarDesdeComprobante($nc, 7);
        $this->service->registrarDesdeComprobante($nc, 7);

        $this->assertEquals(1, MovimientoFiscal::where('origen_tipo', 'ComprobanteFiscal')->where('origen_id', 101)->count());
    }

    // ==================== Fase 5b: percepciones aplicadas en comprobantes ====================

    public function test_calcular_tributos_incluye_codigo_arca(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $imp->update(['codigo_arca' => 7]);
        $this->config($emisor, $imp, ['alicuota' => 3.0]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->cliente(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            'AR-B'
        );

        $this->assertCount(1, $tributos);
        $this->assertSame(7, $tributos[0]['codigo_arca']);
    }

    public function test_registrar_desde_comprobante_genera_percepcion_aplicada(): void
    {
        $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');
        $percIibb = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');

        $c = $this->comprobante(300, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]]);
        $c->setRelation('tributosDetalle', collect([
            new \App\Models\ComprobanteFiscalTributo([
                'impuesto_id' => $percIibb->id,
                'base_imponible' => 1000,
                'alicuota' => 3,
                'monto' => 30,
                'codigo_arca' => 7,
            ]),
        ]));

        $this->service->registrarDesdeComprobante($c, 7);

        $percepciones = MovimientoFiscal::where('origen_tipo', 'ComprobanteFiscal')
            ->where('origen_id', 300)
            ->where('naturaleza', MovimientoFiscal::NATURALEZA_PERCEPCION)
            ->get();

        $this->assertCount(1, $percepciones);
        $this->assertEquals($percIibb->id, $percepciones->first()->impuesto_id);
        $this->assertEquals(MovimientoFiscal::SENTIDO_APLICADO, $percepciones->first()->sentido);
        $this->assertEquals(30.0, (float) $percepciones->first()->monto);
    }

    public function test_nota_de_credito_revierte_tambien_los_tributos_en_negativo(): void
    {
        $this->impuesto('iva_debito', Impuesto::TIPO_IVA, 'debito_fiscal', 'AR');
        $percIibb = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');

        $tributo = fn () => new \App\Models\ComprobanteFiscalTributo([
            'impuesto_id' => $percIibb->id, 'base_imponible' => 1000, 'alicuota' => 3, 'monto' => 30, 'codigo_arca' => 7,
        ]);

        $factura = $this->comprobante(300, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]]);
        $factura->setRelation('tributosDetalle', collect([$tributo()]));
        $this->service->registrarDesdeComprobante($factura, 7);

        // 2 movimientos activos: débito IVA + percepción.
        $this->assertEquals(2, MovimientoFiscal::activos()->where('origen_id', 300)->where('origen_tipo', 'ComprobanteFiscal')->count());

        // La NC lleva copia de los detalles del original (como crearNotaCredito).
        $nc = $this->comprobante(301, [['base_imponible' => 1000, 'alicuota' => 21, 'importe' => 210]], asociadoId: 300);
        $nc->setRelation('tributosDetalle', collect([$tributo()]));
        $this->service->registrarDesdeComprobante($nc, 7);

        // El original sigue activo; la NC registró débito y percepción negativos.
        $this->assertEquals(2, MovimientoFiscal::activos()->where('origen_id', 300)->where('origen_tipo', 'ComprobanteFiscal')->count());

        $reversas = MovimientoFiscal::activos()->where('origen_id', 301)->where('origen_tipo', 'ComprobanteFiscal')->get();
        $this->assertCount(2, $reversas);
        $this->assertEqualsCanonicalizing([-210.0, -30.0], $reversas->pluck('monto')->map(fn ($m) => (float) $m)->all());

        $percepcionNc = $reversas->firstWhere('naturaleza', MovimientoFiscal::NATURALEZA_PERCEPCION);
        $this->assertEquals(MovimientoFiscal::SENTIDO_APLICADO, $percepcionNc->sentido);
        $this->assertEquals($percIibb->id, $percepcionNc->impuesto_id);
    }

    // ==================== registrarDesdeCompra (RF-05, Fase 6) ====================

    protected function compra(int $id, ?int $cuitId, array $percepciones = [], string $fecha = '2026-05-10'): Compra
    {
        $c = new Compra([
            'cuit_id' => $cuitId,
            'sucursal_id' => $this->sucursalId,
            'fecha' => $fecha,
            'usuario_id' => 1,
        ]);
        $c->id = $id; // origen_id (la compra no se persiste; tabla compras inconsistente).
        $c->setRelation('percepciones', collect($percepciones));

        return $c;
    }

    protected function percepcion(Impuesto $impuesto, float $monto, array $extra = []): CompraPercepcion
    {
        $p = new CompraPercepcion(array_merge([
            'impuesto_id' => $impuesto->id,
            'monto' => $monto,
        ], $extra));
        $p->setRelation('impuesto', $impuesto);

        return $p;
    }

    public function test_registrar_desde_compra_genera_credito_fiscal_por_alicuota(): void
    {
        $ivaCred = $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $c = $this->compra(300, $this->cuit()->id);

        $this->service->registrarDesdeCompra($c, [
            ['base_imponible' => 1000, 'alicuota' => 21, 'monto' => 210],
            ['base_imponible' => 500, 'alicuota' => 10.5, 'monto' => 52.5],
        ], 7);

        $movs = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', 300)->get();

        $this->assertCount(2, $movs);
        $this->assertTrue($movs->every(fn ($m) => $m->sentido === MovimientoFiscal::SENTIDO_SUFRIDO
            && $m->naturaleza === MovimientoFiscal::NATURALEZA_CREDITO_FISCAL
            && $m->impuesto_id === $ivaCred->id));
        $this->assertEquals('2026-05', $movs->first()->periodo_fiscal);
        $this->assertEqualsCanonicalizing([210.0, 52.5], $movs->pluck('monto')->map(fn ($m) => (float) $m)->all());
    }

    public function test_registrar_desde_compra_genera_percepciones_sufridas(): void
    {
        $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $percImp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');

        $c = $this->compra(301, $this->cuit()->id, [
            $this->percepcion($percImp, 30.0, ['base_imponible' => 1000, 'alicuota' => 3, 'certificado_numero' => 'X-1']),
        ]);

        $this->service->registrarDesdeCompra($c, [], 7);

        $mov = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', 301)->first();
        $this->assertNotNull($mov);
        $this->assertEquals(MovimientoFiscal::SENTIDO_SUFRIDO, $mov->sentido);
        $this->assertEquals(MovimientoFiscal::NATURALEZA_PERCEPCION, $mov->naturaleza);
        $this->assertEquals($percImp->id, $mov->impuesto_id);
        $this->assertEquals(30.0, (float) $mov->monto);
        $this->assertEquals('X-1', $mov->certificado_numero);
    }

    public function test_registrar_desde_compra_sin_cuit_no_genera(): void
    {
        $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $c = $this->compra(302, null);

        $this->service->registrarDesdeCompra($c, [['base_imponible' => 1000, 'alicuota' => 21, 'monto' => 210]], 7);

        $this->assertEquals(0, MovimientoFiscal::where('origen_tipo', 'Compra')->count());
    }

    public function test_registrar_desde_compra_es_idempotente(): void
    {
        $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $c = $this->compra(303, $this->cuit()->id);
        $linea = [['base_imponible' => 1000, 'alicuota' => 21, 'monto' => 210]];

        $this->service->registrarDesdeCompra($c, $linea, 7);
        $this->service->registrarDesdeCompra($c, $linea, 7);

        $this->assertEquals(1, MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', 303)->count());
    }

    public function test_anular_desde_compra_reversa_cross_periodo(): void
    {
        // Patrón NC cross-período (spec compras-costos): la cancelación NO pisa
        // el original (su período puede estar declarado) — registra una reversa
        // NEGATIVA fechada HOY; ambos quedan activos y netean a cero.
        $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $c = $this->compra(304, $this->cuit()->id, [], '2026-05-10'); // período viejo

        $this->service->registrarDesdeCompra($c, [['base_imponible' => 1000, 'alicuota' => 21, 'monto' => 210]], 7);
        $this->service->anularDesdeCompra($c, 7);

        $movs = MovimientoFiscal::activos()->where('origen_tipo', 'Compra')->where('origen_id', 304)->get();

        $this->assertCount(2, $movs);
        $this->assertEqualsWithDelta(0.0, (float) $movs->sum('monto'), 0.001);

        $reversa = $movs->first(fn ($m) => (float) $m->monto < 0);
        $original = $movs->first(fn ($m) => (float) $m->monto > 0);
        $this->assertEquals('2026-05', $original->periodo_fiscal);
        $this->assertEquals(now()->format('Y-m'), $reversa->periodo_fiscal); // reversa en el período ACTUAL

        // Idempotente: re-anular no duplica (la suma ya es cero).
        $this->service->anularDesdeCompra($c, 7);
        $this->assertEquals(2, MovimientoFiscal::activos()->where('origen_tipo', 'Compra')->where('origen_id', 304)->count());
    }

    public function test_registrar_desde_compra_usa_fecha_comprobante_para_el_periodo(): void
    {
        // RF-06 spec compras-costos: factura de junio cargada en julio computa
        // el crédito en JUNIO (fecha_comprobante rige, no la fecha de carga).
        $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $c = $this->compra(305, $this->cuit()->id, [], '2026-07-09');
        $c->fecha_comprobante = '2026-06-15';

        $this->service->registrarDesdeCompra($c, [['base_imponible' => 1000, 'alicuota' => 21, 'monto' => 210]], 7);

        $mov = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', 305)->first();
        $this->assertEquals('2026-06', $mov->periodo_fiscal);
    }

    public function test_registrar_desde_compra_nota_credito_en_negativo(): void
    {
        // RF-21: la NC de proveedor registra la reversa del crédito con SU
        // desglose, en negativo y en el período de la NC.
        $this->impuesto('iva_credito', Impuesto::TIPO_IVA, 'credito_fiscal', 'AR');
        $nc = $this->compra(306, $this->cuit()->id, [], '2026-07-09');
        $nc->fecha_comprobante = '2026-07-05';
        $nc->compra_origen_id = 300;

        $this->service->registrarDesdeCompra($nc, [['base_imponible' => 300, 'alicuota' => 21, 'monto' => 63]], 7, esNotaCredito: true);

        $mov = MovimientoFiscal::where('origen_tipo', 'Compra')->where('origen_id', 306)->first();
        $this->assertEquals(-63.0, (float) $mov->monto);
        $this->assertEquals(-300.0, (float) $mov->base_imponible);
        $this->assertEquals('2026-07', $mov->periodo_fiscal);
    }

    // ==================== registrarDesdeConciliacion (RF-06, Fase 4a) ====================

    protected function filaConciliacion(?int $impuestoId, float $montoNeto, string $fecha = '2026-03-10', int $id = 555): ConciliacionFila
    {
        $fila = new ConciliacionFila([
            'conciliacion_cuenta_id' => 1,
            'impuesto_id' => $impuestoId,
            'monto_neto' => $montoNeto,
            'fecha' => $fecha,
        ]);
        $fila->id = $id; // origen_id del movimiento fiscal (no se persiste la fila acá).

        return $fila;
    }

    public function test_registrar_desde_conciliacion_crea_movimiento_sufrido_sin_base(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_s', Impuesto::TIPO_IIBB, 'percepcion', 'AR-S');
        $fila = $this->filaConciliacion($imp->id, -25.00, '2026-03-10', 555);

        $mov = $this->service->registrarDesdeConciliacion($fila, $cuit, 9);

        $this->assertNotNull($mov);
        $this->assertEquals(MovimientoFiscal::SENTIDO_SUFRIDO, $mov->sentido);
        $this->assertEquals('percepcion', $mov->naturaleza);
        $this->assertEquals($imp->id, $mov->impuesto_id);
        $this->assertEquals(25.00, (float) $mov->monto); // monto siempre positivo
        $this->assertEquals('2026-03', $mov->periodo_fiscal);
        $this->assertEquals('ConciliacionFila', $mov->origen_tipo);
        $this->assertEquals(555, $mov->origen_id);
        $this->assertNull($mov->base_imponible); // 4b
    }

    public function test_registrar_desde_conciliacion_es_idempotente(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_s', Impuesto::TIPO_IIBB, 'percepcion', 'AR-S');
        $fila = $this->filaConciliacion($imp->id, -25.00, '2026-03-10', 555);

        $this->service->registrarDesdeConciliacion($fila, $cuit, 9);
        $segundo = $this->service->registrarDesdeConciliacion($fila, $cuit, 9);

        $this->assertNull($segundo);
        $this->assertEquals(1, MovimientoFiscal::where('origen_tipo', 'ConciliacionFila')->where('origen_id', 555)->count());
    }

    public function test_registrar_desde_conciliacion_sin_impuesto_no_registra(): void
    {
        $cuit = $this->cuit();
        $fila = $this->filaConciliacion(null, -25.00, '2026-03-10', 556);

        $this->assertNull($this->service->registrarDesdeConciliacion($fila, $cuit, 9));
        $this->assertEquals(0, MovimientoFiscal::query()->count());
    }

    // ==================== validarImpuestoSufrido (RF-06/D4, Fase 4a) ====================

    public function test_validar_alerta_si_el_cuit_no_tiene_configurado_el_impuesto(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_s', Impuesto::TIPO_IIBB, 'percepcion', 'AR-S');
        $fila = $this->filaConciliacion($imp->id, -25.00);

        $alerta = $this->service->validarImpuestoSufrido($fila, $cuit);

        $this->assertNotNull($alerta);
        $this->assertStringContainsString($imp->nombre, $alerta);
    }

    public function test_validar_sin_alerta_si_el_cuit_tiene_el_impuesto_configurado(): void
    {
        $cuit = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_s', Impuesto::TIPO_IIBB, 'percepcion', 'AR-S');
        $this->config($cuit, $imp); // inscripto = true

        $fila = $this->filaConciliacion($imp->id, -25.00);

        $this->assertNull($this->service->validarImpuestoSufrido($fila, $cuit));
    }

    public function test_validar_sin_impuesto_devuelve_null(): void
    {
        $cuit = $this->cuit();
        $fila = $this->filaConciliacion(null, -25.00);

        $this->assertNull($this->service->validarImpuestoSufrido($fila, $cuit));
    }

    // ==================== configVigente ====================

    public function test_config_vigente_devuelve_la_config_actual(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $config = $this->config($emisor, $imp, ['alicuota' => 2.5]);

        $resultado = $this->service->configVigente($emisor, $imp->id);

        $this->assertNotNull($resultado);
        $this->assertEquals($config->id, $resultado->id);
    }

    public function test_config_vigente_gana_la_de_vigencia_mas_reciente(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');

        $vieja = $this->config($emisor, $imp, ['alicuota' => 2.0, 'vigente_desde' => '2026-01-01']);
        $nueva = $this->config($emisor, $imp, ['alicuota' => 3.5, 'vigente_desde' => '2026-06-01']);

        $resultado = $this->service->configVigente($emisor, $imp->id, now());

        $this->assertEquals($nueva->id, $resultado->id);
        $this->assertEquals(3.5, (float) $resultado->alicuota);
    }

    public function test_config_vigente_devuelve_null_si_no_hay_vigente(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['vigente_desde' => '2030-01-01']); // futura

        $resultado = $this->service->configVigente($emisor, $imp->id, now());

        $this->assertNull($resultado);
    }
}

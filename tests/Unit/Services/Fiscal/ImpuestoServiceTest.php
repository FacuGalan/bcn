<?php

namespace Tests\Unit\Services\Fiscal;

use App\Models\ConciliacionFila;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Models\Sucursal;
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
            'alicuota' => 3.0,
            'origen_alicuota' => CuitImpuestoConfig::ORIGEN_MANUAL,
        ], $extra));
    }

    protected function sucursalCon(?string $provincia): Sucursal
    {
        // calcularTributos solo lee ->provincia; un modelo no persistido alcanza.
        return new Sucursal(['provincia' => $provincia]);
    }

    // ==================== calcularTributos: percepción IIBB ====================

    public function test_percepcion_iibb_a_ri_en_la_jurisdiccion_de_la_sucursal(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-B')
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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-C') // CABA, distinta jurisdicción
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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
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
            $this->condicion(CondicionIva::CONSUMIDOR_FINAL),
            1000.0,
            $this->sucursalCon('AR-B')
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
            $this->condicion(CondicionIva::RESPONSABLE_MONOTRIBUTO),
            1000.0,
            $this->sucursalCon('AR-B')
        );

        $this->assertSame([], $tributos);
    }

    public function test_no_percibe_si_el_receptor_es_null(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $tributos = $this->service->calcularTributos($emisor, null, 1000.0, $this->sucursalCon('AR-B'));

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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-B')
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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-B')
        );

        $this->assertSame([], $tributos);
    }

    public function test_respeta_la_fecha_de_la_operacion_para_la_vigencia(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['alicuota' => 3.0, 'vigente_hasta' => '2026-03-31']);

        $receptor = $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO);
        $sucursal = $this->sucursalCon('AR-B');

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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-B')
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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            2000.0,
            $this->sucursalCon(null) // sin provincia: IVA nacional igual aplica
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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0, // < 5000
            $this->sucursalCon('AR-B')
        );

        $this->assertSame([], $tributos);
    }

    public function test_no_percibe_con_neto_cero_o_negativo(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp);

        $this->assertSame([], $this->service->calcularTributos(
            $emisor, $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO), 0.0, $this->sucursalCon('AR-B')
        ));
        $this->assertSame([], $this->service->calcularTributos(
            $emisor, $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO), -100.0, $this->sucursalCon('AR-B')
        ));
    }

    public function test_no_percibe_si_la_config_no_esta_vigente(): void
    {
        $emisor = $this->cuit();
        $imp = $this->impuesto('perc_iibb_ar_b', Impuesto::TIPO_IIBB, 'percepcion', 'AR-B');
        $this->config($emisor, $imp, ['vigente_hasta' => now()->subDay()->toDateString()]);

        $tributos = $this->service->calcularTributos(
            $emisor,
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-B')
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
            $this->condicion(CondicionIva::RESPONSABLE_INSCRIPTO),
            1000.0,
            $this->sucursalCon('AR-B')
        );

        $this->assertCount(2, $tributos);
        $montosPorCodigo = collect($tributos)->pluck('monto', 'codigo');
        $this->assertEquals(30.0, $montosPorCodigo['perc_iibb_ar_b']);
        $this->assertEquals(15.0, $montosPorCodigo['perc_iva']);
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

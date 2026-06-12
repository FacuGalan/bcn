<?php

namespace Tests\Integration\Services;

use App\Models\ConceptoMovimientoCuenta;
use App\Models\ConciliacionCuenta;
use App\Models\ConciliacionFila;
use App\Models\CuentaEmpresa;
use App\Models\FormaPago;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MovimientoCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use App\Services\IntegracionesPago\ConciliacionCuentaService;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 3 — ConciliacionCuentaService (conciliación contra el proveedor).
 *
 * Cubre la máquina de estados de la corrida, el match (todas las
 * clasificaciones), la idempotencia cross-corrida, aplicar/descartar, el
 * ajuste inicial (RF-07) y las corridas programadas (RF-08). El gateway MP se
 * fakea a nivel HTTP (Http::fake) para ejercitar también el parseo real.
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (Fase 3).
 */
class ConciliacionCuentaServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    private ConciliacionCuentaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        if (! IntegracionPago::porCodigo('mercadopago_qr')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        // Conceptos de ledger que la conciliación usa (el fixture de testing
        // no corre el seed de provision).
        $conceptos = [
            ['codigo' => 'cobro_integracion', 'nombre' => 'Cobro por integración de pago', 'tipo' => 'ingreso'],
            ['codigo' => 'comision_integracion', 'nombre' => 'Comisión del proveedor de pago', 'tipo' => 'egreso'],
            ['codigo' => 'retiro_integracion', 'nombre' => 'Retiro a banco desde el proveedor', 'tipo' => 'egreso'],
            ['codigo' => 'devolucion_integracion', 'nombre' => 'Devolución/contracargo en el proveedor', 'tipo' => 'egreso'],
            ['codigo' => 'acreditacion_integracion', 'nombre' => 'Acreditación en el proveedor de pago', 'tipo' => 'ingreso'],
            ['codigo' => 'ajuste_conciliacion', 'nombre' => 'Ajuste por conciliación', 'tipo' => 'ambos'],
            ['codigo' => 'impuesto_integracion', 'nombre' => 'Impuestos y retenciones del proveedor de pago', 'tipo' => 'egreso'],
        ];
        foreach ($conceptos as $i => $concepto) {
            ConceptoMovimientoCuenta::firstOrCreate(
                ['codigo' => $concepto['codigo']],
                $concepto + ['es_sistema' => true, 'orden' => 12 + $i, 'activo' => true],
            );
        }

        $this->service = app(ConciliacionCuentaService::class);
    }

    protected function tearDown(): void
    {
        ConciliacionFila::query()->delete();
        ConciliacionCuenta::query()->delete();
        IntegracionPagoTransaccion::query()->delete();
        IntegracionPagoSucursal::query()->delete();
        MovimientoCuentaEmpresa::query()->delete();
        CuentaEmpresa::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    // ==================== Helpers ====================

    private function crearConfigProd(string $userId = '999888777'): IntegracionPagoSucursal
    {
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');

        return IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mpId,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'produccion',
            'access_token_produccion' => 'APP_USR-PROD-CONC',
            'user_id_externo' => $userId,
            'activo' => true,
        ]);
    }

    private function crearCuentaVinculada(string $identificador = '999888777'): CuentaEmpresa
    {
        return CuentaEmpresa::create([
            'nombre' => 'Mercado Pago '.$identificador,
            'tipo' => CuentaEmpresa::TIPO_BILLETERA,
            'subtipo' => 'mercadopago',
            'identificador_externo' => $identificador,
            'activo' => true,
        ]);
    }

    /**
     * Transacción confirmada + su movimiento de ledger (como lo deja el Paso 2).
     */
    private function crearCobroDelSistema(CuentaEmpresa $cuenta, IntegracionPagoSucursal $config, string $ref, float $monto, ?\Illuminate\Support\Carbon $confirmadoEn = null): IntegracionPagoTransaccion
    {
        $formaPago = FormaPago::firstOrCreate(
            ['codigo' => 'QR_CONC'],
            ['nombre' => 'QR Conciliación', 'concepto' => 'wallet', 'activo' => true],
        );

        $transaccion = IntegracionPagoTransaccion::create([
            'integracion_pago_sucursal_id' => $config->id,
            'forma_pago_id' => $formaPago->id,
            'sucursal_id' => $this->sucursalId,
            'usuario_iniciador_id' => 1,
            'modo_usado' => 'qr_dinamico',
            'monto' => $monto,
            'external_reference' => $ref,
            'estado' => IntegracionPagoTransaccion::ESTADO_CONFIRMADO,
            'confirmado_en' => $confirmadoEn ?? now(),
        ]);

        CuentaEmpresaService::registrarMovimientoAutomatico(
            $cuenta, 'ingreso', $monto, 'cobro_integracion',
            'IntegracionPagoTransaccion', $transaccion->id,
            "Cobro #{$transaccion->id}", 1, $this->sucursalId,
        );

        return $transaccion;
    }

    private function fakearReporteListo(string $csv): void
    {
        Http::fake([
            '*/v1/account/settlement_report/config' => Http::response(['columns' => [['key' => 'DATE']]], 200),
            '*/v1/account/settlement_report/list' => Http::response([[
                'file_name' => 'rep-conc.csv',
                'begin_date' => now()->subDays(7)->format('Y-m-d').'T00:00:00Z',
                'end_date' => now()->format('Y-m-d').'T23:59:59Z',
            ]], 200),
            '*/v1/account/settlement_report/rep-conc.csv' => Http::response($csv, 200),
            '*/v1/account/settlement_report' => Http::response([], 202),
        ]);
    }

    /**
     * Crea la corrida y la avanza con el comando-motor hasta pendiente_revision
     * (dos pasadas: solicitar + descargar/matchear).
     */
    private function conciliarConReporte(CuentaEmpresa $cuenta, string $csv): ConciliacionCuenta
    {
        $this->fakearReporteListo($csv);

        $corrida = $this->service->crearCorrida($cuenta, now()->subDays(7), now(), 1);
        $this->service->procesarPendientes(); // solicita
        $this->service->procesarPendientes(); // descarga + match

        return $corrida->refresh();
    }

    private function csvHeader(): string
    {
        return 'TRANSACTION_TYPE,SOURCE_ID,EXTERNAL_REFERENCE,TRANSACTION_DATE,TRANSACTION_AMOUNT,FEE_AMOUNT,SETTLEMENT_NET_AMOUNT';
    }

    // ==================== Crear corrida (RF-03) ====================

    public function test_crear_corrida_deja_estado_generando_con_snapshot_de_saldo(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $cuenta->update(['saldo_actual' => 1234.56]);

        $corrida = $this->service->crearCorrida($cuenta->refresh(), now()->subDays(7), now(), 99);

        $this->assertSame(ConciliacionCuenta::ESTADO_GENERANDO, $corrida->estado);
        $this->assertEquals(1234.56, (float) $corrida->saldo_sistema);
        $this->assertSame(99, $corrida->usuario_id);
        $this->assertNull($corrida->solicitud_reporte);
    }

    public function test_crear_corrida_falla_sin_identificador_externo(): void
    {
        $cuenta = CuentaEmpresa::create([
            'nombre' => 'Cuenta manual', 'tipo' => 'banco', 'subtipo' => 'otro', 'activo' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no está vinculada');

        $this->service->crearCorrida($cuenta, now()->subDay(), now(), 1);
    }

    public function test_crear_corrida_falla_sin_config_produccion(): void
    {
        // Config en modo TEST: la identidad no es resoluble en producción.
        $mpId = IntegracionPago::porCodigo('mercadopago_qr')->value('id');
        IntegracionPagoSucursal::create([
            'integracion_pago_id' => $mpId,
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-TOKEN',
            'user_id_externo' => '999888777',
            'activo' => true,
        ]);
        $cuenta = $this->crearCuentaVinculada();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('producción');

        $this->service->crearCorrida($cuenta, now()->subDay(), now(), 1);
    }

    public function test_no_permite_segunda_corrida_activa_para_la_misma_cuenta(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $this->service->crearCorrida($cuenta, now()->subDays(7), now(), 1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('en curso');

        $this->service->crearCorrida($cuenta, now()->subDay(), now(), 1);
    }

    // ==================== Avance asíncrono (RF-04) ====================

    public function test_procesar_solicita_el_reporte_y_la_corrida_sigue_generando(): void
    {
        Http::fake([
            '*/v1/account/settlement_report/config' => Http::response(['columns' => [['key' => 'DATE']]], 200),
            '*/v1/account/settlement_report/list' => Http::response([], 200),
            '*/v1/account/settlement_report' => Http::response([], 202),
        ]);

        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $corrida = $this->service->crearCorrida($cuenta, now()->subDays(7), now(), 1);

        $this->service->procesarPendientes();

        $corrida->refresh();
        $this->assertSame(ConciliacionCuenta::ESTADO_GENERANDO, $corrida->estado);
        $this->assertNotNull($corrida->solicitud_reporte);
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/account/settlement_report'));
    }

    public function test_corrida_generando_vencida_pasa_a_error(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $corrida = $this->service->crearCorrida($cuenta, now()->subDays(7), now(), 1);

        ConciliacionCuenta::where('id', $corrida->id)->update(['created_at' => now()->subHours(2)]);

        $this->service->procesarPendientes();

        $corrida->refresh();
        $this->assertSame(ConciliacionCuenta::ESTADO_ERROR, $corrida->estado);
        $this->assertNotNull($corrida->error_mensaje);
    }

    // ==================== Match (RF-05) ====================

    public function test_match_clasifica_cobros_comisiones_solo_proveedor_y_solo_sistema(): void
    {
        $config = $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $this->crearCobroDelSistema($cuenta, $config, 'BCN-TX-1', 1000.00);
        // Cobro del sistema SIN contraparte en el reporte → solo_sistema.
        $this->crearCobroDelSistema($cuenta, $config, 'BCN-TX-2', 800.00);

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "SETTLEMENT,111,BCN-TX-1,{$fecha},1000.00,-41.00,959.00",
            "SETTLEMENT,222,REF-EXTERNA,{$fecha},500.00,-20.00,480.00",
            "WITHDRAWAL,333,,{$fecha},-3000.00,0,-3000.00",
            "SOMETHING_NEW,444,,{$fecha},750.00,0,750.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);

        $this->assertSame(ConciliacionCuenta::ESTADO_PENDIENTE_REVISION, $corrida->estado);
        $this->assertSame('rep-conc.csv', $corrida->archivo_reporte);

        $filas = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->get();

        // Cobro matcheado por external_reference + su comisión propuesta.
        $matcheado = $filas->firstWhere('id_externo', '111');
        $this->assertSame(ConciliacionFila::CLASIFICACION_MATCHEADO, $matcheado->clasificacion);
        $this->assertNotNull($matcheado->integracion_pago_transaccion_id);

        $comision = $filas->where('tipo', ConciliacionFila::TIPO_COMISION)->first();
        $this->assertNotNull($comision);
        $this->assertSame(ConciliacionFila::ACCION_GENERAR_MOVIMIENTO, $comision->accion);
        $this->assertSame('egreso', $comision->tipo_movimiento);
        $this->assertSame('comision_integracion', $comision->concepto_codigo);
        $this->assertEquals(41.00, (float) $comision->monto_neto);

        // Cobro del reporte sin transacción → solo_proveedor con ingreso propuesto.
        $cobroExterno = $filas->firstWhere('id_externo', '222');
        $this->assertSame(ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR, $cobroExterno->clasificacion);
        $this->assertSame('ingreso', $cobroExterno->tipo_movimiento);
        $this->assertSame('acreditacion_integracion', $cobroExterno->concepto_codigo);

        // Retiro → egreso retiro_integracion.
        $retiro = $filas->firstWhere('id_externo', '333');
        $this->assertSame('egreso', $retiro->tipo_movimiento);
        $this->assertSame('retiro_integracion', $retiro->concepto_codigo);

        // Tipo desconocido con crédito → acreditación propuesta... pero el
        // gateway lo normaliza; acá llega como acreditacion (ver gateway test).
        $otro = $filas->firstWhere('id_externo', '444');
        $this->assertSame(ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR, $otro->clasificacion);

        // Cobro del sistema sin fila en el reporte → alerta solo_sistema sin propuesta.
        $soloSistema = $filas->firstWhere('clasificacion', ConciliacionFila::CLASIFICACION_SOLO_SISTEMA);
        $this->assertNotNull($soloSistema);
        $this->assertSame('BCN-TX-2', $soloSistema->referencia);
        $this->assertSame(ConciliacionFila::ACCION_SIN_ACCION, $soloSistema->accion);
        $this->assertNull($soloSistema->tipo_movimiento);

        // Contadores.
        $this->assertSame(1, $corrida->total_matcheados);
        $this->assertSame(3, $corrida->total_solo_proveedor);
        $this->assertSame(1, $corrida->total_solo_sistema);
    }

    public function test_crear_corrida_falla_con_periodo_mayor_al_limite_del_proveedor(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('60');

        $this->service->crearCorrida($cuenta, now()->subDays(61), now(), 1);
    }

    public function test_cobro_con_retenciones_propone_impuesto_por_el_residuo(): void
    {
        $config = $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $this->crearCobroDelSistema($cuenta, $config, 'BCN-TX-1', 1000.00);

        // Neto 940: bruto 1000 - comisión 41 - retenciones 19.
        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "SETTLEMENT,111,BCN-TX-1,{$fecha},1000.00,-41.00,940.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);
        $filas = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->get();

        $impuesto = $filas->firstWhere('tipo', ConciliacionFila::TIPO_IMPUESTO);
        $this->assertNotNull($impuesto, 'El residuo bruto - comisión - neto debe proponerse como impuesto');
        $this->assertSame(ConciliacionFila::CLASIFICACION_MATCHEADO, $impuesto->clasificacion);
        $this->assertSame(ConciliacionFila::ACCION_GENERAR_MOVIMIENTO, $impuesto->accion);
        $this->assertSame('egreso', $impuesto->tipo_movimiento);
        $this->assertSame('impuesto_integracion', $impuesto->concepto_codigo);
        $this->assertEquals(19.00, (float) $impuesto->monto_neto);

        // Al aplicar el saldo converge al neto real: 1000 - 41 - 19 = 940.
        $this->service->aplicar($corrida, 7);
        $this->assertEquals(940.00, (float) $cuenta->fresh()->saldo_actual);
    }

    public function test_filas_de_impuestos_del_reporte_proponen_movimiento_con_el_signo_correcto(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        // tax_payment_iibb contiene "payment": sin el mapeo de tax iría a
        // cobro y propondría un INGRESO por un monto negativo.
        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "TAX_PAYMENT_IIBB,555,,{$fecha},-25.00,0,-25.00",
            "TAX_PAYMENT_IIBB_CANCEL,556,,{$fecha},10.00,0,10.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);
        $filas = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->get();

        $percepcion = $filas->firstWhere('id_externo', '555');
        $this->assertSame(ConciliacionFila::TIPO_IMPUESTO, $percepcion->tipo);
        $this->assertSame(ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR, $percepcion->clasificacion);
        $this->assertSame(ConciliacionFila::ACCION_GENERAR_MOVIMIENTO, $percepcion->accion);
        $this->assertSame('egreso', $percepcion->tipo_movimiento);
        $this->assertSame('impuesto_integracion', $percepcion->concepto_codigo);

        // Devolución de percepción (neto positivo) → ingreso.
        $devolucion = $filas->firstWhere('id_externo', '556');
        $this->assertSame(ConciliacionFila::TIPO_IMPUESTO, $devolucion->tipo);
        $this->assertSame('ingreso', $devolucion->tipo_movimiento);
        $this->assertSame('acreditacion_integracion', $devolucion->concepto_codigo);

        // Aplicar: -25 + 10 = -15.
        $this->service->aplicar($corrida, 7);
        $this->assertEquals(-15.00, (float) $cuenta->fresh()->saldo_actual);
    }

    public function test_cobro_viejo_liquidado_tarde_matchea_y_no_duplica_ingreso(): void
    {
        $config = $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        // Confirmado 20 días atrás, FUERA del período de 7 días de la corrida:
        // el lag del proveedor puede traerlo recién en este reporte.
        $this->crearCobroDelSistema($cuenta, $config, 'BCN-TX-VIEJO', 500.00, now()->subDays(20));
        // Otro cobro viejo que NO viene en el reporte: no debe alertarse
        // como solo_sistema (está fuera del período).
        $this->crearCobroDelSistema($cuenta, $config, 'BCN-TX-VIEJO-2', 300.00, now()->subDays(20));

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "SETTLEMENT,111,BCN-TX-VIEJO,{$fecha},500.00,-5.00,495.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);
        $filas = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->get();

        // Matchea contra la transacción vieja: NO propone ingreso duplicado.
        $cobro = $filas->firstWhere('id_externo', '111');
        $this->assertSame(ConciliacionFila::CLASIFICACION_MATCHEADO, $cobro->clasificacion);
        $this->assertSame(ConciliacionFila::ACCION_SIN_ACCION, $cobro->accion);

        // Sin alertas solo_sistema por cobros fuera del período.
        $this->assertSame(0, $filas->where('clasificacion', ConciliacionFila::CLASIFICACION_SOLO_SISTEMA)->count());

        // Solo la comisión se propone: el saldo queda 800 (cobros) - 5 = 795.
        $this->service->aplicar($corrida, 7);
        $this->assertEquals(795.00, (float) $cuenta->fresh()->saldo_actual);
    }

    // ==================== Aplicar (RF-06) ====================

    public function test_aplicar_genera_movimientos_y_actualiza_saldo(): void
    {
        $config = $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $this->crearCobroDelSistema($cuenta, $config, 'BCN-TX-1', 1000.00); // saldo: 1000

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "SETTLEMENT,111,BCN-TX-1,{$fecha},1000.00,-41.00,959.00",
            "WITHDRAWAL,333,,{$fecha},-500.00,0,-500.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);
        $aplicada = $this->service->aplicar($corrida, 7);

        $this->assertSame(ConciliacionCuenta::ESTADO_APLICADA, $aplicada->estado);
        $this->assertSame(7, $aplicada->aplicada_por);
        $this->assertNotNull($aplicada->aplicada_en);

        // 1 cobro previo + comisión (egreso 41) + retiro (egreso 500) = 3 movimientos.
        $this->assertSame(3, MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)->count());
        $this->assertEquals(1000 - 41 - 500, (float) $cuenta->fresh()->saldo_actual);

        // Origen polimórfico hacia la fila + link inverso.
        $movComision = MovimientoCuentaEmpresa::where('origen_tipo', 'ConciliacionFila')
            ->get()
            ->first(fn ($m) => (float) $m->monto === 41.00);
        $this->assertNotNull($movComision);
        $fila = ConciliacionFila::find($movComision->origen_id);
        $this->assertSame($movComision->id, $fila->movimiento_cuenta_empresa_id);
    }

    public function test_aplicar_respeta_filas_ignoradas(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "WITHDRAWAL,333,,{$fecha},-500.00,0,-500.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);

        ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)
            ->update(['accion' => ConciliacionFila::ACCION_IGNORAR]);

        $this->service->aplicar($corrida, 7);

        $this->assertSame(0, MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)->count());
        $this->assertEquals(0, (float) $cuenta->fresh()->saldo_actual);
    }

    public function test_aplicar_dos_veces_no_duplica(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "WITHDRAWAL,333,,{$fecha},-500.00,0,-500.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);

        $this->service->aplicar($corrida, 7);
        $this->service->aplicar($corrida->refresh(), 7); // idempotente

        $this->assertSame(1, MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)->count());
        $this->assertEquals(-500, (float) $cuenta->fresh()->saldo_actual);
    }

    public function test_reconciliar_periodo_solapado_marca_ya_registrado_y_no_duplica(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "WITHDRAWAL,333,,{$fecha},-500.00,0,-500.00",
        ]);

        $primera = $this->conciliarConReporte($cuenta, $csv);
        $this->service->aplicar($primera, 7);

        // Segunda corrida del MISMO período con el MISMO reporte.
        $segunda = $this->conciliarConReporte($cuenta, $csv);

        $filaRepetida = ConciliacionFila::where('conciliacion_cuenta_id', $segunda->id)
            ->where('id_externo', '333')->first();
        $this->assertSame(ConciliacionFila::CLASIFICACION_YA_REGISTRADO, $filaRepetida->clasificacion);
        $this->assertSame(ConciliacionFila::ACCION_SIN_ACCION, $filaRepetida->accion);

        $this->service->aplicar($segunda, 7);

        // Sigue habiendo UN solo movimiento por el retiro 333.
        $this->assertSame(1, MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)->count());
        $this->assertEquals(-500, (float) $cuenta->fresh()->saldo_actual);
    }

    public function test_descartar_no_toca_el_ledger_y_libera_la_cuenta(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "WITHDRAWAL,333,,{$fecha},-500.00,0,-500.00",
        ]);

        $corrida = $this->conciliarConReporte($cuenta, $csv);
        $this->service->descartar($corrida, 7);

        $this->assertSame(ConciliacionCuenta::ESTADO_DESCARTADA, $corrida->refresh()->estado);
        $this->assertSame(0, MovimientoCuentaEmpresa::count());

        // La cuenta queda libre para una corrida nueva.
        $nueva = $this->service->crearCorrida($cuenta, now()->subDay(), now(), 1);
        $this->assertSame(ConciliacionCuenta::ESTADO_GENERANDO, $nueva->estado);
    }

    // ==================== Ajuste inicial (RF-07) ====================

    public function test_ajuste_inicial_converge_al_saldo_real_informado(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        // Reporte con un retiro de $500: tras aplicarlo el ledger queda en -500.
        $fecha = now()->subDay()->format('Y-m-d\TH:i:s.000-04:00');
        $csv = implode("\n", [
            $this->csvHeader(),
            "WITHDRAWAL,333,,{$fecha},-500.00,0,-500.00",
        ]);
        $corrida = $this->conciliarConReporte($cuenta, $csv);

        // El usuario informa que el saldo REAL total del proveedor es $750:
        // el ajuste se calcula DESPUÉS de los movimientos (750 - (-500) = 1250)
        // y la cuenta queda exactamente en el saldo real.
        $this->service->aplicar($corrida, 7, 750.00);

        $ajuste = MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)
            ->get()
            ->first(fn ($m) => (float) $m->monto === 1250.00);
        $this->assertNotNull($ajuste, 'Debe generarse el ajuste por la diferencia post-conciliación');
        $this->assertSame('ingreso', $ajuste->tipo);
        $this->assertSame('ConciliacionFila', $ajuste->origen_tipo);
        $this->assertEquals(750.00, (float) $cuenta->fresh()->saldo_actual, 'La cuenta debe quedar en el saldo real informado');

        $fila = ConciliacionFila::find($ajuste->origen_id);
        $this->assertSame(ConciliacionFila::TIPO_AJUSTE_INICIAL, $fila->tipo);
    }

    public function test_ajuste_inicial_solo_aplica_en_la_primera_conciliacion(): void
    {
        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();

        $primera = $this->conciliarConReporte($cuenta, $this->csvHeader());
        $this->service->aplicar($primera, 7, 750.00); // genera el ajuste

        $segunda = $this->conciliarConReporte($cuenta, $this->csvHeader());
        $this->service->aplicar($segunda, 7, 999.99); // NO debe generar otro

        $ajustes = MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)->count();
        $this->assertSame(1, $ajustes);
        $this->assertEquals(750.00, (float) $cuenta->fresh()->saldo_actual);
    }

    // ==================== Corridas programadas (RF-08) ====================

    public function test_conciliacion_automatica_crea_la_corrida_diaria_una_sola_vez(): void
    {
        Http::fake([
            '*/v1/account/settlement_report/config' => Http::response(['columns' => [['key' => 'DATE']]], 200),
            '*/v1/account/settlement_report/list' => Http::response([], 200),
            '*/v1/account/settlement_report' => Http::response([], 202),
        ]);

        $this->crearConfigProd();
        $cuenta = $this->crearCuentaVinculada();
        $cuenta->update(['conciliacion_automatica' => true]);

        $this->service->procesarPendientes();

        $corrida = ConciliacionCuenta::deCuenta($cuenta->id)->first();
        $this->assertNotNull($corrida);
        $this->assertSame(ConciliacionCuenta::ORIGEN_PROGRAMADA, $corrida->origen);
        $this->assertNull($corrida->usuario_id);
        $this->assertSame(now()->subDay()->toDateString(), $corrida->desde->toDateString());
        // NUNCA auto-aplica: nace generando y a lo sumo llega a pendiente_revision.
        $this->assertSame(ConciliacionCuenta::ESTADO_GENERANDO, $corrida->estado);

        // Segunda pasada: no duplica la corrida del día.
        $this->service->procesarPendientes();
        $this->assertSame(1, ConciliacionCuenta::deCuenta($cuenta->id)->count());
    }

    public function test_sin_flag_de_conciliacion_automatica_no_crea_corridas(): void
    {
        $this->crearConfigProd();
        $this->crearCuentaVinculada(); // flag default false

        $this->service->procesarPendientes();

        $this->assertSame(0, ConciliacionCuenta::count());
    }
}

<?php

namespace App\Services\IntegracionesPago;

use App\Models\ConciliacionCuenta;
use App\Models\ConciliacionFila;
use App\Models\CuentaEmpresa;
use App\Models\Impuesto;
use App\Models\IntegracionPagoSucursal;
use App\Models\IntegracionPagoTransaccion;
use App\Models\MovimientoCuentaEmpresa;
use App\Services\CuentaEmpresaService;
use App\Services\Fiscal\ImpuestoService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Conciliación del ledger de una CuentaEmpresa contra los movimientos reales
 * del proveedor de pago (Paso 3 de integraciones-pago).
 *
 * Flujo: crearCorrida() (estado generando) → el comando conciliaciones:procesar
 * solicita/descarga el reporte del proveedor (asíncrono) y ejecuta el match →
 * pendiente_revision → el usuario revisa el detalle y aplica() (genera los
 * MovimientoCuentaEmpresa propuestos, origen 'ConciliacionFila') o descarta().
 *
 * Provider-agnostic: el acceso al proveedor pasa por el gateway de la config
 * (solicitarReporteCuenta/obtenerReporteCuenta del contrato).
 *
 * Ref: .claude/specs/conciliacion-mercadopago.md (RF-03..RF-08).
 */
class ConciliacionCuentaService
{
    /** Minutos máximos esperando el reporte del proveedor antes de marcar error. */
    private const TIMEOUT_GENERANDO_MINUTOS = 60;

    /** Tolerancia para considerar saldos iguales (centavos). */
    private const EPSILON = 0.01;

    /**
     * Conceptos del movimiento propuesto según el tipo normalizado de la fila
     * solo-proveedor. retiro_cancelado revierte un retiro → ingreso.
     */
    private const PROPUESTAS_SOLO_PROVEEDOR = [
        ConciliacionFila::TIPO_COBRO => ['ingreso', 'acreditacion_integracion'],
        ConciliacionFila::TIPO_ACREDITACION => ['ingreso', 'acreditacion_integracion'],
        ConciliacionFila::TIPO_RETIRO_CANCELADO => ['ingreso', 'acreditacion_integracion'],
        ConciliacionFila::TIPO_DEVOLUCION => ['egreso', 'devolucion_integracion'],
        ConciliacionFila::TIPO_CONTRACARGO => ['egreso', 'devolucion_integracion'],
        ConciliacionFila::TIPO_RETIRO => ['egreso', 'retiro_integracion'],
        ConciliacionFila::TIPO_IMPUESTO => ['egreso', 'impuesto_integracion'],
    ];

    /** Límite del proveedor: los reportes cubren hasta 60 días (doc MP). */
    private const MAX_DIAS_PERIODO = 60;

    public function __construct(private ImpuestoService $impuestoService) {}

    /**
     * Crea una corrida de conciliación en estado `generando` (RF-03).
     *
     * @throws \RuntimeException si la cuenta no es conciliable o ya tiene una corrida activa
     */
    public function crearCorrida(
        CuentaEmpresa $cuenta,
        CarbonInterface $desde,
        CarbonInterface $hasta,
        ?int $usuarioId,
        string $origen = ConciliacionCuenta::ORIGEN_MANUAL
    ): ConciliacionCuenta {
        if (empty($cuenta->identificador_externo)) {
            throw new \RuntimeException(__('La cuenta no está vinculada a un proveedor de pago (sin identificador externo)'));
        }

        if ($desde->greaterThan($hasta)) {
            throw new \RuntimeException(__('El período de conciliación es inválido'));
        }

        if ($desde->diffInDays($hasta) > self::MAX_DIAS_PERIODO) {
            throw new \RuntimeException(__('El proveedor solo genera reportes de hasta :dias días', ['dias' => self::MAX_DIAS_PERIODO]));
        }

        if ($this->resolverConfigParaCuenta($cuenta) === null) {
            throw new \RuntimeException(__('No hay ninguna integración de pago en producción asociada a esta cuenta'));
        }

        return DB::connection('pymes_tenant')->transaction(function () use ($cuenta, $desde, $hasta, $usuarioId, $origen) {
            $hayActiva = ConciliacionCuenta::deCuenta($cuenta->id)->activas()->lockForUpdate()->exists();

            if ($hayActiva) {
                throw new \RuntimeException(__('Ya hay una conciliación en curso para esta cuenta'));
            }

            $corrida = ConciliacionCuenta::create([
                'cuenta_empresa_id' => $cuenta->id,
                'desde' => $desde->toDateString(),
                'hasta' => $hasta->toDateString(),
                'estado' => ConciliacionCuenta::ESTADO_GENERANDO,
                'origen' => $origen,
                'saldo_sistema' => $cuenta->saldo_actual,
                'usuario_id' => $usuarioId,
            ]);

            Log::info('ConciliacionCuentaService::crearCorrida', [
                'conciliacion_id' => $corrida->id,
                'cuenta_empresa_id' => $cuenta->id,
                'periodo' => "{$corrida->desde->toDateString()}..{$corrida->hasta->toDateString()}",
                'origen' => $origen,
            ]);

            return $corrida;
        });
    }

    /**
     * Config de integración (producción, activa) cuya identidad de cuenta
     * coincide con la CuentaEmpresa. Cualquier config sirve para pedir el
     * reporte: todas las que comparten identidad ven la misma cuenta real.
     */
    public function resolverConfigParaCuenta(CuentaEmpresa $cuenta): ?IntegracionPagoSucursal
    {
        if (empty($cuenta->identificador_externo)) {
            return null;
        }

        return IntegracionPagoSucursal::with('integracion')
            ->where('activo', true)
            ->get()
            ->first(function (IntegracionPagoSucursal $config) use ($cuenta) {
                if (! $config->esProduccion() || ! $config->integracion) {
                    return false;
                }

                $identidad = $config->integracion->getGatewayInstance()->identidadCuentaEmpresa($config);

                return $identidad
                    && $identidad['subtipo'] === $cuenta->subtipo
                    && $identidad['identificador_externo'] === $cuenta->identificador_externo;
            });
    }

    /**
     * Motor del comando programado (RF-04): avanza las corridas `generando`
     * (solicitar reporte → esperar → descargar + match) y crea las corridas
     * diarias de las cuentas con conciliación automática (RF-08).
     *
     * @return int cantidad de corridas que avanzaron de estado
     */
    public function procesarPendientes(): int
    {
        $avanzadas = 0;

        foreach (ConciliacionCuenta::generando()->with('cuentaEmpresa')->get() as $corrida) {
            if ($this->avanzarCorrida($corrida)) {
                $avanzadas++;
            }
        }

        $this->crearCorridasProgramadas();

        return $avanzadas;
    }

    /**
     * Avanza UNA corrida `generando`. Errores transitorios (red, proveedor
     * caído) se loguean y se reintenta en la próxima corrida del comando; el
     * timeout (60 min) corta definitivamente.
     */
    private function avanzarCorrida(ConciliacionCuenta $corrida): bool
    {
        if ($corrida->created_at->diffInMinutes(now()) > self::TIMEOUT_GENERANDO_MINUTOS) {
            $corrida->update([
                'estado' => ConciliacionCuenta::ESTADO_ERROR,
                'error_mensaje' => __('El proveedor no generó el reporte dentro del tiempo esperado'),
            ]);

            return true;
        }

        $config = $this->resolverConfigParaCuenta($corrida->cuentaEmpresa);

        if ($config === null) {
            $corrida->update([
                'estado' => ConciliacionCuenta::ESTADO_ERROR,
                'error_mensaje' => __('No hay ninguna integración de pago en producción asociada a esta cuenta'),
            ]);

            return true;
        }

        $gateway = $config->integracion->getGatewayInstance();

        try {
            if ($corrida->solicitud_reporte === null) {
                $solicitud = $gateway->solicitarReporteCuenta($config, $corrida->desde, $corrida->hasta);

                if ($solicitud === null) {
                    $corrida->update([
                        'estado' => ConciliacionCuenta::ESTADO_ERROR,
                        'error_mensaje' => __('El proveedor no soporta reportes de cuenta'),
                    ]);

                    return true;
                }

                $corrida->update(['solicitud_reporte' => $solicitud]);

                return false;
            }

            $reporte = $gateway->obtenerReporteCuenta($config, $corrida->solicitud_reporte);

            if ($reporte === null) {
                return false; // Todavía generándose: reintentar en la próxima pasada.
            }

            $this->ejecutarMatch($corrida, $reporte['filas'], $reporte['archivo']);

            return true;
        } catch (\Throwable $e) {
            Log::warning('ConciliacionCuentaService::avanzarCorrida falló (se reintenta)', [
                'conciliacion_id' => $corrida->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Corridas diarias automáticas: una por cuenta con el flag activo, por el
     * día anterior, si ese día no está cubierto por otra corrida útil.
     */
    private function crearCorridasProgramadas(): void
    {
        $ayer = now()->subDay()->startOfDay();

        $cuentas = CuentaEmpresa::activas()
            ->where('conciliacion_automatica', true)
            ->whereNotNull('identificador_externo')
            ->get();

        foreach ($cuentas as $cuenta) {
            $cubierto = ConciliacionCuenta::deCuenta($cuenta->id)
                ->whereNotIn('estado', [ConciliacionCuenta::ESTADO_DESCARTADA, ConciliacionCuenta::ESTADO_ERROR])
                ->whereDate('desde', '<=', $ayer)
                ->whereDate('hasta', '>=', $ayer)
                ->exists();

            $hayActiva = ConciliacionCuenta::deCuenta($cuenta->id)->activas()->exists();

            if ($cubierto || $hayActiva) {
                continue;
            }

            try {
                $this->crearCorrida($cuenta, $ayer, $ayer->copy()->endOfDay(), null, ConciliacionCuenta::ORIGEN_PROGRAMADA);
            } catch (\Throwable $e) {
                Log::warning('ConciliacionCuentaService::crearCorridasProgramadas falló para una cuenta', [
                    'cuenta_empresa_id' => $cuenta->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Match del reporte del proveedor contra el ledger (RF-05). Persiste el
     * detalle clasificado y deja la corrida pendiente_revision.
     */
    public function ejecutarMatch(ConciliacionCuenta $corrida, array $filasProveedor, ?string $archivo = null): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($corrida, $filasProveedor, $archivo) {
            // Re-ejecutable: si una pasada anterior dejó filas a medias, se rehacen.
            ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->delete();

            $cuenta = $corrida->cuentaEmpresa;

            // Universo de MATCHEO: TODOS los cobros por integración de la
            // cuenta, sin filtro de fecha — una liquidación tardía del
            // proveedor (lag de su pipeline) puede traer en este reporte un
            // cobro confirmado días antes del período, y si no lo matcheamos
            // se propondría un ingreso DUPLICADO como solo_proveedor.
            $movimientosSistema = MovimientoCuentaEmpresa::where('cuenta_empresa_id', $cuenta->id)
                ->where('origen_tipo', 'IntegracionPagoTransaccion')
                ->where('estado', 'activo')
                ->get();

            $transacciones = IntegracionPagoTransaccion::whereIn('id', $movimientosSistema->pluck('origen_id'))->get();

            // Universo de ALERTA solo-sistema: solo lo confirmado dentro del
            // período (±1 día por timezone) — lo anterior ya fue alertado o
            // conciliado por corridas previas.
            $ventanaSoloSistema = [
                $corrida->desde->copy()->startOfDay()->subDay(),
                $corrida->hasta->copy()->endOfDay()->addDay(),
            ];

            $porReferencia = $transacciones->whereNotNull('external_reference')->keyBy('external_reference');
            $porIdExterno = $transacciones->whereNotNull('external_id')->keyBy('external_id');

            // Idempotencia cross-corrida: (tipo, id_externo) ya materializados
            // para esta cuenta en corridas aplicadas anteriores (RF-05).
            $yaRegistradas = ConciliacionFila::whereNotNull('movimiento_cuenta_empresa_id')
                ->whereNotNull('id_externo')
                ->whereHas('conciliacion', fn ($q) => $q->where('cuenta_empresa_id', $cuenta->id))
                ->get(['tipo', 'id_externo'])
                ->map(fn ($f) => $f->tipo.'|'.$f->id_externo)
                ->flip();

            $transaccionesMatcheadas = [];

            foreach ($filasProveedor as $fila) {
                $this->clasificarFilaProveedor($corrida, $fila, $porReferencia, $porIdExterno, $yaRegistradas, $transaccionesMatcheadas);
            }

            // Solo-sistema: cobros del ledger sin contraparte en el reporte (alerta).
            foreach ($transacciones as $transaccion) {
                if (isset($transaccionesMatcheadas[$transaccion->id])) {
                    continue;
                }

                if ($transaccion->confirmado_en === null
                    || $transaccion->confirmado_en->lt($ventanaSoloSistema[0])
                    || $transaccion->confirmado_en->gt($ventanaSoloSistema[1])) {
                    continue;
                }

                $movimiento = $movimientosSistema->firstWhere('origen_id', $transaccion->id);

                ConciliacionFila::create([
                    'conciliacion_cuenta_id' => $corrida->id,
                    'tipo' => ConciliacionFila::TIPO_COBRO,
                    'clasificacion' => ConciliacionFila::CLASIFICACION_SOLO_SISTEMA,
                    'id_externo' => $transaccion->external_id,
                    'referencia' => $transaccion->external_reference,
                    'fecha' => $transaccion->confirmado_en,
                    'descripcion' => __('Cobro por integración').' #'.$transaccion->id.' ('.$transaccion->modo_usado.')',
                    'monto_bruto' => (float) ($movimiento?->monto ?? $transaccion->monto),
                    'monto_neto' => (float) ($movimiento?->monto ?? $transaccion->monto),
                    'accion' => ConciliacionFila::ACCION_SIN_ACCION,
                    'integracion_pago_transaccion_id' => $transaccion->id,
                ]);
            }

            $this->recalcularTotales($corrida);

            $corrida->update([
                'estado' => ConciliacionCuenta::ESTADO_PENDIENTE_REVISION,
                'archivo_reporte' => $archivo,
            ]);

            Log::info('ConciliacionCuentaService::ejecutarMatch completado', [
                'conciliacion_id' => $corrida->id,
                'filas_proveedor' => count($filasProveedor),
            ]);
        });
    }

    /**
     * Clasifica UNA fila del reporte y persiste su ConciliacionFila (más la
     * fila hija de comisión cuando el cobro matchea con el sistema).
     */
    private function clasificarFilaProveedor(
        ConciliacionCuenta $corrida,
        array $fila,
        $porReferencia,
        $porIdExterno,
        $yaRegistradas,
        array &$transaccionesMatcheadas
    ): void {
        $tipo = $fila['tipo'] ?? ConciliacionFila::TIPO_OTRO;
        $idExterno = $fila['id_externo'] ?? null;

        // Desglose fiscal (RF-06): el gateway ya mapeó TAX_DETAIL → impuesto del
        // catálogo; acá solo resolvemos el id. La fila cruda viaja a datos_extra.
        $impuestoId = ! empty($fila['impuesto']['codigo'])
            ? Impuesto::porCodigo($fila['impuesto']['codigo'])->value('id')
            : null;

        $base = [
            'conciliacion_cuenta_id' => $corrida->id,
            'tipo' => $tipo,
            'id_externo' => $idExterno,
            'referencia' => $fila['referencia'] ?? null,
            'fecha' => $fila['fecha'] ?? null,
            'descripcion' => $fila['descripcion'] ?? null,
            'datos_extra' => $fila['datos_extra'] ?? null,
            'monto_bruto' => (float) ($fila['monto_bruto'] ?? 0),
            'comision' => (float) ($fila['comision'] ?? 0),
            'monto_neto' => (float) ($fila['monto_neto'] ?? 0),
            'impuesto_id' => $impuestoId,
        ];

        // ¿Una corrida aplicada anterior ya materializó esta fila? (períodos solapados)
        if ($idExterno !== null && isset($yaRegistradas[$tipo.'|'.$idExterno])) {
            ConciliacionFila::create($base + [
                'clasificacion' => ConciliacionFila::CLASIFICACION_YA_REGISTRADO,
                'accion' => ConciliacionFila::ACCION_SIN_ACCION,
            ]);

            return;
        }

        if ($tipo === ConciliacionFila::TIPO_COBRO) {
            $transaccion = ($fila['referencia'] ?? null) !== null ? $porReferencia->get($fila['referencia']) : null;
            $transaccion ??= $idExterno !== null ? $porIdExterno->get($idExterno) : null;

            if ($transaccion) {
                $transaccionesMatcheadas[$transaccion->id] = true;

                ConciliacionFila::create($base + [
                    'clasificacion' => ConciliacionFila::CLASIFICACION_MATCHEADO,
                    'accion' => ConciliacionFila::ACCION_SIN_ACCION,
                    'integracion_pago_transaccion_id' => $transaccion->id,
                ]);

                // El sistema registró el BRUTO del cobro; la comisión del
                // proveedor se propone como egreso granular (decisión 3).
                $comision = (float) ($fila['comision'] ?? 0);
                if ($comision > self::EPSILON) {
                    $yaComision = $idExterno !== null && isset($yaRegistradas[ConciliacionFila::TIPO_COMISION.'|'.$idExterno]);

                    ConciliacionFila::create([
                        'conciliacion_cuenta_id' => $corrida->id,
                        'tipo' => ConciliacionFila::TIPO_COMISION,
                        'clasificacion' => $yaComision
                            ? ConciliacionFila::CLASIFICACION_YA_REGISTRADO
                            : ConciliacionFila::CLASIFICACION_MATCHEADO,
                        'id_externo' => $idExterno,
                        'referencia' => $fila['referencia'] ?? null,
                        'fecha' => $fila['fecha'] ?? null,
                        'descripcion' => __('Comisión sobre cobro').' '.($fila['referencia'] ?? $idExterno ?? ''),
                        'monto_bruto' => $comision,
                        'monto_neto' => $comision,
                        'accion' => $yaComision ? ConciliacionFila::ACCION_SIN_ACCION : ConciliacionFila::ACCION_GENERAR_MOVIMIENTO,
                        'tipo_movimiento' => 'egreso',
                        'concepto_codigo' => 'comision_integracion',
                        'integracion_pago_transaccion_id' => $transaccion->id,
                    ]);
                }

                // Deducciones MÁS ALLÁ de la comisión (retenciones y
                // percepciones impositivas dentro del cobro): si el neto del
                // proveedor no cierra con bruto - comisión, el residuo se
                // propone como egreso de impuestos para que el saldo
                // converja. El cálculo impositivo fino por condición de IVA
                // es un feature aparte; acá registramos lo ya descontado.
                $residuo = round((float) $base['monto_bruto'] - $comision - (float) $base['monto_neto'], 2);
                if ($residuo > self::EPSILON) {
                    $yaImpuesto = $idExterno !== null && isset($yaRegistradas[ConciliacionFila::TIPO_IMPUESTO.'|'.$idExterno]);

                    ConciliacionFila::create([
                        'conciliacion_cuenta_id' => $corrida->id,
                        'tipo' => ConciliacionFila::TIPO_IMPUESTO,
                        'clasificacion' => $yaImpuesto
                            ? ConciliacionFila::CLASIFICACION_YA_REGISTRADO
                            : ConciliacionFila::CLASIFICACION_MATCHEADO,
                        'id_externo' => $idExterno,
                        'referencia' => $fila['referencia'] ?? null,
                        'fecha' => $fila['fecha'] ?? null,
                        'descripcion' => __('Impuestos/retenciones sobre cobro').' '.($fila['referencia'] ?? $idExterno ?? ''),
                        'monto_bruto' => $residuo,
                        'monto_neto' => $residuo,
                        'accion' => $yaImpuesto ? ConciliacionFila::ACCION_SIN_ACCION : ConciliacionFila::ACCION_GENERAR_MOVIMIENTO,
                        'tipo_movimiento' => 'egreso',
                        'concepto_codigo' => 'impuesto_integracion',
                        'integracion_pago_transaccion_id' => $transaccion->id,
                    ]);
                }

                return;
            }
        }

        // Sin contraparte en el sistema → solo_proveedor con movimiento propuesto.
        [$tipoMovimiento, $concepto] = self::PROPUESTAS_SOLO_PROVEEDOR[$tipo] ?? [
            $base['monto_neto'] >= 0 ? 'ingreso' : 'egreso',
            'ajuste_conciliacion',
        ];

        // Anulación/devolución de un impuesto (tax_*_cancel, neto positivo):
        // entra plata, no sale — mismo patrón que retiro_cancelado.
        if ($tipo === ConciliacionFila::TIPO_IMPUESTO && $base['monto_neto'] > 0) {
            [$tipoMovimiento, $concepto] = ['ingreso', 'acreditacion_integracion'];
        }

        // `otro` queda informativo: el usuario decide activarlo (default ignorar).
        $accion = $tipo === ConciliacionFila::TIPO_OTRO
            ? ConciliacionFila::ACCION_IGNORAR
            : ConciliacionFila::ACCION_GENERAR_MOVIMIENTO;

        $filaCreada = ConciliacionFila::create($base + [
            'clasificacion' => ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR,
            'accion' => abs($base['monto_neto']) > self::EPSILON ? $accion : ConciliacionFila::ACCION_IGNORAR,
            'tipo_movimiento' => $tipoMovimiento,
            'concepto_codigo' => $concepto,
        ]);

        // Alerta esperado-vs-real (RF-06/D4): solo para impuestos identificados,
        // si la cuenta tiene CUIT asignado. Se muestra en la revisión (badge).
        if ($impuestoId !== null && ($cuit = $corrida->cuentaEmpresa?->cuit) !== null) {
            $alerta = $this->impuestoService->validarImpuestoSufrido($filaCreada, $cuit);

            if ($alerta !== null) {
                $filaCreada->update(['alerta_validacion' => $alerta]);
            }
        }
    }

    /**
     * Recalcula contadores y montos propuestos de la corrida (se invoca tras
     * el match y cuando el usuario cambia acciones en la revisión).
     */
    public function recalcularTotales(ConciliacionCuenta $corrida): void
    {
        $filas = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)->get();
        $propuestas = $filas->where('accion', ConciliacionFila::ACCION_GENERAR_MOVIMIENTO);

        $corrida->update([
            'total_matcheados' => $filas->where('clasificacion', ConciliacionFila::CLASIFICACION_MATCHEADO)
                ->where('tipo', '!=', ConciliacionFila::TIPO_COMISION)->count(),
            'total_solo_proveedor' => $filas->where('clasificacion', ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR)->count(),
            'total_solo_sistema' => $filas->where('clasificacion', ConciliacionFila::CLASIFICACION_SOLO_SISTEMA)->count(),
            'monto_propuesto_ingresos' => round($propuestas->where('tipo_movimiento', 'ingreso')->sum(fn ($f) => abs((float) $f->monto_neto)), 2),
            'monto_propuesto_egresos' => round($propuestas->where('tipo_movimiento', 'egreso')->sum(fn ($f) => abs((float) $f->monto_neto)), 2),
        ]);
    }

    /**
     * Aplica la corrida (RF-06): genera un MovimientoCuentaEmpresa por cada
     * fila con acción generar_movimiento (origen 'ConciliacionFila') y, si
     * corresponde, el ajuste inicial (RF-07: el usuario informa el saldo REAL
     * del proveedor al cierre y la cuenta converge exacto). Idempotente: una
     * corrida ya aplicada se devuelve tal cual; una fila ya materializada en
     * otra corrida se saltea y queda marcada ya_registrado.
     */
    public function aplicar(ConciliacionCuenta $corrida, int $usuarioId, ?float $saldoFinalProveedor = null): ConciliacionCuenta
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($corrida, $usuarioId, $saldoFinalProveedor) {
            $corrida = ConciliacionCuenta::lockForUpdate()->findOrFail($corrida->id);

            if ($corrida->estaAplicada()) {
                return $corrida; // Doble click / convergencia: no duplica.
            }

            if (! $corrida->esEditable()) {
                throw new \RuntimeException(__('La conciliación no está pendiente de revisión'));
            }

            $cuenta = $corrida->cuentaEmpresa;
            $cuitFiscal = $cuenta->cuit; // RF-07: CUIT al que se imputan los impuestos sufridos.

            $filas = ConciliacionFila::where('conciliacion_cuenta_id', $corrida->id)
                ->propuestas()
                ->get();

            foreach ($filas as $fila) {
                // Guard de idempotencia cross-corrida al momento de aplicar.
                if ($fila->id_externo !== null) {
                    $yaExiste = ConciliacionFila::whereNotNull('movimiento_cuenta_empresa_id')
                        ->where('id', '!=', $fila->id)
                        ->where('tipo', $fila->tipo)
                        ->where('id_externo', $fila->id_externo)
                        ->whereHas('conciliacion', fn ($q) => $q->where('cuenta_empresa_id', $cuenta->id))
                        ->exists();

                    if ($yaExiste) {
                        $fila->update([
                            'clasificacion' => ConciliacionFila::CLASIFICACION_YA_REGISTRADO,
                            'accion' => ConciliacionFila::ACCION_SIN_ACCION,
                        ]);

                        continue;
                    }
                }

                $movimiento = CuentaEmpresaService::registrarMovimientoAutomatico(
                    $cuenta,
                    $fila->tipo_movimiento,
                    round(abs((float) $fila->monto_neto), 2),
                    $fila->concepto_codigo,
                    'ConciliacionFila',
                    $fila->id,
                    __('Conciliación').' #'.$corrida->id.': '.($fila->descripcion ?: $fila->tipo),
                    $usuarioId,
                );

                $fila->update(['movimiento_cuenta_empresa_id' => $movimiento->id]);

                // Ledger fiscal (RF-06): además del movimiento de cuenta, las
                // filas de impuesto IDENTIFICADAS generan su movimiento fiscal
                // sufrido, imputado al CUIT de la cuenta. Sin CUIT o sin
                // impuesto reconocido no se genera (RF-07: el ledger no se
                // bloquea, la corrida lo avisa en la revisión).
                if ($cuitFiscal !== null && $fila->impuesto_id !== null && $fila->tipo === ConciliacionFila::TIPO_IMPUESTO) {
                    $this->impuestoService->registrarDesdeConciliacion($fila, $cuitFiscal, $usuarioId);
                }
            }

            // El ajuste va DESPUÉS de los movimientos: la diferencia se mide
            // contra el saldo del ledger ya conciliado, así la cuenta queda
            // exactamente en el saldo real informado.
            if ($saldoFinalProveedor !== null) {
                $this->aplicarAjusteInicial($corrida, $cuenta, $usuarioId, $saldoFinalProveedor);
            }

            $this->recalcularTotales($corrida);

            $corrida->update([
                'estado' => ConciliacionCuenta::ESTADO_APLICADA,
                'aplicada_por' => $usuarioId,
                'aplicada_en' => now(),
            ]);

            Log::info('ConciliacionCuentaService::aplicar completado', [
                'conciliacion_id' => $corrida->id,
                'cuenta_empresa_id' => $cuenta->id,
                'movimientos_generados' => $filas->count(),
            ]);

            return $corrida->refresh();
        });
    }

    /**
     * Ajuste inicial (RF-07, cierre de D11): en la PRIMERA conciliación de la
     * cuenta, el usuario informa el saldo REAL TOTAL del proveedor al cierre
     * del período (el que ve en su app: disponible + a liberar + reserva) y se
     * registra un ajuste_conciliacion por la diferencia contra el saldo del
     * ledger YA conciliado, dejando la cuenta exactamente en el saldo real.
     *
     * Se pide el saldo al CIERRE (no al inicio) porque es el dato que el
     * usuario tiene a mano; absorbe toda la historia previa al período
     * (decidido con el usuario en la validación en vivo, 2026-06-12).
     */
    private function aplicarAjusteInicial(
        ConciliacionCuenta $corrida,
        CuentaEmpresa $cuenta,
        int $usuarioId,
        float $saldoFinalProveedor
    ): void {
        $hayAplicadaPrevia = ConciliacionCuenta::deCuenta($cuenta->id)
            ->where('id', '!=', $corrida->id)
            ->where('estado', ConciliacionCuenta::ESTADO_APLICADA)
            ->exists();

        if ($hayAplicadaPrevia) {
            return; // El ajuste inicial solo aplica una vez.
        }

        $saldoLedger = (float) $cuenta->fresh()->saldo_actual;
        $diferencia = round($saldoFinalProveedor - $saldoLedger, 2);

        if (abs($diferencia) <= self::EPSILON) {
            return;
        }

        $cierrePeriodo = $corrida->hasta->copy()->endOfDay();

        $fila = ConciliacionFila::create([
            'conciliacion_cuenta_id' => $corrida->id,
            'tipo' => ConciliacionFila::TIPO_AJUSTE_INICIAL,
            'clasificacion' => ConciliacionFila::CLASIFICACION_SOLO_PROVEEDOR,
            'fecha' => $cierrePeriodo,
            'descripcion' => __('Ajuste inicial: saldo real del proveedor al :fecha', ['fecha' => $cierrePeriodo->format('d/m/Y')]),
            'monto_neto' => $diferencia,
            'accion' => ConciliacionFila::ACCION_GENERAR_MOVIMIENTO,
            'tipo_movimiento' => $diferencia > 0 ? 'ingreso' : 'egreso',
            'concepto_codigo' => 'ajuste_conciliacion',
        ]);

        $movimiento = CuentaEmpresaService::registrarMovimientoAutomatico(
            $cuenta,
            $fila->tipo_movimiento,
            abs($diferencia),
            'ajuste_conciliacion',
            'ConciliacionFila',
            $fila->id,
            (string) $fila->descripcion,
            $usuarioId,
        );

        $fila->update(['movimiento_cuenta_empresa_id' => $movimiento->id]);
    }

    /**
     * Descarta la corrida sin tocar el ledger (permite re-conciliar el período).
     */
    public function descartar(ConciliacionCuenta $corrida, ?int $usuarioId = null): void
    {
        if (! $corrida->esEditable()) {
            throw new \RuntimeException(__('La conciliación no está pendiente de revisión'));
        }

        $corrida->update(['estado' => ConciliacionCuenta::ESTADO_DESCARTADA]);

        Log::info('ConciliacionCuentaService::descartar', [
            'conciliacion_id' => $corrida->id,
            'usuario_id' => $usuarioId,
        ]);
    }
}

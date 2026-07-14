<?php

namespace App\Services\Fiscal;

use App\Models\Cliente;
use App\Models\ClienteImpuestoConfig;
use App\Models\Compra;
use App\Models\ComprobanteFiscal;
use App\Models\ConciliacionFila;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Núcleo del sistema impositivo (Fase 2, RF-03/RF-04).
 *
 * Única puerta de ESCRITURA del ledger fiscal `movimientos_fiscales`
 * (append-only: nunca se edita ni borra; la anulación genera un contraasiento).
 * Livewire, reportes y demás services solo leen.
 *
 * Esta fase implementa el núcleo aislado:
 *  - registrarMovimientoFiscal / anularMovimientoFiscal (ledger + contraasiento)
 *  - configVigente (resolución de la config impositiva del CUIT)
 *  - calcularTributos (matriz v1 conservador de percepciones aplicadas)
 *
 * Los hooks que ALIMENTAN el ledger (registrarDesde*) están cableados:
 * comprobantes (Fase 5), conciliación MP (Fase 4) y compras (PR #153,
 * registrarDesdeCompra/anularDesdeCompra al confirmar/cancelar).
 *
 * Convención de alícuota: porcentaje (ej. 3.0000 = 3%), igual que el resto del
 * sistema (ComprobanteFiscalIva) → monto = base * alicuota / 100.
 *
 * Ref: .claude/specs/sistema-impositivo.md (Fase 2, RF-03, RF-04).
 */
class ImpuestoService
{
    /**
     * Registra un movimiento fiscal en el ledger (única puerta de escritura).
     *
     * El `periodo_fiscal` (YYYY-MM) se calcula SIEMPRE desde `fecha` al
     * registrar (inmutable, no depende de timezone en consultas). Cualquier
     * `periodo_fiscal` que venga en $datos se ignora.
     *
     * $datos: cuit_id, impuesto_id, sentido, naturaleza, fecha, monto
     * (requeridos) + sucursal_id, base_imponible, alicuota, certificado_numero,
     * origen_tipo, origen_id, observaciones, usuario_id (opcionales).
     *
     * Semántica de signos: monto positivo. Sólo las reversas de nota de crédito
     * (registrarDesdeComprobante) pasan `$permitirNegativo` y registran montos
     * negativos — la posición fiscal suma montos, así que una fila negativa
     * resta el débito/percepción en el período de la NC.
     */
    public function registrarMovimientoFiscal(array $datos, bool $permitirNegativo = false): MovimientoFiscal
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $this->validarDatosMovimiento($datos, $permitirNegativo);

            $fecha = Carbon::parse($datos['fecha']);

            $movimiento = MovimientoFiscal::create([
                'cuit_id' => $datos['cuit_id'],
                'sucursal_id' => $datos['sucursal_id'] ?? null,
                'impuesto_id' => $datos['impuesto_id'],
                'sentido' => $datos['sentido'],
                'naturaleza' => $datos['naturaleza'],
                'fecha' => $fecha->toDateString(),
                'periodo_fiscal' => $fecha->format('Y-m'),
                'base_imponible' => $datos['base_imponible'] ?? null,
                'alicuota' => $datos['alicuota'] ?? null,
                'monto' => round((float) $datos['monto'], 2),
                'certificado_numero' => $datos['certificado_numero'] ?? null,
                'origen_tipo' => $datos['origen_tipo'] ?? null,
                'origen_id' => $datos['origen_id'] ?? null,
                'estado' => MovimientoFiscal::ESTADO_ACTIVO,
                'observaciones' => $datos['observaciones'] ?? null,
                'usuario_id' => $datos['usuario_id'] ?? null,
            ]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Movimiento fiscal registrado', [
                'movimiento_id' => $movimiento->id,
                'cuit_id' => $movimiento->cuit_id,
                'impuesto_id' => $movimiento->impuesto_id,
                'sentido' => $movimiento->sentido,
                'periodo_fiscal' => $movimiento->periodo_fiscal,
                'monto' => $movimiento->monto,
            ]);

            return $movimiento->fresh();
        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            Log::error('Error al registrar movimiento fiscal', [
                'error' => $e->getMessage(),
                'datos' => $datos,
            ]);

            throw $e;
        }
    }

    /**
     * Anula un movimiento fiscal por contraasiento (append-only).
     *
     * El original pasa a estado=anulado y se crea una fila de reversa linkeada
     * (movimiento_anulado_id → original, estado=anulado) como prueba inmutable
     * de la anulación (quién/cuándo/por qué). La posición fiscal suma solo
     * estado=activo, por lo que la anulación saca limpio el original sin
     * necesidad de aritmética con signo (monto queda siempre positivo).
     *
     * Anular es CORRECCIÓN DE ERROR de carga: hace desaparecer el movimiento
     * retroactivamente de su período. Un evento fiscal nuevo que revierte otro
     * (la nota de crédito) NO pasa por acá — registra sus propios movimientos
     * negativos en SU período (ver registrarDesdeComprobante), porque el período
     * del original puede estar ya declarado ante el fisco.
     *
     * @throws Exception si el movimiento ya fue anulado o es un contraasiento.
     */
    public function anularMovimientoFiscal(MovimientoFiscal $mov, int $usuarioId, ?string $motivo = null): MovimientoFiscal
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $original = MovimientoFiscal::lockForUpdate()->findOrFail($mov->id);

            if (! $original->estaActivo()) {
                throw new Exception(__('El movimiento fiscal ya fue anulado'));
            }

            if ($original->esContraasiento()) {
                throw new Exception(__('No se puede anular un contraasiento'));
            }

            // RF-B9 (hardening-circuito-precios): un movimiento generado por un
            // origen (compra/venta/conciliación) solo se revierte por el
            // circuito de ese origen (cancelación/NC) — anularlo a mano
            // desbalancearía la reversa espejo del origen.
            if ($original->origen_tipo !== null) {
                throw new Exception(__('Este movimiento lo generó :origen #:id: se revierte cancelando o acreditando desde su origen', [
                    'origen' => $original->origen_tipo,
                    'id' => $original->origen_id,
                ]));
            }

            $contraasiento = MovimientoFiscal::create([
                'cuit_id' => $original->cuit_id,
                'sucursal_id' => $original->sucursal_id,
                'impuesto_id' => $original->impuesto_id,
                'sentido' => $original->sentido,
                'naturaleza' => $original->naturaleza,
                'fecha' => $original->fecha->toDateString(),
                'periodo_fiscal' => $original->periodo_fiscal,
                'base_imponible' => $original->base_imponible,
                'alicuota' => $original->alicuota,
                'monto' => $original->monto,
                'certificado_numero' => $original->certificado_numero,
                'origen_tipo' => $original->origen_tipo,
                'origen_id' => $original->origen_id,
                'movimiento_anulado_id' => $original->id,
                'estado' => MovimientoFiscal::ESTADO_ANULADO,
                'observaciones' => $motivo
                    ? __('Anulación').": {$motivo}"
                    : __('Anulación del movimiento fiscal #:id', ['id' => $original->id]),
                'usuario_id' => $usuarioId,
            ]);

            $original->update(['estado' => MovimientoFiscal::ESTADO_ANULADO]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Movimiento fiscal anulado por contraasiento', [
                'original_id' => $original->id,
                'contraasiento_id' => $contraasiento->id,
                'usuario_id' => $usuarioId,
            ]);

            return $contraasiento->fresh();
        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();

            Log::error('Error al anular movimiento fiscal', [
                'error' => $e->getMessage(),
                'movimiento_id' => $mov->id,
            ]);

            throw $e;
        }
    }

    /**
     * Resuelve la configuración impositiva vigente de un CUIT para un impuesto.
     *
     * Si hay varias configs vigentes para el mismo cuit+impuesto (vigencias
     * solapadas), gana la de `vigente_desde` más reciente; una config sin
     * `vigente_desde` (siempre vigente) queda como fallback.
     */
    public function configVigente(Cuit $cuit, int $impuestoId, ?Carbon $fecha = null): ?CuitImpuestoConfig
    {
        $fecha = $fecha ?? now();

        return CuitImpuestoConfig::query()
            ->where('cuit_id', $cuit->id)
            ->where('impuesto_id', $impuestoId)
            ->vigentes($fecha->toDateString())
            ->orderByRaw('vigente_desde IS NULL, vigente_desde DESC')
            ->first();
    }

    /**
     * Calcula los tributos (percepciones) aplicables al emitir un comprobante.
     *
     * Matriz v1 conservador (decisión del usuario, 2026-06-16): una percepción
     * se aplica solo si el CUIT emisor es agente de percepción (config vigente,
     * inscripto, con alícuota cargada) Y el receptor es Responsable Inscripto.
     *  - Percepción IVA (nacional): automática a todo RI, sin condicionar
     *    jurisdicción — salvo cliente con certificado de exclusión (perfil
     *    fiscal `exento` para el impuesto; RG 2226). No toma alícuota por sujeto.
     *  - Percepción IIBB (provincial): solo si la jurisdicción del impuesto
     *    coincide con la jurisdicción de la operación. Esta sale del DOMICILIO
     *    FISCAL del punto de venta del comprobante (RF-11, Fase 9), no de la
     *    sucursal física. Además se REFINA por el perfil fiscal del receptor
     *    (RF-15, Fase 10): `cliente_impuesto_configs` del cliente para el mismo
     *    impuesto define exención / alícuota por sujeto (manual o padrón). Sin
     *    config del cliente decide la flag `percibir_no_empadronados` del agente
     *    (D7): true ⇒ alícuota fija del agente; false (default) ⇒ no percibe.
     * Respeta `alicuota_minimo_base` (del cliente si está, si no del agente; si el
     * neto gravado es menor → no percibe) y `monto_minimo_percepcion` del agente
     * (si el importe resultante no lo alcanza → no se practica).
     * Consumidor final / monotributo / exento / receptor null → sin percepción.
     *
     * El IVA débito del comprobante NO se calcula acá (lo hace
     * ComprobanteFiscalIva); esto devuelve solo las percepciones extra.
     *
     * @param  ?Cliente  $receptor  cliente percibido (su condición de IVA gatea el RI y su perfil fiscal refina el IIBB)
     * @param  ?string  $jurisdiccion  ISO 3166-2 del domicilio del PV (jurisdicción de la operación); null = sin jurisdicción → no aplica IIBB provincial
     * @param  ?Carbon  $fecha  fecha de la operación (para la vigencia de la config); por defecto hoy
     * @return array<int, array{impuesto_id:int, codigo:string, tipo:string, jurisdiccion:?string, base_imponible:float, alicuota:float, monto:float}>
     */
    public function calcularTributos(Cuit $emisor, ?Cliente $receptor, float $netoGravado, ?string $jurisdiccion = null, ?Carbon $fecha = null): array
    {
        // Pendiente normativo (con el contador, ver "Revisión pendiente" en el
        // spec): el gate "solo RI" como receptor puede saltear monotributistas
        // empadronados en IIBB (los padrones ARBA/AGIP los incluyen) — relajarlo
        // re-abre D6. Convenio Multilateral: diferido. El "monto no sujeto"
        // (deducción de base, típico de retenciones) no se modela.
        if ($netoGravado <= 0) {
            return [];
        }

        // Solo un Responsable Inscripto puede actuar como agente de percepción/
        // retención (un monotributo/exento no puede, aunque la config lo diga).
        if ($emisor->condicionIva?->codigo !== CondicionIva::RESPONSABLE_INSCRIPTO) {
            return [];
        }

        // v1: solo se percibe a Responsables Inscriptos.
        if ($receptor === null || $receptor->condicionIva?->codigo !== CondicionIva::RESPONSABLE_INSCRIPTO) {
            return [];
        }

        $jurisdiccionOperacion = $jurisdiccion;
        $base = round($netoGravado, 2);

        $configs = $emisor->impuestoConfigs()
            ->vigentes($fecha?->toDateString())
            ->where('inscripto', true)
            ->where('es_agente_percepcion', true)
            ->whereNotNull('alicuota')
            ->with('impuesto')
            ->get()
            // Vigencias solapadas para el mismo impuesto: gana la de
            // vigente_desde más reciente (misma regla que configVigente) — sin
            // esto se percibiría dos veces el mismo impuesto.
            ->groupBy('impuesto_id')
            ->map(fn ($grupo) => $grupo->sortByDesc(
                fn ($c) => $c->vigente_desde?->format('Y-m-d') ?? ''
            )->first())
            ->values();

        // Perfil fiscal del receptor (RF-15, Fase 10): refina el IIBB por sujeto
        // y la exclusión de IVA. Una sola query, un ganador por impuesto: el
        // override MANUAL del contador le gana al padrón (que el importador no
        // pise la fila manual no alcanza — acá pueden coexistir vigentes ambas);
        // a igual origen, la vigencia más reciente.
        $configsCliente = $receptor->impuestoConfigs()
            ->vigentes($fecha?->toDateString())
            ->get()
            ->groupBy('impuesto_id')
            ->map(fn ($grupo) => $grupo->sortByDesc(fn ($c) => sprintf(
                '%d|%s',
                $c->origen_alicuota === ClienteImpuestoConfig::ORIGEN_MANUAL ? 1 : 0,
                $c->vigente_desde?->format('Y-m-d') ?? ''
            ))->first());

        $tributos = [];

        foreach ($configs as $config) {
            $impuesto = $config->impuesto;

            if ($impuesto === null || ! $impuesto->activo) {
                continue;
            }

            // Solo percepciones de IVA o IIBB.
            if (! in_array($impuesto->tipo, [Impuesto::TIPO_IVA, Impuesto::TIPO_IIBB], true)) {
                continue;
            }

            if ($impuesto->naturaleza_default !== MovimientoFiscal::NATURALEZA_PERCEPCION) {
                continue;
            }

            // Certificado de exclusión de percepción de IVA (RG 2226): un perfil
            // fiscal del cliente marcado exento para este impuesto lo excluye.
            // A diferencia del IIBB, para IVA no se toma alícuota por sujeto
            // (la exclusión es todo-o-nada).
            if ($impuesto->tipo === Impuesto::TIPO_IVA
                && ($configsCliente->get($impuesto->id)?->exento ?? false)) {
                continue;
            }

            // Por defecto se usa la config del AGENTE (alícuota fija + base mínima).
            $alicuota = (float) $config->alicuota;
            $minimoBase = $config->alicuota_minimo_base !== null ? (float) $config->alicuota_minimo_base : null;

            // IIBB provincial: la jurisdicción del impuesto debe coincidir con
            // la jurisdicción de la operación (domicilio fiscal del PV) y se refina
            // por el perfil fiscal del receptor (RF-15, Fase 10). La percepción de
            // IVA NO consulta al cliente (automática, comportamiento 5b).
            if ($impuesto->tipo === Impuesto::TIPO_IIBB) {
                if ($jurisdiccionOperacion === null || $impuesto->jurisdiccion !== $jurisdiccionOperacion) {
                    continue;
                }

                $configCliente = $configsCliente->get($impuesto->id);

                if ($configCliente !== null) {
                    // Exento explícito (certificado / no alcanzado) ⇒ no se percibe.
                    if ($configCliente->exento) {
                        continue;
                    }
                    // Alícuota por sujeto (manual o padrón) ⇒ pisa la fija del agente.
                    if ($configCliente->alicuota !== null) {
                        $alicuota = (float) $configCliente->alicuota;
                    }
                    // Base mínima del cliente si está cargada; si no, la del agente.
                    if ($configCliente->alicuota_minimo_base !== null) {
                        $minimoBase = (float) $configCliente->alicuota_minimo_base;
                    }
                } elseif (! $config->percibir_no_empadronados) {
                    // D7: receptor RI sin perfil fiscal para este IIBB. Solo se
                    // percibe (a la alícuota fija del agente) si el agente activó
                    // explícitamente "percibir a no empadronados"; default seguro: no.
                    continue;
                }
            }

            // Umbral de base imponible: si el neto es menor, no se percibe.
            if ($minimoBase !== null && $base < $minimoBase) {
                continue;
            }

            $monto = round($base * $alicuota / 100, 2);

            if ($monto <= 0) {
                continue;
            }

            // Monto mínimo de percepción del régimen: si el importe resultante
            // no lo alcanza, la percepción no se practica (distinto del umbral
            // de base; ej. percepción IVA RG 2408).
            if ($config->monto_minimo_percepcion !== null && $monto < (float) $config->monto_minimo_percepcion) {
                continue;
            }

            $tributos[] = [
                'impuesto_id' => $impuesto->id,
                'codigo' => $impuesto->codigo,
                'codigo_arca' => $impuesto->codigo_arca,
                'tipo' => $impuesto->tipo,
                'jurisdiccion' => $impuesto->jurisdiccion,
                'base_imponible' => $base,
                'alicuota' => $alicuota,
                'monto' => $monto,
            ];
        }

        return $tributos;
    }

    /**
     * Percepciones aplicadas de un comprobante (Fase 5b). Wrapper sobre
     * calcularTributos que resuelve el receptor desde el cliente de la venta —
     * mismo origen de verdad para el cálculo en la venta (cobro) y en la emisión
     * (ComprobanteFiscalService), garantizando cobrado == facturado.
     *
     * @return array<int,array<string,mixed>> tributos (ver calcularTributos)
     */
    public function calcularPercepcionesComprobante(
        Cuit $emisor,
        ?Cliente $cliente,
        float $netoGravado,
        ?string $jurisdiccion = null,
        ?Carbon $fecha = null
    ): array {
        return $this->calcularTributos($emisor, $cliente, $netoGravado, $jurisdiccion, $fecha);
    }

    /**
     * Registra los movimientos fiscales de un comprobante autorizado (RF-04,
     * Fase 5a). Se invoca DESPUÉS de obtener el CAE y de commitear el
     * comprobante (un CAE ya emitido no se pierde si el ledger fallara).
     *
     * - Factura/comprobante: IVA débito fiscal por alícuota (sentido aplicado),
     *   tomado del desglose ya calculado en ComprobanteFiscalIva — alimenta la
     *   posición de IVA sin recalcular nada. Ídem percepciones aplicadas (5b)
     *   desde tributosDetalle.
     * - Nota de crédito (comprobante_asociado_id): registra sus PROPIOS
     *   movimientos con monto NEGATIVO, imputados al período de la NC. NO se
     *   anulan los movimientos del comprobante original: una NC de julio sobre
     *   una factura de junio ajusta la posición de JULIO — junio puede estar ya
     *   declarado ante el fisco, y el Libro IVA Ventas también computa la NC por
     *   su fecha de emisión (así libro y posición reconcilian en ambos meses).
     *   En el caso mismo-período el neto es idéntico a la vieja anulación.
     *
     * Idempotente: si el comprobante ya tiene movimientos activos, no duplica.
     */
    public function registrarDesdeComprobante(ComprobanteFiscal $c, ?int $usuarioId = null): void
    {
        // Idempotencia: ya registrado para este comprobante (factura o NC).
        $yaRegistrado = MovimientoFiscal::query()
            ->activos()
            ->where('origen_tipo', 'ComprobanteFiscal')
            ->where('origen_id', $c->id)
            ->exists();

        if ($yaRegistrado) {
            return;
        }

        // Solo un Responsable Inscripto genera IVA débito fiscal (Factura A/B/M).
        // Un Monotributo/exento (Factura C) no discrimina ni debe IVA → su
        // comprobante no alimenta la posición de IVA.
        if ($c->cuit?->condicionIva?->codigo !== CondicionIva::RESPONSABLE_INSCRIPTO) {
            return;
        }

        $esNotaCredito = $c->comprobante_asociado_id !== null;
        $signo = $esNotaCredito ? -1 : 1;
        $observaciones = $esNotaCredito
            ? __('Nota de crédito del comprobante #:id', ['id' => $c->comprobante_asociado_id])
            : null;

        $ivaDebito = Impuesto::porCodigo('iva_debito')->first();

        if ($ivaDebito !== null) {
            foreach ($c->detallesIva as $iva) {
                if (round((float) $iva->importe, 2) <= 0) {
                    continue; // Alícuota 0% / exento: no genera débito fiscal.
                }

                $this->registrarMovimientoFiscal([
                    'cuit_id' => $c->cuit_id,
                    'sucursal_id' => $c->sucursal_id,
                    'impuesto_id' => $ivaDebito->id,
                    'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
                    'naturaleza' => MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
                    'fecha' => $c->fecha_emision,
                    'base_imponible' => $signo * (float) $iva->base_imponible,
                    'alicuota' => $iva->alicuota,
                    'monto' => $signo * (float) $iva->importe,
                    'origen_tipo' => 'ComprobanteFiscal',
                    'origen_id' => $c->id,
                    'observaciones' => $observaciones,
                    'usuario_id' => $usuarioId,
                ], permitirNegativo: $esNotaCredito);
            }
        }

        // Percepciones APLICADAS (Fase 5b): el comercio actúa como agente. Cada
        // tributo del comprobante es deuda a depositar ante el fisco (sentido
        // aplicado, naturaleza percepción) — NO integra la posición de IVA propia.
        // La NC lleva copia de los tributos del original → acá salen en negativo.
        foreach ($c->tributosDetalle as $tributo) {
            if (round((float) $tributo->monto, 2) <= 0) {
                continue;
            }

            $this->registrarMovimientoFiscal([
                'cuit_id' => $c->cuit_id,
                'sucursal_id' => $c->sucursal_id,
                'impuesto_id' => $tributo->impuesto_id,
                'sentido' => MovimientoFiscal::SENTIDO_APLICADO,
                'naturaleza' => MovimientoFiscal::NATURALEZA_PERCEPCION,
                'fecha' => $c->fecha_emision,
                'base_imponible' => $signo * (float) $tributo->base_imponible,
                'alicuota' => $tributo->alicuota,
                'monto' => $signo * (float) $tributo->monto,
                'origen_tipo' => 'ComprobanteFiscal',
                'origen_id' => $c->id,
                'observaciones' => $observaciones,
                'usuario_id' => $usuarioId,
            ], permitirNegativo: $esNotaCredito);
        }
    }

    /**
     * Registra los movimientos fiscales de una compra (RF-05, Fase 6): IVA
     * crédito fiscal + percepciones/retenciones sufridas, imputados al CUIT de
     * la compra (`compras.cuit_id`).
     *
     * CONTRATO DESACOPLADO del módulo de compras (hoy inconsistente: el modelo
     * espera columnas que la tabla no tiene). No depende de las columnas de
     * compras/detalle ni persiste nada de compras: lee fuentes fiscales limpias.
     *  - $ivaCredito: array de {base_imponible?, alicuota?, monto} — el crédito
     *    fiscal de IVA discriminado por alícuota. SOLO Factura A da crédito (lo
     *    decide el caller según el tipo de comprobante); vacío para B/C o
     *    proveedor monotributo.
     *  - Percepciones/retenciones: se leen de `$compra->percepciones`
     *    (compra_percepciones, que el módulo de compras persiste al cargar la
     *    factura) → ledger sentido sufrido con la naturaleza del impuesto.
     *
     * Guard: sin `cuit_id` no genera nada (compra sin atribución fiscal).
     * Idempotente por origen. El caller (CompraService) arma $ivaCredito desde
     * `compra_ivas` (fuente canónica) SOLO si fiscal AND discrimina AND CUIT
     * comprador RI; cancelar → anularDesdeCompra().
     *
     * $esNotaCredito (RF-21): la NC de proveedor registra la reversa del
     * crédito con SU PROPIO desglose, en NEGATIVO y en el período de la NC
     * (patrón NC cross-período) — el caller pasa los montos del documento en
     * positivo y acá se invierten.
     */
    public function registrarDesdeCompra(Compra $compra, array $ivaCredito = [], ?int $usuarioId = null, bool $esNotaCredito = false): void
    {
        if ($compra->cuit_id === null) {
            return; // Sin atribución fiscal: no alimenta el ledger.
        }

        $yaRegistrado = MovimientoFiscal::query()
            ->activos()
            ->where('origen_tipo', 'Compra')
            ->where('origen_id', $compra->id)
            ->exists();

        if ($yaRegistrado) {
            return;
        }

        // Período del crédito = fecha del COMPROBANTE del proveedor (RF-06 del
        // spec compras-costos: una factura de junio cargada en julio computa
        // el crédito en JUNIO). Fallback a la fecha de la compra.
        $fecha = $compra->fecha_comprobante ?? $compra->fecha ?? now();
        $signo = $esNotaCredito ? -1 : 1;
        // RF-B12: una NC suelta (sin compra origen) no dice "compra origen #0".
        $observaciones = $esNotaCredito
            ? ($compra->compra_origen_id !== null
                ? __('Nota de crédito de proveedor (compra origen #:id)', ['id' => $compra->compra_origen_id])
                : __('Nota de crédito de proveedor (sin compra origen)'))
            : null;

        // IVA crédito fiscal por alícuota (sentido sufrido).
        $impuestoIvaCredito = Impuesto::porCodigo('iva_credito')->first();

        if ($impuestoIvaCredito !== null) {
            foreach ($ivaCredito as $linea) {
                $monto = round(abs((float) ($linea['monto'] ?? 0)), 2);

                if ($monto <= 0) {
                    continue;
                }

                $this->registrarMovimientoFiscal([
                    'cuit_id' => $compra->cuit_id,
                    'sucursal_id' => $compra->sucursal_id,
                    'impuesto_id' => $impuestoIvaCredito->id,
                    'sentido' => MovimientoFiscal::SENTIDO_SUFRIDO,
                    'naturaleza' => MovimientoFiscal::NATURALEZA_CREDITO_FISCAL,
                    'fecha' => $fecha,
                    'base_imponible' => isset($linea['base_imponible']) ? $signo * abs((float) $linea['base_imponible']) : null,
                    'alicuota' => $linea['alicuota'] ?? null,
                    'monto' => $signo * $monto,
                    'origen_tipo' => 'Compra',
                    'origen_id' => $compra->id,
                    'observaciones' => $observaciones,
                    'usuario_id' => $usuarioId,
                ], permitirNegativo: $esNotaCredito);
            }
        }

        // Percepciones/retenciones sufridas (ya persistidas en compra_percepciones).
        // D25: al ledger va solo la parte COMPUTABLE (monto × coeficiente); el
        // resto es costo y lo prorratea CompraService. Coeficiente NULL =
        // legado/sin dato ⇒ 100% computable (comportamiento histórico).
        foreach ($compra->percepciones as $percepcion) {
            $impuesto = $percepcion->impuesto;
            $coeficiente = $percepcion->coeficiente !== null ? (float) $percepcion->coeficiente : 1.0;
            $monto = round(abs((float) $percepcion->monto) * $coeficiente, 2);

            if ($impuesto === null || $monto <= 0) {
                continue;
            }

            $this->registrarMovimientoFiscal([
                'cuit_id' => $compra->cuit_id,
                'sucursal_id' => $compra->sucursal_id,
                'impuesto_id' => $impuesto->id,
                'sentido' => MovimientoFiscal::SENTIDO_SUFRIDO,
                'naturaleza' => $impuesto->naturaleza_default,
                'fecha' => $fecha,
                'base_imponible' => $percepcion->base_imponible !== null ? $signo * abs((float) $percepcion->base_imponible) : null,
                'alicuota' => $percepcion->alicuota,
                'monto' => $signo * $monto,
                'certificado_numero' => $percepcion->certificado_numero,
                'origen_tipo' => 'Compra',
                'origen_id' => $compra->id,
                'observaciones' => $observaciones,
                'usuario_id' => $usuarioId,
            ], permitirNegativo: $esNotaCredito);
        }
    }

    /**
     * Revierte los movimientos fiscales de una compra al cancelarla (RF-05),
     * con el patrón NC CROSS-PERÍODO (revisión Fable + spec compras-costos):
     * el período original puede estar ya declarado ante el fisco, así que la
     * cancelación NO pisa el original — registra reversas NEGATIVAS fechadas
     * HOY (período actual), dejando los originales activos (netean a cero).
     *
     * Idempotente: si la suma neta del origen ya es cero, la reversa ya corrió.
     * Cancela también NCs (sus movimientos negativos se revierten en positivo).
     */
    public function anularDesdeCompra(Compra $compra, ?int $usuarioId = null): void
    {
        $movimientos = MovimientoFiscal::query()
            ->activos()
            ->where('origen_tipo', 'Compra')
            ->where('origen_id', $compra->id)
            ->get();

        if ($movimientos->isEmpty() || abs((float) $movimientos->sum('monto')) < 0.01) {
            return;
        }

        foreach ($movimientos as $original) {
            $this->registrarMovimientoFiscal([
                'cuit_id' => $original->cuit_id,
                'sucursal_id' => $original->sucursal_id,
                'impuesto_id' => $original->impuesto_id,
                'sentido' => $original->sentido,
                'naturaleza' => $original->naturaleza,
                'fecha' => now(),
                'base_imponible' => $original->base_imponible !== null ? -1 * (float) $original->base_imponible : null,
                'alicuota' => $original->alicuota,
                'monto' => -1 * (float) $original->monto,
                'certificado_numero' => $original->certificado_numero,
                'origen_tipo' => 'Compra',
                'origen_id' => $compra->id,
                'observaciones' => __('Cancelación de compra #:id (reversa del movimiento #:mov)', [
                    'id' => $compra->id,
                    'mov' => $original->id,
                ]),
                'usuario_id' => $usuarioId ?? (int) ($compra->usuario_id ?? 0),
            ], permitirNegativo: true);
        }
    }

    /**
     * Registra el movimiento fiscal SUFRIDO de una fila de conciliación cuyo
     * impuesto ya fue identificado (RF-06). Se invoca al APLICAR la corrida.
     *
     * El impuesto y su naturaleza salen del catálogo (la fila ya tiene
     * impuesto_id resuelto por el gateway). El monto es lo efectivamente
     * descontado por el proveedor (monto_neto, en positivo). Imputado al CUIT
     * de la cuenta (RF-07).
     *
     * REVISAR (4b): se registra SIN base imponible ni alícuota porque el formato
     * de TAXES_DISAGGREGATED (de donde sale la base) todavía no está confirmado
     * contra un reporte real. Cuando se tenga, poblar base_imponible/alicuota
     * desde fila->datos_extra y activar la validación esperado-vs-real.
     *
     * Idempotente: si ya existe un movimiento fiscal activo con este origen, no
     * duplica.
     */
    public function registrarDesdeConciliacion(ConciliacionFila $fila, Cuit $cuit, ?int $usuarioId = null): ?MovimientoFiscal
    {
        if ($fila->impuesto_id === null) {
            return null; // Residuo genérico sin impuesto reconocido: no se registra.
        }

        $yaRegistrado = MovimientoFiscal::query()
            ->activos()
            ->where('origen_tipo', 'ConciliacionFila')
            ->where('origen_id', $fila->id)
            ->exists();

        if ($yaRegistrado) {
            return null;
        }

        $impuesto = $fila->impuesto ?? Impuesto::find($fila->impuesto_id);

        if ($impuesto === null) {
            return null;
        }

        $monto = round(abs((float) $fila->monto_neto), 2);

        if ($monto <= 0) {
            return null;
        }

        return $this->registrarMovimientoFiscal([
            'cuit_id' => $cuit->id,
            'impuesto_id' => $impuesto->id,
            'sentido' => MovimientoFiscal::SENTIDO_SUFRIDO,
            'naturaleza' => $impuesto->naturaleza_default,
            'fecha' => $fila->fecha ?? now(),
            'base_imponible' => null, // REVISAR (4b): pendiente formato TAXES_DISAGGREGATED.
            'alicuota' => null,
            'monto' => $monto,
            'origen_tipo' => 'ConciliacionFila',
            'origen_id' => $fila->id,
            'observaciones' => __('Impuesto sufrido vía conciliación').' #'.$fila->conciliacion_cuenta_id,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * Valida un impuesto sufrido de la conciliación contra la config del CUIT
     * (RF-06/D4) y devuelve el texto de alerta a mostrar en la revisión, o null
     * si todo está en orden.
     *
     * v1 (4a): sin la base imponible solo se puede chequear si el CUIT tiene
     * configurado/alcanzado el impuesto que el proveedor descontó. La comparación
     * de alícuota efectiva (monto/base) vs. la configurada queda para 4b.
     *
     * REVISAR (4b): cuando se tenga la base (TAXES_DISAGGREGATED), comparar la
     * alícuota efectiva contra config->alicuota y alertar si difiere > tolerancia.
     */
    public function validarImpuestoSufrido(ConciliacionFila $fila, Cuit $cuit): ?string
    {
        if ($fila->impuesto_id === null) {
            return null;
        }

        $fecha = $fila->fecha ? Carbon::parse($fila->fecha) : null;
        $config = $this->configVigente($cuit, (int) $fila->impuesto_id, $fecha);

        if ($config === null || ! $config->inscripto) {
            $impuesto = $fila->impuesto ?? Impuesto::find($fila->impuesto_id);

            return __('El proveedor descontó :impuesto pero el CUIT no lo tiene configurado: revisá si corresponde', [
                'impuesto' => $impuesto?->nombre ?? __('un impuesto'),
            ]);
        }

        return null;
    }

    /**
     * Valida los campos requeridos de un movimiento fiscal antes de registrarlo.
     * Monto negativo solo con $permitirNegativo (reversas de NC); cero nunca.
     */
    private function validarDatosMovimiento(array $datos, bool $permitirNegativo = false): void
    {
        foreach (['cuit_id', 'impuesto_id', 'sentido', 'naturaleza', 'fecha', 'monto'] as $campo) {
            if (! isset($datos[$campo]) || $datos[$campo] === '') {
                throw new Exception(__('Falta el campo requerido :campo del movimiento fiscal', ['campo' => $campo]));
            }
        }

        if (! in_array($datos['sentido'], [MovimientoFiscal::SENTIDO_SUFRIDO, MovimientoFiscal::SENTIDO_APLICADO], true)) {
            throw new Exception(__('Sentido de movimiento fiscal inválido: :valor', ['valor' => $datos['sentido']]));
        }

        $naturalezasValidas = [
            MovimientoFiscal::NATURALEZA_PERCEPCION,
            MovimientoFiscal::NATURALEZA_RETENCION,
            MovimientoFiscal::NATURALEZA_DEBITO_FISCAL,
            MovimientoFiscal::NATURALEZA_CREDITO_FISCAL,
            MovimientoFiscal::NATURALEZA_TRIBUTO,
        ];

        if (! in_array($datos['naturaleza'], $naturalezasValidas, true)) {
            throw new Exception(__('Naturaleza de movimiento fiscal inválida: :valor', ['valor' => $datos['naturaleza']]));
        }

        $monto = round((float) $datos['monto'], 2);

        if ($monto == 0.0 || (! $permitirNegativo && $monto < 0)) {
            throw new Exception(__('El monto del movimiento fiscal debe ser positivo'));
        }
    }
}

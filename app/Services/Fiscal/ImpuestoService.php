<?php

namespace App\Services\Fiscal;

use App\Models\ComprobanteFiscal;
use App\Models\ConciliacionFila;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\CuitImpuestoConfig;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use App\Models\Sucursal;
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
 * Los hooks que ALIMENTAN el ledger desde comprobantes/compras/conciliación
 * (registrarDesde*) se cablean en fases posteriores (4/5/6).
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
     */
    public function registrarMovimientoFiscal(array $datos): MovimientoFiscal
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $this->validarDatosMovimiento($datos);

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
     * REVISAR (Fable): esto solo hace anulación TOTAL. RF-04 pide contraasientos
     * PROPORCIONALES para la nota de crédito (reversa parcial de un débito/tributo).
     * Falta un método tipo `revertirParcial(mov, monto)` o que registrarDesdeComprobante
     * de la NC genere las reversas proporcionales. Se resuelve en Fase 5.
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
     *  - Percepción IVA (nacional): sin condicionamiento por jurisdicción.
     *  - Percepción IIBB (provincial): solo si la jurisdicción del impuesto
     *    coincide con la provincia de la SUCURSAL de la operación. El match fino
     *    por provincia del receptor queda para la fase de padrones (D3): hoy no
     *    tenemos ese dato confiable.
     * Respeta `alicuota_minimo_base` (si el neto gravado es menor → no percibe).
     * Consumidor final / monotributo / exento / receptor null → sin percepción.
     *
     * El IVA débito del comprobante NO se calcula acá (lo hace
     * ComprobanteFiscalIva); esto devuelve solo las percepciones extra.
     *
     * @param  ?Carbon  $fecha  fecha de la operación (para la vigencia de la config); por defecto hoy
     * @return array<int, array{impuesto_id:int, codigo:string, tipo:string, jurisdiccion:?string, base_imponible:float, alicuota:float, monto:float}>
     */
    public function calcularTributos(Cuit $emisor, ?CondicionIva $receptor, float $netoGravado, ?Sucursal $sucursal = null, ?Carbon $fecha = null): array
    {
        // REVISAR (Fable): la matriz es v1 conservador (ver "Revisión pendiente"
        // en el spec). Puntos que AÚN faltan auditar contra la normativa real:
        //  - "solo RI" como receptor ignora regímenes que perciben también a
        //    monotributo/exento según jurisdicción.
        //  - Falta un "monto mínimo de percepción" (sobre el importe resultante)
        //    y un "monto no sujeto" (se resta de la base); hoy solo
        //    alicuota_minimo_base como umbral de base.
        //  - IIBB no contempla Convenio Multilateral ni padrón del receptor
        //    (diferido a fase padrones).
        if ($netoGravado <= 0) {
            return [];
        }

        // Solo un Responsable Inscripto puede actuar como agente de percepción/
        // retención (un monotributo/exento no puede, aunque la config lo diga).
        if ($emisor->condicionIva?->codigo !== CondicionIva::RESPONSABLE_INSCRIPTO) {
            return [];
        }

        // v1: solo se percibe a Responsables Inscriptos.
        if ($receptor === null || $receptor->codigo !== CondicionIva::RESPONSABLE_INSCRIPTO) {
            return [];
        }

        $jurisdiccionOperacion = $sucursal?->provincia;
        $base = round($netoGravado, 2);

        $configs = $emisor->impuestoConfigs()
            ->vigentes($fecha?->toDateString())
            ->where('inscripto', true)
            ->where('es_agente_percepcion', true)
            ->whereNotNull('alicuota')
            ->with('impuesto')
            ->get();

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

            // IIBB provincial: la jurisdicción del impuesto debe coincidir con
            // la provincia de la sucursal de la operación.
            if ($impuesto->tipo === Impuesto::TIPO_IIBB) {
                if ($jurisdiccionOperacion === null || $impuesto->jurisdiccion !== $jurisdiccionOperacion) {
                    continue;
                }
            }

            // Base mínima para aplicar la percepción.
            // REVISAR (Fable): se interpreta `alicuota_minimo_base` como umbral
            // de BASE imponible (si el neto es menor, no se percibe). Muchos
            // regímenes definen además un "monto mínimo de percepción" (sobre el
            // importe resultante) y/o un "monto no sujeto" que se resta de la
            // base. Hoy no se modela ninguno de esos dos.
            if ($config->alicuota_minimo_base !== null && $base < (float) $config->alicuota_minimo_base) {
                continue;
            }

            $alicuota = (float) $config->alicuota;
            $monto = round($base * $alicuota / 100, 2);

            if ($monto <= 0) {
                continue;
            }

            $tributos[] = [
                'impuesto_id' => $impuesto->id,
                'codigo' => $impuesto->codigo,
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
     * Registra los movimientos fiscales de un comprobante autorizado (RF-04,
     * Fase 5a). Se invoca DESPUÉS de obtener el CAE y de commitear el
     * comprobante (un CAE ya emitido no se pierde si el ledger fallara).
     *
     * - Factura/comprobante: IVA débito fiscal por alícuota (sentido aplicado),
     *   tomado del desglose ya calculado en ComprobanteFiscalIva — alimenta la
     *   posición de IVA sin recalcular nada.
     * - Nota de crédito (comprobante_asociado_id): contraasiento de los
     *   movimientos del comprobante original (anulación; las NC de este sistema
     *   son por el total). REVISAR (Fable): NC PARCIAL requeriría reversa
     *   proporcional.
     *
     * Idempotente: si el comprobante ya tiene movimientos activos, no duplica.
     * Las percepciones APLICADAS (caso agente, sentido aplicado naturaleza
     * percepción) son Fase 5b (deben viajar a AFIP en el mismo acto).
     */
    public function registrarDesdeComprobante(ComprobanteFiscal $c, ?int $usuarioId = null): void
    {
        // Nota de crédito → contraasiento de los movimientos del original.
        if ($c->comprobante_asociado_id !== null) {
            $movimientos = MovimientoFiscal::query()
                ->activos()
                ->where('origen_tipo', 'ComprobanteFiscal')
                ->where('origen_id', $c->comprobante_asociado_id)
                ->get();

            foreach ($movimientos as $movimiento) {
                $this->anularMovimientoFiscal(
                    $movimiento,
                    $usuarioId ?? (int) ($c->usuario_id ?? 0),
                    __('Nota de crédito #:id', ['id' => $c->id]),
                );
            }

            return;
        }

        // Idempotencia: ya registrado para este comprobante.
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

        $ivaDebito = Impuesto::porCodigo('iva_debito')->first();

        if ($ivaDebito === null) {
            return;
        }

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
                'base_imponible' => $iva->base_imponible,
                'alicuota' => $iva->alicuota,
                'monto' => $iva->importe,
                'origen_tipo' => 'ComprobanteFiscal',
                'origen_id' => $c->id,
                'usuario_id' => $usuarioId,
            ]);
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
     */
    private function validarDatosMovimiento(array $datos): void
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

        if (round((float) $datos['monto'], 2) <= 0) {
            throw new Exception(__('El monto del movimiento fiscal debe ser positivo'));
        }
    }
}

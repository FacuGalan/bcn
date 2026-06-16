<?php

namespace App\Services\Fiscal;

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
     * @return array<int, array{impuesto_id:int, codigo:string, tipo:string, jurisdiccion:?string, base_imponible:float, alicuota:float, monto:float}>
     */
    public function calcularTributos(Cuit $emisor, ?CondicionIva $receptor, float $netoGravado, ?Sucursal $sucursal = null): array
    {
        // REVISAR (Fable): la matriz es v1 conservador (ver "Revisión pendiente"
        // en el spec). Puntos a auditar contra la normativa real:
        //  - "solo RI" ignora regímenes que perciben a monotributo/exento según
        //    jurisdicción; faltaría un "monto mínimo de percepción" (importe, no
        //    solo base mínima); IIBB no contempla Convenio Multilateral ni padrón
        //    del receptor (diferido a fase padrones).
        //  - No se cruza la condición del EMISOR: un monotributista no debería
        //    poder ser agente de percepción aunque la config lo diga.
        //  - vigentes() usa now(); cuando se cablee en emisión (Fase 5) debería
        //    usar la FECHA del comprobante, no la fecha actual.
        if ($netoGravado <= 0) {
            return [];
        }

        // v1: solo se percibe a Responsables Inscriptos.
        if ($receptor === null || $receptor->codigo !== CondicionIva::RESPONSABLE_INSCRIPTO) {
            return [];
        }

        $jurisdiccionOperacion = $sucursal?->provincia;
        $base = round($netoGravado, 2);

        $configs = $emisor->impuestoConfigs()
            ->vigentes()
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

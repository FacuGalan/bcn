<?php

namespace App\Services\Fiscal;

use App\Models\ComprobanteFiscal;
use App\Models\Cuit;
use App\Models\Impuesto;
use App\Models\MovimientoFiscal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Posición fiscal y libros de IVA (RF-09, Fase 7 sistema-impositivo).
 *
 * Servicio de SOLO LECTURA: arma la posición de IVA / IIBB de un CUIT en un
 * período (YYYY-MM) y los subdiarios de IVA ventas/compras. No escribe nada —
 * el ledger lo escribe únicamente ImpuestoService.
 *
 * Fuentes:
 *  - Posición: `movimientos_fiscales` (el ledger fiscal append-only). El débito
 *    fiscal (Fase 5a, desde comprobantes) y el crédito fiscal (Fase 6, desde
 *    compras) ya viven ahí, así que la posición es la suma de los movimientos
 *    activos del período.
 *  - Libro IVA Ventas: `comprobantes_fiscales` autorizados (la fuente con el
 *    detalle por comprobante y por alícuota — un libro IVA ES la lista de
 *    comprobantes). Reconcilia con la posición por construcción: el débito
 *    fiscal del ledger se genera desde estos mismos comprobantes.
 *  - Libro IVA Compras: se arma desde el ledger (`movimientos_fiscales` con
 *    origen Compra), porque el módulo de compras está hoy inconsistente y no
 *    cablea su hook. Cuando se reconcilie compras, sumará el detalle por
 *    proveedor sin cambiar este contrato.
 *
 * Semántica de signos (clave): la posición de IVA es
 *   débito fiscal − crédito fiscal − (percepciones/retenciones de IVA SUFRIDAS).
 * Las percepciones/retenciones que el comercio APLICA como agente (sentido
 * `aplicado`) NO son parte de su posición de IVA: son deuda a depositar y se
 * informan aparte.
 *
 * Ref: .claude/specs/sistema-impositivo.md (RF-09, Fase 7).
 */
class PosicionFiscalService
{
    /**
     * Posición de IVA del CUIT en el período (RF-09).
     *
     * @return array{periodo:string, debito_fiscal:float, credito_fiscal:float,
     *   saldo_tecnico:float, percepciones_iva_sufridas:float,
     *   retenciones_iva_sufridas:float, a_cuenta:float, saldo:float,
     *   a_pagar:float, saldo_a_favor:float, percepciones_iva_aplicadas:float,
     *   retenciones_iva_aplicadas:float}
     */
    public function posicionIva(Cuit $cuit, string $periodo): array
    {
        $movimientos = MovimientoFiscal::query()
            ->activos()
            ->deCuit($cuit->id)
            ->dePeriodo($periodo)
            ->with('impuesto')
            ->get();

        $debitoFiscal = 0.0;
        $creditoFiscal = 0.0;
        $percepcionesSufridas = 0.0;
        $retencionesSufridas = 0.0;
        // Como agente (deuda a depositar, NO parte de la posición de IVA).
        $percepcionesAplicadas = 0.0;
        $retencionesAplicadas = 0.0;

        foreach ($movimientos as $mov) {
            $monto = (float) $mov->monto;
            $esIva = $mov->impuesto !== null && $mov->impuesto->tipo === Impuesto::TIPO_IVA;

            switch ($mov->naturaleza) {
                case MovimientoFiscal::NATURALEZA_DEBITO_FISCAL:
                    $debitoFiscal += $monto;
                    break;

                case MovimientoFiscal::NATURALEZA_CREDITO_FISCAL:
                    $creditoFiscal += $monto;
                    break;

                case MovimientoFiscal::NATURALEZA_PERCEPCION:
                    if (! $esIva) {
                        break;
                    }
                    if ($mov->sentido === MovimientoFiscal::SENTIDO_SUFRIDO) {
                        $percepcionesSufridas += $monto;
                    } else {
                        $percepcionesAplicadas += $monto;
                    }
                    break;

                case MovimientoFiscal::NATURALEZA_RETENCION:
                    if (! $esIva) {
                        break;
                    }
                    if ($mov->sentido === MovimientoFiscal::SENTIDO_SUFRIDO) {
                        $retencionesSufridas += $monto;
                    } else {
                        $retencionesAplicadas += $monto;
                    }
                    break;
            }
        }

        $debitoFiscal = round($debitoFiscal, 2);
        $creditoFiscal = round($creditoFiscal, 2);
        $percepcionesSufridas = round($percepcionesSufridas, 2);
        $retencionesSufridas = round($retencionesSufridas, 2);

        $saldoTecnico = round($debitoFiscal - $creditoFiscal, 2);
        $aCuenta = round($percepcionesSufridas + $retencionesSufridas, 2);
        // Saldo > 0 → IVA a pagar. Saldo < 0 → saldo a favor del contribuyente.
        $saldo = round($saldoTecnico - $aCuenta, 2);

        return [
            'periodo' => $periodo,
            'debito_fiscal' => $debitoFiscal,
            'credito_fiscal' => $creditoFiscal,
            'saldo_tecnico' => $saldoTecnico,
            'percepciones_iva_sufridas' => $percepcionesSufridas,
            'retenciones_iva_sufridas' => $retencionesSufridas,
            'a_cuenta' => $aCuenta,
            'saldo' => $saldo,
            'a_pagar' => round(max($saldo, 0), 2),
            'saldo_a_favor' => round(max(-$saldo, 0), 2),
            'percepciones_iva_aplicadas' => round($percepcionesAplicadas, 2),
            'retenciones_iva_aplicadas' => round($retencionesAplicadas, 2),
        ];
    }

    /**
     * Posición de IIBB por jurisdicción (RF-09).
     *
     * Cada jurisdicción (código ISO 3166-2, ej. `AR-B`) lista:
     *  - base_imponible: ingresos del período (neto gravado de los comprobantes
     *    cuya sucursal pertenece a esa provincia; NC restan).
     *  - percepciones / retenciones SUFRIDAS a cuenta + total a_cuenta.
     *  - percepciones / retenciones APLICADAS (como agente, deuda a depositar).
     *
     * REVISAR (Fable): (1) la base imponible de IIBB se toma como `neto_gravado`
     * de ventas; varias jurisdicciones consideran ingresos brutos (gravado + no
     * gravado + exento) — confirmar con el contador. (2) No contempla Convenio
     * Multilateral (reparto de base entre jurisdicciones) ni alícuota por padrón
     * del sujeto. Diferido a la fase de padrones.
     *
     * @return array<int, array{jurisdiccion:string, jurisdiccion_nombre:string,
     *   base_imponible:float, percepciones_sufridas:float,
     *   retenciones_sufridas:float, a_cuenta:float, percepciones_aplicadas:float,
     *   retenciones_aplicadas:float}>
     */
    public function posicionIibb(Cuit $cuit, string $periodo): array
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        /** @var array<string, array<string, float>> $jurisdicciones */
        $jurisdicciones = [];

        $ensure = function (string $iso) use (&$jurisdicciones): void {
            if (! isset($jurisdicciones[$iso])) {
                $jurisdicciones[$iso] = [
                    'base_imponible' => 0.0,
                    'percepciones_sufridas' => 0.0,
                    'retenciones_sufridas' => 0.0,
                    'percepciones_aplicadas' => 0.0,
                    'retenciones_aplicadas' => 0.0,
                ];
            }
        };

        // Percepciones / retenciones de IIBB del ledger, por jurisdicción.
        $movimientos = MovimientoFiscal::query()
            ->activos()
            ->deCuit($cuit->id)
            ->dePeriodo($periodo)
            ->with('impuesto')
            ->whereHas('impuesto', fn ($q) => $q->where('tipo', Impuesto::TIPO_IIBB))
            ->get();

        foreach ($movimientos as $mov) {
            $iso = $mov->impuesto->jurisdiccion ?? 'AR';
            $ensure($iso);
            $monto = (float) $mov->monto;

            $sufrido = $mov->sentido === MovimientoFiscal::SENTIDO_SUFRIDO;

            if ($mov->naturaleza === MovimientoFiscal::NATURALEZA_PERCEPCION) {
                $jurisdicciones[$iso][$sufrido ? 'percepciones_sufridas' : 'percepciones_aplicadas'] += $monto;
            } elseif ($mov->naturaleza === MovimientoFiscal::NATURALEZA_RETENCION) {
                $jurisdicciones[$iso][$sufrido ? 'retenciones_sufridas' : 'retenciones_aplicadas'] += $monto;
            }
        }

        // Base imponible de ventas por jurisdicción. La jurisdicción sale del
        // DOMICILIO FISCAL del punto de venta del comprobante (RF-11, Fase 9), no
        // de la sucursal física: una caja puede facturar con un PV cuyo CUIT está
        // domiciliado en otra provincia. Fallback a la provincia de la sucursal y,
        // en última instancia, 'AR' (sin jurisdicción provincial definida).
        $comprobantes = ComprobanteFiscal::query()
            ->autorizados()
            ->where('cuit_id', $cuit->id)
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->with(['puntoVenta.cuitDomicilio:id,provincia', 'sucursal:id,provincia'])
            ->get(['id', 'tipo', 'punto_venta_id', 'sucursal_id', 'neto_gravado', 'comprobante_asociado_id']);

        foreach ($comprobantes as $comprobante) {
            $iso = $comprobante->puntoVenta?->cuitDomicilio?->provincia
                ?: $comprobante->sucursal?->provincia;

            if (empty($iso)) {
                $iso = 'AR'; // Sin jurisdicción definida.
            }

            $ensure($iso);
            // Las notas de crédito restan ingresos.
            $signo = $comprobante->esNotaCredito() ? -1 : 1;
            $jurisdicciones[$iso]['base_imponible'] += $signo * (float) $comprobante->neto_gravado;
        }

        $resultado = [];

        foreach ($jurisdicciones as $iso => $datos) {
            $aCuenta = round($datos['percepciones_sufridas'] + $datos['retenciones_sufridas'], 2);

            $resultado[] = [
                'jurisdiccion' => $iso,
                'jurisdiccion_nombre' => $this->nombreJurisdiccion($iso),
                'base_imponible' => round($datos['base_imponible'], 2),
                'percepciones_sufridas' => round($datos['percepciones_sufridas'], 2),
                'retenciones_sufridas' => round($datos['retenciones_sufridas'], 2),
                'a_cuenta' => $aCuenta,
                'percepciones_aplicadas' => round($datos['percepciones_aplicadas'], 2),
                'retenciones_aplicadas' => round($datos['retenciones_aplicadas'], 2),
            ];
        }

        // Orden estable por código de jurisdicción.
        usort($resultado, fn ($a, $b) => strcmp($a['jurisdiccion'], $b['jurisdiccion']));

        return $resultado;
    }

    /**
     * Subdiario de IVA Ventas: comprobantes fiscales autorizados del CUIT en el
     * período, ordenados por fecha y número, con su desglose por alícuota
     * (`detallesIva`), tributos y receptor ya cargados (RF-09).
     *
     * @return Collection<int, ComprobanteFiscal>
     */
    public function libroIvaVentas(Cuit $cuit, string $periodo): Collection
    {
        [$desde, $hasta] = $this->rangoPeriodo($periodo);

        return ComprobanteFiscal::query()
            ->autorizados()
            ->where('cuit_id', $cuit->id)
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->with(['detallesIva', 'tributosDetalle.impuesto', 'sucursal:id,nombre,provincia', 'cliente:id,nombre'])
            ->orderBy('fecha_emision')
            ->orderBy('punto_venta_numero')
            ->orderBy('numero_comprobante')
            ->get();
    }

    /**
     * Subdiario de IVA Compras: armado desde el ledger fiscal (movimientos con
     * origen `Compra`) agrupado por compra (RF-09). Cada línea expone el crédito
     * fiscal de IVA y las percepciones/retenciones sufridas de esa compra.
     *
     * Mientras el módulo de compras esté inconsistente (no cablea su hook), esto
     * estará vacío en la práctica; cuando se reconcilie, se poblará sin tocar
     * este método.
     *
     * @return Collection<int, array{origen_id:?int, fecha:?string,
     *   credito_fiscal:float, percepciones:float, retenciones:float,
     *   movimientos:Collection<int, MovimientoFiscal>}>
     */
    public function libroIvaCompras(Cuit $cuit, string $periodo): Collection
    {
        $movimientos = MovimientoFiscal::query()
            ->activos()
            ->deCuit($cuit->id)
            ->dePeriodo($periodo)
            ->sufridos()
            ->where('origen_tipo', 'Compra')
            ->with('impuesto')
            ->orderBy('fecha')
            ->orderBy('origen_id')
            ->get();

        return $movimientos
            ->groupBy('origen_id')
            ->map(function (Collection $grupo) {
                $creditoFiscal = 0.0;
                $percepciones = 0.0;
                $retenciones = 0.0;

                foreach ($grupo as $mov) {
                    $monto = (float) $mov->monto;

                    switch ($mov->naturaleza) {
                        case MovimientoFiscal::NATURALEZA_CREDITO_FISCAL:
                            $creditoFiscal += $monto;
                            break;
                        case MovimientoFiscal::NATURALEZA_PERCEPCION:
                            $percepciones += $monto;
                            break;
                        case MovimientoFiscal::NATURALEZA_RETENCION:
                            $retenciones += $monto;
                            break;
                    }
                }

                $primero = $grupo->first();

                return [
                    'origen_id' => $primero->origen_id,
                    'fecha' => $primero->fecha?->toDateString(),
                    'credito_fiscal' => round($creditoFiscal, 2),
                    'percepciones' => round($percepciones, 2),
                    'retenciones' => round($retenciones, 2),
                    'movimientos' => $grupo->values(),
                ];
            })
            ->values();
    }

    /**
     * Totales del libro IVA ventas (para el pie del subdiario y reconciliación
     * con la posición).
     *
     * @param  Collection<int, ComprobanteFiscal>  $comprobantes
     * @return array{neto_gravado:float, neto_no_gravado:float, neto_exento:float, iva:float, tributos:float, total:float}
     */
    public function totalesLibroVentas(Collection $comprobantes): array
    {
        $tot = [
            'neto_gravado' => 0.0,
            'neto_no_gravado' => 0.0,
            'neto_exento' => 0.0,
            'iva' => 0.0,
            'tributos' => 0.0,
            'total' => 0.0,
        ];

        foreach ($comprobantes as $c) {
            $signo = $c->esNotaCredito() ? -1 : 1;
            $tot['neto_gravado'] += $signo * (float) $c->neto_gravado;
            $tot['neto_no_gravado'] += $signo * (float) $c->neto_no_gravado;
            $tot['neto_exento'] += $signo * (float) $c->neto_exento;
            $tot['iva'] += $signo * (float) $c->iva_total;
            $tot['tributos'] += $signo * (float) $c->tributos;
            $tot['total'] += $signo * (float) $c->total;
        }

        return array_map(fn ($v) => round($v, 2), $tot);
    }

    /**
     * Convierte un período `YYYY-MM` al rango de fechas [primer día, último día].
     *
     * @return array{0:string, 1:string}
     */
    private function rangoPeriodo(string $periodo): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $periodo.'-01')->startOfMonth();

        return [$inicio->toDateString(), $inicio->copy()->endOfMonth()->toDateString()];
    }

    /**
     * Nombre legible de una jurisdicción ISO 3166-2 (reusa el catálogo del
     * modelo Sucursal); `AR` = nacional / sin jurisdicción provincial.
     */
    private function nombreJurisdiccion(string $iso): string
    {
        if ($iso === 'AR') {
            return __('Sin jurisdicción');
        }

        return \App\Models\Sucursal::PROVINCIAS_AR[$iso] ?? $iso;
    }
}

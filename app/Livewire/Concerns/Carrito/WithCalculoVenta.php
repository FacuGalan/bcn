<?php

namespace App\Livewire\Concerns\Carrito;

use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\Cupon;
use App\Models\ListaPrecio;
use App\Models\Promocion;
use App\Models\PromocionEspecial;
use Illuminate\Support\Facades\Log;

/**
 * Calculo de venta con promociones e IVA en NuevaVenta.
 *
 * Encapsula:
 * - Calculo de precio segun lista (precio base efectivo + lista activa con condiciones).
 * - Pool de unidades para promociones especiales (NxM, combo, menu).
 * - Promociones especiales: filtro por contexto, evaluacion greedy/optima, consumo de unidades.
 * - Promociones comunes: filtro, mejor combinacion (combinables vs exclusivas), distribucion por item.
 * - Desglose de IVA por alicuota con prorrateo de descuentos.
 * - Aplicacion en orden: promos -> descuento general -> cupon -> articulos canjeados -> canje puntos.
 * - Resolucion del tipo_iva_id por item (articulo, concepto con/sin categoria).
 *
 * Dependencias externas (resueltas via $this-> desde el componente que use el trait):
 * - $this->items                       (WithCarritoItems)
 * - $this->resultado                   (NuevaVenta)
 * - $this->sucursalId                  (SucursalAware)
 * - $this->listaPrecioId, listasPreciosDisponibles  (NuevaVenta)
 * - $this->formaVentaId, canalVentaId, formaPagoId  (NuevaVenta)
 * - $this->descuentoGeneral*           (WithDescuentos)
 * - $this->cuponAplicado, cuponInfo,
 *   cuponMontoDescuento, cuponArticulosBonificados,
 *   cuponService                       (WithCupones / NuevaVenta)
 * - $this->canjePuntosActivo, canjePuntosMonto  (WithPuntos)
 * - $this->cargarCuotasFormaPago(),
 *   calcularAjusteFormaPago(),
 *   formaPagoPermiteCuotas             (NuevaVenta — iran a WithPagosDesglose)
 */
trait WithCalculoVenta
{
    // =========================================
    // PRECIO POR LISTA
    // =========================================

    protected function obtenerPrecioConLista(Articulo $articulo): array
    {
        $precioBaseArticulo = $articulo->obtenerPrecioBaseEfectivo($this->sucursalId);

        // Obtener precio de la lista base
        $listaBase = ListaPrecio::obtenerListaBase($this->sucursalId);
        $precioListaBase = $precioBaseArticulo;
        if ($listaBase) {
            $precioInfoBase = $listaBase->obtenerPrecioArticulo($articulo, $precioBaseArticulo);
            $precioListaBase = $precioInfoBase['precio'];
        }

        // Si no hay lista seleccionada, usar lista base
        if (! $this->listaPrecioId) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        $listaPrecio = ListaPrecio::find($this->listaPrecioId);
        if (! $listaPrecio) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Si la lista seleccionada ES la base, no mostrar ajuste
        if ($listaPrecio->es_lista_base) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Validar condiciones de la lista
        $contexto = [
            'forma_pago_id' => $this->formaPagoId,
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
        ];

        if (! $listaPrecio->validarCondiciones($contexto)) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Lista estática que no cubre este artículo → caer a lista base
        if ($listaPrecio->estatica && ! $listaPrecio->cubreArticulo($articulo)) {
            return [
                'precio' => $precioListaBase,
                'precio_base' => $precioBaseArticulo,
                'tiene_ajuste' => false,
            ];
        }

        // Lista diferente a la base y cumple condiciones
        $precioInfo = $listaPrecio->obtenerPrecioArticulo($articulo, $precioBaseArticulo);

        return [
            'precio' => $precioInfo['precio'],
            'precio_base' => $precioBaseArticulo,
            'tiene_ajuste' => true,
        ];
    }

    /**
     * Resuelve el tipo_iva_id de un item del carrito.
     * - Artículos: del modelo Articulo.
     * - Conceptos con categoría: del tipo_iva de la categoría.
     * - Conceptos sin categoría: null (VentaService usa iva_porcentaje del detalle).
     */
    protected function resolverTipoIvaId(array $item): ?int
    {
        if (! ($item['es_concepto'] ?? false)) {
            if (! empty($item['articulo_id'])) {
                $articulo = Articulo::find($item['articulo_id']);

                return $articulo?->tipo_iva_id;
            }

            return null;
        }

        if (! empty($item['categoria_id'])) {
            $categoria = Categoria::find($item['categoria_id']);

            return $categoria?->tipo_iva_id;
        }

        return null;
    }

    // =========================================
    // CÁLCULO DE VENTA CON PROMOCIONES
    // =========================================

    public function calcularVenta()
    {
        if (empty($this->items)) {
            $this->resultado = null;

            return;
        }

        $resultado = [
            'items' => [],
            'subtotal' => 0,
            'promociones_especiales_aplicadas' => [],
            'promociones_comunes_aplicadas' => [],
            'total_descuentos' => 0,
            'total_final' => 0,
        ];

        // Crear pool de unidades disponibles
        $poolUnidades = $this->crearPoolUnidades();

        // Obtener información de promociones de la lista seleccionada
        $infoPromos = $this->obtenerInfoPromocionesLista();

        // Marcar artículos excluidos de promociones
        $articulosExcluidos = [];
        if (! $infoPromos['aplica_promociones']) {
            // La lista no aplica promociones, todos excluidos
            foreach ($poolUnidades as &$unidad) {
                $unidad['excluido_promociones'] = true;
                $articulosExcluidos[$unidad['articulo_id']] = true;
            }
        } elseif ($infoPromos['promociones_alcance'] === 'excluir_lista' && $this->listaPrecioId) {
            // Solo excluir artículos con precio especial en la lista
            $listaPrecio = ListaPrecio::find($this->listaPrecioId);
            if ($listaPrecio) {
                foreach ($poolUnidades as &$unidad) {
                    $categoriaId = $unidad['categoria_id'] ?? null;
                    $tienePrecioEspecial = $listaPrecio->articulos()
                        ->where(function ($query) use ($unidad, $categoriaId) {
                            $query->where('articulo_id', $unidad['articulo_id']);
                            if ($categoriaId) {
                                $query->orWhere('categoria_id', $categoriaId);
                            }
                        })
                        ->exists();
                    if ($tienePrecioEspecial) {
                        $unidad['excluido_promociones'] = true;
                        $articulosExcluidos[$unidad['articulo_id']] = true;
                    }
                }
            }
        }
        unset($unidad);

        // Items bonificados por un cupón aplicado tampoco entran en promociones (comunes ni especiales).
        // Regla 2026-05-07: el cupón tiene prioridad y excluye al item del resto de descuentos
        // automáticos, evitando combinaciones que regalen producto al cliente sin que el cajero lo note.
        if ($this->cuponAplicado && ! empty($this->cuponArticulosBonificados)) {
            $bonificadosCupon = array_flip($this->cuponArticulosBonificados);
            foreach ($poolUnidades as &$unidad) {
                if (isset($bonificadosCupon[$unidad['articulo_id']])) {
                    $unidad['excluido_promociones'] = true;
                    $articulosExcluidos[$unidad['articulo_id']] = true;
                }
            }
            unset($unidad);
        }

        // Calcular subtotal
        foreach ($this->items as $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (float) ($item['cantidad'] ?? 1);
            $resultado['subtotal'] += $precio * $cantidad;
        }

        // Contexto de la venta
        $contexto = [
            'sucursal_id' => $this->sucursalId,
            'forma_venta_id' => $this->formaVentaId,
            'canal_venta_id' => $this->canalVentaId,
            'forma_pago_id' => $this->formaPagoId,
            'fecha' => now()->format('Y-m-d'),
            'dia_semana' => (int) now()->dayOfWeek,
            'hora' => now()->format('H:i:s'),
        ];

        // Preparar items para promociones comunes (con info de exclusión).
        // Excluir items con es_invitacion=true: no participan del motor de
        // beneficios (RF-11 spec invitaciones).
        $itemsParaPromos = [];
        foreach ($this->getItemsParaMotorBeneficios() as $index => $item) {
            $articuloId = $item['articulo_id'] ?? null;
            $itemsParaPromos[$index] = [
                'articulo_id' => $articuloId,
                'categoria_id' => $item['categoria_id'] ?? null,
                'nombre' => $item['nombre'],
                'precio' => (float) ($item['precio'] ?? 0),
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'excluido_promociones' => isset($articulosExcluidos[$articuloId]),
            ];
        }

        // 1. Aplicar promociones especiales (NxM, Combo, Menú)
        //    a) Forzadas: se aplican siempre por orden de prioridad (ej: liquidar stock).
        //    b) Automáticas: el sistema elige la combinación que MÁS ahorra al cliente.
        if ($infoPromos['aplica_promociones']) {
            $promocionesEspeciales = $this->obtenerPromocionesEspeciales($contexto);

            $forzadas = array_values(array_filter(
                $promocionesEspeciales,
                fn ($p) => ($p['modo_aplicacion'] ?? 'automatica') === 'forzada'
            ));
            $automaticas = array_values(array_filter(
                $promocionesEspeciales,
                fn ($p) => ($p['modo_aplicacion'] ?? 'automatica') === 'automatica'
            ));

            // a) Aplicar forzadas secuencialmente (comportamiento legacy: greedy por prioridad)
            foreach ($forzadas as $promo) {
                $aplicacion = $this->intentarAplicarPromocionEspecial($promo, $poolUnidades);
                if ($aplicacion['aplicada']) {
                    $this->consumirUnidadesPromoEspecial($poolUnidades, $promo, $aplicacion);
                    $resultado['promociones_especiales_aplicadas'][] = $this->armarResultadoPromoEspecial($promo, $aplicacion);
                    $resultado['total_descuentos'] += $aplicacion['descuento'];
                }
            }

            // b) Aplicar automáticas: elegir el subset que maximiza el ahorro sobre el pool restante
            if (! empty($automaticas)) {
                $mejor = $this->encontrarMejorCombinacionEspeciales($automaticas, $poolUnidades);
                foreach ($mejor['aplicaciones'] as $aplicacionGanadora) {
                    $promo = $aplicacionGanadora['promo'];
                    $aplicacion = $aplicacionGanadora['aplicacion'];
                    $this->consumirUnidadesPromoEspecial($poolUnidades, $promo, $aplicacion);
                    $resultado['promociones_especiales_aplicadas'][] = $this->armarResultadoPromoEspecial($promo, $aplicacion);
                    $resultado['total_descuentos'] += $aplicacion['descuento'];
                }
            }

            // 2. Aplicar promociones comunes a items (soporta combinabilidad)
            $promocionesComunes = $this->obtenerPromocionesComunes($contexto);

            // Calcular unidades libres por item para promociones comunes
            foreach ($itemsParaPromos as $itemIndex => &$itemPromo) {
                $unidadesDelItem = array_values(array_filter($poolUnidades, fn ($u) => $u['item_index'] === $itemIndex));
                $unidadesLibres = array_values(array_filter($unidadesDelItem, fn ($u) => ! ($u['consumida'] ?? false)));
                $cantidadLibre = count($unidadesLibres);
                $cantidadTotal = count($unidadesDelItem);

                // Solo excluir si TODAS las unidades fueron consumidas
                if ($cantidadLibre === 0 && $cantidadTotal > 0) {
                    $itemPromo['excluido_promociones'] = true;
                } elseif ($cantidadLibre < $cantidadTotal) {
                    // Hay unidades parcialmente consumidas - ajustar cantidad
                    $itemPromo['cantidad_original'] = $itemPromo['cantidad'];
                    $itemPromo['cantidad'] = $cantidadLibre;
                }
            }
            unset($itemPromo);

            $resultado['promociones_comunes_aplicadas'] = $this->aplicarPromocionesComunes(
                $promocionesComunes,
                $itemsParaPromos,
                $contexto
            );

            // Sumar descuentos de promociones comunes
            foreach ($resultado['promociones_comunes_aplicadas'] as $promoComun) {
                $resultado['total_descuentos'] += $promoComun['descuento'];
            }
        }

        // Preparar información de items con estado
        foreach ($this->items as $index => $item) {
            // Filtrar unidades de este item específico y reindexar
            $unidadesDelItem = array_values(array_filter($poolUnidades, fn ($u) => $u['item_index'] === $index));
            $unidadesConsumidas = array_values(array_filter($unidadesDelItem, fn ($u) => $u['consumida'] ?? false));
            $unidadesLibres = array_values(array_filter($unidadesDelItem, fn ($u) => ! ($u['consumida'] ?? false)));
            $articuloId = $item['articulo_id'] ?? null;
            $excluido = isset($articulosExcluidos[$articuloId]);

            // Obtener promociones comunes aplicadas a este item
            $promocionesComunes = $itemsParaPromos[$index]['promociones_comunes'] ?? [];
            $descuentoComun = $itemsParaPromos[$index]['total_descuento_comun'] ?? 0;

            // Obtener info completa de promociones especiales aplicadas a este item.
            // Una promo puede tocar varias unidades del mismo item (ej: 3x2 con 3 unidades del mismo articulo);
            // sumamos los descuentos atribuidos a cada unidad bajo la misma promo para que el agregado por
            // item refleje el descuento real recibido (no el total de la promo replicado).
            $promosEspecialesItem = [];
            foreach ($unidadesConsumidas as $unidad) {
                if (empty($unidad['promo_especial_info'])) {
                    continue;
                }
                $info = $unidad['promo_especial_info'];
                $promoKey = $info['id'];
                if (! isset($promosEspecialesItem[$promoKey])) {
                    $promosEspecialesItem[$promoKey] = $info;
                    $promosEspecialesItem[$promoKey]['descuento'] = (float) ($info['descuento'] ?? 0);
                } else {
                    $promosEspecialesItem[$promoKey]['descuento'] += (float) ($info['descuento'] ?? 0);
                }
            }

            $resultado['items'][$index] = [
                'articulo_id' => $articuloId,
                'nombre' => $item['nombre'],
                'precio_base' => (float) ($item['precio_base'] ?? $item['precio'] ?? 0),
                'precio_lista' => (float) ($item['precio'] ?? 0),
                'cantidad' => (float) ($item['cantidad'] ?? 1),
                'subtotal' => (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1),
                'unidades_consumidas' => count($unidadesConsumidas),
                'unidades_libres' => count($unidadesLibres),
                'excluido_promociones' => $excluido,
                'tiene_ajuste' => $item['tiene_ajuste'] ?? false,
                'promociones_especiales' => array_values($promosEspecialesItem), // Array de objetos completos
                'promociones_comunes' => $promocionesComunes,
                'descuento_comun' => $descuentoComun,
            ];
        }

        // Validar límite máximo de descuento (70% del subtotal)
        if ($resultado['subtotal'] > 0) {
            $maxDescuento = $resultado['subtotal'] * 0.70;
            if ($resultado['total_descuentos'] > $maxDescuento) {
                Log::warning("Descuento total {$resultado['total_descuentos']} excede 70% del subtotal {$resultado['subtotal']}");
                $resultado['total_descuentos'] = $maxDescuento;
            }
        }

        // Calcular total final (después de descuentos de promociones)
        $resultado['total_final'] = max(0, $resultado['subtotal'] - $resultado['total_descuentos']);

        // Aplicar descuento general monto_fijo (RF-32): se resta del total DESPUÉS de promociones
        $resultado['descuento_general_monto'] = 0;
        if ($this->descuentoGeneralActivo) {
            if ($this->descuentoGeneralTipo === 'monto_fijo') {
                $montoFijo = min($this->descuentoGeneralValor, $resultado['total_final']);
                $resultado['descuento_general_monto'] = round($montoFijo, 2);
                $resultado['total_final'] = max(0, $resultado['total_final'] - $montoFijo);
            } elseif ($this->descuentoGeneralTipo === 'porcentaje') {
                // Para %: el monto es la suma de los descuentos aplicados por renglón (ya está en los precios).
                // Excluir items invitados — no reciben descuento general (RF-11).
                $montoTotal = 0;
                foreach ($this->getItemsParaMotorBeneficios() as $item) {
                    if ($item['ajuste_manual_tipo'] === 'porcentaje' && $item['precio_sin_ajuste_manual'] !== null) {
                        $precioOriginal = (float) $item['precio_sin_ajuste_manual'];
                        $precioActual = (float) $item['precio'];
                        $montoTotal += ($precioOriginal - $precioActual) * (float) ($item['cantidad'] ?? 1);
                    }
                }
                $resultado['descuento_general_monto'] = round($montoTotal, 2);
            }
            $this->descuentoGeneralMonto = $resultado['descuento_general_monto'];
        }

        // Aplicar cupón (RF-17, RF-18): se resta del total DESPUÉS de desc. general
        $resultado['monto_cupon'] = 0;
        if ($this->cuponAplicado && $this->cuponInfo) {
            $cuponId = $this->cuponInfo['id'];
            $cupon = Cupon::find($cuponId);
            if ($cupon) {
                if ($cupon->aplicaATotal()) {
                    $descuento = $this->cuponService->calcularDescuento($cupon, $resultado['total_final']);
                    $this->cuponMontoDescuento = $descuento['monto_descuento'];
                } elseif ($cupon->aplicaAArticulos()) {
                    // Recalcular con límite de cantidad por artículo.
                    // Items invitados NO son elegibles para cupon (RF-11).
                    $articulosCupon = $cupon->articulos()->get()->keyBy('id');
                    $bonificados = [];
                    $itemsParaCalculo = [];
                    foreach ($this->getItemsParaMotorBeneficios() as $item) {
                        $articuloId = $item['articulo_id'] ?? null;
                        if ($articuloId && $articulosCupon->has($articuloId)) {
                            $bonificados[] = $articuloId;
                            $itemsParaCalculo[] = $item;
                        }
                    }
                    $descuento = $this->cuponService->calcularDescuento(
                        $cupon, $resultado['total_final'], $bonificados, $itemsParaCalculo
                    );
                    $this->cuponMontoDescuento = $descuento['monto_descuento'];
                    $this->cuponArticulosBonificados = $bonificados;
                }

                // Detectar si el cupón se "recortó" naturalmente: cuando el valor original
                // del cupón es mayor al descuento que efectivamente puede aplicar (ej: cupón
                // monto fijo de $5000 sobre un item de $1000). CuponService ya hace el cap
                // con min(valor, montoElegible), pero el cajero debe enterarse para que sepa
                // que el cupón rinde menos de lo nominal en este carrito.
                $valorNominal = (float) $cupon->valor_descuento;
                $aplicoMenosDeLoNominal = $cupon->esMontoFijo()
                    && round($this->cuponMontoDescuento, 2) < round($valorNominal, 2);

                if ($aplicoMenosDeLoNominal) {
                    if (! $this->cuponRecortadoPorCap) {
                        $this->cuponRecortadoPorCap = true;
                        $this->dispatch(
                            'toast-warning',
                            message: __('El cupón rinde menos del valor nominal en este carrito (cap por monto disponible).')
                        );
                    }
                } else {
                    $this->cuponRecortadoPorCap = false;
                }

                $resultado['monto_cupon'] = $this->cuponMontoDescuento;
                $resultado['total_final'] = max(0, $resultado['total_final'] - $this->cuponMontoDescuento);
                $this->cuponInfo['monto_descuento'] = $this->cuponMontoDescuento;
            }
        }

        // Aplicar artículos canjeados con puntos (RF-10, RF-11): se restan del total
        $resultado['articulos_canjeados_monto'] = 0;
        foreach ($this->items as $item) {
            if ($item['pagado_con_puntos'] ?? false) {
                $resultado['articulos_canjeados_monto'] += (float) ($item['precio'] ?? 0) * (float) ($item['cantidad'] ?? 1);
            }
        }
        if ($resultado['articulos_canjeados_monto'] > 0) {
            $resultado['total_final'] = max(0, $resultado['total_final'] - $resultado['articulos_canjeados_monto']);
        }

        // Aplicar canje de puntos como pago (RF-09): se resta del total
        $resultado['puntos_usados_monto'] = 0;
        if ($this->canjePuntosActivo && $this->canjePuntosMonto > 0) {
            $montoCanje = min($this->canjePuntosMonto, $resultado['total_final']);
            $resultado['puntos_usados_monto'] = round($montoCanje, 2);
            $resultado['total_final'] = max(0, $resultado['total_final'] - $montoCanje);
        }

        // Calcular desglose de IVA (incluye todos los descuentos)
        $totalDescuentosParaIva = $resultado['total_descuentos'];
        if ($this->descuentoGeneralActivo && $this->descuentoGeneralTipo === 'monto_fijo') {
            $totalDescuentosParaIva += $resultado['descuento_general_monto'];
        }
        $totalDescuentosParaIva += $resultado['monto_cupon'];
        $totalDescuentosParaIva += $resultado['articulos_canjeados_monto'];
        $totalDescuentosParaIva += $resultado['puntos_usados_monto'];
        $resultado['desglose_iva'] = $this->calcularDesgloseIva(
            $resultado['items'],
            $totalDescuentosParaIva,
            $resultado['subtotal']
        );

        $this->resultado = $resultado;

        // Recalcular cuotas si la forma de pago permite cuotas (porque el total cambió)
        if ($this->formaPagoPermiteCuotas && $this->formaPagoId) {
            $this->cargarCuotasFormaPago();
        }

        // Recalcular ajuste de forma de pago si hay una seleccionada
        if ($this->formaPagoId) {
            $this->calcularAjusteFormaPago();
        }

        // Recalcular percepciones fiscales (Fase 5b): dependen del neto gravado, que
        // acaba de cambiar. calcularMontoFacturaFiscal recalcula monto fiscal + percepción.
        if (method_exists($this, 'calcularMontoFacturaFiscal')) {
            $this->calcularMontoFacturaFiscal();
        }
    }

    /**
     * Hook de lifecycle de Livewire (trait): corre justo antes de serializar el
     * componente, después de TODAS las mutaciones de `$this->resultado`
     * (incluido el recálculo del ajuste por forma de pago).
     *
     * Normaliza el CERO NEGATIVO (-0.0) que pueden producir las restas de floats
     * del desglose — p.ej. al invitar un ítem que integraba una promoción
     * compartida, su contribución a un bucket queda en `-0.0`. PHP serializa
     * `json_encode(-0.0)` como `-0`, pero el runtime JS de Livewire lo reenvía
     * como `0`; el checksum del snapshot deja de coincidir y se lanza
     * `CorruptComponentPayloadException` al cobrar. Forzar `+0.0` deja ambos
     * lados (PHP/JS) serializando idéntico.
     */
    public function dehydrateWithCalculoVenta(): void
    {
        if (is_array($this->resultado)) {
            $this->resultado = $this->normalizarCerosNegativos($this->resultado);
        }
    }

    /**
     * Reemplaza recursivamente cualquier float `-0.0` por `0.0` (cero positivo)
     * dentro de una estructura, para evitar divergencias de serialización
     * PHP/JS que rompen el checksum de Livewire. Los enteros y demás valores
     * quedan intactos.
     *
     * @param  mixed  $valor
     * @return mixed
     */
    protected function normalizarCerosNegativos($valor)
    {
        if (is_array($valor)) {
            foreach ($valor as $clave => $sub) {
                $valor[$clave] = $this->normalizarCerosNegativos($sub);
            }

            return $valor;
        }

        // -0.0 == 0.0 es true: cualquier cero flotante se fuerza a +0.0 literal.
        if (is_float($valor) && $valor == 0.0) {
            return 0.0;
        }

        return $valor;
    }

    /**
     * Calcula el desglose de IVA por alícuota
     *
     * Los precios de los items ya incluyen IVA, por lo que:
     * 1. Calculamos el neto de cada item: precio / (1 + alícuota/100)
     * 2. Agrupamos por código de alícuota
     * 3. Si hay descuentos, los prorrateamos proporcionalmente a los netos
     * 4. Recalculamos el IVA sobre los netos con descuento
     *
     * @param  array  $items  Items del resultado con precio y cantidad
     * @param  float  $totalDescuentos  Total de descuentos aplicados (promociones)
     * @param  float  $subtotal  Subtotal antes de descuentos
     * @return array Desglose por alícuota + totales
     */
    protected function calcularDesgloseIva(array $items, float $totalDescuentos, float $subtotal): array
    {
        // Inicializar acumuladores por alícuota
        $porAlicuota = [];

        // Calcular neto e IVA de cada item y agrupar
        foreach ($this->items as $index => $item) {
            $precio = (float) ($item['precio'] ?? 0);
            $cantidad = (float) ($item['cantidad'] ?? 1);
            $ivaCodigo = $item['iva_codigo'] ?? 5;
            $ivaPorcentaje = (float) ($item['iva_porcentaje'] ?? 21);
            $ivaNombre = $item['iva_nombre'] ?? 'IVA 21%';
            $precioIvaIncluido = $item['precio_iva_incluido'] ?? true;

            $subtotalItem = $precio * $cantidad;

            // Calcular neto e IVA del item
            if ($ivaPorcentaje == 0) {
                // Exento o No Gravado: todo es neto
                $netoItem = $subtotalItem;
                $ivaItem = 0;
            } elseif ($precioIvaIncluido) {
                // Precio incluye IVA: neto = precio / (1 + alícuota/100)
                $netoItem = $subtotalItem / (1 + $ivaPorcentaje / 100);
                $ivaItem = $subtotalItem - $netoItem;
            } else {
                // Precio no incluye IVA (raro pero posible)
                $netoItem = $subtotalItem;
                $ivaItem = $subtotalItem * ($ivaPorcentaje / 100);
            }

            // Inicializar alícuota si no existe
            if (! isset($porAlicuota[$ivaCodigo])) {
                $porAlicuota[$ivaCodigo] = [
                    'codigo' => $ivaCodigo,
                    'nombre' => $ivaNombre,
                    'porcentaje' => $ivaPorcentaje,
                    'neto_sin_descuento' => 0,
                    'iva_sin_descuento' => 0,
                    'subtotal_sin_descuento' => 0,
                    'neto' => 0,
                    'iva' => 0,
                    'subtotal' => 0,
                    'descuento_aplicado' => 0,
                ];
            }

            // Acumular valores sin descuento
            $porAlicuota[$ivaCodigo]['neto_sin_descuento'] += $netoItem;
            $porAlicuota[$ivaCodigo]['iva_sin_descuento'] += $ivaItem;
            $porAlicuota[$ivaCodigo]['subtotal_sin_descuento'] += $subtotalItem;
        }

        // Calcular totales sin descuento
        $totalNetoSinDesc = array_sum(array_column($porAlicuota, 'neto_sin_descuento'));
        $totalIvaSinDesc = array_sum(array_column($porAlicuota, 'iva_sin_descuento'));

        // Prorratear descuentos si los hay
        // IMPORTANTE: Los descuentos se aplican al precio CON IVA, por lo que
        // hay que prorratear sobre el subtotal (con IVA) y luego convertir a neto
        $totalSubtotalSinDesc = array_sum(array_column($porAlicuota, 'subtotal_sin_descuento'));

        if ($totalDescuentos > 0 && $totalSubtotalSinDesc > 0) {
            foreach ($porAlicuota as $codigo => &$alicuota) {
                // Proporción del subtotal (con IVA) de esta alícuota sobre el total
                $proporcion = $alicuota['subtotal_sin_descuento'] / $totalSubtotalSinDesc;

                // Descuento asignado a esta alícuota (con IVA incluido)
                $descuentoAlicuotaConIva = $totalDescuentos * $proporcion;

                // Convertir el descuento a neto (el descuento "incluye" IVA proporcionalmente)
                if ($alicuota['porcentaje'] > 0) {
                    $descuentoNetoAlicuota = $descuentoAlicuotaConIva / (1 + $alicuota['porcentaje'] / 100);
                } else {
                    $descuentoNetoAlicuota = $descuentoAlicuotaConIva; // Exento o no gravado
                }

                // Nuevo neto después del descuento
                $nuevoNeto = max(0, $alicuota['neto_sin_descuento'] - $descuentoNetoAlicuota);

                // Recalcular IVA sobre el nuevo neto
                $nuevoIva = $nuevoNeto * ($alicuota['porcentaje'] / 100);

                $alicuota['descuento_aplicado'] = round($descuentoAlicuotaConIva, 3);
                $alicuota['neto'] = round($nuevoNeto, 3);
                $alicuota['iva'] = round($nuevoIva, 3);
                $alicuota['subtotal'] = round($nuevoNeto + $nuevoIva, 3);
            }
            unset($alicuota);
        } else {
            // Sin descuentos: neto final = neto sin descuento
            foreach ($porAlicuota as $codigo => &$alicuota) {
                $alicuota['neto'] = round($alicuota['neto_sin_descuento'], 3);
                $alicuota['iva'] = round($alicuota['iva_sin_descuento'], 3);
                $alicuota['subtotal'] = round($alicuota['subtotal_sin_descuento'], 3);
            }
            unset($alicuota);
        }

        // Redondear valores sin descuento
        foreach ($porAlicuota as $codigo => &$alicuota) {
            $alicuota['neto_sin_descuento'] = round($alicuota['neto_sin_descuento'], 3);
            $alicuota['iva_sin_descuento'] = round($alicuota['iva_sin_descuento'], 3);
            $alicuota['subtotal_sin_descuento'] = round($alicuota['subtotal_sin_descuento'], 3);
        }
        unset($alicuota);

        // Ordenar por código de alícuota
        ksort($porAlicuota);

        // Calcular totales finales
        $totalNeto = array_sum(array_column($porAlicuota, 'neto'));
        $totalIva = array_sum(array_column($porAlicuota, 'iva'));
        $totalFinal = array_sum(array_column($porAlicuota, 'subtotal'));

        return [
            'por_alicuota' => array_values($porAlicuota),
            'total_neto' => round($totalNeto, 3),
            'total_iva' => round($totalIva, 3),
            'total' => round($totalFinal, 3),
            'descuento_aplicado' => round($totalDescuentos, 3),
        ];
    }

    /**
     * Crea un pool de unidades individuales para aplicar promociones
     */
    protected function crearPoolUnidades(): array
    {
        $pool = [];
        $idCounter = 0;

        // Items invitados no entran al pool: no cuentan para thresholds de
        // promos especiales NxM/Combo/Menu (RF-11 spec invitaciones).
        foreach ($this->getItemsParaMotorBeneficios() as $itemIndex => $item) {
            $cantidad = max(1, (float) ($item['cantidad'] ?? 1));

            for ($i = 0; $i < $cantidad; $i++) {
                $pool[] = [
                    'id' => 'u_'.($idCounter++),
                    'item_index' => $itemIndex,
                    'articulo_id' => $item['articulo_id'],
                    'categoria_id' => $item['categoria_id'] ?? null,
                    'precio' => (float) ($item['precio'] ?? 0),
                    'consumida' => false,
                    'consumida_por' => null,
                    'excluido_promociones' => false,
                ];
            }
        }

        return $pool;
    }

    /**
     * Obtiene la información de promociones de la lista seleccionada
     */
    protected function obtenerInfoPromocionesLista(): array
    {
        $listaSeleccionada = collect($this->listasPreciosDisponibles)
            ->firstWhere('id', $this->listaPrecioId);

        if (! $listaSeleccionada) {
            return [
                'aplica_promociones' => true,
                'promociones_alcance' => 'todos',
            ];
        }

        return [
            'aplica_promociones' => $listaSeleccionada['aplica_promociones'] ?? true,
            'promociones_alcance' => $listaSeleccionada['promociones_alcance'] ?? 'todos',
        ];
    }

    // =========================================
    // PROMOCIONES ESPECIALES
    // =========================================

    protected function obtenerPromocionesEspeciales(array $contexto): array
    {
        $promociones = PromocionEspecial::where('sucursal_id', $this->sucursalId)
            ->where('activo', true)
            ->where(function ($q) {
                $q->whereNull('vigencia_desde')
                    ->orWhere('vigencia_desde', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('vigencia_hasta')
                    ->orWhere('vigencia_hasta', '>=', now());
            })
            ->with(['grupos.articulos', 'escalas'])
            ->orderBy('prioridad')
            ->get();

        return $promociones->filter(function ($promo) use ($contexto) {
            // Verificar usos disponibles
            if (! $promo->tieneUsosDisponibles()) {
                return false;
            }

            return $this->promocionEspecialCumpleCondiciones($promo, $contexto);
        })->map(function ($promo) {
            return $this->convertirPromocionEspecialAArray($promo);
        })->toArray();
    }

    protected function promocionEspecialCumpleCondiciones($promo, array $contexto): bool
    {
        // Verificar forma de venta (si la promo requiere una específica)
        if ($promo->forma_venta_id) {
            if (empty($contexto['forma_venta_id']) || $promo->forma_venta_id != $contexto['forma_venta_id']) {
                return false;
            }
        }

        // Verificar canal de venta
        if ($promo->canal_venta_id) {
            if (empty($contexto['canal_venta_id']) || $promo->canal_venta_id != $contexto['canal_venta_id']) {
                return false;
            }
        }

        // Verificar formas de pago
        $fpIds = $promo->formas_pago_ids ?? ($promo->forma_pago_id ? [$promo->forma_pago_id] : []);
        if (! empty($fpIds)) {
            if (empty($contexto['forma_pago_id']) || ! in_array($contexto['forma_pago_id'], $fpIds)) {
                return false;
            }
        }

        // Verificar día de la semana
        if (! empty($promo->dias_semana) && ! in_array($contexto['dia_semana'], $promo->dias_semana)) {
            return false;
        }

        // Verificar horario
        if ($promo->hora_desde && $contexto['hora'] < $promo->hora_desde) {
            return false;
        }
        if ($promo->hora_hasta && $contexto['hora'] > $promo->hora_hasta) {
            return false;
        }

        return true;
    }

    protected function convertirPromocionEspecialAArray($promo): array
    {
        return [
            'id' => $promo->id,
            'nombre' => $promo->nombre,
            'tipo' => $promo->tipo,
            'prioridad' => $promo->prioridad,
            'modo_aplicacion' => $promo->modo_aplicacion ?? 'automatica',
            // NxM básico
            'nxm_lleva' => $promo->nxm_lleva,
            'nxm_bonifica' => $promo->nxm_bonifica,
            'nxm_articulos_ids' => $promo->nxm_articulos_ids ?? ($promo->nxm_articulo_id ? [$promo->nxm_articulo_id] : []),
            'nxm_categorias_ids' => $promo->nxm_categorias_ids ?? ($promo->nxm_categoria_id ? [$promo->nxm_categoria_id] : []),
            'beneficio_tipo' => $promo->beneficio_tipo ?? 'gratis',
            'beneficio_porcentaje' => $promo->beneficio_porcentaje ?? 100,
            'usa_escalas' => $promo->usa_escalas,
            'escalas' => $promo->escalas->toArray(),
            // NxM avanzado
            'grupos_trigger' => $promo->gruposTrigger ? $promo->gruposTrigger->map(fn ($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray() : [],
            'grupos_reward' => $promo->gruposReward ? $promo->gruposReward->map(fn ($g) => [
                'nombre' => $g->nombre,
                'articulos_ids' => $g->articulos->pluck('id')->toArray(),
            ])->toArray() : [],
            // Combo/Menu
            'precio_tipo' => $promo->precio_tipo,
            'precio_valor' => $promo->precio_valor,
            'grupos' => $promo->grupos->map(fn ($g) => [
                'nombre' => $g->nombre,
                'cantidad' => $g->cantidad,
                'articulos' => $g->articulos->map(fn ($a) => [
                    'id' => $a->id,
                    'precio' => $a->precio_base,
                ])->toArray(),
            ])->toArray(),
        ];
    }

    protected function intentarAplicarPromocionEspecial(array $promo, array $poolUnidades): array
    {
        // Filtrar solo unidades disponibles
        $unidadesDisponibles = array_filter($poolUnidades, fn ($u) => ! $u['consumida'] && ! ($u['excluido_promociones'] ?? false));

        return match ($promo['tipo']) {
            'nxm' => $this->aplicarNxMBasico($promo, $unidadesDisponibles),
            'nxm_avanzado' => $this->aplicarNxMAvanzado($promo, $unidadesDisponibles),
            'combo' => $this->aplicarCombo($promo, $unidadesDisponibles),
            'menu' => $this->aplicarMenu($promo, $unidadesDisponibles),
            default => ['aplicada' => false, 'razon' => 'Tipo de promoción no soportado'],
        };
    }

    /**
     * Marca las unidades consumidas por una promo especial sobre el pool pasado por referencia.
     */
    protected function consumirUnidadesPromoEspecial(array &$poolUnidades, array $promo, array $aplicacion): void
    {
        $descuentoPorUnidad = $aplicacion['descuento_por_unidad'] ?? [];

        foreach ($aplicacion['unidades_consumidas'] as $unidadIdConsumida) {
            foreach ($poolUnidades as $idx => $unidad) {
                if ($unidad['id'] === $unidadIdConsumida) {
                    $poolUnidades[$idx]['consumida'] = true;
                    $poolUnidades[$idx]['consumida_por'] = $promo['nombre'];
                    // descuento atribuido a esta unidad (no el total de la promo).
                    // Las unidades trigger reciben 0; las bonificadas reciben su descuento real.
                    $poolUnidades[$idx]['promo_especial_info'] = [
                        'id' => $promo['id'],
                        'promocion_especial_id' => $promo['id'],
                        'nombre' => $promo['nombre'],
                        'tipo' => $promo['tipo'],
                        'descuento' => (float) ($descuentoPorUnidad[$unidadIdConsumida] ?? 0),
                    ];
                }
            }
        }
    }

    /**
     * Arma el objeto "promoción aplicada" para el array de resultado.
     */
    protected function armarResultadoPromoEspecial(array $promo, array $aplicacion): array
    {
        return [
            'id' => $promo['id'],
            'promocion_especial_id' => $promo['id'],
            'nombre' => $promo['nombre'],
            'tipo' => $promo['tipo'],
            'descuento' => $aplicacion['descuento'],
            'descripcion' => $aplicacion['descripcion'],
            'unidades_usadas' => count($aplicacion['unidades_consumidas']),
        ];
    }

    /**
     * Encuentra la mejor combinación de promociones especiales AUTOMÁTICAS que maximiza
     * el ahorro total del cliente. Evalúa subsets exhaustivamente si hay ≤10 promos,
     * greedy por descuento estimado si hay más.
     *
     * Dentro de cada subset, las promos se aplican en orden de prioridad.
     *
     * Retorna: ['descuento_total' => float, 'aplicaciones' => [['promo' => ..., 'aplicacion' => ...], ...]]
     */
    protected function encontrarMejorCombinacionEspeciales(array $promociones, array $poolInicial): array
    {
        $mejor = ['descuento_total' => 0.0, 'aplicaciones' => []];

        if (empty($promociones)) {
            return $mejor;
        }

        $n = count($promociones);

        if ($n <= 10) {
            // Exhaustivo: probar todos los subsets no vacíos (2^n - 1)
            $totalSubsets = 1 << $n;
            for ($mask = 1; $mask < $totalSubsets; $mask++) {
                $subset = [];
                for ($j = 0; $j < $n; $j++) {
                    if ($mask & (1 << $j)) {
                        $subset[] = $promociones[$j];
                    }
                }

                $resultado = $this->evaluarSubsetEspeciales($subset, $poolInicial);

                if ($resultado['descuento_total'] > $mejor['descuento_total']) {
                    $mejor = $resultado;
                }
            }
        } else {
            // Greedy: ordenar por descuento estimado DESC y aplicar secuencialmente
            $conDescuento = [];
            foreach ($promociones as $promo) {
                $prueba = $this->intentarAplicarPromocionEspecial($promo, $poolInicial);
                $conDescuento[] = [
                    'promo' => $promo,
                    'descuento' => $prueba['aplicada'] ? (float) $prueba['descuento'] : 0.0,
                ];
            }
            usort($conDescuento, fn ($a, $b) => $b['descuento'] <=> $a['descuento']);
            $ordenadas = array_map(fn ($item) => $item['promo'], $conDescuento);

            $mejor = $this->evaluarSubsetEspeciales($ordenadas, $poolInicial);
        }

        return $mejor;
    }

    /**
     * Aplica un subset de promociones especiales sobre una copia del pool y
     * retorna el descuento total acumulado + las aplicaciones efectivas.
     * Las promos se evalúan en orden de prioridad; si una no puede aplicarse, se salta.
     */
    protected function evaluarSubsetEspeciales(array $subset, array $poolInicial): array
    {
        usort($subset, fn ($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $pool = $poolInicial;
        $descuentoTotal = 0.0;
        $aplicaciones = [];

        foreach ($subset as $promo) {
            $aplicacion = $this->intentarAplicarPromocionEspecial($promo, $pool);
            if ($aplicacion['aplicada']) {
                $this->consumirUnidadesPromoEspecial($pool, $promo, $aplicacion);
                $descuentoTotal += (float) $aplicacion['descuento'];
                $aplicaciones[] = ['promo' => $promo, 'aplicacion' => $aplicacion];
            }
        }

        return [
            'descuento_total' => $descuentoTotal,
            'aplicaciones' => $aplicaciones,
        ];
    }

    protected function aplicarNxMBasico(array $promo, array $unidadesDisponibles): array
    {
        // Filtrar unidades que aplican a esta promoción
        $unidadesAplicables = array_filter($unidadesDisponibles, function ($u) use ($promo) {
            $tieneRestriccion = ! empty($promo['nxm_articulos_ids']) || ! empty($promo['nxm_categorias_ids']);

            if (! $tieneRestriccion) {
                return false;
            }

            if (! empty($promo['nxm_articulos_ids']) && in_array($u['articulo_id'], $promo['nxm_articulos_ids'])) {
                return true;
            }

            if (! empty($promo['nxm_categorias_ids']) && in_array($u['categoria_id'], $promo['nxm_categorias_ids'])) {
                return true;
            }

            return false;
        });

        $cantidadDisponible = count($unidadesAplicables);

        // Determinar lleva/bonifica según escalas o valores fijos
        $lleva = $promo['nxm_lleva'];
        $bonifica = $promo['nxm_bonifica'];
        $beneficioTipo = $promo['beneficio_tipo'] ?? 'gratis';
        $beneficioPorcentaje = $promo['beneficio_porcentaje'] ?? 100;

        if ($promo['usa_escalas'] && ! empty($promo['escalas'])) {
            $escalaAplicable = null;
            foreach ($promo['escalas'] as $escala) {
                $desde = (float) ($escala['cantidad_desde'] ?? 0);
                $hasta = (float) ($escala['cantidad_hasta'] ?? PHP_INT_MAX);
                if ($cantidadDisponible >= $desde && ($hasta === 0 || $cantidadDisponible <= $hasta)) {
                    $escalaAplicable = $escala;
                    break;
                }
            }

            if (! $escalaAplicable) {
                return ['aplicada' => false, 'razon' => 'No hay escala aplicable'];
            }

            $lleva = (int) $escalaAplicable['lleva'];
            $bonifica = (int) $escalaAplicable['bonifica'];
            $beneficioTipo = $escalaAplicable['beneficio_tipo'] ?? 'gratis';
            $beneficioPorcentaje = $escalaAplicable['beneficio_porcentaje'] ?? 100;
        }

        if ($cantidadDisponible < $lleva) {
            return ['aplicada' => false, 'razon' => "Necesita $lleva, hay $cantidadDisponible"];
        }

        // Ordenar por precio descendente para bonificar los más caros
        usort($unidadesAplicables, fn ($a, $b) => $b['precio'] <=> $a['precio']);

        $vecesAplicable = floor($cantidadDisponible / $lleva);
        $totalUnidadesEnPromo = $lleva * $vecesAplicable;
        $totalBonificadas = $bonifica * $vecesAplicable;
        $unidadesConsumidas = [];
        $descuentoPorUnidad = [];
        $descuentoTotal = 0;

        // Bonificar los N items más caros del pool completo
        for ($i = 0; $i < $totalBonificadas && $i < $totalUnidadesEnPromo; $i++) {
            $unidad = $unidadesAplicables[$i];
            $descuentoUnidad = $beneficioTipo === 'gratis'
                ? (float) $unidad['precio']
                : (float) $unidad['precio'] * ($beneficioPorcentaje / 100);
            $descuentoTotal += $descuentoUnidad;
            $descuentoPorUnidad[$unidad['id']] = $descuentoUnidad;
        }

        // Marcar todas las unidades participantes como consumidas (las trigger sin descuento)
        for ($i = 0; $i < $totalUnidadesEnPromo; $i++) {
            $unidadId = $unidadesAplicables[$i]['id'];
            $unidadesConsumidas[] = $unidadId;
            if (! isset($descuentoPorUnidad[$unidadId])) {
                $descuentoPorUnidad[$unidadId] = 0.0;
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No se pudo aplicar'];
        }

        $descripcionBeneficio = $beneficioTipo === 'gratis' ? 'gratis' : "{$beneficioPorcentaje}% dto";

        return [
            'aplicada' => true,
            'descuento' => $descuentoTotal,
            'descripcion' => "Lleva {$lleva} → {$bonifica} {$descripcionBeneficio} (x{$vecesAplicable})",
            'unidades_consumidas' => $unidadesConsumidas,
            'descuento_por_unidad' => $descuentoPorUnidad,
        ];
    }

    protected function aplicarNxMAvanzado(array $promo, array $unidadesDisponibles): array
    {
        $triggerIds = [];
        foreach ($promo['grupos_trigger'] as $grupo) {
            $triggerIds = array_merge($triggerIds, $grupo['articulos_ids'] ?? []);
        }
        $rewardIds = [];
        foreach ($promo['grupos_reward'] as $grupo) {
            $rewardIds = array_merge($rewardIds, $grupo['articulos_ids'] ?? []);
        }

        $unidadesTrigger = array_values(array_filter($unidadesDisponibles, fn ($u) => in_array($u['articulo_id'], $triggerIds)));
        $unidadesReward = array_values(array_filter($unidadesDisponibles, fn ($u) => in_array($u['articulo_id'], $rewardIds)));

        $lleva = $promo['nxm_lleva'];
        $bonifica = $promo['nxm_bonifica'];
        $beneficioTipo = $promo['beneficio_tipo'] ?? 'gratis';
        $beneficioPorcentaje = $promo['beneficio_porcentaje'] ?? 100;

        if ($promo['usa_escalas'] && ! empty($promo['escalas'])) {
            $cantidadTrigger = count($unidadesTrigger);
            $escalaAplicable = null;
            foreach ($promo['escalas'] as $escala) {
                $desde = (float) ($escala['cantidad_desde'] ?? 0);
                $hasta = (float) ($escala['cantidad_hasta'] ?? PHP_INT_MAX);
                if ($cantidadTrigger >= $desde && ($hasta === 0 || $cantidadTrigger <= $hasta)) {
                    $escalaAplicable = $escala;
                    break;
                }
            }

            if ($escalaAplicable) {
                $lleva = (int) $escalaAplicable['lleva'];
                $bonifica = (int) $escalaAplicable['bonifica'];
                $beneficioTipo = $escalaAplicable['beneficio_tipo'] ?? 'gratis';
                $beneficioPorcentaje = $escalaAplicable['beneficio_porcentaje'] ?? 100;
            }
        }

        if (count($unidadesTrigger) < $lleva) {
            return ['aplicada' => false, 'razon' => "Necesita {$lleva} triggers"];
        }

        if (count($unidadesReward) < $bonifica) {
            return ['aplicada' => false, 'razon' => "Necesita {$bonifica} rewards"];
        }

        usort($unidadesReward, fn ($a, $b) => $b['precio'] <=> $a['precio']);

        $vecesAplicable = min(
            floor(count($unidadesTrigger) / $lleva),
            floor(count($unidadesReward) / $bonifica)
        );

        $unidadesConsumidas = [];
        $descuentoPorUnidad = [];
        $descuentoTotal = 0;

        for ($vez = 0; $vez < $vecesAplicable; $vez++) {
            for ($i = 0; $i < $lleva; $i++) {
                $idx = $vez * $lleva + $i;
                if (isset($unidadesTrigger[$idx])) {
                    $unidadId = $unidadesTrigger[$idx]['id'];
                    $unidadesConsumidas[] = $unidadId;
                    $descuentoPorUnidad[$unidadId] = 0.0;
                }
            }

            for ($i = 0; $i < $bonifica; $i++) {
                $idx = $vez * $bonifica + $i;
                if (isset($unidadesReward[$idx])) {
                    $unidad = $unidadesReward[$idx];
                    $unidadesConsumidas[] = $unidad['id'];

                    $descuentoUnidad = $beneficioTipo === 'gratis'
                        ? (float) $unidad['precio']
                        : (float) $unidad['precio'] * ($beneficioPorcentaje / 100);
                    $descuentoTotal += $descuentoUnidad;
                    $descuentoPorUnidad[$unidad['id']] = $descuentoUnidad;
                }
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No se pudo aplicar'];
        }

        $descripcionBeneficio = $beneficioTipo === 'gratis' ? 'gratis' : "{$beneficioPorcentaje}% dto";

        return [
            'aplicada' => true,
            'descuento' => $descuentoTotal,
            'descripcion' => "Lleva {$lleva} → {$bonifica} {$descripcionBeneficio} (x{$vecesAplicable})",
            'unidades_consumidas' => $unidadesConsumidas,
            'descuento_por_unidad' => $descuentoPorUnidad,
        ];
    }

    protected function aplicarCombo(array $promo, array $unidadesDisponibles): array
    {
        if (empty($promo['grupos'])) {
            return ['aplicada' => false, 'razon' => 'Combo sin artículos'];
        }

        $unidadesConsumidas = [];
        $unidadesConsumidasInfo = [];
        $precioNormal = 0;

        foreach ($promo['grupos'] as $grupo) {
            $cantidadRequerida = (float) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = $grupo['articulos'] ?? [];

            if (empty($articulosDelGrupo)) {
                continue;
            }

            // Obtener todos los IDs de artículos válidos para este grupo
            $articulosIdsDelGrupo = array_column($articulosDelGrupo, 'id');

            // Buscar unidades de CUALQUIER artículo del grupo (no solo el primero)
            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn ($u) => in_array($u['articulo_id'], $articulosIdsDelGrupo) && ! in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => 'Faltan artículos para el grupo'];
            }

            // Ordenar por precio ascendente para consumir los más baratos primero
            usort($unidadesDeEsteGrupo, fn ($a, $b) => $a['precio'] <=> $b['precio']);

            for ($i = 0; $i < $cantidadRequerida; $i++) {
                $unidad = $unidadesDeEsteGrupo[$i];
                $unidadesConsumidas[] = $unidad['id'];
                $unidadesConsumidasInfo[] = $unidad;
                $precioNormal += $unidad['precio'];
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No hay artículos'];
        }

        $descuento = 0;
        if ($promo['precio_tipo'] === 'fijo') {
            $precioCombo = (float) $promo['precio_valor'];
            $descuento = max(0, $precioNormal - $precioCombo);
        } else {
            $porcentajeDto = (float) $promo['precio_valor'];
            $descuento = $precioNormal * ($porcentajeDto / 100);
        }

        $descuentoPorUnidad = $this->prorratearDescuentoPorPrecio($unidadesConsumidasInfo, (float) $descuento, (float) $precioNormal);

        return [
            'aplicada' => true,
            'descuento' => $descuento,
            'descripcion' => $promo['precio_tipo'] === 'fijo'
                ? 'Combo a $'.number_format($promo['precio_valor'], 0, ',', '.')
                : "Combo con {$promo['precio_valor']}% dto",
            'unidades_consumidas' => $unidadesConsumidas,
            'descuento_por_unidad' => $descuentoPorUnidad,
        ];
    }

    protected function aplicarMenu(array $promo, array $unidadesDisponibles): array
    {
        if (empty($promo['grupos'])) {
            return ['aplicada' => false, 'razon' => 'Menú sin grupos'];
        }

        $unidadesConsumidas = [];
        $unidadesConsumidasInfo = [];
        $precioNormal = 0;

        foreach ($promo['grupos'] as $grupo) {
            $cantidadRequerida = (float) ($grupo['cantidad'] ?? 1);
            $articulosDelGrupo = array_column($grupo['articulos'] ?? [], 'id');

            if (empty($articulosDelGrupo)) {
                return ['aplicada' => false, 'razon' => "Grupo '{$grupo['nombre']}' sin artículos"];
            }

            $unidadesDeEsteGrupo = array_values(array_filter(
                $unidadesDisponibles,
                fn ($u) => in_array($u['articulo_id'], $articulosDelGrupo) && ! in_array($u['id'], $unidadesConsumidas)
            ));

            if (count($unidadesDeEsteGrupo) < $cantidadRequerida) {
                return ['aplicada' => false, 'razon' => "Faltan artículos para '{$grupo['nombre']}'"];
            }

            usort($unidadesDeEsteGrupo, fn ($a, $b) => $a['precio'] <=> $b['precio']);

            for ($i = 0; $i < $cantidadRequerida; $i++) {
                $unidad = $unidadesDeEsteGrupo[$i];
                $unidadesConsumidas[] = $unidad['id'];
                $unidadesConsumidasInfo[] = $unidad;
                $precioNormal += $unidad['precio'];
            }
        }

        if (empty($unidadesConsumidas)) {
            return ['aplicada' => false, 'razon' => 'No hay artículos'];
        }

        $descuento = 0;
        if ($promo['precio_tipo'] === 'fijo') {
            $precioMenu = (float) $promo['precio_valor'];
            $descuento = max(0, $precioNormal - $precioMenu);
        } else {
            $porcentajeDto = (float) $promo['precio_valor'];
            $descuento = $precioNormal * ($porcentajeDto / 100);
        }

        $descuentoPorUnidad = $this->prorratearDescuentoPorPrecio($unidadesConsumidasInfo, (float) $descuento, (float) $precioNormal);

        return [
            'aplicada' => true,
            'descuento' => $descuento,
            'descripcion' => $promo['precio_tipo'] === 'fijo'
                ? 'Menú a $'.number_format($promo['precio_valor'], 0, ',', '.')
                : "Menú con {$promo['precio_valor']}% dto",
            'unidades_consumidas' => $unidadesConsumidas,
            'descuento_por_unidad' => $descuentoPorUnidad,
        ];
    }

    /**
     * Prorratea un descuento global entre las unidades consumidas en proporción a su precio.
     * La última unidad recibe el residuo para que la suma sea exactamente igual al descuento total.
     *
     * @param  array  $unidades  Array de unidades con keys 'id' y 'precio'
     * @return array<string, float> Mapa unidad_id => descuento atribuido
     */
    protected function prorratearDescuentoPorPrecio(array $unidades, float $descuentoTotal, float $precioNormal): array
    {
        $resultado = [];
        if (empty($unidades) || $descuentoTotal <= 0 || $precioNormal <= 0) {
            foreach ($unidades as $u) {
                $resultado[$u['id']] = 0.0;
            }

            return $resultado;
        }

        $acumulado = 0.0;
        $cantidad = count($unidades);
        foreach ($unidades as $i => $u) {
            if ($i === $cantidad - 1) {
                $resultado[$u['id']] = round($descuentoTotal - $acumulado, 2);
            } else {
                $atribuido = round($descuentoTotal * ((float) $u['precio'] / $precioNormal), 2);
                $resultado[$u['id']] = $atribuido;
                $acumulado += $atribuido;
            }
        }

        return $resultado;
    }

    // =========================================
    // PROMOCIONES COMUNES
    // =========================================

    protected function obtenerPromocionesComunes(array $contexto): array
    {
        $promociones = Promocion::where('sucursal_id', $this->sucursalId)
            ->activas()
            ->vigentes()
            ->conUsosDisponibles()
            ->automaticas() // Solo promociones que no requieren cupón (cupón se maneja aparte)
            ->with(['condiciones', 'escalas'])
            ->ordenadoPorPrioridad()
            ->get();

        // Filtrar por día de la semana y horario
        return $promociones->filter(function ($promo) use ($contexto) {
            // Verificar día de la semana
            if (! $promo->aplicaEnDiaSemana($contexto['dia_semana'])) {
                return false;
            }
            // Verificar horario
            if (! $promo->aplicaEnHorario($contexto['hora'])) {
                return false;
            }

            return true;
        })->map(function ($promo) {
            return $this->convertirPromocionComunAArray($promo);
        })->toArray();
    }

    protected function convertirPromocionComunAArray($promo): array
    {
        $condiciones = $promo->condiciones;
        $articulosIds = $condiciones->where('tipo_condicion', 'por_articulo')
            ->pluck('articulo_id')->filter()->values()->toArray();
        $categoriasIds = $condiciones->where('tipo_condicion', 'por_categoria')
            ->pluck('categoria_id')->filter()->values()->toArray();
        $condicionMontoMinimo = $condiciones->firstWhere('tipo_condicion', 'por_total_compra');
        $condicionCantidadMinima = $condiciones->firstWhere('tipo_condicion', 'por_cantidad');
        $formasPagoIds = $condiciones->where('tipo_condicion', 'por_forma_pago')
            ->pluck('forma_pago_id')->filter()->values()->toArray();
        $condicionFormaVenta = $condiciones->firstWhere('tipo_condicion', 'por_forma_venta');
        $condicionCanalVenta = $condiciones->firstWhere('tipo_condicion', 'por_canal');

        return [
            'id' => $promo->id,
            'nombre' => $promo->nombre,
            'tipo' => $promo->tipo,
            'valor' => $promo->valor,
            'prioridad' => $promo->prioridad,
            'combinable' => $promo->combinable,
            'escalas' => $promo->escalas->toArray(),
            'articulos_ids' => $articulosIds,
            'categorias_ids' => $categoriasIds,
            'monto_minimo' => $condicionMontoMinimo?->monto_minimo,
            'cantidad_minima' => $condicionCantidadMinima?->cantidad_minima,
            'formas_pago_ids' => $formasPagoIds,
            'forma_venta_id' => $condicionFormaVenta?->forma_venta_id,
            'canal_venta_id' => $condicionCanalVenta?->canal_venta_id,
            'dias_semana' => $promo->dias_semana,
            'hora_desde' => $promo->hora_desde,
            'hora_hasta' => $promo->hora_hasta,
        ];
    }

    /**
     * Verifica si una promoción cumple las condiciones del contexto de venta
     */
    protected function promocionCumpleCondiciones(array $promo, array $contexto): bool
    {
        // Verificar monto mínimo
        if (! empty($promo['monto_minimo'])) {
            if (($contexto['subtotal'] ?? 0) < (float) $promo['monto_minimo']) {
                return false;
            }
        }

        // Verificar cantidad mínima
        if (! empty($promo['cantidad_minima'])) {
            if (($contexto['cantidad_total'] ?? 0) < (float) $promo['cantidad_minima']) {
                return false;
            }
        }

        // Verificar forma de pago: si la promoción requiere formas de pago específicas
        if (! empty($promo['formas_pago_ids'])) {
            if (! empty($contexto['forma_pago_id'])) {
                if (! in_array($contexto['forma_pago_id'], $promo['formas_pago_ids'])) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar forma de venta
        if (! empty($promo['forma_venta_id'])) {
            if (! empty($contexto['forma_venta_id'])) {
                if ($promo['forma_venta_id'] != $contexto['forma_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar canal de venta
        if (! empty($promo['canal_venta_id'])) {
            if (! empty($contexto['canal_venta_id'])) {
                if ($promo['canal_venta_id'] != $contexto['canal_venta_id']) {
                    return false;
                }
            } else {
                return false;
            }
        }

        // Verificar día de la semana
        if (! empty($promo['dias_semana']) && ! in_array($contexto['dia_semana'], $promo['dias_semana'])) {
            return false;
        }

        // Verificar horario
        if (! empty($promo['hora_desde']) && $contexto['hora'] < $promo['hora_desde']) {
            return false;
        }
        if (! empty($promo['hora_hasta']) && $contexto['hora'] > $promo['hora_hasta']) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si una promoción aplica a un item específico
     */
    protected function promocionAplicaAItem(array $promo, ?int $articuloId, ?int $categoriaId): bool
    {
        $tieneRestriccion = ! empty($promo['articulos_ids']) || ! empty($promo['categorias_ids']);

        if (! $tieneRestriccion) {
            return true;
        }

        // Aplica si el artículo está en la lista O pertenece a una categoría seleccionada
        if (! empty($promo['articulos_ids']) && in_array($articuloId, $promo['articulos_ids'])) {
            return true;
        }

        if (! empty($promo['categorias_ids']) && in_array($categoriaId, $promo['categorias_ids'])) {
            return true;
        }

        return false;
    }

    /**
     * Aplica promociones comunes a los items (soporta múltiples promociones combinables)
     */
    protected function aplicarPromocionesComunes(array $promociones, array &$items, array $contexto): array
    {
        $promocionesAplicadas = [];
        $cantidadTotal = array_sum(array_column($items, 'cantidad'));
        $subtotal = array_sum(array_map(fn ($i) => $i['precio'] * $i['cantidad'], $items));

        $contextoCompleto = array_merge($contexto, [
            'subtotal' => $subtotal,
            'cantidad_total' => $cantidadTotal,
        ]);

        // Filtrar promociones que cumplen condiciones generales
        $promocionesValidas = array_filter($promociones, fn ($p) => $this->promocionCumpleCondiciones($p, $contextoCompleto));

        // Procesar cada item
        foreach ($items as $itemIndex => &$item) {
            $articuloId = $item['articulo_id'];
            $categoriaId = $item['categoria_id'] ?? null;
            $cantidad = (float) $item['cantidad'];
            $precioUnitario = (float) $item['precio'];
            $subtotalItem = $precioUnitario * $cantidad;

            // Saltar items excluidos de promociones
            if (! empty($item['excluido_promociones'])) {
                continue;
            }

            // Filtrar promociones que aplican a este item
            $promocionesParaItem = array_filter($promocionesValidas, fn ($p) => $this->promocionAplicaAItem($p, $articuloId, $categoriaId));

            if (empty($promocionesParaItem)) {
                continue;
            }

            // Encontrar la mejor combinación de promociones para este item
            $mejorCombinacion = $this->encontrarMejorCombinacion(
                array_values($promocionesParaItem),
                $subtotalItem,
                $cantidad
            );

            if (! empty($mejorCombinacion['promociones'])) {
                $item['promociones_comunes'] = [];
                $item['total_descuento_comun'] = 0;

                foreach ($mejorCombinacion['promociones'] as $promoAplicada) {
                    // Guardar objeto completo (no solo el nombre) para trazabilidad
                    $item['promociones_comunes'][] = $promoAplicada;
                    $item['total_descuento_comun'] += $promoAplicada['descuento'];

                    // Agregar al resumen global si no existe
                    $yaExiste = false;
                    foreach ($promocionesAplicadas as &$pa) {
                        if ($pa['id'] === $promoAplicada['id']) {
                            $pa['descuento'] += $promoAplicada['descuento'];
                            $pa['items_afectados'][] = $itemIndex;
                            $yaExiste = true;
                            break;
                        }
                    }
                    if (! $yaExiste) {
                        $promocionesAplicadas[] = [
                            'id' => $promoAplicada['id'],
                            'nombre' => $promoAplicada['nombre'],
                            'tipo' => $promoAplicada['tipo'],
                            'descuento' => $promoAplicada['descuento'],
                            'descripcion' => $promoAplicada['descripcion'],
                            'items_afectados' => [$itemIndex],
                        ];
                    }
                }
            }
        }

        return $promocionesAplicadas;
    }

    /**
     * Encuentra la mejor combinación de promociones para un item
     */
    protected function encontrarMejorCombinacion(array $promociones, float $montoInicial, int $cantidad): array
    {
        if (empty($promociones)) {
            return ['monto_final' => $montoInicial, 'promociones' => []];
        }

        // Separar excluyentes de combinables
        $excluyentes = array_filter($promociones, fn ($p) => ! $p['combinable']);
        $combinables = array_values(array_filter($promociones, fn ($p) => $p['combinable']));

        $mejorResultado = ['monto_final' => $montoInicial, 'promociones' => []];

        // 1. Evaluar cada excluyente por separado — O(n)
        foreach ($excluyentes as $promo) {
            $resultado = $this->calcularCombinacion([$promo], $montoInicial, $cantidad);
            if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                $mejorResultado = $resultado;
            }
        }

        // 2. Evaluar combinables
        if (! empty($combinables)) {
            $n = count($combinables);

            if ($n <= 15) {
                // Exhaustiva para sets pequeños — O(2^n)
                $totalCombinaciones = pow(2, $n);
                for ($i = 1; $i < $totalCombinaciones; $i++) {
                    $combinacion = [];
                    for ($j = 0; $j < $n; $j++) {
                        if ($i & (1 << $j)) {
                            $combinacion[] = $combinables[$j];
                        }
                    }
                    $resultado = $this->calcularCombinacion($combinacion, $montoInicial, $cantidad);
                    if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                        $mejorResultado = $resultado;
                    }
                }
            } else {
                // Greedy para sets grandes — O(n log n)
                $resultado = $this->calcularCombinacionGreedy($combinables, $montoInicial, $cantidad);
                if ($resultado['monto_final'] < $mejorResultado['monto_final']) {
                    $mejorResultado = $resultado;
                }
            }
        }

        return $mejorResultado;
    }

    /**
     * Fallback greedy para sets grandes de promociones combinables.
     */
    protected function calcularCombinacionGreedy(array $combinables, float $montoInicial, int $cantidad): array
    {
        $conDescuento = [];
        foreach ($combinables as $promo) {
            $ajuste = $this->calcularAjustePromocion($promo, $montoInicial, $cantidad);
            $conDescuento[] = ['promo' => $promo, 'descuento_estimado' => $ajuste['valor']];
        }

        usort($conDescuento, fn ($a, $b) => $b['descuento_estimado'] <=> $a['descuento_estimado']);
        $ordenadas = array_map(fn ($item) => $item['promo'], $conDescuento);

        return $this->calcularCombinacion($ordenadas, $montoInicial, $cantidad);
    }

    /**
     * Calcula el resultado de aplicar una combinación de promociones
     */
    protected function calcularCombinacion(array $combinacion, float $montoInicial, int $cantidad): array
    {
        // Ordenar por prioridad
        usort($combinacion, fn ($a, $b) => $a['prioridad'] <=> $b['prioridad']);

        $montoActual = $montoInicial;
        $promocionesAplicadas = [];

        foreach ($combinacion as $promo) {
            $ajuste = $this->calcularAjustePromocion($promo, $montoActual, $cantidad);

            if ($ajuste['valor'] > 0) {
                $esRecargo = in_array($promo['tipo'], ['recargo_porcentaje', 'recargo_monto']);
                $montoActual = $esRecargo
                    ? $montoActual + $ajuste['valor']
                    : $montoActual - $ajuste['valor'];
                $promocionesAplicadas[] = [
                    'id' => $promo['id'],
                    'promocion_id' => $promo['id'], // ID explícito para guardar en BD
                    'nombre' => $promo['nombre'],
                    'tipo' => $promo['tipo'],
                    'tipo_beneficio' => $this->mapearTipoBeneficio($promo['tipo']),
                    'valor' => $promo['valor'] ?? 0, // Valor original (%, monto, etc.)
                    'descuento' => $ajuste['valor'],
                    'descuento_item' => $ajuste['valor'], // Alias para guardarPromocionesDetalle
                    'descripcion' => $ajuste['descripcion'],
                ];
            }
        }

        return [
            'monto_final' => max(0, $montoActual),
            'promociones' => $promocionesAplicadas,
        ];
    }

    /**
     * Calcula el ajuste de una promoción sobre un monto
     */
    protected function calcularAjustePromocion(array $promo, float $monto, int $cantidad): array
    {
        $valor = 0;
        $descripcion = '';

        switch ($promo['tipo']) {
            case 'descuento_porcentaje':
                $porcentaje = (float) $promo['valor'];
                $valor = round($monto * ($porcentaje / 100), 2);
                $descripcion = "{$porcentaje}% dto";
                break;

            case 'descuento_monto':
                $valor = min((float) $promo['valor'], $monto);
                $descripcion = '$'.number_format($promo['valor'], 0, ',', '.').' dto';
                break;

            case 'precio_fijo':
                $precioFijoTotal = (float) $promo['valor'] * $cantidad;
                $valor = max(0, $monto - $precioFijoTotal);
                $descripcion = 'Precio fijo $'.number_format($promo['valor'], 0, ',', '.');
                break;

            case 'descuento_escalonado':
                if (! empty($promo['escalas'])) {
                    $escalas = collect($promo['escalas'])
                        ->filter(fn ($e) => ! empty($e['cantidad_desde']) && ! empty($e['valor']))
                        ->sortByDesc('cantidad_desde');

                    foreach ($escalas as $escala) {
                        if ($cantidad >= $escala['cantidad_desde']) {
                            $tipoDesc = $escala['tipo_descuento'] ?? 'porcentaje';
                            if ($tipoDesc === 'porcentaje') {
                                $porcentaje = (float) $escala['valor'];
                                $valor = round($monto * ($porcentaje / 100), 2);
                                $descripcion = "{$porcentaje}% dto escalonado";
                            } elseif ($tipoDesc === 'precio_fijo') {
                                $precioFijoTotal = (float) $escala['valor'] * $cantidad;
                                $valor = max(0, $monto - $precioFijoTotal);
                                $descripcion = 'Precio fijo escalonado $'.number_format($escala['valor'], 0, ',', '.');
                            } else {
                                $valor = min((float) $escala['valor'], $monto);
                                $descripcion = 'Monto fijo escalonado';
                            }
                            break;
                        }
                    }
                }
                break;
        }

        return ['valor' => $valor, 'descripcion' => $descripcion];
    }

    /**
     * Mapea el tipo de promoción al tipo_beneficio para la BD
     */
    protected function mapearTipoBeneficio(string $tipo): string
    {
        return match ($tipo) {
            'descuento_porcentaje' => 'porcentaje',
            'descuento_monto' => 'monto_fijo',
            'precio_fijo' => 'precio_especial',
            'descuento_escalonado' => 'porcentaje', // Generalmente es %
            default => 'porcentaje',
        };
    }

    // =========================================
    // FILTRO PARA MOTOR DE BENEFICIOS (INVITACIONES)
    // =========================================

    /**
     * Items elegibles para el motor de beneficios comerciales (promociones
     * comunes y especiales, cupones, descuento general). Excluye los items
     * con `es_invitacion=true`: una cortesia no cuenta para thresholds NxM,
     * monto minimo de cupon, base del descuento general, ni recibe descuentos
     * propios. Ver RF-11 del spec `.claude/specs/invitaciones-pedidos-ventas.md`.
     *
     * Devuelve un array asociativo `[indice_original => item]` para que las
     * iteraciones existentes `foreach (... as $index => $item)` mantengan el
     * indice del carrito real (ej: aplicar precios al item en `$this->items`).
     */
    protected function getItemsParaMotorBeneficios(): array
    {
        $resultado = [];
        foreach ($this->items as $index => $item) {
            if (empty($item['es_invitacion'])) {
                $resultado[$index] = $item;
            }
        }

        return $resultado;
    }
}

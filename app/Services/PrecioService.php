<?php

namespace App\Services;

use App\Models\Articulo;
use App\Models\ListaPrecio;
use App\Models\ListaPrecioArticulo;
use App\Models\Promocion;
use App\Models\PromocionCondicion;
use App\Models\PromocionEscala;
use App\Models\FormaPago;
use App\Models\FormaPagoCuota;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Precios con Listas de Precios
 *
 * Centraliza toda la lógica de cálculo de precios con el sistema de listas de precios.
 * Maneja:
 * - Búsqueda de lista de precios aplicable según contexto
 * - Cálculo de precio según lista (ajuste porcentual o precio fijo)
 * - Aplicación de promociones con sistema de prioridades
 * - Validaciones temporales (fecha, día de semana, horario)
 * - Descuentos escalonados por cantidad
 * - Promociones combinables vs excluyentes
 * - Recargos/descuentos por forma de pago
 * - Cálculos con cuotas
 * - Redondeo configurable
 *
 * JERARQUÍA DE BÚSQUEDA DE LISTA DE PRECIOS:
 * 1. Lista seleccionada manualmente
 * 2. Lista asignada al cliente
 * 3. Lista que cumpla condiciones (ordenada por prioridad)
 * 4. Lista base de la sucursal (fallback)
 *
 * JERARQUÍA DE PRECIO DENTRO DE UNA LISTA:
 * 1. Precio fijo para artículo específico
 * 2. Ajuste porcentaje para artículo específico
 * 3. Ajuste porcentaje para categoría del artículo
 * 4. Ajuste porcentaje del encabezado de la lista
 *
 * FASE 2 - Sistema de Listas de Precios
 */
class PrecioService
{
    /**
     * Obtiene la lista de precios aplicable para un contexto de venta
     *
     * @param int $sucursalId ID de la sucursal
     * @param array $contexto Contexto de la venta
     * @param int|null $listaPrecioIdManual ID de lista seleccionada manualmente
     * @param int|null $clienteId ID del cliente
     * @return ListaPrecio|null
     */
    public function obtenerListaAplicable(
        int $sucursalId,
        array $contexto = [],
        ?int $listaPrecioIdManual = null,
        ?int $clienteId = null
    ): ?ListaPrecio {
        return ListaPrecio::buscarListaAplicable(
            $sucursalId,
            $contexto,
            $listaPrecioIdManual,
            $clienteId
        );
    }

    /**
     * Obtiene el precio de un artículo según una lista de precios
     *
     * @param Articulo $articulo
     * @param ListaPrecio $listaPrecio
     * @return array ['precio' => float, 'ajuste_porcentaje' => float, 'origen' => string, 'precio_base' => float]
     */
    public function obtenerPrecioConLista(Articulo $articulo, ListaPrecio $listaPrecio): array
    {
        return $listaPrecio->obtenerPrecioArticulo($articulo);
    }

    /**
     * Obtiene el precio base de un artículo para una sucursal y contexto
     *
     * @param int $articuloId ID del artículo
     * @param int $sucursalId ID de la sucursal
     * @param array $contexto Contexto de la venta
     * @param int|null $listaPrecioIdManual ID de lista seleccionada manualmente
     * @param int|null $clienteId ID del cliente
     * @return array|null ['precio' => float, 'lista_precio' => ListaPrecio|null, 'ajuste_porcentaje' => float, 'origen' => string]
     */
    public function obtenerPrecioBase(
        int $articuloId,
        int $sucursalId,
        array $contexto = [],
        ?int $listaPrecioIdManual = null,
        ?int $clienteId = null
    ): ?array {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) {
            Log::warning("Artículo {$articuloId} no encontrado");
            return null;
        }

        // Buscar lista aplicable
        $listaPrecio = $this->obtenerListaAplicable(
            $sucursalId,
            $contexto,
            $listaPrecioIdManual,
            $clienteId
        );

        if ($listaPrecio) {
            $resultado = $listaPrecio->obtenerPrecioArticulo($articulo);
            $resultado['lista_precio'] = $listaPrecio;
            $resultado['lista_precio_id'] = $listaPrecio->id;
            $resultado['lista_precio_nombre'] = $listaPrecio->nombre;
            return $resultado;
        }

        // Sin lista, usar precio base del artículo
        return [
            'precio' => (float) $articulo->precio_base,
            'precio_sin_redondeo' => (float) $articulo->precio_base,
            'ajuste_porcentaje' => 0,
            'origen' => 'articulo_sin_lista',
            'precio_base' => (float) $articulo->precio_base,
            'lista_precio' => null,
            'lista_precio_id' => null,
            'lista_precio_nombre' => null,
        ];
    }

    /**
     * Calcula el precio final de un artículo aplicando todas las reglas
     *
     * Este es el método principal que orquesta todo el cálculo:
     * 1. Obtiene precio según lista de precios aplicable
     * 2. Busca y aplica promociones (si la lista lo permite)
     * 3. Valida límites de descuento
     * 4. Calcula IVA
     * 5. Aplica ajustes por forma de pago
     *
     * @param int $articuloId ID del artículo
     * @param int $sucursalId ID de la sucursal
     * @param float $cantidad Cantidad de unidades
     * @param array $contexto Contexto de la venta con claves:
     *   - forma_venta_id: int|null
     *   - canal_venta_id: int|null
     *   - forma_pago_id: int|null
     *   - cuotas: int|null
     *   - fecha: Carbon|null
     *   - hora: string|null (HH:MM:SS)
     *   - dia_semana: int|null (0=Domingo, 6=Sábado)
     *   - total_compra: float|null (para validar monto mínimo)
     *   - codigo_cupon: string|null
     *   - lista_precio_id: int|null (lista seleccionada manualmente)
     *   - cliente_id: int|null
     * @return array Desglose completo del cálculo
     */
    public function calcularPrecioFinal(
        int $articuloId,
        int $sucursalId,
        float $cantidad,
        array $contexto = []
    ): array {
        // Obtener artículo
        $articulo = Articulo::find($articuloId);
        if (!$articulo) {
            throw new \Exception("Artículo {$articuloId} no encontrado");
        }

        // Establecer valores por defecto del contexto
        $contexto = array_merge([
            'forma_venta_id' => null,
            'canal_venta_id' => null,
            'forma_pago_id' => null,
            'cuotas' => null,
            'fecha' => now(),
            'hora' => now()->format('H:i:s'),
            'dia_semana' => (int) now()->dayOfWeek,
            'total_compra' => null,
            'codigo_cupon' => null,
            'lista_precio_id' => null,
            'cliente_id' => null,
            'cantidad' => $cantidad,
        ], $contexto);

        // 1. Obtener precio según lista de precios aplicable
        $precioInfo = $this->obtenerPrecioBase(
            $articuloId,
            $sucursalId,
            $contexto,
            $contexto['lista_precio_id'],
            $contexto['cliente_id']
        );

        if (!$precioInfo) {
            throw new \Exception("No se encontró precio para el artículo {$articulo->nombre}");
        }

        $precioUnitario = $precioInfo['precio'];
        $subtotal = $precioUnitario * $cantidad;
        $listaPrecio = $precioInfo['lista_precio'];

        // 2. Determinar si aplican promociones según la lista
        $aplicarPromociones = true;
        $promocionesAlcance = 'todos';

        if ($listaPrecio) {
            $aplicarPromociones = $listaPrecio->aplica_promociones;
            $promocionesAlcance = $listaPrecio->promociones_alcance;
        }

        // 3. Obtener y aplicar promociones (si corresponde)
        $promocionesAplicadas = [];
        $descuentoTotal = 0;

        if ($aplicarPromociones) {
            // Si el alcance es 'excluir_lista', verificar si el artículo tiene precio especial en la lista
            $articuloExcluidoDePromociones = false;
            if ($promocionesAlcance === 'excluir_lista' && $listaPrecio) {
                // Verificar si el artículo tiene un precio específico en esta lista
                $tienePrecioEspecial = $listaPrecio->articulos()
                    ->where('articulo_id', $articuloId)
                    ->exists();
                $articuloExcluidoDePromociones = $tienePrecioEspecial;
            }

            if (!$articuloExcluidoDePromociones) {
                $contexto['articulo_id'] = $articuloId;
                $contexto['categoria_id'] = $articulo->categoria_id;
                $contexto['total'] = $subtotal;

                $promocionesAplicadas = $this->aplicarPromociones(
                    $sucursalId,
                    $contexto,
                    $subtotal
                );

                $descuentoTotal = array_sum(array_column($promocionesAplicadas, 'monto_descuento'));
            }
        }

        // 4. Validar límite máximo de descuento (70% para descuentos finales)
        $descuentoPorcentaje = ($subtotal > 0) ? ($descuentoTotal / $subtotal) * 100 : 0;
        if ($descuentoPorcentaje > 70) {
            Log::warning("Descuento de {$descuentoPorcentaje}% excede el límite de 70%");
            $descuentoTotal = $subtotal * 0.70;
            $descuentoPorcentaje = 70;
        }

        $subtotalConDescuento = $subtotal - $descuentoTotal;

        // 5. Calcular IVA
        $tipoIva = $articulo->tipoIva;
        $ivaPorcentaje = $tipoIva ? $tipoIva->porcentaje : 0;

        if ($articulo->precio_iva_incluido) {
            // Precio incluye IVA, hay que extraerlo
            $precioSinIva = $subtotalConDescuento / (1 + ($ivaPorcentaje / 100));
            $ivaMonto = $subtotalConDescuento - $precioSinIva;
            $precioConIva = $subtotalConDescuento;
        } else {
            // Precio sin IVA, hay que agregarlo
            $precioSinIva = $subtotalConDescuento;
            $ivaMonto = $precioSinIva * ($ivaPorcentaje / 100);
            $precioConIva = $precioSinIva + $ivaMonto;
        }

        // 6. Aplicar recargos/descuentos por forma de pago (si aplica)
        $ajusteFormaPago = $this->calcularAjusteFormaPago(
            $contexto['forma_pago_id'],
            $precioConIva,
            $contexto['cuotas']
        );

        $precioFinal = $precioConIva + $ajusteFormaPago['monto'];

        // Resultado completo
        return [
            // Información del artículo
            'articulo_id' => $articuloId,
            'articulo_nombre' => $articulo->nombre,
            'cantidad' => $cantidad,

            // Precio base y lista
            'precio_base_articulo' => (float) $articulo->precio_base,
            'precio_unitario_lista' => round($precioUnitario, 2),
            'lista_precio_id' => $precioInfo['lista_precio_id'],
            'lista_precio_nombre' => $precioInfo['lista_precio_nombre'],
            'ajuste_porcentaje_lista' => $precioInfo['ajuste_porcentaje'],
            'origen_precio' => $precioInfo['origen'],

            // Subtotales
            'subtotal_sin_descuento' => round($subtotal, 2),

            // Promociones
            'aplica_promociones' => $aplicarPromociones,
            'promociones_alcance' => $promocionesAlcance,
            'promociones_aplicadas' => $promocionesAplicadas,
            'descuento_total' => round($descuentoTotal, 2),
            'descuento_porcentaje' => round($descuentoPorcentaje, 2),
            'subtotal_con_descuento' => round($subtotalConDescuento, 2),

            // IVA
            'iva_porcentaje' => $ivaPorcentaje,
            'iva_incluido' => $articulo->precio_iva_incluido,
            'precio_sin_iva' => round($precioSinIva, 2),
            'iva_monto' => round($ivaMonto, 2),
            'precio_con_iva' => round($precioConIva, 2),

            // Forma de pago
            'ajuste_forma_pago' => $ajusteFormaPago,

            // Total final
            'precio_final' => round($precioFinal, 2),
            'precio_final_unitario' => round($precioFinal / $cantidad, 2),
        ];
    }

    /**
     * Busca y aplica todas las promociones aplicables a un contexto
     *
     * Implementa el sistema de prioridades y combinabilidad:
     * 1. Obtiene todas las promociones vigentes y activas
     * 2. Filtra por condiciones que se cumplen
     * 3. Ordena por prioridad (menor número = mayor prioridad)
     * 4. Aplica promociones según combinabilidad
     *
     * @param int $sucursalId ID de la sucursal
     * @param array $contexto Contexto completo de la venta
     * @param float $subtotal Subtotal sobre el cual aplicar descuentos
     * @return array Lista de promociones aplicadas
     */
    public function aplicarPromociones(
        int $sucursalId,
        array $contexto,
        float $subtotal
    ): array {
        // 1. Obtener promociones candidatas
        $promociones = $this->obtenerPromocionesAplicables($sucursalId, $contexto);

        if ($promociones->isEmpty()) {
            return [];
        }

        // 2. Ordenar por prioridad (menor número = mayor prioridad)
        $promociones = $promociones->sortBy([
            ['prioridad', 'asc'],
            ['id', 'asc']
        ]);

        // 3. Aplicar promociones según combinabilidad
        $promocionesAplicadas = [];
        $montoAcumulado = $subtotal;
        $yaAplicoExcluyente = false;

        foreach ($promociones as $promocion) {
            if ($yaAplicoExcluyente) {
                break;
            }

            $ajuste = $this->calcularAjustePromocion(
                $promocion,
                $montoAcumulado,
                $contexto['cantidad'] ?? 1
            );

            if ($ajuste['valor'] > 0) {
                $promocionesAplicadas[] = [
                    'promocion_id' => $promocion->id,
                    'nombre' => $promocion->nombre,
                    'tipo' => $ajuste['tipo'],
                    'porcentaje' => $ajuste['porcentaje'],
                    'monto_descuento' => $ajuste['valor'],
                    'prioridad' => $promocion->prioridad,
                    'combinable' => $promocion->combinable,
                ];

                if ($ajuste['tipo'] === 'descuento') {
                    $montoAcumulado -= $ajuste['valor'];
                }

                if (!$promocion->combinable) {
                    $yaAplicoExcluyente = true;
                }

                $promocion->incrementarUso();
            }
        }

        return $promocionesAplicadas;
    }

    /**
     * Obtiene todas las promociones que podrían aplicar a un contexto
     *
     * @param int $sucursalId
     * @param array $contexto
     * @return Collection
     */
    private function obtenerPromocionesAplicables(int $sucursalId, array $contexto): Collection
    {
        $fecha = $contexto['fecha'] ?? now();
        $diaSemana = $contexto['dia_semana'] ?? (int) now()->dayOfWeek;
        $hora = $contexto['hora'] ?? now()->format('H:i:s');

        $query = Promocion::where('sucursal_id', $sucursalId)
                          ->where('activo', true)
                          ->vigentes($fecha)
                          ->conUsosDisponibles();

        if (!empty($contexto['codigo_cupon'])) {
            $query->where('codigo_cupon', $contexto['codigo_cupon']);
        } else {
            $query->automaticas();
        }

        $promociones = $query->with('condiciones', 'escalas')->get();

        return $promociones->filter(function ($promocion) use ($diaSemana, $hora, $contexto) {
            if (!$promocion->aplicaEnDiaSemana($diaSemana)) {
                return false;
            }

            if (!$promocion->aplicaEnHorario($hora)) {
                return false;
            }

            return $this->validarCondicionesPromocion($promocion, $contexto);
        });
    }

    /**
     * Valida que se cumplan todas las condiciones de una promoción
     *
     * @param Promocion $promocion
     * @param array $contexto
     * @return bool
     */
    private function validarCondicionesPromocion(Promocion $promocion, array $contexto): bool
    {
        if ($promocion->condiciones->isEmpty()) {
            return true;
        }

        foreach ($promocion->condiciones as $condicion) {
            if (!$condicion->seCumple($contexto)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calcula el ajuste de una promoción
     *
     * @param Promocion $promocion
     * @param float $monto
     * @param int $cantidad
     * @return array
     */
    private function calcularAjustePromocion(Promocion $promocion, float $monto, int $cantidad): array
    {
        return $promocion->calcularAjuste($monto, $cantidad);
    }

    /**
     * Calcula ajustes por forma de pago
     *
     * @param int|null $formaPagoId
     * @param float $total
     * @param int|null $cuotas
     * @return array
     */
    private function calcularAjusteFormaPago(?int $formaPagoId, float $total, ?int $cuotas = null): array
    {
        if (!$formaPagoId) {
            return [
                'tipo' => 'ninguno',
                'descripcion' => 'Sin ajuste',
                'monto' => 0,
                'detalle' => [],
            ];
        }

        $formaPago = FormaPago::find($formaPagoId);
        if (!$formaPago) {
            return [
                'tipo' => 'ninguno',
                'descripcion' => 'Forma de pago no encontrada',
                'monto' => 0,
                'detalle' => [],
            ];
        }

        $resultado = [
            'tipo' => 'ninguno',
            'descripcion' => $formaPago->nombre,
            'monto' => 0,
            'detalle' => [],
        ];

        if ($cuotas && $cuotas > 1 && $formaPago->permite_cuotas) {
            $planCuotas = FormaPagoCuota::where('forma_pago_id', $formaPago->id)
                                        ->where('cantidad_cuotas', $cuotas)
                                        ->where('activo', true)
                                        ->first();

            if ($planCuotas) {
                $recargoMonto = $planCuotas->calcularRecargo($total);
                $valorCuota = $planCuotas->calcularValorCuota($total);

                $resultado['tipo'] = 'recargo_cuotas';
                $resultado['monto'] = $recargoMonto;
                $resultado['detalle'] = [
                    'cantidad_cuotas' => $cuotas,
                    'recargo_porcentaje' => $planCuotas->recargo_porcentaje,
                    'recargo_monto' => $recargoMonto,
                    'total_con_recargo' => $total + $recargoMonto,
                    'valor_cuota' => $valorCuota,
                    'descripcion' => $planCuotas->obtenerDescripcion($valorCuota),
                ];
            }
        }

        return $resultado;
    }

    /**
     * Calcula precio para múltiples artículos (carrito completo)
     *
     * @param array $items Array de items con estructura:
     *   [
     *     ['articulo_id' => int, 'cantidad' => float],
     *     ...
     *   ]
     * @param int $sucursalId
     * @param array $contexto Contexto de la venta
     * @return array Desglose completo del carrito
     */
    public function calcularCarrito(array $items, int $sucursalId, array $contexto = []): array
    {
        $itemsCalculados = [];
        $subtotalGeneral = 0;
        $descuentoTotalGeneral = 0;
        $ivaTotalGeneral = 0;

        // Calcular primero todos los items
        foreach ($items as $item) {
            $calculo = $this->calcularPrecioFinal(
                $item['articulo_id'],
                $sucursalId,
                $item['cantidad'],
                $contexto
            );

            $itemsCalculados[] = $calculo;
            $subtotalGeneral += $calculo['subtotal_sin_descuento'];
        }

        // Recalcular con total_compra para promociones por monto mínimo
        $contexto['total_compra'] = $subtotalGeneral;

        foreach ($itemsCalculados as &$item) {
            $calculoConTotal = $this->calcularPrecioFinal(
                $item['articulo_id'],
                $sucursalId,
                $item['cantidad'],
                $contexto
            );

            $item = $calculoConTotal;
            $descuentoTotalGeneral += $calculoConTotal['descuento_total'];
            $ivaTotalGeneral += $calculoConTotal['iva_monto'];
        }

        $totalFinal = array_sum(array_column($itemsCalculados, 'precio_final'));

        // Obtener info de la lista usada (todas deberían usar la misma)
        $listaInfo = null;
        if (!empty($itemsCalculados)) {
            $listaInfo = [
                'lista_precio_id' => $itemsCalculados[0]['lista_precio_id'],
                'lista_precio_nombre' => $itemsCalculados[0]['lista_precio_nombre'],
            ];
        }

        return [
            'items' => $itemsCalculados,
            'cantidad_items' => count($items),
            'lista_precio' => $listaInfo,
            'subtotal' => round($subtotalGeneral, 2),
            'descuento_total' => round($descuentoTotalGeneral, 2),
            'iva_total' => round($ivaTotalGeneral, 2),
            'total' => round($totalFinal, 2),
        ];
    }

    /**
     * Obtiene todas las listas de precios disponibles para una sucursal
     *
     * @param int $sucursalId
     * @param bool $incluirInactivas
     * @return Collection
     */
    public function obtenerListasPreciosSucursal(int $sucursalId, bool $incluirInactivas = false): Collection
    {
        $query = ListaPrecio::porSucursal($sucursalId)
                            ->ordenadoPorPrioridad()
                            ->conCondiciones();

        if (!$incluirInactivas) {
            $query->activas();
        }

        return $query->get();
    }

    /**
     * Crea la lista base obligatoria para una sucursal si no existe
     *
     * @param int $sucursalId
     * @param string $nombre
     * @return ListaPrecio
     */
    public function asegurarListaBase(int $sucursalId, string $nombre = 'Lista Base'): ListaPrecio
    {
        $listaBase = ListaPrecio::obtenerListaBase($sucursalId);

        if (!$listaBase) {
            $listaBase = ListaPrecio::crearListaBase($sucursalId, $nombre);
        }

        return $listaBase;
    }

    /**
     * Simula el precio de un artículo con una lista específica (sin guardar)
     *
     * @param int $articuloId
     * @param int $listaPrecioId
     * @return array|null
     */
    public function simularPrecioConLista(int $articuloId, int $listaPrecioId): ?array
    {
        $articulo = Articulo::find($articuloId);
        $listaPrecio = ListaPrecio::find($listaPrecioId);

        if (!$articulo || !$listaPrecio) {
            return null;
        }

        return $listaPrecio->obtenerPrecioArticulo($articulo);
    }

    /**
     * Obtiene un resumen comparativo de precios de un artículo en todas las listas de una sucursal
     *
     * @param int $articuloId
     * @param int $sucursalId
     * @return array
     */
    public function compararPreciosEnListas(int $articuloId, int $sucursalId): array
    {
        $articulo = Articulo::find($articuloId);
        if (!$articulo) {
            return [];
        }

        $listas = $this->obtenerListasPreciosSucursal($sucursalId);
        $comparacion = [];

        foreach ($listas as $lista) {
            $precioInfo = $lista->obtenerPrecioArticulo($articulo);
            $comparacion[] = [
                'lista_id' => $lista->id,
                'lista_nombre' => $lista->nombre,
                'es_lista_base' => $lista->es_lista_base,
                'precio_base_articulo' => (float) $articulo->precio_base,
                'precio_con_lista' => $precioInfo['precio'],
                'ajuste_porcentaje' => $precioInfo['ajuste_porcentaje'],
                'origen' => $precioInfo['origen'],
                'diferencia' => $precioInfo['precio'] - (float) $articulo->precio_base,
            ];
        }

        return $comparacion;
    }
}

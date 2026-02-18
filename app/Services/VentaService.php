<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Models\Stock;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Cliente;
use App\Models\Articulo;
use App\Models\ConceptoPago;
use App\Models\FormaPago;
use App\Models\ComprobanteFiscal;
use App\Services\ARCA\ComprobanteFiscalService;
use App\Models\MovimientoStock;
use App\Models\Receta;
use App\Models\Sucursal;
use App\Services\CobroService;
use App\Services\CuentaCorrienteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * Servicio de Ventas
 *
 * Maneja toda la lógica de negocio relacionada con ventas:
 * - Creación y actualización de ventas
 * - Gestión de stock
 * - Movimientos de caja
 * - Control de cuenta corriente de clientes
 * - Cálculos de IVA y descuentos
 *
 * FASE 3 - Sistema Multi-Sucursal (Servicios)
 */
class VentaService
{
    /**
     * Advertencias de stock recopiladas durante la validación (modo 'advierte')
     */
    public array $advertenciasStock = [];

    /**
     * Si true, permite que el stock quede negativo al descontar (modos 'advierte' y 'no_controla')
     */
    protected bool $permitirStockNegativo = false;

    /**
     * Crea una nueva venta con sus detalles
     *
     * @param array $data Datos de la venta
     * @param array $detalles Array de detalles de la venta
     * @return Venta
     * @throws Exception
     */
    public function crearVenta(array $data, array $detalles): Venta
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            // Validar que haya detalles
            if (empty($detalles)) {
                throw new Exception('La venta debe tener al menos un artículo');
            }

            // Validar stock si los artículos controlan stock
            $this->advertenciasStock = [];
            $this->validarStockDisponible($data['sucursal_id'], $detalles);

            // Validar crédito del cliente si es venta a cuenta corriente
            if (isset($data['cliente_id']) && ($data['es_cuenta_corriente'] ?? false)) {
                $this->validarCreditoCliente($data['cliente_id'], $data['sucursal_id'], $data['total'] ?? 0);
            }

            // Validar que la caja esté abierta si hay caja asignada
            if (isset($data['caja_id']) && $data['caja_id']) {
                $this->validarCajaAbierta($data['caja_id']);
            }

            // Generar número de venta si no viene (por caja)
            if (empty($data['numero']) && !empty($data['caja_id'])) {
                $data['numero'] = $this->generarNumeroVenta($data['caja_id']);
            }

            // Verificar si vienen totales ya calculados
            $usarTotalesProporcionados = $data['_usar_totales_proporcionados'] ?? false;

            // Log para debugging
            Log::info('VentaService::crearVenta - Datos recibidos', [
                '_usar_totales_proporcionados' => $usarTotalesProporcionados,
                'subtotal' => $data['subtotal'] ?? 'NO DEFINIDO',
                'descuento' => $data['descuento'] ?? 'NO DEFINIDO',
                'total' => $data['total'] ?? 'NO DEFINIDO',
                'iva' => $data['iva'] ?? 'NO DEFINIDO',
                'detalles_count' => count($detalles),
            ]);

            // Crear la venta con totales proporcionados o en 0 para recalcular
            $esCuentaCorriente = $data['es_cuenta_corriente'] ?? false;

            $venta = Venta::create([
                'sucursal_id' => $data['sucursal_id'],
                'cliente_id' => $data['cliente_id'] ?? null,
                'caja_id' => $data['caja_id'] ?? null,
                'canal_venta_id' => $data['canal_venta_id'] ?? null,
                'forma_venta_id' => $data['forma_venta_id'] ?? null,
                'lista_precio_id' => $data['lista_precio_id'] ?? null,
                'punto_venta_id' => $data['punto_venta_id'] ?? null,
                'forma_pago_id' => $data['forma_pago_id'] ?? null,
                'usuario_id' => $data['usuario_id'],
                'numero' => $data['numero'],
                'fecha' => $data['fecha'] ?? now(),
                'subtotal' => $usarTotalesProporcionados ? ($data['subtotal'] ?? 0) : 0,
                'descuento' => $data['descuento'] ?? 0,
                'total' => $usarTotalesProporcionados ? ($data['total'] ?? 0) : 0,
                'ajuste_forma_pago' => $data['ajuste_forma_pago'] ?? 0,
                'total_final' => $usarTotalesProporcionados ? ($data['total_final'] ?? $data['total'] ?? 0) : 0,
                'iva' => $usarTotalesProporcionados ? ($data['iva'] ?? 0) : 0,
                'estado' => $esCuentaCorriente ? 'pendiente' : 'completada',
                'es_cuenta_corriente' => $esCuentaCorriente,
                'saldo_pendiente_cache' => $data['saldo_pendiente_cache'] ?? 0,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
            ]);

            // Guardar promociones aplicadas si vienen en los datos
            if ($usarTotalesProporcionados) {
                $this->guardarPromocionesVenta($venta, $data);
            }

            // Crear detalles de la venta
            foreach ($detalles as $detalle) {
                $this->crearDetalleVenta($venta, $detalle, $usarTotalesProporcionados);
            }

            // Solo recalcular totales si no vienen proporcionados
            if (!$usarTotalesProporcionados) {
                $venta->actualizarTotales();
            }

            // Actualizar stock
            $this->actualizarStockPorVenta($venta);

            // NOTA: El movimiento de caja se registra desde NuevaVenta
            // para cada pago individual (desglosePagos), no aquí.
            // Esto permite manejar ventas con múltiples formas de pago.

            // Actualizar saldo del cliente si es cuenta corriente
            if ($venta->cliente_id && ($venta->es_cuenta_corriente || $venta->forma_pago === 'cta_cte')) {
                $this->actualizarSaldoCliente($venta);
            }

            DB::connection('pymes_tenant')->commit();

            // Actualizar cache de saldo del cliente si es cuenta corriente (después del commit)
            if ($venta->cliente_id && $venta->es_cuenta_corriente) {
                $ccService = new CuentaCorrienteService();
                $ccService->actualizarCacheCliente($venta->cliente_id, $venta->sucursal_id);
            }

            Log::info('Venta creada exitosamente', [
                'venta_id' => $venta->id,
                'numero_comprobante' => $venta->numero_comprobante,
                'total' => $venta->total,
            ]);

            return $venta->fresh(['detalles', 'cliente', 'sucursal', 'caja']);

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al crear venta', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Crea un detalle de venta con cálculos de IVA
     *
     * @param Venta $venta
     * @param array $detalle
     * @param bool $usarDatosProporcionados Si true, usa los datos del detalle sin recalcular
     * @return VentaDetalle
     */
    protected function crearDetalleVenta(Venta $venta, array $detalle, bool $usarDatosProporcionados = false): VentaDetalle
    {
        $articulo = Articulo::findOrFail($detalle['articulo_id']);
        $tipoIva = $articulo->tipoIva;

        $precioUnitario = $detalle['precio_unitario'];
        $cantidad = $detalle['cantidad'];

        // Log para debugging
        Log::info('VentaService::crearDetalleVenta', [
            'venta_id' => $venta->id,
            'articulo_id' => $detalle['articulo_id'],
            'usarDatosProporcionados' => $usarDatosProporcionados,
            'precio_lista_recibido' => $detalle['precio_lista'] ?? 'NO DEFINIDO',
            'descuento_promocion_recibido' => $detalle['descuento_promocion'] ?? 'NO DEFINIDO',
            'tiene_promocion_recibido' => $detalle['tiene_promocion'] ?? 'NO DEFINIDO',
        ]);

        if ($usarDatosProporcionados) {
            // Usar datos proporcionados desde la UI (ya calculados)
            $ivaPorcentaje = $detalle['iva_porcentaje'] ?? $tipoIva->porcentaje;
            $precioIvaIncluido = $detalle['precio_iva_incluido'] ?? true;

            // Calcular precio sin IVA
            if ($ivaPorcentaje > 0 && $precioIvaIncluido) {
                $precioSinIva = $precioUnitario / (1 + $ivaPorcentaje / 100);
            } else {
                $precioSinIva = $precioUnitario;
            }

            $subtotalSinIva = $precioSinIva * $cantidad;
            $ivaMonto = $subtotalSinIva * ($ivaPorcentaje / 100);
            $subtotal = $precioUnitario * $cantidad; // Subtotal con IVA incluido
            $descuentoPromocion = $detalle['descuento_promocion'] ?? 0;
            $total = $subtotal - $descuentoPromocion; // Total después de descuentos de promoción

            $ventaDetalle = VentaDetalle::create([
                'venta_id' => $venta->id,
                'articulo_id' => $articulo->id,
                'tipo_iva_id' => $tipoIva->id,
                'lista_precio_id' => $detalle['lista_precio_id'] ?? null,
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'precio_lista' => $detalle['precio_lista'] ?? $precioUnitario,
                'precio_opcionales' => $detalle['precio_opcionales'] ?? 0,
                'iva_porcentaje' => $ivaPorcentaje,
                'precio_sin_iva' => round($precioSinIva, 2),
                'descuento' => $detalle['descuento'] ?? 0,
                'descuento_promocion' => round($descuentoPromocion, 2),
                'tiene_promocion' => $detalle['tiene_promocion'] ?? false,
                'iva_monto' => round($ivaMonto, 2),
                'subtotal' => round($subtotal, 2),
                'total' => round($total, 2),
                // Ajuste manual
                'ajuste_manual_tipo' => $detalle['ajuste_manual_tipo'] ?? null,
                'ajuste_manual_valor' => $detalle['ajuste_manual_valor'] ?? null,
                'precio_sin_ajuste_manual' => $detalle['precio_sin_ajuste_manual'] ?? null,
            ]);

            // Guardar promociones aplicadas al detalle
            if (!empty($detalle['_promociones_item'])) {
                $this->guardarPromocionesDetalle($ventaDetalle, $detalle['_promociones_item']);
            }

            // Guardar opcionales seleccionados
            if (!empty($detalle['opcionales'])) {
                $this->guardarOpcionalesDetalle($ventaDetalle, $detalle['opcionales']);
            }

            return $ventaDetalle;
        }

        // Modo legacy: recalcular todo
        $descuento = $detalle['descuento'] ?? 0;
        $precioConDescuento = $precioUnitario - $descuento;

        // Determinar si el precio incluye IVA según configuración del artículo
        if ($articulo->precio_iva_incluido) {
            $precioSinIva = $tipoIva->obtenerPrecioSinIva($precioConDescuento, true);
        } else {
            $precioSinIva = $precioConDescuento;
        }

        // Calcular subtotal e IVA
        $subtotalSinIva = $precioSinIva * $cantidad;
        $ivaMonto = $subtotalSinIva * ($tipoIva->porcentaje / 100);
        $subtotal = $subtotalSinIva + $ivaMonto;

        return VentaDetalle::create([
            'venta_id' => $venta->id,
            'articulo_id' => $articulo->id,
            'tipo_iva_id' => $tipoIva->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'iva_porcentaje' => $tipoIva->porcentaje,
            'precio_sin_iva' => $precioSinIva,
            'descuento' => $descuento,
            'iva_monto' => $ivaMonto,
            'subtotal' => $subtotal,
            'total' => $subtotal, // En modo legacy, total = subtotal (sin promociones)
        ]);
    }

    /**
     * Guarda las promociones aplicadas a la venta
     *
     * @param Venta $venta
     * @param array $data Datos con _promociones_comunes y _promociones_especiales
     */
    protected function guardarPromocionesVenta(Venta $venta, array $data): void
    {
        $promocionesComunes = $data['_promociones_comunes'] ?? [];
        $promocionesEspeciales = $data['_promociones_especiales'] ?? [];

        // Guardar promociones comunes (ej: 5% de descuento)
        foreach ($promocionesComunes as $promo) {
            DB::connection('pymes_tenant')->table('venta_promociones')->insert([
                'venta_id' => $venta->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => $promo['promocion_id'] ?? $promo['id'] ?? null,
                'promocion_especial_id' => null,
                'forma_pago_id' => null,
                'codigo_cupon' => null,
                'descripcion_promocion' => $promo['nombre'] ?? $promo['descripcion'] ?? 'Promoción',
                'tipo_beneficio' => $promo['tipo_beneficio'] ?? 'porcentaje',
                'valor_beneficio' => $promo['valor'] ?? $promo['valor_beneficio'] ?? 0, // Valor original (%, monto)
                'descuento_aplicado' => $promo['descuento'] ?? 0,
                'monto_minimo_requerido' => $promo['monto_minimo'] ?? null,
                'created_at' => now(),
            ]);
        }

        // Guardar promociones especiales (ej: coca + alfajor)
        foreach ($promocionesEspeciales as $promo) {
            // tipo_beneficio debe ser 'porcentaje' o 'monto_fijo'
            $tipoBeneficio = $promo['tipo'] ?? 'monto_fijo';
            if (!in_array($tipoBeneficio, ['porcentaje', 'monto_fijo'])) {
                $tipoBeneficio = 'monto_fijo'; // Para combos, nx1, etc. usamos monto_fijo
            }

            DB::connection('pymes_tenant')->table('venta_promociones')->insert([
                'venta_id' => $venta->id,
                'tipo_promocion' => 'promocion_especial',
                'promocion_id' => null,
                'promocion_especial_id' => $promo['promocion_especial_id'] ?? $promo['id'] ?? null,
                'forma_pago_id' => null,
                'codigo_cupon' => null,
                'descripcion_promocion' => $promo['nombre'] ?? $promo['descripcion'] ?? 'Promoción Especial',
                'tipo_beneficio' => $tipoBeneficio,
                'valor_beneficio' => $promo['descuento'] ?? 0,
                'descuento_aplicado' => $promo['descuento'] ?? 0,
                'monto_minimo_requerido' => null,
                'created_at' => now(),
            ]);
        }

        Log::info('VentaService::guardarPromocionesVenta', [
            'venta_id' => $venta->id,
            'promociones_comunes' => count($promocionesComunes),
            'promociones_especiales' => count($promocionesEspeciales),
        ]);
    }

    /**
     * Guarda las promociones aplicadas a un detalle de venta
     *
     * @param VentaDetalle $detalle
     * @param array $promocionesItem Info de promociones del ítem
     */
    protected function guardarPromocionesDetalle(VentaDetalle $detalle, array $promocionesItem): void
    {
        // Promociones comunes aplicadas al ítem (ahora vienen como objetos completos)
        $promocionesComunes = $promocionesItem['promociones_comunes'] ?? [];
        foreach ($promocionesComunes as $promo) {
            // Si es string (backwards compatibility), saltar
            if (is_string($promo)) {
                continue;
            }

            DB::connection('pymes_tenant')->table('venta_detalle_promociones')->insert([
                'venta_detalle_id' => $detalle->id,
                'tipo_promocion' => 'promocion',
                'promocion_id' => $promo['promocion_id'] ?? $promo['id'] ?? null,
                'promocion_especial_id' => null,
                'lista_precio_id' => null,
                'descripcion_promocion' => $promo['nombre'] ?? 'Promoción',
                'tipo_beneficio' => $promo['tipo_beneficio'] ?? 'porcentaje',
                'valor_beneficio' => $promo['valor'] ?? $promo['valor_beneficio'] ?? 0, // Valor original (%, monto)
                'descuento_aplicado' => $promo['descuento_item'] ?? $promo['descuento'] ?? 0,
                'cantidad_requerida' => null,
                'cantidad_bonificada' => null,
                'created_at' => now(),
            ]);
        }

        // Promociones especiales que incluyen este ítem (ahora vienen como objetos completos)
        $promocionesEspeciales = $promocionesItem['promociones_especiales'] ?? [];
        foreach ($promocionesEspeciales as $promo) {
            // Si es string (backwards compatibility), crear registro básico
            if (is_string($promo)) {
                DB::connection('pymes_tenant')->table('venta_detalle_promociones')->insert([
                    'venta_detalle_id' => $detalle->id,
                    'tipo_promocion' => 'promocion_especial',
                    'promocion_id' => null,
                    'promocion_especial_id' => null,
                    'lista_precio_id' => null,
                    'descripcion_promocion' => $promo,
                    'tipo_beneficio' => 'monto_fijo',
                    'valor_beneficio' => 0,
                    'descuento_aplicado' => 0,
                    'cantidad_requerida' => null,
                    'cantidad_bonificada' => null,
                    'created_at' => now(),
                ]);
                continue;
            }

            // Objeto completo con toda la info
            DB::connection('pymes_tenant')->table('venta_detalle_promociones')->insert([
                'venta_detalle_id' => $detalle->id,
                'tipo_promocion' => 'promocion_especial',
                'promocion_id' => null,
                'promocion_especial_id' => $promo['promocion_especial_id'] ?? $promo['id'] ?? null,
                'lista_precio_id' => null,
                'descripcion_promocion' => $promo['nombre'] ?? 'Promoción Especial',
                'tipo_beneficio' => 'monto_fijo', // Promociones especiales siempre son monto fijo
                'valor_beneficio' => $promo['descuento'] ?? 0,
                'descuento_aplicado' => $promo['descuento'] ?? 0, // El descuento se distribuye a nivel venta
                'cantidad_requerida' => null,
                'cantidad_bonificada' => null,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Guarda los opcionales seleccionados de un detalle de venta
     */
    protected function guardarOpcionalesDetalle(VentaDetalle $detalle, array $opcionales): void
    {
        foreach ($opcionales as $grupo) {
            foreach ($grupo['selecciones'] as $sel) {
                DB::connection('pymes_tenant')->table('venta_detalle_opcionales')->insert([
                    'venta_detalle_id' => $detalle->id,
                    'grupo_opcional_id' => $grupo['grupo_id'],
                    'opcional_id' => $sel['opcional_id'],
                    'nombre_grupo' => $grupo['grupo_nombre'],
                    'nombre_opcional' => $sel['nombre'],
                    'cantidad' => $sel['cantidad'] ?? 1,
                    'precio_extra' => $sel['precio_extra'] ?? 0,
                    'subtotal_extra' => ($sel['precio_extra'] ?? 0) * ($sel['cantidad'] ?? 1),
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Valida que hay stock disponible para todos los artículos
     *
     * @param int $sucursalId
     * @param array $detalles
     * @throws Exception
     */
    protected function validarStockDisponible(int $sucursalId, array $detalles): void
    {
        // Determinar modo de control de stock de la sucursal
        $sucursal = Sucursal::find($sucursalId);
        $controlStock = $sucursal->control_stock_venta ?? 'bloquea';

        // Permitir stock negativo si no bloquea
        $this->permitirStockNegativo = ($controlStock !== 'bloquea');

        // Si no controla validación, salir (el stock se descuenta igualmente en actualizarStockPorVenta)
        if ($controlStock === 'no_controla') {
            return;
        }

        // Acumular ingredientes necesarios para recetas (un ingrediente puede repetirse)
        $ingredientesNecesarios = []; // [articulo_id => ['cantidad' => X, 'nombre' => Y]]
        $faltantes = []; // Mensajes de faltantes para modo 'advierte'

        foreach ($detalles as $detalle) {
            $articulo = Articulo::findOrFail($detalle['articulo_id']);

            // Solo validar si el artículo controla stock en esta sucursal
            $modoStock = $articulo->getModoStock($sucursalId);
            if ($modoStock === 'ninguno') {
                continue;
            }

            if ($modoStock === 'receta') {
                // Acumular ingredientes de la receta del artículo
                $receta = $articulo->resolverReceta($sucursalId);
                if ($receta) {
                    foreach ($receta->ingredientes as $ingrediente) {
                        $cantNecesaria = $ingrediente->cantidad * $detalle['cantidad'] / $receta->cantidad_producida;
                        $artId = $ingrediente->articulo_id;
                        if (!isset($ingredientesNecesarios[$artId])) {
                            $ingredientesNecesarios[$artId] = [
                                'cantidad' => 0,
                                'nombre' => $ingrediente->articulo->nombre ?? "Artículo #$artId",
                            ];
                        }
                        $ingredientesNecesarios[$artId]['cantidad'] += $cantNecesaria;
                    }
                }

                // Acumular ingredientes de opcionales con receta
                $this->acumularIngredientesOpcionales(
                    $detalle['opcionales'] ?? [], $detalle['cantidad'],
                    $sucursalId, $ingredientesNecesarios
                );

                continue;
            }

            // modo 'unitario': validar stock del artículo directamente
            $stock = Stock::where('sucursal_id', $sucursalId)
                         ->where('articulo_id', $detalle['articulo_id'])
                         ->first();

            if (!$stock) {
                $msg = "El artículo '{$articulo->nombre}' no tiene stock en esta sucursal";
                if ($controlStock === 'bloquea') {
                    throw new Exception($msg);
                }
                $faltantes[] = $msg;
                continue;
            }

            if ($stock->cantidad < $detalle['cantidad']) {
                $msg = "Stock insuficiente para '{$articulo->nombre}'. Disponible: {$stock->cantidad}, Solicitado: {$detalle['cantidad']}";
                if ($controlStock === 'bloquea') {
                    throw new Exception($msg);
                }
                $faltantes[] = $msg;
            }

            // Acumular ingredientes de opcionales con receta (para modo unitario también)
            $this->acumularIngredientesOpcionales(
                $detalle['opcionales'] ?? [], $detalle['cantidad'],
                $sucursalId, $ingredientesNecesarios
            );
        }

        // Validar stock de todos los ingredientes acumulados
        foreach ($ingredientesNecesarios as $articuloId => $info) {
            $stock = Stock::where('sucursal_id', $sucursalId)
                         ->where('articulo_id', $articuloId)
                         ->first();

            $disponible = $stock ? (float) $stock->cantidad : 0;
            if ($disponible < $info['cantidad']) {
                $msg = "Stock insuficiente del ingrediente '{$info['nombre']}'. " .
                    "Disponible: " . round($disponible, 2) . ", Necesario: " . round($info['cantidad'], 2);
                if ($controlStock === 'bloquea') {
                    throw new Exception($msg);
                }
                $faltantes[] = $msg;
            }
        }

        // En modo 'advierte', guardar faltantes como advertencias
        if ($controlStock === 'advierte' && !empty($faltantes)) {
            $this->advertenciasStock = $faltantes;
        }
    }

    /**
     * Acumula ingredientes necesarios de opcionales con receta
     */
    protected function acumularIngredientesOpcionales(
        array $opcionales, float $cantidadDetalle,
        int $sucursalId, array &$ingredientesNecesarios
    ): void {
        foreach ($opcionales as $grupo) {
            foreach ($grupo['selecciones'] as $sel) {
                $recetaOpc = Receta::resolver('Opcional', $sel['opcional_id'], $sucursalId);
                if ($recetaOpc) {
                    $cantOpcional = ($sel['cantidad'] ?? 1) * $cantidadDetalle;
                    foreach ($recetaOpc->ingredientes as $ingrediente) {
                        $cantNecesaria = $ingrediente->cantidad * $cantOpcional / $recetaOpc->cantidad_producida;
                        $artId = $ingrediente->articulo_id;
                        if (!isset($ingredientesNecesarios[$artId])) {
                            $ingredientesNecesarios[$artId] = [
                                'cantidad' => 0,
                                'nombre' => $ingrediente->articulo->nombre ?? "Artículo #$artId",
                            ];
                        }
                        $ingredientesNecesarios[$artId]['cantidad'] += $cantNecesaria;
                    }
                }
            }
        }
    }

    /**
     * Valida el crédito disponible del cliente
     *
     * @param int $clienteId
     * @param int $sucursalId
     * @param float $montoVenta
     * @throws Exception
     */
    protected function validarCreditoCliente(int $clienteId, int $sucursalId, float $montoVenta): void
    {
        $cliente = Cliente::findOrFail($clienteId);

        if (!$cliente->tieneDisponibilidadCredito($montoVenta, $sucursalId)) {
            $disponible = $cliente->obtenerCreditoDisponibleEnSucursal($sucursalId);
            throw new Exception(
                "Crédito insuficiente. Disponible: $" . number_format($disponible, 2) .
                ", Necesario: $" . number_format($montoVenta, 2)
            );
        }
    }

    /**
     * Valida que la caja esté abierta
     *
     * @param int $cajaId
     * @throws Exception
     */
    protected function validarCajaAbierta(int $cajaId): void
    {
        $caja = Caja::findOrFail($cajaId);

        if (!$caja->estaAbierta()) {
            throw new Exception('La caja debe estar abierta para realizar ventas');
        }
    }

    /**
     * Actualiza el stock por la venta realizada
     *
     * @param Venta $venta
     */
    protected function actualizarStockPorVenta(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            $articulo = $detalle->articulo;

            // Solo actualizar stock si el artículo lo controla en esta sucursal
            $modoStock = $articulo->getModoStock($venta->sucursal_id);
            if ($modoStock === 'ninguno') {
                continue;
            }

            if ($modoStock === 'receta') {
                $receta = $articulo->resolverReceta($venta->sucursal_id);
                if ($receta) {
                    $this->descontarStockPorReceta(
                        $receta, $detalle->cantidad, $venta->sucursal_id,
                        $venta->id, $detalle->id,
                        "Venta #{$venta->id} - Receta {$articulo->nombre}",
                        $venta->usuario_id
                    );
                }
                // Descontar stock de opcionales con receta de este detalle
                $this->descontarStockOpcionalesDetalle($detalle, $venta);
                continue;
            }

            // modo 'unitario': descontar stock del artículo directamente
            $stock = Stock::where('sucursal_id', $venta->sucursal_id)
                         ->where('articulo_id', $detalle->articulo_id)
                         ->firstOrFail();

            $stock->disminuir($detalle->cantidad, $this->permitirStockNegativo);

            // Registrar movimiento de stock
            MovimientoStock::crearMovimientoVenta(
                $detalle->articulo_id,
                $venta->sucursal_id,
                $detalle->cantidad,
                $venta->id,
                $detalle->id,
                "Venta #{$venta->id}",
                $venta->usuario_id
            );

            // Descontar stock de opcionales con receta de este detalle
            $this->descontarStockOpcionalesDetalle($detalle, $venta);
        }
    }

    /**
     * Descuenta stock de ingredientes según una receta
     */
    protected function descontarStockPorReceta(
        Receta $receta, float $cantidadVendida, int $sucursalId,
        int $ventaId, int $ventaDetalleId, string $conceptoBase, int $usuarioId
    ): void {
        foreach ($receta->ingredientes as $ingrediente) {
            $cantidadDescontar = $ingrediente->cantidad * $cantidadVendida / $receta->cantidad_producida;

            $stock = Stock::where('sucursal_id', $sucursalId)
                         ->where('articulo_id', $ingrediente->articulo_id)
                         ->first();

            if ($stock) {
                $stock->disminuir($cantidadDescontar, $this->permitirStockNegativo);

                MovimientoStock::crearMovimientoVenta(
                    $ingrediente->articulo_id, $sucursalId, $cantidadDescontar,
                    $ventaId, $ventaDetalleId,
                    $conceptoBase, $usuarioId
                );
            }
        }
    }

    /**
     * Descuenta stock de opcionales con receta para un detalle de venta
     */
    protected function descontarStockOpcionalesDetalle(VentaDetalle $detalle, Venta $venta): void
    {
        $opcionalesDetalle = DB::connection('pymes_tenant')
            ->table('venta_detalle_opcionales')
            ->where('venta_detalle_id', $detalle->id)
            ->get();

        foreach ($opcionalesDetalle as $opcDet) {
            $recetaOpc = Receta::resolver('Opcional', $opcDet->opcional_id, $venta->sucursal_id);
            if ($recetaOpc) {
                $this->descontarStockPorReceta(
                    $recetaOpc, $opcDet->cantidad * $detalle->cantidad,
                    $venta->sucursal_id, $venta->id, $detalle->id,
                    "Venta #{$venta->id} - Opcional {$opcDet->nombre_opcional}",
                    $venta->usuario_id
                );
            }
        }
    }

    /**
     * Registra el movimiento de caja por la venta
     *
     * @param Venta $venta
     */
    protected function registrarMovimientoCaja(Venta $venta): void
    {
        $caja = Caja::findOrFail($venta->caja_id);

        // Crear movimiento de caja
        $movimiento = new MovimientoCaja();
        $movimiento->caja_id = $caja->id;
        $movimiento->tipo_movimiento = 'ingreso';
        $movimiento->concepto = "Venta #{$venta->numero_comprobante}";
        $movimiento->monto = $venta->total;
        $movimiento->forma_pago = $venta->forma_pago;
        $movimiento->referencia = $venta->numero_comprobante;
        $movimiento->venta_id = $venta->id;
        $movimiento->usuario_id = $venta->usuario_id;

        // Calcular saldos
        $movimiento->calcularSaldos();
        $movimiento->save();

        // Actualizar saldo de la caja
        $caja->aumentarSaldo($venta->total);
    }

    /**
     * Actualiza el saldo del cliente en cuenta corriente
     *
     * @param Venta $venta
     */
    protected function actualizarSaldoCliente(Venta $venta): void
    {
        $cliente = Cliente::findOrFail($venta->cliente_id);
        $cliente->ajustarSaldoEnSucursal($venta->sucursal_id, $venta->total);
    }

    /**
     * Genera un número de venta único secuencial por caja
     *
     * Formato: NUMERO_CAJA-SECUENCIAL (ej: 0001-00000001)
     * La secuencia es independiente por cada caja.
     *
     * @param int $cajaId
     * @return string
     */
    protected function generarNumeroVenta(int $cajaId): string
    {
        $caja = Caja::findOrFail($cajaId);

        // Obtener el último número de venta para esta caja
        $ultimaVenta = Venta::where('caja_id', $cajaId)
                           ->whereNotNull('numero')
                           ->orderBy('id', 'desc')
                           ->first();

        $secuencial = 1;
        if ($ultimaVenta && $ultimaVenta->numero) {
            // Extraer el número secuencial del formato actual (ej: "0001-00000001" -> 1)
            $partes = explode('-', $ultimaVenta->numero);
            $ultimoSecuencial = end($partes);
            $secuencial = intval($ultimoSecuencial) + 1;
        }

        // Formato: NUMERO_CAJA-SECUENCIAL (ej: 0001-00000001)
        return sprintf('%04d-%08d', $caja->numero, $secuencial);
    }

    /**
     * Cancela una venta completamente y revierte todos sus efectos
     * - Emite nota de crédito si tiene comprobantes fiscales (y emitirNotaCredito=true)
     * - Revierte el stock
     * - Anula todos los pagos
     * - Revierte saldo del cliente si era cuenta corriente
     * - Revierte movimientos de caja
     * - Marca la venta como "cancelada"
     *
     * @param int $ventaId
     * @param string|null $motivo Motivo de la cancelación
     * @param bool $emitirNotaCredito Si debe emitir NC para comprobantes fiscales
     * @return array ['venta' => Venta, 'notas_credito' => ComprobanteFiscal[]]
     * @throws Exception
     */
    public function cancelarVentaCompleta(int $ventaId, ?string $motivo = null, bool $emitirNotaCredito = true): array
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $venta = Venta::with(['pagos', 'caja', 'cliente', 'comprobantesFiscales'])->findOrFail($ventaId);

            if ($venta->estaCancelada()) {
                throw new Exception('La venta ya está cancelada');
            }

            $usuarioId = Auth::id();
            $ahora = now();
            $notasCredito = [];

            // 0. Si tiene comprobantes fiscales y se debe emitir NC
            // Primero calculamos el saldo fiscal neto (facturas - notas de crédito)
            $todosComprobantes = $venta->comprobantesFiscales()
                ->autorizados()
                ->get();

            $saldoFiscal = 0;
            $facturasParaAnular = [];

            foreach ($todosComprobantes as $cf) {
                if ($cf->esFactura()) {
                    // Verificar si esta factura ya tiene NC asociada
                    $ncAsociadas = $cf->notasCredito()->autorizados()->sum('total');
                    $saldoPendiente = floatval($cf->total) - floatval($ncAsociadas);

                    if ($saldoPendiente > 0.01) {
                        $saldoFiscal += $saldoPendiente;
                        $facturasParaAnular[] = $cf;
                    }
                }
            }

            // Solo emitir NC si hay saldo fiscal pendiente
            if ($saldoFiscal > 0.01 && $emitirNotaCredito && count($facturasParaAnular) > 0) {
                $comprobanteFiscalService = new ComprobanteFiscalService();

                foreach ($facturasParaAnular as $comprobante) {
                    $notaCredito = $comprobanteFiscalService->crearNotaCredito(
                        $comprobante,
                        $venta,
                        $motivo ?? 'Cancelación de venta',
                        $usuarioId
                    );
                    $notasCredito[] = $notaCredito;
                }
            }

            // 1. Revertir stock
            $this->revertirStockPorVenta($venta);

            // 2. Anular todos los pagos y revertir movimientos de caja
            foreach ($venta->pagos as $pago) {
                // Si el pago afecta caja y tiene movimiento, revertirlo
                if ($pago->afecta_caja && $pago->movimiento_caja_id) {
                    $movimiento = MovimientoCaja::find($pago->movimiento_caja_id);
                    if ($movimiento && $venta->caja) {
                        $venta->caja->disminuirSaldo($pago->monto_final);
                    }
                }

                // Marcar pago como anulado y desmarcar como facturado
                $pago->update([
                    'estado' => 'anulado',
                    'anulado_por_usuario_id' => $usuarioId,
                    'anulado_at' => $ahora,
                    'motivo_anulacion' => $motivo ?? 'Cancelación completa de venta',
                    'comprobante_fiscal_id' => null,
                    'monto_facturado' => null,
                ]);
            }

            // 3. Revertir saldo del cliente si era cuenta corriente
            if ($venta->es_cuenta_corriente && $venta->cliente_id) {
                $cliente = Cliente::findOrFail($venta->cliente_id);
                // Disminuir el saldo que debía (ya no debe nada de esta venta)
                $cliente->ajustarSaldoEnSucursal($venta->sucursal_id, -$venta->total_final);
            }

            // 4. Marcar venta como cancelada y actualizar cache fiscal
            $venta->update([
                'estado' => 'cancelada',
                'anulado_por_usuario_id' => $usuarioId,
                'anulado_at' => $ahora,
                'motivo_anulacion' => $motivo ?? 'Cancelación completa',
                'monto_fiscal_cache' => 0,
                'monto_no_fiscal_cache' => 0,
                'saldo_pendiente_cache' => 0, // Ya no hay saldo pendiente
            ]);

            DB::connection('pymes_tenant')->commit();

            // 5. Anular movimientos de cuenta corriente con contraasientos
            if ($venta->es_cuenta_corriente && $venta->cliente_id) {
                $ccService = new CuentaCorrienteService();
                $ccService->anularMovimientosVenta($venta, $motivo ?? 'Cancelación de venta', $usuarioId);
            }

            Log::info('Venta cancelada completamente', [
                'venta_id' => $venta->id,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
                'notas_credito_emitidas' => count($notasCredito),
            ]);

            return [
                'venta' => $venta->fresh(),
                'notas_credito' => $notasCredito,
            ];

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al cancelar venta completa', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Anula solo la parte fiscal de una venta
     * - Emite nota de crédito para cada comprobante fiscal
     * - NO revierte stock (los artículos ya fueron entregados)
     * - NO cancela la venta ni los pagos
     * - Desmarca los pagos como facturados (comprobante_fiscal_id = null)
     * - Actualiza el cache fiscal de la venta
     *
     * @param int $ventaId
     * @param string|null $motivo Motivo de la anulación fiscal
     * @return array ['venta' => Venta, 'notas_credito' => ComprobanteFiscal[]]
     * @throws Exception
     */
    public function anularSoloParteFiscal(int $ventaId, ?string $motivo = null): array
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $venta = Venta::with(['pagos', 'comprobantesFiscales'])->findOrFail($ventaId);

            if ($venta->estaCancelada()) {
                throw new Exception('La venta está cancelada');
            }

            // Obtener solo facturas autorizadas (no NC)
            $comprobantesFiscales = $venta->comprobantesFiscales()
                ->facturas()
                ->autorizados()
                ->get();

            if ($comprobantesFiscales->count() === 0) {
                throw new Exception('La venta no tiene comprobantes fiscales para anular');
            }

            $usuarioId = Auth::id();
            $notasCredito = [];
            $comprobanteFiscalService = new ComprobanteFiscalService();

            // 1. Emitir nota de crédito para cada comprobante fiscal
            foreach ($comprobantesFiscales as $comprobante) {
                $notaCredito = $comprobanteFiscalService->crearNotaCredito(
                    $comprobante,
                    $venta,
                    $motivo ?? 'Anulación fiscal',
                    $usuarioId
                );
                $notasCredito[] = $notaCredito;
            }

            // 2. Desmarcar pagos como facturados (los que estaban marcados)
            foreach ($venta->pagos as $pago) {
                if ($pago->comprobante_fiscal_id) {
                    $pago->update([
                        'comprobante_fiscal_id' => null,
                        'monto_facturado' => null,
                    ]);
                }
            }

            // 3. Actualizar cache fiscal de la venta
            $venta->update([
                'monto_fiscal_cache' => 0,
                'monto_no_fiscal_cache' => $venta->total_final,
            ]);

            DB::connection('pymes_tenant')->commit();

            Log::info('Parte fiscal de venta anulada', [
                'venta_id' => $venta->id,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
                'notas_credito_emitidas' => count($notasCredito),
            ]);

            return [
                'venta' => $venta->fresh(),
                'notas_credito' => $notasCredito,
            ];

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al anular parte fiscal de venta', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Anula los pagos de una venta y la convierte a cuenta corriente
     * - NO revierte el stock (los artículos ya fueron entregados)
     * - Anula todos los pagos existentes
     * - Crea un nuevo pago como cuenta corriente
     * - Actualiza el saldo del cliente (ahora debe el total)
     * - La venta permanece como "completada" pero ahora es cuenta corriente
     *
     * IMPORTANTE: Solo disponible si la venta NO es ya cuenta corriente
     *
     * @param int $ventaId
     * @param string|null $motivo Motivo de la anulación de pagos
     * @return Venta
     * @throws Exception
     */
    public function anularPagosYPasarACtaCte(int $ventaId, ?string $motivo = null): Venta
    {
        DB::connection('pymes_tenant')->beginTransaction();

        try {
            $venta = Venta::with(['pagos', 'caja', 'cliente'])->findOrFail($ventaId);

            if ($venta->estaCancelada()) {
                throw new Exception('La venta está cancelada');
            }

            if ($venta->es_cuenta_corriente) {
                throw new Exception('La venta ya es cuenta corriente');
            }

            if (!$venta->cliente_id) {
                throw new Exception('La venta debe tener un cliente asignado para pasar a cuenta corriente');
            }

            $usuarioId = Auth::id();
            $ahora = now();

            // 1. Anular todos los pagos existentes y revertir movimientos de caja
            foreach ($venta->pagos as $pago) {
                // Si el pago afecta caja y tiene movimiento, revertirlo
                if ($pago->afecta_caja && $pago->movimiento_caja_id) {
                    $movimiento = MovimientoCaja::find($pago->movimiento_caja_id);
                    if ($movimiento && $venta->caja) {
                        $venta->caja->disminuirSaldo($pago->monto_final);
                    }
                }

                // Marcar pago como anulado
                $pago->update([
                    'estado' => 'anulado',
                    'anulado_por_usuario_id' => $usuarioId,
                    'anulado_at' => $ahora,
                    'motivo_anulacion' => $motivo ?? 'Conversión a cuenta corriente',
                ]);
            }

            // 2. Buscar la forma de pago de tipo cuenta corriente (crédito cliente)
            $formaPagoCtaCte = FormaPago::whereHas('conceptoPago', function ($q) {
                $q->where('codigo', ConceptoPago::CREDITO_CLIENTE);
            })->first();

            if (!$formaPagoCtaCte) {
                throw new Exception('No se encontró la forma de pago de cuenta corriente configurada');
            }

            // 3. Crear nuevo pago como cuenta corriente
            VentaPago::create([
                'venta_id' => $venta->id,
                'forma_pago_id' => $formaPagoCtaCte->id,
                'concepto_pago_id' => $formaPagoCtaCte->concepto_pago_id,
                'monto_base' => $venta->total_final,
                'ajuste_porcentaje' => 0,
                'monto_ajuste' => 0,
                'monto_final' => $venta->total_final,
                'saldo_pendiente' => $venta->total_final,
                'es_cuenta_corriente' => true,
                'afecta_caja' => false,
                'estado' => 'activo',
                'observaciones' => $motivo ?? 'Pago convertido a cuenta corriente',
            ]);

            // 4. Actualizar saldo del cliente (ahora debe el total)
            $cliente = Cliente::findOrFail($venta->cliente_id);
            $cliente->ajustarSaldoEnSucursal($venta->sucursal_id, $venta->total_final);

            // 5. Actualizar la venta
            $venta->update([
                'es_cuenta_corriente' => true,
                'saldo_pendiente_cache' => $venta->total_final,
                'estado' => 'pendiente', // Ahora está pendiente de cobro
            ]);

            DB::connection('pymes_tenant')->commit();

            // Registrar el movimiento de CC para el nuevo pago
            $ventaPagoCC = VentaPago::where('venta_id', $venta->id)
                ->where('es_cuenta_corriente', true)
                ->orderByDesc('id')
                ->first();

            if ($ventaPagoCC) {
                $this->registrarMovimientoCCPago($ventaPagoCC, $usuarioId);
            }

            Log::info('Venta convertida a cuenta corriente', [
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'monto' => $venta->total_final,
                'usuario_id' => $usuarioId,
                'motivo' => $motivo,
            ]);

            return $venta->fresh();

        } catch (Exception $e) {
            DB::connection('pymes_tenant')->rollBack();
            Log::error('Error al convertir venta a cuenta corriente', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancela una venta (método legacy - redirige a cancelarVentaCompleta)
     *
     * @param int $ventaId
     * @return Venta
     * @throws Exception
     * @deprecated Usar cancelarVentaCompleta() en su lugar
     */
    public function cancelarVenta(int $ventaId): Venta
    {
        return $this->cancelarVentaCompleta($ventaId);
    }

    /**
     * Registra el movimiento de cuenta corriente para un VentaPago de CC
     *
     * Debe llamarse después de crear un VentaPago con es_cuenta_corriente = true
     * y si la venta tiene cliente asignado.
     *
     * @param VentaPago $ventaPago
     * @param int $usuarioId
     * @return \App\Models\MovimientoCuentaCorriente|null
     */
    public function registrarMovimientoCCPago(VentaPago $ventaPago, int $usuarioId): ?\App\Models\MovimientoCuentaCorriente
    {
        if (!$ventaPago->es_cuenta_corriente) {
            return null;
        }

        $venta = $ventaPago->venta;
        if (!$venta || !$venta->cliente_id) {
            return null;
        }

        // Actualizar saldo_pendiente del VentaPago (estado se mantiene 'activo')
        $ventaPago->update([
            'saldo_pendiente' => $ventaPago->monto_final,
        ]);

        // Registrar el movimiento de cuenta corriente
        $ccService = new CuentaCorrienteService();
        $movimiento = $ccService->registrarMovimientoVenta($ventaPago, $usuarioId);

        return $movimiento;
    }

    /**
     * Procesa los pagos de una venta y registra movimientos en cuenta corriente
     * Registra la venta completa y todos los pagos para tener trazabilidad total
     *
     * Flujo:
     * 1. Registra el total de la venta como DEBE (deuda inicial)
     * 2. Registra cada pago NO-CC como HABER (pago inmediato que reduce deuda)
     * 3. Los pagos CC quedan como deuda pendiente (saldo_pendiente > 0)
     *
     * @param Venta $venta
     * @param int $usuarioId
     * @return array Movimientos creados
     */
    public function procesarPagosCuentaCorriente(Venta $venta, int $usuarioId): array
    {
        $movimientos = [];

        // Solo procesar si la venta tiene cliente
        if (!$venta->cliente_id) {
            return $movimientos;
        }

        $ccService = new CuentaCorrienteService();

        // Construir descripcion_comprobantes
        $comprobantes = [];

        // Cargar comprobantes fiscales
        $venta->load('comprobantesFiscales');

        // Verificar si hay un comprobante fiscal por el total de la venta
        $tieneFacturaTotal = $venta->comprobantesFiscales->contains('es_total_venta', true);

        // Calcular monto total de comprobantes fiscales y del ticket
        $totalFiscal = $venta->comprobantesFiscales->sum('total');
        $montoTicket = max(0, $venta->total_final - $totalFiscal);

        // Solo mostrar ticket si NO hay factura por el total (es decir, hay parte en negro)
        if (!$tieneFacturaTotal) {
            $montoFormateado = '$' . number_format($montoTicket, 2, ',', '.');
            $comprobantes[] = "Ticket {$venta->numero} ({$montoFormateado})";
        }

        // Agregar comprobantes fiscales si existen
        if ($venta->comprobantesFiscales->isNotEmpty()) {
            foreach ($venta->comprobantesFiscales as $cf) {
                // Determinar abreviatura según tipo y letra
                $tipoAbrev = match($cf->tipo) {
                    'factura_a', 'factura_b', 'factura_c' => 'FA',
                    'nota_credito_a', 'nota_credito_b', 'nota_credito_c' => 'NC',
                    'nota_debito_a', 'nota_debito_b', 'nota_debito_c' => 'ND',
                    default => 'CF'
                };
                $letra = $cf->letra ?? '';
                $pv = str_pad($cf->punto_venta_numero ?? 0, 4, '0', STR_PAD_LEFT);
                $num = str_pad($cf->numero_comprobante ?? 0, 8, '0', STR_PAD_LEFT);
                $montoFormateado = '$' . number_format($cf->total, 2, ',', '.');
                $comprobantes[] = "{$tipoAbrev} {$letra} {$pv}-{$num} ({$montoFormateado})";
            }
        }

        $descripcionComprobantes = implode(' | ', $comprobantes);

        // 1. Registrar el total de la venta como DEBE
        $movimientos[] = \App\Models\MovimientoCuentaCorriente::create([
            'cliente_id' => $venta->cliente_id,
            'sucursal_id' => $venta->sucursal_id,
            'fecha' => $venta->fecha,
            'tipo' => \App\Models\MovimientoCuentaCorriente::TIPO_VENTA,
            'debe' => $venta->total_final,
            'haber' => 0,
            'saldo_favor_debe' => 0,
            'saldo_favor_haber' => 0,
            'documento_tipo' => \App\Models\MovimientoCuentaCorriente::DOC_VENTA,
            'documento_id' => $venta->id,
            'venta_id' => $venta->id,
            'venta_pago_id' => null,
            'cobro_id' => null,
            'concepto' => "Venta #{$venta->id}",
            'descripcion_comprobantes' => $descripcionComprobantes,
            'usuario_id' => $usuarioId,
        ]);

        // 2. Registrar cada pago como HABER
        $pagos = $venta->pagos()->with('formaPago')->get();

        foreach ($pagos as $ventaPago) {
            $nombreFormaPago = $ventaPago->formaPago?->nombre ?? 'Pago';

            if ($ventaPago->es_cuenta_corriente) {
                // Pago en cuenta corriente: actualizar saldo_pendiente pero NO crear haber
                // (queda como deuda pendiente)
                $ventaPago->update([
                    'saldo_pendiente' => $ventaPago->monto_final,
                ]);
            } else {
                // Pago inmediato (efectivo, tarjeta, etc.): crear HABER que cancela parte de la deuda
                $movimientos[] = \App\Models\MovimientoCuentaCorriente::create([
                    'cliente_id' => $venta->cliente_id,
                    'sucursal_id' => $venta->sucursal_id,
                    'fecha' => $venta->fecha,
                    'tipo' => \App\Models\MovimientoCuentaCorriente::TIPO_COBRO,
                    'debe' => 0,
                    'haber' => $ventaPago->monto_final,
                    'saldo_favor_debe' => 0,
                    'saldo_favor_haber' => 0,
                    'documento_tipo' => \App\Models\MovimientoCuentaCorriente::DOC_VENTA_PAGO,
                    'documento_id' => $ventaPago->id,
                    'venta_id' => $venta->id,
                    'venta_pago_id' => $ventaPago->id,
                    'cobro_id' => null,
                    'concepto' => "Pago {$nombreFormaPago} (Venta #{$venta->id})",
                    'descripcion_comprobantes' => $descripcionComprobantes,
                    'usuario_id' => $usuarioId,
                ]);
            }
        }

        // 3. Actualizar cache del cliente
        $ccService->actualizarCacheCliente($venta->cliente_id, $venta->sucursal_id);

        return $movimientos;
    }

    /**
     * Revierte el stock por cancelación de venta
     *
     * @param Venta $venta
     */
    protected function revertirStockPorVenta(Venta $venta): void
    {
        $usuarioId = Auth::id() ?? $venta->usuario_id;

        foreach ($venta->detalles as $detalle) {
            $articulo = $detalle->articulo;

            $modoStock = $articulo->getModoStock($venta->sucursal_id);
            if ($modoStock === 'ninguno') {
                continue;
            }

            if ($modoStock === 'receta') {
                $receta = $articulo->resolverReceta($venta->sucursal_id);
                if ($receta) {
                    $this->revertirStockPorReceta(
                        $receta, $detalle->cantidad, $venta->sucursal_id,
                        $venta->id, $detalle->id,
                        "Anulación Venta #{$venta->id} - Receta {$articulo->nombre}",
                        $usuarioId
                    );
                }
                // Revertir stock de opcionales con receta
                $this->revertirStockOpcionalesDetalle($detalle, $venta, $usuarioId);
                continue;
            }

            // modo 'unitario': revertir stock del artículo directamente
            $stock = Stock::where('sucursal_id', $venta->sucursal_id)
                         ->where('articulo_id', $detalle->articulo_id)
                         ->first();

            if ($stock) {
                $stock->aumentar($detalle->cantidad);

                // Registrar movimiento de anulación
                MovimientoStock::crearMovimientoAnulacionVenta(
                    $detalle->articulo_id,
                    $venta->sucursal_id,
                    $detalle->cantidad,
                    $venta->id,
                    $detalle->id,
                    "Anulación Venta #{$venta->id}",
                    $usuarioId
                );
            }

            // Revertir stock de opcionales con receta
            $this->revertirStockOpcionalesDetalle($detalle, $venta, $usuarioId);
        }
    }

    /**
     * Revierte stock de ingredientes según una receta (anulación)
     */
    protected function revertirStockPorReceta(
        Receta $receta, float $cantidadVendida, int $sucursalId,
        int $ventaId, int $ventaDetalleId, string $conceptoBase, int $usuarioId
    ): void {
        foreach ($receta->ingredientes as $ingrediente) {
            $cantidadRevertir = $ingrediente->cantidad * $cantidadVendida / $receta->cantidad_producida;

            $stock = Stock::where('sucursal_id', $sucursalId)
                         ->where('articulo_id', $ingrediente->articulo_id)
                         ->first();

            if ($stock) {
                $stock->aumentar($cantidadRevertir);

                MovimientoStock::crearMovimientoAnulacionVenta(
                    $ingrediente->articulo_id, $sucursalId, $cantidadRevertir,
                    $ventaId, $ventaDetalleId,
                    $conceptoBase, $usuarioId
                );
            }
        }
    }

    /**
     * Revierte stock de opcionales con receta para un detalle de venta (anulación)
     */
    protected function revertirStockOpcionalesDetalle(VentaDetalle $detalle, Venta $venta, int $usuarioId): void
    {
        $opcionalesDetalle = DB::connection('pymes_tenant')
            ->table('venta_detalle_opcionales')
            ->where('venta_detalle_id', $detalle->id)
            ->get();

        foreach ($opcionalesDetalle as $opcDet) {
            $recetaOpc = Receta::resolver('Opcional', $opcDet->opcional_id, $venta->sucursal_id);
            if ($recetaOpc) {
                $this->revertirStockPorReceta(
                    $recetaOpc, $opcDet->cantidad * $detalle->cantidad,
                    $venta->sucursal_id, $venta->id, $detalle->id,
                    "Anulación Venta #{$venta->id} - Opcional {$opcDet->nombre_opcional}",
                    $usuarioId
                );
            }
        }
    }
}

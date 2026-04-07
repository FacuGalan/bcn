<?php

namespace App\Services;

use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\MovimientoPunto;
use App\Models\Venta;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CuponService
{
    /**
     * Crea un cupón desde puntos del cliente.
     * Descuenta los puntos al momento de crear (no al usar).
     */
    public function crearCuponDesdePuntos(int $clienteId, array $data, int $usuarioId): Cupon
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($clienteId, $data, $usuarioId) {
            $puntosService = new PuntosService;
            $config = $puntosService->getConfiguracion();

            if (! $config || ! $config->activo) {
                throw new Exception('El programa de puntos no está activo');
            }

            $puntosNecesarios = (int) ($data['puntos_consumidos'] ?? 0);
            if ($puntosNecesarios <= 0) {
                throw new Exception('Debe especificar la cantidad de puntos para el cupón');
            }

            $saldo = $puntosService->obtenerSaldo($clienteId);
            if ($puntosNecesarios > $saldo) {
                throw new Exception("Puntos insuficientes. Disponibles: {$saldo}, Necesarios: {$puntosNecesarios}");
            }

            $cupon = Cupon::create([
                'codigo' => $data['codigo'] ?? $this->generarCodigo(),
                'tipo' => 'puntos',
                'cliente_id' => $clienteId,
                'descripcion' => $data['descripcion'] ?? null,
                'modo_descuento' => $data['modo_descuento'],
                'valor_descuento' => $data['valor_descuento'],
                'aplica_a' => $data['aplica_a'] ?? 'total',
                'uso_maximo' => $data['uso_maximo'] ?? 1,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'puntos_consumidos' => $puntosNecesarios,
                'created_by_usuario_id' => $usuarioId,
            ]);

            // Vincular artículos si aplica a artículos específicos
            if ($cupon->aplicaAArticulos()) {
                $this->attachArticulosConCantidad($cupon, $data);
            }

            // Vincular formas de pago permitidas
            if (! empty($data['forma_pago_ids'])) {
                $cupon->formasPago()->attach($data['forma_pago_ids']);
            }

            // Descontar puntos (crea movimiento negativo en ledger)
            $sucursalId = $data['sucursal_id'] ?? 1;
            MovimientoPunto::crearMovimientoCanjeCupon(
                $clienteId,
                $sucursalId,
                $puntosNecesarios,
                $cupon->id,
                $usuarioId
            );

            // Actualizar cache del cliente
            $puntosService->actualizarCacheCliente($clienteId);

            Log::info('Cupón creado desde puntos', [
                'cupon_id' => $cupon->id,
                'codigo' => $cupon->codigo,
                'cliente_id' => $clienteId,
                'puntos_consumidos' => $puntosNecesarios,
            ]);

            return $cupon;
        });
    }

    /**
     * Crea un cupón promocional (sin puntos, sin cliente).
     */
    public function crearCuponPromocional(array $data, int $usuarioId): Cupon
    {
        return DB::connection('pymes_tenant')->transaction(function () use ($data, $usuarioId) {
            $cupon = Cupon::create([
                'codigo' => $data['codigo'] ?? $this->generarCodigo(),
                'tipo' => 'promocional',
                'cliente_id' => null,
                'descripcion' => $data['descripcion'] ?? null,
                'modo_descuento' => $data['modo_descuento'],
                'valor_descuento' => $data['valor_descuento'],
                'aplica_a' => $data['aplica_a'] ?? 'total',
                'uso_maximo' => $data['uso_maximo'] ?? 1,
                'fecha_vencimiento' => $data['fecha_vencimiento'] ?? null,
                'puntos_consumidos' => 0,
                'created_by_usuario_id' => $usuarioId,
            ]);

            // Vincular artículos si aplica a artículos específicos
            if ($cupon->aplicaAArticulos()) {
                $this->attachArticulosConCantidad($cupon, $data);
            }

            // Vincular formas de pago permitidas
            if (! empty($data['forma_pago_ids'])) {
                $cupon->formasPago()->attach($data['forma_pago_ids']);
            }

            Log::info('Cupón promocional creado', [
                'cupon_id' => $cupon->id,
                'codigo' => $cupon->codigo,
                'modo_descuento' => $cupon->modo_descuento,
                'valor_descuento' => $cupon->valor_descuento,
            ]);

            return $cupon;
        });
    }

    /**
     * Valida un cupón para su uso en una venta.
     * Retorna array con estado, cupón y mensaje.
     */
    public function validarCupon(string $codigo, ?int $clienteId = null): array
    {
        $cupon = Cupon::porCodigo($codigo)->first();

        if (! $cupon) {
            return ['valid' => false, 'cupon' => null, 'message' => __('Cupón inválido')];
        }

        if (! $cupon->estaVigente()) {
            if ($cupon->fecha_vencimiento && $cupon->fecha_vencimiento->lt(now()->startOfDay())) {
                return ['valid' => false, 'cupon' => $cupon, 'message' => __('Cupón expirado')];
            }

            return ['valid' => false, 'cupon' => $cupon, 'message' => __('Cupón inactivo')];
        }

        if (! $cupon->tieneUsosDisponibles()) {
            return ['valid' => false, 'cupon' => $cupon, 'message' => __('Uso máximo alcanzado')];
        }

        if (! $cupon->puedeSerUsadoPor($clienteId)) {
            return ['valid' => false, 'cupon' => $cupon, 'message' => __('Este cupón pertenece a otro cliente')];
        }

        return ['valid' => true, 'cupon' => $cupon, 'message' => __('Cupón válido')];
    }

    /**
     * Calcula el descuento que aplicaría un cupón en una venta.
     *
     * @param  array  $itemsCarrito  Opcional: items del carrito con ['articulo_id' => int, 'precio' => float, 'cantidad' => int]
     */
    public function calcularDescuento(Cupon $cupon, float $totalVenta, array $articuloIdsEnCarrito = [], array $itemsCarrito = []): array
    {
        $montoDescuento = 0;
        $articulosBonificados = [];

        if ($cupon->aplicaATotal()) {
            if ($cupon->esPorcentaje()) {
                $montoDescuento = $totalVenta * ((float) $cupon->valor_descuento / 100);
            } else {
                $montoDescuento = (float) $cupon->valor_descuento;
            }
            $montoDescuento = min($montoDescuento, $totalVenta);
        } elseif ($cupon->aplicaAArticulos()) {
            $articulosCupon = $cupon->articulos()->get()->keyBy('id');
            $idsEnCupon = $articulosCupon->keys()->toArray();
            $articulosBonificados = array_intersect($idsEnCupon, $articuloIdsEnCarrito);

            // Si tenemos items con precios, calcular con límite de cantidad
            if (! empty($itemsCarrito)) {
                $montoDescuento = $this->calcularDescuentoPorArticulos($cupon, $articulosCupon, $itemsCarrito);
                $montoDescuento = min($montoDescuento, $totalVenta);
            }
        }

        return [
            'monto_descuento' => round($montoDescuento, 2),
            'articulos_bonificados' => $articulosBonificados,
        ];
    }

    /**
     * Calcula el descuento para artículos específicos respetando cantidad por artículo.
     */
    private function calcularDescuentoPorArticulos(Cupon $cupon, $articulosCupon, array $itemsCarrito): float
    {
        $montoElegible = 0;

        foreach ($itemsCarrito as $item) {
            $articuloId = (int) ($item['articulo_id'] ?? 0);

            if (! $articulosCupon->has($articuloId)) {
                continue;
            }

            $pivotCantidad = $articulosCupon->get($articuloId)->pivot->cantidad;
            $cantidadEnCarrito = (int) ($item['cantidad'] ?? 1);
            $precioUnitario = (float) ($item['precio'] ?? 0);

            // Si pivot cantidad es null → aplica a todas las unidades
            $cantidadElegible = $pivotCantidad !== null
                ? min($cantidadEnCarrito, $pivotCantidad)
                : $cantidadEnCarrito;

            $montoElegible += $precioUnitario * $cantidadElegible;
        }

        if ($cupon->esPorcentaje()) {
            return $montoElegible * ((float) $cupon->valor_descuento / 100);
        }

        // Monto fijo: el descuento es el valor fijo, limitado al monto elegible
        return min((float) $cupon->valor_descuento, $montoElegible);
    }

    /**
     * Registra el uso de un cupón en una venta.
     * Se llama desde VentaService dentro de su transacción.
     */
    public function aplicarCuponEnVenta(
        Cupon $cupon,
        Venta $venta,
        float $montoDescontado,
        int $usuarioId
    ): CuponUso {
        // Revalidar en el momento de aplicar
        if (! $cupon->tieneUsosDisponibles()) {
            throw new Exception('El cupón ya alcanzó su uso máximo');
        }

        $uso = CuponUso::create([
            'cupon_id' => $cupon->id,
            'venta_id' => $venta->id,
            'cliente_id' => $venta->cliente_id,
            'sucursal_id' => $venta->sucursal_id,
            'monto_descontado' => $montoDescontado,
            'fecha' => now(),
            'usuario_id' => $usuarioId,
            'created_at' => now(),
        ]);

        // Incrementar contador de uso
        $cupon->increment('uso_actual');

        Log::info('Cupón aplicado en venta', [
            'cupon_id' => $cupon->id,
            'codigo' => $cupon->codigo,
            'venta_id' => $venta->id,
            'monto_descontado' => $montoDescontado,
        ]);

        return $uso;
    }

    /**
     * Revierte el uso de un cupón al anular una venta.
     * Decrementa uso_actual para que el cupón pueda usarse de nuevo.
     */
    public function revertirUsoCupon(CuponUso $uso): void
    {
        $cupon = $uso->cupon;

        if ($cupon && $cupon->uso_actual > 0) {
            $cupon->decrement('uso_actual');
        }

        Log::info('Uso de cupón revertido por anulación', [
            'cupon_uso_id' => $uso->id,
            'cupon_id' => $uso->cupon_id,
            'venta_id' => $uso->venta_id,
        ]);
    }

    /**
     * Valida que las formas de pago de la venta sean compatibles con el cupón.
     */
    public function validarFormasPagoCupon(Cupon $cupon, array $formaPagoIds): array
    {
        if (! $cupon->tieneRestriccionFormasPago()) {
            return ['valid' => true, 'message' => '', 'formas_pago_permitidas' => []];
        }

        $permitidas = $cupon->formasPago()->pluck('nombre', 'formas_pago.id')->toArray();

        if ($cupon->aceptaTodasFormasPago($formaPagoIds)) {
            return ['valid' => true, 'message' => '', 'formas_pago_permitidas' => $permitidas];
        }

        $nombresPermitidas = implode(', ', $permitidas);

        return [
            'valid' => false,
            'message' => __('El cupón :code solo es válido para: :methods', [
                'code' => $cupon->codigo,
                'methods' => $nombresPermitidas,
            ]),
            'formas_pago_permitidas' => $permitidas,
        ];
    }

    /**
     * Genera un código único para cupón con formato CUP-XXXXXX.
     */
    public function generarCodigo(): string
    {
        do {
            $codigo = 'CUP-'.strtoupper(Str::random(6));
        } while (Cupon::where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Vincula artículos al cupón con cantidad opcional desde el array de data.
     */
    private function attachArticulosConCantidad(Cupon $cupon, array $data): void
    {
        // Formato nuevo: articulo_cantidades => [id => cantidad|null]
        if (! empty($data['articulo_cantidades'])) {
            foreach ($data['articulo_cantidades'] as $articuloId => $cantidad) {
                $cupon->articulos()->attach($articuloId, [
                    'cantidad' => $cantidad ?: null,
                ]);
            }

            return;
        }

        // Formato legacy: articulo_ids => [id, id, ...]
        if (! empty($data['articulo_ids'])) {
            $cupon->articulos()->attach($data['articulo_ids']);
        }
    }
}

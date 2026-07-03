<?php

namespace App\Http\Resources;

use App\Models\PedidoDelivery;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación API v1 de un pedido delivery (RF-11).
 *
 * La misma forma se usa para integradores y para el seguimiento público (el
 * público recibe una vista recortada — ver SeguimientoResource).
 */
class PedidoDeliveryResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var PedidoDelivery $pedido */
        $pedido = $this->resource;

        return [
            'id' => (int) $pedido->id,
            'numero' => $pedido->numero,
            'numero_display' => $pedido->numero_display,
            'tipo' => $pedido->tipo,
            'origen' => $pedido->origen,
            'origen_referencia' => $pedido->origen_referencia,
            'estado' => $pedido->estado_pedido,
            'estado_label' => $pedido->estado_label,
            'estado_pago' => $pedido->estado_pago,
            'por_aceptar' => $pedido->estado_pedido === PedidoDelivery::ESTADO_BORRADOR
                && $pedido->origen !== PedidoDelivery::ORIGEN_PANEL,
            'cliente' => [
                'nombre' => $pedido->nombre_cliente_final,
                'telefono' => $pedido->telefono_cliente_final,
                'email' => $pedido->email_cliente_final,
            ],
            'entrega' => $pedido->tipo === PedidoDelivery::TIPO_DELIVERY ? [
                'direccion' => $pedido->direccion_entrega,
                'referencia' => $pedido->direccion_referencia,
                'latitud' => $pedido->latitud !== null ? (float) $pedido->latitud : null,
                'longitud' => $pedido->longitud !== null ? (float) $pedido->longitud : null,
                'zona' => $pedido->zona?->nombre,
                'costo_envio' => (float) $pedido->costo_envio,
                'distancia_km' => $pedido->distancia_km !== null ? (float) $pedido->distancia_km : null,
                'repartidor' => $pedido->repartidor?->nombre,
            ] : null,
            'totales' => [
                'subtotal' => (float) $pedido->subtotal,
                'iva' => (float) $pedido->iva,
                'descuento' => (float) $pedido->descuento,
                'total' => (float) $pedido->total,
                'total_final' => (float) $pedido->total_final,
                'monto_cupon' => (float) $pedido->monto_cupon,
            ],
            'items' => $pedido->relationLoaded('detalles')
                ? $pedido->detalles->map(fn ($d) => [
                    'articulo_id' => $d->articulo_id,
                    'descripcion' => $d->articulo?->nombre ?? $d->concepto_descripcion,
                    'es_costo_envio' => (bool) $d->es_costo_envio,
                    'cantidad' => (float) $d->cantidad,
                    'precio_unitario' => (float) $d->precio_unitario,
                    'total' => (float) $d->total,
                    'opcionales' => $d->relationLoaded('opcionales')
                        ? $d->opcionales->map(fn ($o) => [
                            'descripcion' => $o->descripcion,
                            'precio' => (float) $o->precio,
                            'cantidad' => (float) $o->cantidad,
                        ])->values()
                        : [],
                ])->values()
                : [],
            'hora_pactada_at' => $pedido->hora_pactada_at?->toIso8601String(),
            'token_seguimiento' => $pedido->token_seguimiento,
            'observaciones' => $pedido->observaciones,
            'fecha' => $pedido->fecha?->toIso8601String(),
            'timestamps' => [
                'confirmado_at' => $pedido->confirmado_at?->toIso8601String(),
                'en_preparacion_at' => $pedido->en_preparacion_at?->toIso8601String(),
                'listo_at' => $pedido->listo_at?->toIso8601String(),
                'en_camino_at' => $pedido->en_camino_at?->toIso8601String(),
                'entregado_at' => $pedido->entregado_at?->toIso8601String(),
                'cancelado_at' => $pedido->cancelado_at?->toIso8601String(),
            ],
        ];
    }
}

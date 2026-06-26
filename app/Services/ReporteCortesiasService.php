<?php

namespace App\Services;

use App\Models\User;
use App\Models\VentaDetalle;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service de solo lectura: Reporte de Cortesías (invitaciones) sobre ventas.
 *
 * Fuente canónica: renglones de venta con `es_invitacion = true`. El monto
 * regalado por línea está cacheado en `ventas_detalle.monto_invitado`
 * (= cantidad × precio_unitario_original). Sumar ese campo da el total
 * invitado sin recomputar precios.
 *
 * Se filtra siempre por sucursal + rango de fechas de la VENTA y se excluyen
 * las ventas anuladas (`anulado_at` no nulo). Toda venta (POS directa o por
 * conversión de pedido) queda registrada en `ventas`, por lo que esta única
 * fuente evita duplicar cortesías de pedidos ya convertidos.
 *
 * Es un service de reporte: no muta datos, por eso no abre transacciones.
 */
class ReporteCortesiasService
{
    /**
     * Genera el reporte completo de cortesías para una sucursal y período.
     *
     * @return array{kpis: array, por_usuario: array, por_articulo: array, detalle: array}
     */
    public function generar(int $sucursalId, Carbon $desde, Carbon $hasta): array
    {
        $renglones = $this->obtenerRenglonesInvitados($sucursalId, $desde, $hasta);

        return [
            'kpis' => $this->calcularKpis($renglones),
            'por_usuario' => $this->agruparPorUsuario($renglones),
            'por_articulo' => $this->agruparPorArticulo($renglones),
            'detalle' => $this->armarDetalle($renglones),
        ];
    }

    /**
     * Trae todos los renglones invitados del período con su venta y artículo,
     * ordenados por fecha de la venta. Es la base de todos los desgloses.
     */
    private function obtenerRenglonesInvitados(int $sucursalId, Carbon $desde, Carbon $hasta): Collection
    {
        return VentaDetalle::query()
            ->where('es_invitacion', true)
            ->with([
                'venta:id,numero,fecha,sucursal_id,anulado_at',
                'articulo:id,nombre',
            ])
            ->whereHas('venta', function ($q) use ($sucursalId, $desde, $hasta) {
                $q->where('sucursal_id', $sucursalId)
                    ->whereBetween('fecha', [$desde, $hasta])
                    ->whereNull('anulado_at');
            })
            ->get()
            ->sortBy(fn (VentaDetalle $d) => $d->venta?->fecha)
            ->values();
    }

    /**
     * KPIs de cabecera: monto total regalado, renglones y comprobantes únicos.
     */
    private function calcularKpis(Collection $renglones): array
    {
        return [
            'monto_total' => (float) $renglones->sum(fn (VentaDetalle $d) => (float) $d->monto_invitado),
            'cantidad_renglones' => $renglones->count(),
            'cantidad_comprobantes' => $renglones->pluck('venta_id')->unique()->count(),
            'cantidad_articulos' => (float) $renglones->sum(fn (VentaDetalle $d) => (float) $d->cantidad),
        ];
    }

    /**
     * Desglose por usuario que registró la invitación, ordenado por monto desc.
     */
    private function agruparPorUsuario(Collection $renglones): array
    {
        $nombres = $this->resolverNombresUsuarios(
            $renglones->pluck('invitado_por_usuario_id')->filter()->unique()
        );

        return $renglones
            ->groupBy('invitado_por_usuario_id')
            ->map(function (Collection $grupo, $usuarioId) use ($nombres) {
                return [
                    'usuario_id' => $usuarioId ?: null,
                    'usuario' => $usuarioId ? ($nombres[$usuarioId] ?? __('Usuario eliminado')) : __('Sin usuario'),
                    'monto' => (float) $grupo->sum(fn (VentaDetalle $d) => (float) $d->monto_invitado),
                    'renglones' => $grupo->count(),
                    'comprobantes' => $grupo->pluck('venta_id')->unique()->count(),
                ];
            })
            ->sortByDesc('monto')
            ->values()
            ->toArray();
    }

    /**
     * Desglose por artículo invitado, ordenado por monto desc.
     * Los conceptos libres (sin artículo) se agrupan bajo una etiqueta común.
     */
    private function agruparPorArticulo(Collection $renglones): array
    {
        return $renglones
            ->groupBy('articulo_id')
            ->map(function (Collection $grupo, $articuloId) {
                $primero = $grupo->first();

                return [
                    'articulo_id' => $articuloId ?: null,
                    'articulo' => $articuloId
                        ? ($primero->articulo?->nombre ?? __('Artículo eliminado'))
                        : __('Concepto libre'),
                    'cantidad' => (float) $grupo->sum(fn (VentaDetalle $d) => (float) $d->cantidad),
                    'monto' => (float) $grupo->sum(fn (VentaDetalle $d) => (float) $d->monto_invitado),
                    'renglones' => $grupo->count(),
                ];
            })
            ->sortByDesc('monto')
            ->values()
            ->toArray();
    }

    /**
     * Listado detallado renglón por renglón (fecha, comprobante, artículo,
     * cantidad, monto, motivo, usuario) para revisión caso por caso.
     */
    private function armarDetalle(Collection $renglones): array
    {
        $nombres = $this->resolverNombresUsuarios(
            $renglones->pluck('invitado_por_usuario_id')->filter()->unique()
        );

        return $renglones->map(function (VentaDetalle $d) use ($nombres) {
            return [
                'fecha' => $d->venta?->fecha?->format('d/m/Y H:i'),
                'comprobante' => $d->venta?->numero,
                'articulo' => $d->obtenerNombre(),
                'cantidad' => (float) $d->cantidad,
                'monto' => (float) $d->monto_invitado,
                'motivo' => $d->invitacion_motivo,
                'usuario' => $d->invitado_por_usuario_id
                    ? ($nombres[$d->invitado_por_usuario_id] ?? __('Usuario eliminado'))
                    : __('Sin usuario'),
            ];
        })->toArray();
    }

    /**
     * Resuelve id => name de usuarios en una sola consulta a la BD config.
     */
    private function resolverNombresUsuarios(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->pluck('name', 'id');
    }
}

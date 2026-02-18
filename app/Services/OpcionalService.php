<?php

namespace App\Services;

use App\Models\ArticuloGrupoOpcional;
use App\Models\ArticuloGrupoOpcionalOpcion;
use App\Models\GrupoOpcional;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;

class OpcionalService
{
    /**
     * Asigna un grupo opcional a un artículo en TODAS las sucursales activas.
     * Crea filas en articulo_grupo_opcional y articulo_grupo_opcional_opcion
     * con los valores default del catálogo global.
     *
     * @return int Cantidad de sucursales donde se creó la asignación
     */
    public function asignarGrupoAArticulo(int $articuloId, int $grupoId): int
    {
        $grupo = GrupoOpcional::with(['opcionales' => fn($q) => $q->where('activo', true)->orderBy('orden')])->findOrFail($grupoId);
        $sucursales = Sucursal::activas()->pluck('id');

        $count = 0;

        DB::connection('pymes_tenant')->transaction(function () use ($articuloId, $grupo, $sucursales, &$count) {
            // Calcular siguiente orden para este artículo (máximo actual + 1)
            $maxOrden = ArticuloGrupoOpcional::where('articulo_id', $articuloId)
                ->max('orden') ?? 0;
            $nuevoOrden = $maxOrden + 1;

            foreach ($sucursales as $sucursalId) {
                // Verificar que no exista ya
                $exists = ArticuloGrupoOpcional::where('articulo_id', $articuloId)
                    ->where('grupo_opcional_id', $grupo->id)
                    ->where('sucursal_id', $sucursalId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                // Crear asignación del grupo
                $asignacion = ArticuloGrupoOpcional::create([
                    'articulo_id' => $articuloId,
                    'grupo_opcional_id' => $grupo->id,
                    'sucursal_id' => $sucursalId,
                    'activo' => true,
                    'orden' => $nuevoOrden,
                ]);

                // Crear opciones con valores default
                foreach ($grupo->opcionales as $opcional) {
                    ArticuloGrupoOpcionalOpcion::create([
                        'articulo_grupo_opcional_id' => $asignacion->id,
                        'opcional_id' => $opcional->id,
                        'precio_extra' => $opcional->precio_extra,
                        'activo' => true,
                        'disponible' => true,
                        'orden' => $opcional->orden,
                    ]);
                }

                $count++;
            }
        });

        return $count;
    }

    /**
     * Desasigna un grupo opcional de un artículo en TODAS las sucursales.
     * Elimina las filas de articulo_grupo_opcional (cascade elimina opciones).
     */
    public function desasignarGrupoDeArticulo(int $articuloId, int $grupoId): void
    {
        DB::connection('pymes_tenant')->transaction(function () use ($articuloId, $grupoId) {
            $asignaciones = ArticuloGrupoOpcional::where('articulo_id', $articuloId)
                ->where('grupo_opcional_id', $grupoId)
                ->get();

            foreach ($asignaciones as $asignacion) {
                $asignacion->opciones()->delete();
                $asignacion->delete();
            }
        });
    }

    /**
     * Marca un opcional como agotado en una sucursal (disponible=false
     * en TODAS las asignaciones de esa sucursal).
     */
    public function marcarAgotado(int $opcionalId, int $sucursalId): void
    {
        ArticuloGrupoOpcionalOpcion::where('opcional_id', $opcionalId)
            ->whereHas('articuloGrupoOpcional', fn($q) => $q->where('sucursal_id', $sucursalId))
            ->update(['disponible' => false]);
    }

    /**
     * Marca un opcional como disponible en una sucursal.
     */
    public function marcarDisponible(int $opcionalId, int $sucursalId): void
    {
        ArticuloGrupoOpcionalOpcion::where('opcional_id', $opcionalId)
            ->whereHas('articuloGrupoOpcional', fn($q) => $q->where('sucursal_id', $sucursalId))
            ->update(['disponible' => true]);
    }

    /**
     * Query optimizada para obtener todos los opcionales de un artículo
     * en una sucursal, listos para la venta.
     */
    public function obtenerOpcionalesParaVenta(int $articuloId, int $sucursalId): array
    {
        $asignaciones = ArticuloGrupoOpcional::with([
                'grupoOpcional',
                // Incluir activas (disponibles + agotadas) pero NO inactivas
                'opciones' => fn($q) => $q->where('activo', true)
                    ->whereHas('opcional', fn($q2) => $q2->where('activo', true))
                    ->with('opcional')
                    ->orderBy('orden'),
            ])
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $sucursalId)
            ->where('activo', true)
            ->whereHas('grupoOpcional', fn($q) => $q->where('activo', true))
            ->orderBy('orden')
            ->get();

        return $asignaciones->map(function ($asig) {
            $grupo = $asig->grupoOpcional;
            return [
                'grupo_id' => $grupo->id,
                'nombre' => $grupo->nombre,
                'tipo' => $grupo->tipo,
                'obligatorio' => $grupo->obligatorio,
                'min_seleccion' => $grupo->min_seleccion,
                'max_seleccion' => $grupo->max_seleccion,
                'opciones' => $asig->opciones->map(fn($op) => [
                    'opcional_id' => $op->opcional_id,
                    'nombre' => $op->opcional->nombre,
                    'precio_extra' => $op->precio_extra,
                    'disponible' => $op->disponible,
                ])->toArray(),
            ];
        })->toArray();
    }
}

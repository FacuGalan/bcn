<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use App\Models\ConsumidorDireccion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Direcciones guardadas del consumidor (RF-T2, spec tienda-online).
 *
 * CRUD sobre config.consumidor_direcciones. El checkout de la tienda las
 * precarga; el pedido sigue copiando SNAPSHOT (el alta de pedidos no
 * referencia estas filas — nada cambia ahí).
 */
class DireccionesController extends Controller
{
    /** Tope de direcciones guardadas por consumidor. */
    public const MAX_DIRECCIONES = 10;

    /**
     * GET /v1/consumidores/direcciones — default primero.
     */
    public function index(Request $request): JsonResponse
    {
        $direcciones = $request->user()->direcciones()
            ->with('localidad:id,nombre')
            ->orderByDesc('es_default')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $direcciones->map(fn ($d) => $this->payload($d))->all(),
        ]);
    }

    /**
     * POST /v1/consumidores/direcciones — la primera dirección queda como
     * default automáticamente.
     */
    public function store(Request $request): JsonResponse
    {
        $consumidor = $request->user();
        $datos = $this->validar($request);

        if ($consumidor->direcciones()->count() >= self::MAX_DIRECCIONES) {
            throw new \Exception(__('Alcanzaste el máximo de :max direcciones guardadas', ['max' => self::MAX_DIRECCIONES]));
        }

        $esDefault = ($datos['es_default'] ?? false) || $consumidor->direcciones()->doesntExist();

        if ($esDefault) {
            $consumidor->direcciones()->update(['es_default' => false]);
        }

        $direccion = $consumidor->direcciones()->create([...$datos, 'es_default' => $esDefault]);

        return response()->json(['data' => $this->payload($direccion->load('localidad:id,nombre'))], 201);
    }

    /**
     * PATCH /v1/consumidores/direcciones/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $consumidor = $request->user();
        $direccion = $consumidor->direcciones()->findOrFail($id);

        $datos = $this->validar($request, parcial: true);

        if (($datos['es_default'] ?? false) && ! $direccion->es_default) {
            $consumidor->direcciones()->update(['es_default' => false]);
        }

        // No permitir des-defaultear la única default (siempre queda una).
        if (array_key_exists('es_default', $datos) && ! $datos['es_default'] && $direccion->es_default) {
            unset($datos['es_default']);
        }

        $direccion->update($datos);

        return response()->json(['data' => $this->payload($direccion->fresh()->load('localidad:id,nombre'))]);
    }

    /**
     * DELETE /v1/consumidores/direcciones/{id} — si se borra la default, la
     * más nueva de las restantes pasa a default.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $consumidor = $request->user();
        $direccion = $consumidor->direcciones()->findOrFail($id);

        $eraDefault = $direccion->es_default;
        $direccion->delete();

        if ($eraDefault) {
            $consumidor->direcciones()->orderByDesc('id')->first()?->update(['es_default' => true]);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    protected function validar(Request $request, bool $parcial = false): array
    {
        $req = $parcial ? 'sometimes' : 'required';

        return $request->validate([
            'alias' => 'nullable|string|max:50',
            'direccion' => "{$req}|string|min:3|max:255",
            'referencia' => 'nullable|string|max:255',
            'localidad_id' => 'nullable|integer|exists:config.localidades,id',
            'latitud' => 'nullable|numeric|between:-90,90',
            'longitud' => 'nullable|numeric|between:-180,180',
            'es_default' => 'sometimes|boolean',
        ]);
    }

    protected function payload(ConsumidorDireccion $direccion): array
    {
        return [
            'id' => $direccion->id,
            'alias' => $direccion->alias,
            'direccion' => $direccion->direccion,
            'referencia' => $direccion->referencia,
            'localidad_id' => $direccion->localidad_id,
            'localidad' => $direccion->localidad?->nombre,
            'latitud' => $direccion->latitud !== null ? (float) $direccion->latitud : null,
            'longitud' => $direccion->longitud !== null ? (float) $direccion->longitud : null,
            'es_default' => (bool) $direccion->es_default,
        ];
    }
}

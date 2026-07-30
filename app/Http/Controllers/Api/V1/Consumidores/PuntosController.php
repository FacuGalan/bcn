<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\Tienda;
use App\Services\Pedidos\PuntosTiendaService;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Saldos de puntos del consumidor CROSS-comercio (RF-T42, ronda cuenta
 * consumidor) — la vista "mis puntos" de /mi-cuenta.
 *
 * Fan-out CONTROLADO (patrón del historial RF-T3): solo los comercios donde
 * el consumidor ya tiene cliente mapeado (consumidor_comercio) — sin
 * mapping no hay ledger posible. Un tenant caído se saltea con log, no
 * voltea la respuesta. Solo-lectura: nunca crea clientes (D11).
 */
class PuntosController extends Controller
{
    /**
     * GET /v1/consumidores/puntos — por cada tienda de un comercio con
     * mapping: el estado del programa para ese cliente. Solo entradas con
     * programa ACTIVO (una tienda sin programa no es "0 puntos", es nada).
     */
    public function index(Request $request, PuntosTiendaService $puntosTienda, TenantService $tenants): JsonResponse
    {
        $consumidor = $request->user();

        $mappings = $consumidor->comercios()->pluck('cliente_id', 'comercio_id');

        if ($mappings->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $tiendas = Tienda::whereIn('comercio_id', $mappings->keys())->get();

        $saldos = [];
        foreach ($tiendas as $tienda) {
            try {
                $tenants->usarComercioParaProceso((int) $tienda->comercio_id);

                $clienteId = (int) $mappings->get($tienda->comercio_id);
                if (! $clienteId || ! Cliente::find($clienteId)) {
                    continue;
                }

                $sucursal = Sucursal::find($tienda->sucursal_id);
                if (! $sucursal) {
                    continue;
                }

                $info = $puntosTienda->info($sucursal, $clienteId);
                if (! ($info['activo'] ?? false)) {
                    continue;
                }

                $saldos[] = [
                    'tienda' => [
                        'slug' => $tienda->slug,
                        'nombre' => $sucursal->nombre,
                        'habilitada' => (bool) $tienda->habilitada,
                    ],
                    'saldo' => $info['saldo'],
                    'saldo_en_pesos' => $info['saldo_en_pesos'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Puntos del consumidor: comercio inaccesible', [
                    'consumidor_id' => $consumidor->id,
                    'comercio_id' => $tienda->comercio_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['data' => $saldos]);
    }
}

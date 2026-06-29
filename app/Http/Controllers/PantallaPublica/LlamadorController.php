<?php

namespace App\Http\Controllers\PantallaPublica;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Services\PantallaPublicaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Monitor llamador de pedidos (pantalla Clase B remota, sin sesión).
 *
 * Las rutas de página sirven un SHELL NEUTRO (sin datos sensibles): el JS lee el
 * token de localStorage (o lo bootstrapea desde la URL/QR o canjeando el código
 * corto) y carga la personalización + el snapshot vía el endpoint acotado al
 * token. El endpoint de snapshot usa el middleware `pantalla.token`.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02b, RF-03).
 */
class LlamadorController extends Controller
{
    /** Shell genérico (start_url de la PWA): el JS resuelve el token de localStorage. */
    public function index(): View
    {
        return view('pantallas.llamador', ['bootstrapToken' => null, 'bootstrapCodigo' => null]);
    }

    /** Entrada por QR: el token largo viene en la URL; el JS lo persiste. */
    public function porToken(string $token): View
    {
        return view('pantallas.llamador', ['bootstrapToken' => $token, 'bootstrapCodigo' => null]);
    }

    /** Entrada corta tipeable en TV: el JS canjea el código por el token. */
    public function porCodigo(string $codigo): View
    {
        return view('pantallas.llamador', ['bootstrapToken' => null, 'bootstrapCodigo' => $codigo]);
    }

    /**
     * Snapshot acotado al token (cold start + personalización). El middleware
     * `pantalla.token` ya resolvió la sucursal y configuró el tenant.
     */
    public function snapshot(Request $request, PantallaPublicaService $service): JsonResponse
    {
        /** @var Sucursal $sucursal */
        $sucursal = $request->attributes->get('pantalla_sucursal');
        $config = $sucursal->getConfigLlamador();

        return response()->json([
            'sucursal' => ['nombre' => $sucursal->nombrePantallaCliente()],
            'config' => $config,
            'logo' => ! empty($config['mostrar_logo']) ? $sucursal->logoPantallaClienteUrl() : null,
            'pedidos' => $service->pedidosParaLlamador($sucursal),
        ]);
    }
}

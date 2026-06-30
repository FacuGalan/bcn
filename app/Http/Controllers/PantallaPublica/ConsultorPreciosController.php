<?php

namespace App\Http\Controllers\PantallaPublica;

use App\Http\Controllers\Controller;
use App\Models\Sucursal;
use App\Services\PantallaPublicaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consultor de precios público (pantalla Clase B remota, sin sesión).
 *
 * Las rutas de página sirven un SHELL NEUTRO: el JS resuelve el token de
 * localStorage (o lo bootstrapea desde la URL/QR o canjeando el código corto) y
 * carga la personalización + busca contra el endpoint acotado al token.
 *
 * Los endpoints de datos (config + buscar) están gateados por
 * `usa_consultor_precios`: si la sucursal no activó la pantalla, responden 404
 * (los precios no quedan consultables salvo activación explícita — RF-08).
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02b, RF-05, RF-08).
 */
class ConsultorPreciosController extends Controller
{
    /** Shell genérico (start_url de la PWA): el JS resuelve el token de localStorage. */
    public function index(): View
    {
        return view('pantallas.consultor-precios', ['bootstrapToken' => null, 'bootstrapCodigo' => null]);
    }

    /** Entrada por QR: el token largo viene en la URL; el JS lo persiste. */
    public function porToken(string $token): View
    {
        return view('pantallas.consultor-precios', ['bootstrapToken' => $token, 'bootstrapCodigo' => null]);
    }

    /** Entrada corta tipeable en TV/tablet: el JS canjea el código por el token. */
    public function porCodigo(string $codigo): View
    {
        return view('pantallas.consultor-precios', ['bootstrapToken' => null, 'bootstrapCodigo' => $codigo]);
    }

    /** Personalización + nombre de sucursal (cold start). El middleware ya resolvió el tenant. */
    public function config(Request $request): JsonResponse
    {
        /** @var Sucursal $sucursal */
        $sucursal = $request->attributes->get('pantalla_sucursal');

        // No 404 si está apagado: el cliente necesita saberlo para mostrar el
        // cartel de "desactivado" (en vez de quedar colgado tras vincular). Los
        // PRECIOS siguen protegidos en buscar() (404). El nombre/branding de la
        // sucursal no es sensible.
        $activo = (bool) $sucursal->usa_consultor_precios;
        $config = $sucursal->getConfigConsultorPrecios();

        return response()->json([
            'activo' => $activo,
            'sucursal' => ['nombre' => $sucursal->nombrePantallaCliente()],
            'config' => $config,
            'logo' => $activo && ! empty($config['mostrar_logo']) ? $sucursal->logoPantallaClienteUrl() : null,
        ]);
    }

    /** Búsqueda de precios acotada al token. Payload mínimo (nombre, precio, unidad, promos). */
    public function buscar(Request $request, PantallaPublicaService $service): JsonResponse
    {
        /** @var Sucursal $sucursal */
        $sucursal = $request->attributes->get('pantalla_sucursal');

        abort_unless((bool) $sucursal->usa_consultor_precios, 404);

        $q = (string) $request->query('q', '');

        return response()->json([
            'resultados' => $service->buscarPreciosPublico($sucursal, $q),
        ]);
    }
}

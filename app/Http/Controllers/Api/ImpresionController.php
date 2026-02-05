<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ImpresionService;
use App\Models\Venta;
use App\Models\ComprobanteFiscal;
use App\Models\CierreTurno;
use App\Models\Cobro;
use App\Models\Impresora;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImpresionController extends Controller
{
    protected ImpresionService $impresionService;

    public function __construct(ImpresionService $impresionService)
    {
        $this->impresionService = $impresionService;
    }

    /**
     * Obtiene datos de impresión para ticket de venta
     */
    public function ticketVenta(int $ventaId): JsonResponse
    {
        try {
            $venta = Venta::findOrFail($ventaId);
            $datos = $this->impresionService->generarTicketVenta($venta);

            return response()->json($datos);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene datos de impresión para factura
     */
    public function factura(int $comprobanteId): JsonResponse
    {
        try {
            $comprobante = ComprobanteFiscal::findOrFail($comprobanteId);
            $datos = $this->impresionService->generarFactura($comprobante);

            return response()->json($datos);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera datos para prueba de impresión
     */
    public function prueba(int $impresoraId): JsonResponse
    {
        try {
            $impresora = Impresora::findOrFail($impresoraId);
            $datos = $this->impresionService->generarPrueba($impresora);

            return response()->json($datos);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista impresoras activas
     */
    public function listar(): JsonResponse
    {
        try {
            $impresoras = $this->impresionService->listarImpresoras();
            return response()->json($impresoras);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene datos de impresión para cierre de turno
     */
    public function cierreTurno(int $cierreId): JsonResponse
    {
        try {
            $cierre = CierreTurno::findOrFail($cierreId);
            $datos = $this->impresionService->generarCierreTurno($cierre);

            return response()->json($datos);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene datos de impresión para recibo de cobro
     */
    public function reciboCobro(int $cobroId): JsonResponse
    {
        try {
            $cobro = Cobro::findOrFail($cobroId);
            $datos = $this->impresionService->generarReciboCobro($cobro);

            return response()->json($datos);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Firma mensajes para QZ Tray (seguridad)
     */
    public function firmarMensaje(Request $request): JsonResponse
    {
        $request->validate(['request' => 'required|string']);

        try {
            $privateKeyPath = storage_path('app/qz/private-key.pem');

            if (!file_exists($privateKeyPath)) {
                // Si no hay clave, retornar firma vacía (modo desarrollo)
                return response()->json(['signature' => '']);
            }

            $privateKey = file_get_contents($privateKeyPath);
            $signature = null;
            openssl_sign($request->input('request'), $signature, $privateKey, OPENSSL_ALGO_SHA512);

            return response()->json([
                'signature' => base64_encode($signature)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

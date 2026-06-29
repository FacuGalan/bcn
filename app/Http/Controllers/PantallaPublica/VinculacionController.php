<?php

namespace App\Http\Controllers\PantallaPublica;

use App\Http\Controllers\Controller;
use App\Services\PantallaPublicaService;
use Illuminate\Http\JsonResponse;

/**
 * Vinculación de dispositivos Clase B: canjea el código corto (tipeado en una TV)
 * por el token largo, que el dispositivo guarda en localStorage para entrar
 * directo en adelante.
 *
 * Endpoint público de solo-lectura, con rate limiting en la ruta. El código
 * corto solo habilita ver pantallas públicas de baja sensibilidad; el canal de
 * Reverb y los endpoints de datos usan el token largo inadivinable.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-02b).
 */
class VinculacionController extends Controller
{
    public function canjear(string $codigo, PantallaPublicaService $service): JsonResponse
    {
        $token = $service->canjearCodigoCorto($codigo);

        if (! $token) {
            return response()->json(['error' => 'codigo_invalido'], 404);
        }

        return response()->json(['token' => $token]);
    }
}

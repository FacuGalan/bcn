<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use App\Mail\Consumidores\RecuperarPasswordConsumidor;
use App\Mail\Consumidores\VerificarEmailConsumidor;
use App\Models\Consumidor;
use App\Services\Consumidores\ConsumidorTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Auth de consumidores de la tienda online (RF-T1, spec tienda-online).
 *
 * El consumidor es la cuenta GLOBAL cross-comercio (BD config). El Bearer
 * emitido acá lo guarda la TIENDA en su sesión server-side (nunca viaja al
 * navegador del consumidor). Decisión RF-T1 (2026-07-16): se puede pedir
 * SIN verificar el email; la verificación desbloquea historial/cuenta.
 */
class AuthController extends Controller
{
    public function __construct(protected ConsumidorTokenService $tokens) {}

    /**
     * POST /v1/consumidores/registro — crea la cuenta, manda el email de
     * verificación y devuelve un Bearer (puede operar sin verificar).
     */
    public function registro(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => 'required|string|min:2|max:150',
            'email' => 'required|email|max:150|unique:config.consumidores,email',
            'password' => 'required|string|min:8|max:100',
            'telefono' => 'nullable|string|max:30',
        ]);

        $consumidor = Consumidor::create($datos);

        $this->enviarVerificacion($consumidor);

        return response()->json([
            'data' => [
                'token' => $consumidor->createToken('tienda')->plainTextToken,
                'consumidor' => $this->perfil($consumidor),
            ],
        ], 201);
    }

    /**
     * POST /v1/consumidores/login — email + password → Bearer.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $consumidor = Consumidor::where('email', $datos['email'])->first();

        if (! $consumidor || ! Hash::check($datos['password'], $consumidor->getAuthPassword())) {
            throw ValidationException::withMessages([
                'email' => __('Email o password incorrectos'),
            ]);
        }

        return response()->json([
            'data' => [
                'token' => $consumidor->createToken('tienda')->plainTextToken,
                'consumidor' => $this->perfil($consumidor),
            ],
        ]);
    }

    /**
     * POST /v1/consumidores/logout — revoca el token actual.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * GET /v1/consumidores/me — perfil + banderas.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->perfil($request->user())]);
    }

    /**
     * POST /v1/consumidores/verificar — {token} del email → marca el email
     * como verificado (idempotente). Público: el link del email aterriza en
     * la tienda, que reenvía el token acá sin necesidad de sesión.
     */
    public function verificar(Request $request): JsonResponse
    {
        $datos = $request->validate(['token' => 'required|string|max:500']);

        $consumidor = $this->tokens->validarTokenVerificacion($datos['token']);

        if (! $consumidor) {
            throw new \Exception(__('El link de verificación es inválido o venció'));
        }

        if (! $consumidor->email_verified_at) {
            $consumidor->forceFill(['email_verified_at' => now()])->save();

            Log::info('Consumidor verificó su email', ['consumidor_id' => $consumidor->id]);
        }

        return response()->json(['data' => $this->perfil($consumidor)]);
    }

    /**
     * POST /v1/consumidores/reenviar-verificacion — reenvía el email de
     * verificación al consumidor autenticado (no-op si ya verificó).
     */
    public function reenviarVerificacion(Request $request): JsonResponse
    {
        $consumidor = $request->user();

        if (! $consumidor->email_verified_at) {
            $this->enviarVerificacion($consumidor);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /v1/consumidores/recuperar — {email} → manda el link de reset.
     * SIEMPRE responde ok (sin revelar si el email existe).
     */
    public function recuperar(Request $request): JsonResponse
    {
        $datos = $request->validate(['email' => 'required|email']);

        $consumidor = Consumidor::where('email', $datos['email'])->first();

        if ($consumidor) {
            try {
                Mail::to($consumidor->email)->send(
                    new RecuperarPasswordConsumidor($consumidor, $this->tokens->generarTokenReset($consumidor))
                );
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el email de recuperación de consumidor', [
                    'consumidor_id' => $consumidor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /v1/consumidores/restablecer — {token, password} → cambia el
     * password y revoca TODOS los tokens (cierra sesiones abiertas).
     */
    public function restablecer(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => 'required|string|max:500',
            'password' => 'required|string|min:8|max:100',
        ]);

        $consumidor = $this->tokens->validarTokenReset($datos['token']);

        if (! $consumidor) {
            throw new \Exception(__('El link de recuperación es inválido, venció o ya fue usado'));
        }

        $consumidor->forceFill(['password' => $datos['password']])->save();
        $consumidor->tokens()->delete();

        Log::info('Consumidor restableció su password', ['consumidor_id' => $consumidor->id]);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * Payload público del perfil (nunca expone password ni tokens).
     */
    protected function perfil(Consumidor $consumidor): array
    {
        return [
            'id' => $consumidor->id,
            'nombre' => $consumidor->nombre,
            'email' => $consumidor->email,
            'telefono' => $consumidor->telefono,
            'email_verificado' => $consumidor->email_verified_at !== null,
        ];
    }

    /**
     * Envía la verificación sin romper el flujo si el mailer falla (el
     * consumidor puede reenviarla luego).
     */
    protected function enviarVerificacion(Consumidor $consumidor): void
    {
        try {
            Mail::to($consumidor->email)->send(
                new VerificarEmailConsumidor($consumidor, $this->tokens->generarTokenVerificacion($consumidor))
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el email de verificación de consumidor', [
                'consumidor_id' => $consumidor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

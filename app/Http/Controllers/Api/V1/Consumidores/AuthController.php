<?php

namespace App\Http\Controllers\Api\V1\Consumidores;

use App\Http\Controllers\Controller;
use App\Mail\Consumidores\RecuperarPasswordConsumidor;
use App\Mail\Consumidores\VerificarEmailConsumidor;
use App\Models\Consumidor;
use App\Services\Consumidores\ConsumidorTokenService;
use App\Services\Consumidores\DispositivoService;
use App\Services\Consumidores\GoogleIdTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
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
    /** RF-T73: intentos fallidos por EMAIL antes del lockout (además del throttle por IP). */
    protected const MAX_INTENTOS_LOGIN = 5;

    /** RF-T73: ventana base del lockout (15 min); se duplica por lockout consecutivo, máx 4 h. */
    protected const LOCKOUT_BASE_SEGUNDOS = 900;

    protected const LOCKOUT_MAX_SEGUNDOS = 14400;

    public function __construct(
        protected ConsumidorTokenService $tokens,
        protected GoogleIdTokenService $google,
        protected DispositivoService $dispositivos,
    ) {}

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
            'recordarme' => 'sometimes|boolean',
        ]);

        $consumidor = Consumidor::create($datos);

        $this->enviarVerificacion($consumidor);

        return response()->json([
            'data' => [
                'token' => $consumidor->createToken('tienda')->plainTextToken,
                'consumidor' => $this->perfil($consumidor),
                'dispositivo' => $this->emitirDispositivo($request, $consumidor),
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
            'recordarme' => 'sometimes|boolean',
        ]);

        // RF-T73: lockout por EMAIL (el throttle de la ruta es por IP y no
        // frena un ataque distribuido). Bloqueado ⇒ el MISMO error genérico:
        // no confirma que el email exista ni que haya lockout.
        $claveLockout = $this->claveLockout($datos['email']);

        if (RateLimiter::tooManyAttempts($claveLockout, self::MAX_INTENTOS_LOGIN)) {
            $this->fallarCredenciales();
        }

        $consumidor = Consumidor::where('email', $datos['email'])->first();

        // Cuentas creadas via Google (RF-T49) no tienen password: mismo
        // error genérico (no revelar el método de acceso de una cuenta).
        if (! $consumidor || ! $consumidor->getAuthPassword() || ! Hash::check($datos['password'], $consumidor->getAuthPassword())) {
            RateLimiter::hit($claveLockout, $this->ventanaLockout($claveLockout));
            $this->fallarCredenciales();
        }

        RateLimiter::clear($claveLockout);
        Cache::forget($claveLockout.':nivel');

        return response()->json([
            'data' => [
                'token' => $consumidor->createToken('tienda')->plainTextToken,
                'consumidor' => $this->perfil($consumidor),
                'dispositivo' => $this->emitirDispositivo($request, $consumidor),
            ],
        ]);
    }

    /**
     * POST /v1/consumidores/auth/google — Sign in with Google (RF-T49).
     *
     * Recibe el credential de Google Identity Services (la tienda lo obtiene
     * en el navegador), lo verifica server-side y resuelve la cuenta:
     * google_id existente → login; email existente → linkea; si no, crea la
     * cuenta SIN password. Si Google es autoritativo sobre el email (Gmail o
     * Workspace verificado), la cuenta queda VERIFICADA sin mail ni plazo
     * (RF-T40); el caso raro no autoritativo sigue el flujo normal.
     */
    public function google(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'credential' => 'required|string|max:4096',
            'recordarme' => 'sometimes|boolean',
        ]);

        if (! $this->google->configurado()) {
            return response()->json([
                'message' => __('El acceso con Google no está disponible'),
                'codigo' => 'google_no_configurado',
            ], 503);
        }

        $claims = $this->google->verificar($datos['credential']);

        if ($claims === null) {
            throw ValidationException::withMessages([
                'credential' => __('No pudimos validar tu cuenta de Google. Probá de nuevo.'),
            ]);
        }

        $consumidor = Consumidor::where('google_id', $claims['sub'])->first();
        $creado = false;

        if (! $consumidor) {
            $consumidor = Consumidor::where('email', $claims['email'])->first();

            if ($consumidor) {
                // Cuenta previa con ese email (password o Google viejo sin
                // linkear): se linkea de una — el ID token prueba que es SU
                // cuenta de Google con ese email.
                $consumidor->forceFill(['google_id' => $claims['sub']])->save();

                Log::info('Consumidor linkeó su cuenta de Google', ['consumidor_id' => $consumidor->id]);
            } else {
                $consumidor = new Consumidor([
                    'nombre' => $this->nombreDesdeClaims($claims),
                    'email' => $claims['email'],
                ]);
                $consumidor->google_id = $claims['sub'];
                $consumidor->save();
                $creado = true;

                Log::info('Consumidor creado via Google', ['consumidor_id' => $consumidor->id]);
            }
        }

        if (! $consumidor->email_verified_at && $this->google->esAutoritativo($claims)) {
            $consumidor->forceFill(['email_verified_at' => now()])->save();
        }

        // Caso raro: cuenta nueva con email del que Google NO es autoritativo
        // → verificación por email como cualquier registro.
        if ($creado && ! $consumidor->email_verified_at) {
            $this->enviarVerificacion($consumidor);
        }

        return response()->json([
            'data' => [
                'token' => $consumidor->createToken('tienda')->plainTextToken,
                'consumidor' => $this->perfil($consumidor),
                'creado' => $creado,
                'dispositivo' => $this->emitirDispositivo($request, $consumidor),
            ],
        ], $creado ? 201 : 200);
    }

    /**
     * POST /v1/consumidores/auth/recordar — canjea el par selector/validator
     * de un dispositivo recordado (RF-T66) por un Bearer nuevo, ROTANDO el
     * validator. Público: es el re-login silencioso de la tienda cuando la
     * sesión murió pero la cookie de dispositivo sigue viva.
     */
    public function recordar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'selector' => 'required|string|max:64',
            'validator' => 'required|string|max:128',
        ]);

        $resultado = $this->dispositivos->canjear(
            $datos['selector'],
            $datos['validator'],
            $request->userAgent(),
            $request->ip(),
        );

        if ($resultado === null) {
            return response()->json([
                'message' => __('El dispositivo no es válido o venció'),
                'codigo' => 'dispositivo_invalido',
            ], 401);
        }

        return response()->json([
            'data' => [
                'token' => $resultado['consumidor']->createToken('tienda')->plainTextToken,
                'consumidor' => $this->perfil($resultado['consumidor']),
                'dispositivo' => $resultado['dispositivo'],
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
     * PATCH /v1/consumidores/me — edita el perfil (RF-T39). El EMAIL no se
     * cambia por acá: es la sal del token de verificación (cambiarlo
     * invalidaría los links en vuelo) — flujo propio post-v1. El password
     * se cambia por recuperar/restablecer.
     */
    public function actualizarPerfil(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => 'sometimes|required|string|min:2|max:150',
            'telefono' => 'sometimes|nullable|string|max:30',
            'fecha_nacimiento' => 'sometimes|nullable|date|before:today',
        ]);

        $consumidor = $request->user();
        $consumidor->fill($datos)->save();

        return response()->json(['data' => $this->perfil($consumidor)]);
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
        // RF-T66: el cambio de password también mata los dispositivos
        // recordados (si cambió porque se la robaron, las cookies remember
        // del atacante quedan muertas).
        $this->dispositivos->revocarTodos($consumidor);

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
            // RF-T19: pre-llena el cumpleaños en el checkout de cualquier tienda.
            'fecha_nacimiento' => $consumidor->fecha_nacimiento?->format('Y-m-d'),
            'email_verificado' => $consumidor->email_verified_at !== null,
            // RF-T40: fecha límite para verificar (null si ya verificó). La
            // tienda muestra la cuenta regresiva sin calcular nada.
            'verificacion_vence_el' => $consumidor->verificacionVenceEl()?->toIso8601String(),
        ];
    }

    /**
     * Nombre para la cuenta nueva desde los claims de Google: claim `name`,
     * con fallback a la parte local del email (respetando los límites del
     * registro: 2-150).
     */
    protected function nombreDesdeClaims(array $claims): string
    {
        $nombre = trim((string) ($claims['name'] ?? ''));

        if (mb_strlen($nombre) < 2) {
            $nombre = ucfirst(str($claims['email'])->before('@')->toString());
        }

        return mb_substr($nombre, 0, 150);
    }

    /**
     * RF-T66: emite un dispositivo recordado si el request lo pidió
     * (`recordarme: true`). Null si no — la clave viaja igual en la
     * respuesta para que el shape sea estable.
     */
    protected function emitirDispositivo(Request $request, Consumidor $consumidor): ?array
    {
        if (! $request->boolean('recordarme')) {
            return null;
        }

        return $this->dispositivos->emitir($consumidor, $request->userAgent(), $request->ip());
    }

    /** RF-T73: bucket de lockout por email (case-insensitive, sin persistir el email). */
    protected function claveLockout(string $email): string
    {
        return 'login-email:'.hash('sha256', mb_strtolower(trim($email)));
    }

    /**
     * RF-T73: ventana del bucket de intentos. Base 15 min; cada lockout
     * consecutivo duplica la ventana del SIGUIENTE bucket (máx 4 h). El
     * nivel vive en cache 24 h y se limpia con un login exitoso.
     */
    protected function ventanaLockout(string $clave): int
    {
        $nivel = (int) Cache::get($clave.':nivel', 0);

        // Este hit completa el lockout ⇒ el próximo bucket dura el doble.
        if (RateLimiter::attempts($clave) + 1 >= self::MAX_INTENTOS_LOGIN) {
            Cache::put($clave.':nivel', min($nivel + 1, 5), now()->addDay());
        }

        return (int) min(self::LOCKOUT_BASE_SEGUNDOS * (2 ** $nivel), self::LOCKOUT_MAX_SEGUNDOS);
    }

    /** Error genérico de credenciales: idéntico exista o no la cuenta, haya o no lockout. */
    protected function fallarCredenciales(): never
    {
        throw ValidationException::withMessages([
            'email' => __('Email o password incorrectos'),
        ]);
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

<?php

namespace App\Livewire\Forms;

use App\Models\Comercio;
use App\Models\User;
use App\Services\SessionManagerService;
use App\Services\TenantService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * Formulario de Login Multi-Tenant
 *
 * Maneja la autenticación de usuarios en el sistema multi-tenant:
 * - Validación de comercio por email
 * - Autenticación de usuario por username
 * - Verificación de acceso al comercio
 * - Control de sesiones concurrentes
 * - Establecimiento del comercio activo
 *
 * @package App\Livewire\Forms
 * @author BCN Pymes
 * @version 1.0.0
 */
class LoginForm extends Form
{
    /**
     * Email del comercio al que se quiere acceder
     * Opcional para System Admins
     *
     * @var string
     */
    #[Validate('nullable|string|email')]
    public string $comercio_email = '';

    /**
     * Nombre de usuario para login
     *
     * @var string
     */
    #[Validate('required|string')]
    public string $username = '';

    /**
     * Contraseña del usuario
     *
     * @var string
     */
    #[Validate('required|string')]
    public string $password = '';

    /**
     * Recordar sesión
     *
     * @var bool
     */
    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Clave de sesión para almacenar ID del usuario validado
     */
    protected const SESSION_VALIDATED_USER_ID = 'login_validation.user_id';

    /**
     * Clave de sesión para almacenar ID del comercio validado
     */
    protected const SESSION_VALIDATED_COMERCIO_ID = 'login_validation.comercio_id';

    /**
     * Intenta autenticar las credenciales del usuario en el sistema multi-tenant
     *
     * Flujo:
     * 1. Verificar rate limiting
     * 2. Buscar comercio por email
     * 3. Buscar usuario por username
     * 4. Verificar password
     * 5. Verificar acceso del usuario al comercio
     * 6. Controlar sesiones concurrentes (solicitar confirmación si es necesario)
     * 7. Autenticar usuario (o solicitar confirmación)
     * 8. Establecer comercio activo
     *
     * @return array Array con información sobre si necesita confirmación
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): array
    {
        $this->ensureIsNotRateLimited();

        // 1. Buscar el usuario por username primero
        $user = User::where('username', $this->username)->first();

        if (!$user) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.username' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        // 2. Verificar la contraseña
        if (!Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.password' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        // 3. Verificar que el usuario esté activo
        if (!$user->activo) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.username' => 'Tu cuenta ha sido desactivada. Contacta al administrador.',
            ]);
        }

        // 4. Si es System Admin, no necesita comercio - va directo al selector
        if ($user->isSystemAdmin()) {
            $sessionManager = app(SessionManagerService::class);

            Session::put(self::SESSION_VALIDATED_USER_ID, $user->id);
            Session::put(self::SESSION_VALIDATED_COMERCIO_ID, null);

            if ($sessionManager->hasReachedSessionLimit($user)) {
                $sessionsInfo = $sessionManager->getSessionsInfo($user);
                $sessionsToClose = $sessionManager->getActiveSessionsCount($user) - $user->max_concurrent_sessions + 1;

                return [
                    'needsConfirmation' => true,
                    'sessionsToClose' => $sessionsToClose,
                    'sessionsInfo' => $sessionsInfo,
                    'maxSessions' => $user->max_concurrent_sessions,
                    'isSystemAdmin' => true,
                ];
            }

            return $this->completeLogin();
        }

        // 5. Para usuarios normales, verificar comercio
        if (empty($this->comercio_email)) {
            throw ValidationException::withMessages([
                'form.comercio_email' => 'Debes ingresar el email del comercio.',
            ]);
        }

        $comercio = Comercio::where('email', $this->comercio_email)->first();

        if (!$comercio) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.comercio_email' => 'El comercio no existe.',
            ]);
        }

        // 6. Verificar que el usuario tenga acceso al comercio
        if (!$user->hasAccessToComercio($comercio->id)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.comercio_email' => 'No tienes acceso a este comercio.',
            ]);
        }

        // 7. Controlar sesiones concurrentes
        $sessionManager = app(SessionManagerService::class);

        // Guardar IDs en sesión para uso posterior (persiste entre requests de Livewire)
        Session::put(self::SESSION_VALIDATED_USER_ID, $user->id);
        Session::put(self::SESSION_VALIDATED_COMERCIO_ID, $comercio->id);

        if ($sessionManager->hasReachedSessionLimit($user)) {
            // Obtener información de sesiones que se cerrarán
            $sessionsInfo = $sessionManager->getSessionsInfo($user);
            $sessionsToClose = $sessionManager->getActiveSessionsCount($user) - $user->max_concurrent_sessions + 1;

            // Retornar información para solicitar confirmación
            return [
                'needsConfirmation' => true,
                'sessionsToClose' => $sessionsToClose,
                'sessionsInfo' => $sessionsInfo,
                'maxSessions' => $user->max_concurrent_sessions,
            ];
        }

        // Si no necesita confirmación, proceder con el login
        return $this->completeLogin();
    }

    /**
     * Completa el proceso de login después de la confirmación (o si no necesita confirmación)
     *
     * @param array $selectedSessionIds Array de IDs de sesiones a cerrar (opcional)
     * @return array Array indicando que el login fue exitoso
     */
    public function completeLogin(array $selectedSessionIds = []): array
    {
        // Recuperar IDs desde la sesión
        $userId = Session::get(self::SESSION_VALIDATED_USER_ID);
        $comercioId = Session::get(self::SESSION_VALIDATED_COMERCIO_ID);

        if (!$userId) {
            throw new \Exception('No hay usuario validado. Debe llamar authenticate() primero.');
        }

        // Obtener modelo de usuario
        $user = User::find($userId);

        if (!$user) {
            throw new \Exception('Usuario no encontrado en base de datos.');
        }

        $sessionManager = app(SessionManagerService::class);

        // Cerrar sesiones si es necesario
        if ($sessionManager->hasReachedSessionLimit($user)) {
            if (!empty($selectedSessionIds)) {
                // Cerrar sesiones específicas seleccionadas por el usuario
                $sessionManager->closeSpecificSessions($selectedSessionIds);
            } else {
                // Cerrar sesiones más antiguas automáticamente
                $sessionManager->freeSessionSpace($user);
            }
        }

        // Actualizar password_visible si no está configurado
        if (!$user->hasPasswordVisible()) {
            $user->setPasswordVisible($this->password);
            $user->save();
        }

        // Autenticar al usuario
        Auth::login($user, $this->remember);

        // Si es System Admin, no establecer comercio - irá al selector
        if ($user->isSystemAdmin()) {
            // Limpiar rate limiting
            RateLimiter::clear($this->throttleKey());

            // Limpiar datos temporales de la sesión
            Session::forget([
                self::SESSION_VALIDATED_USER_ID,
                self::SESSION_VALIDATED_COMERCIO_ID,
            ]);

            return [
                'needsConfirmation' => false,
                'success' => true,
                'isSystemAdmin' => true,
            ];
        }

        // Para usuarios normales, establecer el comercio
        if (!$comercioId) {
            throw new \Exception('No hay comercio validado para usuario normal.');
        }

        $comercio = Comercio::find($comercioId);

        if (!$comercio) {
            throw new \Exception('Comercio no encontrado en base de datos.');
        }

        // Establecer el comercio activo en la sesión
        $tenantService = app(TenantService::class);
        $tenantService->setComercio($comercio);

        // Establecer sucursal por defecto
        $this->establecerSucursalPorDefecto($user);

        // Limpiar rate limiting
        RateLimiter::clear($this->throttleKey());

        // Limpiar datos temporales de la sesión
        Session::forget([
            self::SESSION_VALIDATED_USER_ID,
            self::SESSION_VALIDATED_COMERCIO_ID,
        ]);

        return [
            'needsConfirmation' => false,
            'success' => true,
            'isSystemAdmin' => false,
        ];
    }

    /**
     * Cancela el proceso de login y limpia los datos temporales
     *
     * @return void
     */
    public function cancelLogin(): void
    {
        // Limpiar datos temporales de la sesión
        Session::forget([
            self::SESSION_VALIDATED_USER_ID,
            self::SESSION_VALIDATED_COMERCIO_ID,
        ]);
    }

    /**
     * Verifica que la solicitud de autenticación no esté limitada por intentos
     *
     * @return void
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.comercio_email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Establece la sucursal por defecto para el usuario
     * Y también establece la primera caja disponible
     *
     * @param User $user Usuario autenticado
     * @return void
     */
    protected function establecerSucursalPorDefecto(User $user): void
    {
        // Obtener sucursales disponibles para el usuario
        $sucursalesDisponibles = \App\Services\SucursalService::getSucursalesDisponibles();

        if ($sucursalesDisponibles->isEmpty()) {
            return;
        }

        // Establecer la primera sucursal disponible (principal primero)
        $sucursalPorDefecto = $sucursalesDisponibles->first();
        Session::put('sucursal_id', $sucursalPorDefecto->id);

        // Establecer la primera caja disponible de la sucursal
        \App\Services\CajaService::establecerPrimeraCajaDisponible();
    }

    /**
     * Obtiene la clave de rate limiting para throttling
     *
     * @return string Clave única basada en email comercio + username + IP
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->comercio_email . '|' . $this->username) . '|' . request()->ip()
        );
    }
}

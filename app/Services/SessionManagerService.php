<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Servicio de gestión de sesiones concurrentes
 *
 * Este servicio maneja la lógica para controlar cuántos dispositivos simultáneos
 * puede utilizar un usuario para estar logueado en el sistema. Incluye:
 * - Verificación de límite de sesiones
 * - Limpieza automática de sesiones antiguas
 * - Cierre forzado de sesiones cuando se excede el límite
 *
 * @package App\Services
 * @author BCN Pymes
 * @version 1.0.0
 */
class SessionManagerService
{
    /**
     * Tiempo en segundos para considerar una sesión como expirada
     * Por defecto: 7200 segundos (2 horas) - debe coincidir con SESSION_LIFETIME en .env
     *
     * @var int
     */
    protected int $sessionLifetime;

    /**
     * Constructor del servicio
     */
    public function __construct()
    {
        // Obtener el lifetime desde la configuración (en minutos, convertir a segundos)
        $this->sessionLifetime = config('session.lifetime', 120) * 60;
    }

    /**
     * Verifica si el usuario ha alcanzado el límite de sesiones concurrentes
     *
     * @param User $user Usuario a verificar
     * @return bool True si ha alcanzado el límite, false si aún tiene espacio
     */
    public function hasReachedSessionLimit(User $user): bool
    {
        $activeSessions = $this->getActiveSessionsCount($user);
        return $activeSessions >= $user->max_concurrent_sessions;
    }

    /**
     * Obtiene el número de sesiones activas del usuario
     *
     * @param User $user Usuario a verificar
     * @return int Número de sesiones activas
     */
    public function getActiveSessionsCount(User $user): int
    {
        $this->cleanExpiredSessions();

        return DB::connection('config')
            ->table('sessions')
            ->where('user_id', $user->id)
            ->count();
    }

    /**
     * Obtiene todas las sesiones activas de un usuario
     *
     * @param User $user Usuario
     * @return \Illuminate\Support\Collection Colección de sesiones
     */
    public function getActiveSessions(User $user): \Illuminate\Support\Collection
    {
        $this->cleanExpiredSessions();

        return DB::connection('config')
            ->table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get();
    }

    /**
     * Libera espacio cerrando las sesiones más antiguas si se excede el límite
     *
     * Cuando un usuario intenta iniciar sesión y ya tiene el máximo de sesiones,
     * este método cierra las sesiones más antiguas para dar espacio a la nueva.
     *
     * @param User $user Usuario que intenta iniciar sesión
     * @return int Número de sesiones cerradas
     */
    public function freeSessionSpace(User $user): int
    {
        $activeSessions = $this->getActiveSessions($user);
        $maxSessions = $user->max_concurrent_sessions;

        // Si no se alcanzó el límite, no hacer nada
        if ($activeSessions->count() < $maxSessions) {
            return 0;
        }

        // Calcular cuántas sesiones debemos cerrar para dejar espacio a una nueva
        $sessionsToClose = $activeSessions->count() - $maxSessions + 1;

        // Obtener las sesiones más antiguas
        $oldestSessions = $activeSessions
            ->sortBy('last_activity')
            ->take($sessionsToClose);

        // Cerrar las sesiones más antiguas
        foreach ($oldestSessions as $session) {
            $this->destroySession($session->id);
        }

        return $sessionsToClose;
    }

    /**
     * Destruye una sesión específica por su ID
     *
     * @param string $sessionId ID de la sesión a destruir
     * @return bool True si se destruyó correctamente
     */
    public function destroySession(string $sessionId): bool
    {
        return DB::connection('config')
            ->table('sessions')
            ->where('id', $sessionId)
            ->delete() > 0;
    }

    /**
     * Cierra múltiples sesiones específicas por sus IDs
     *
     * Útil cuando el usuario selecciona manualmente qué sesiones cerrar
     * en el modal de confirmación de login.
     *
     * @param array $sessionIds Array de IDs de sesiones a cerrar
     * @return int Número de sesiones cerradas
     */
    public function closeSpecificSessions(array $sessionIds): int
    {
        if (empty($sessionIds)) {
            return 0;
        }

        return DB::connection('config')
            ->table('sessions')
            ->whereIn('id', $sessionIds)
            ->delete();
    }

    /**
     * Destruye todas las sesiones de un usuario excepto la actual
     *
     * Útil para implementar "cerrar sesión en otros dispositivos"
     *
     * @param User $user Usuario
     * @param string|null $exceptSessionId ID de sesión a mantener (opcional)
     * @return int Número de sesiones cerradas
     */
    public function destroyOtherSessions(User $user, ?string $exceptSessionId = null): int
    {
        $query = DB::connection('config')
            ->table('sessions')
            ->where('user_id', $user->id);

        if ($exceptSessionId) {
            $query->where('id', '!=', $exceptSessionId);
        }

        return $query->delete();
    }

    /**
     * Limpia todas las sesiones expiradas de la base de datos
     *
     * Considera una sesión expirada si su last_activity es mayor
     * al tiempo de vida configurado.
     *
     * @return int Número de sesiones limpiadas
     */
    public function cleanExpiredSessions(): int
    {
        $expirationTime = now()->timestamp - $this->sessionLifetime;

        return DB::connection('config')
            ->table('sessions')
            ->where('last_activity', '<', $expirationTime)
            ->delete();
    }

    /**
     * Obtiene información detallada de las sesiones activas de un usuario
     *
     * Incluye información como IP, user agent, última actividad, etc.
     *
     * @param User $user Usuario
     * @return array Array con información de cada sesión
     */
    public function getSessionsInfo(User $user): array
    {
        $sessions = $this->getActiveSessions($user);
        $currentSessionId = Session::getId();

        return $sessions->map(function ($session) use ($currentSessionId) {
            return [
                'id' => $session->id,
                'ip_address' => $session->ip_address,
                'user_agent' => $this->parseUserAgent($session->user_agent),
                'last_activity' => $session->last_activity,
                'last_activity_human' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                'is_current' => $session->id === $currentSessionId,
            ];
        })->toArray();
    }

    /**
     * Parsea el user agent para obtener información legible
     *
     * IMPORTANTE: El orden de detección es crítico porque algunos navegadores
     * incluyen palabras clave de otros (ej: Edge incluye "Chrome" en su UA)
     *
     * @param string $userAgent User agent string
     * @return array Array con información del navegador y dispositivo
     */
    protected function parseUserAgent(string $userAgent): array
    {
        // Detección de navegador
        // ORDEN IMPORTANTE: Detectar los más específicos primero
        $browser = 'Desconocido';

        // Edge (debe ir antes que Chrome porque Edge contiene "Chrome" en su UA)
        // Edge moderno usa "Edg/" (sin la 'e' final)
        if (str_contains($userAgent, 'Edg/') || str_contains($userAgent, 'Edge/')) {
            $browser = 'Edge';
        }
        // Opera (debe ir antes que Chrome porque también lo contiene)
        elseif (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/')) {
            $browser = 'Opera';
        }
        // Chrome (después de Edge y Opera)
        elseif (str_contains($userAgent, 'Chrome/') && !str_contains($userAgent, 'Edg/')) {
            $browser = 'Chrome';
        }
        // Firefox
        elseif (str_contains($userAgent, 'Firefox/')) {
            $browser = 'Firefox';
        }
        // Safari (debe ir después de Chrome porque Chrome también contiene "Safari")
        elseif (str_contains($userAgent, 'Safari/') && !str_contains($userAgent, 'Chrome/')) {
            $browser = 'Safari';
        }
        // Internet Explorer
        elseif (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident/')) {
            $browser = 'Internet Explorer';
        }

        // Detección de sistema operativo
        $platform = 'Desconocido';

        // Android (debe ir antes que Linux porque Android es Linux)
        if (str_contains($userAgent, 'Android')) {
            $platform = 'Android';
        }
        // iOS (iPhone, iPad, iPod)
        elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') || str_contains($userAgent, 'iPod')) {
            $platform = 'iOS';
        }
        // Windows
        elseif (str_contains($userAgent, 'Windows NT')) {
            // Extraer versión de Windows si es posible
            if (str_contains($userAgent, 'Windows NT 10.0')) {
                $platform = 'Windows 10/11';
            } elseif (str_contains($userAgent, 'Windows NT 6.3')) {
                $platform = 'Windows 8.1';
            } elseif (str_contains($userAgent, 'Windows NT 6.2')) {
                $platform = 'Windows 8';
            } elseif (str_contains($userAgent, 'Windows NT 6.1')) {
                $platform = 'Windows 7';
            } else {
                $platform = 'Windows';
            }
        }
        // macOS
        elseif (str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS X')) {
            $platform = 'macOS';
        }
        // Linux
        elseif (str_contains($userAgent, 'Linux')) {
            $platform = 'Linux';
        }
        // Chrome OS
        elseif (str_contains($userAgent, 'CrOS')) {
            $platform = 'Chrome OS';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'raw' => $userAgent,
        ];
    }

    /**
     * Actualiza el número máximo de sesiones concurrentes para un usuario
     *
     * Si el nuevo límite es menor al actual y el usuario tiene más sesiones activas,
     * se cierran las sesiones más antiguas.
     *
     * @param User $user Usuario
     * @param int $newLimit Nuevo límite de sesiones
     * @return bool True si se actualizó correctamente
     */
    public function updateSessionLimit(User $user, int $newLimit): bool
    {
        if ($newLimit < 1) {
            throw new \InvalidArgumentException('El límite de sesiones debe ser al menos 1');
        }

        $user->max_concurrent_sessions = $newLimit;
        $user->save();

        // Si el nuevo límite es menor, cerrar sesiones excedentes
        $activeSessions = $this->getActiveSessionsCount($user);
        if ($activeSessions > $newLimit) {
            $sessionsToClose = $activeSessions - $newLimit;
            $oldestSessions = $this->getActiveSessions($user)
                ->sortBy('last_activity')
                ->take($sessionsToClose);

            foreach ($oldestSessions as $session) {
                $this->destroySession($session->id);
            }
        }

        return true;
    }
}

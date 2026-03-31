<?php

namespace App\Services;

use App\Models\Comercio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Servicio de gestión de Tenants (Multi-comercio)
 *
 * Este servicio maneja la lógica multi-tenant del sistema, permitiendo:
 * - Establecer el comercio activo en la sesión
 * - Configurar dinámicamente el prefijo de tablas para el comercio actual
 * - Obtener información del comercio activo
 * - Reconectar a la base de datos con el prefijo correcto
 *
 * @author BCN Pymes
 *
 * @version 1.1.0
 */
class TenantService
{
    /**
     * Clave de sesión para almacenar el comercio activo
     *
     * @var string
     */
    protected const SESSION_KEY = 'comercio_activo_id';

    /**
     * Claves de sesión para caché de configuración tenant
     */
    protected const SESSION_PREFIX_KEY = 'tenant_prefix';

    protected const SESSION_DATABASE_KEY = 'tenant_database';

    /**
     * Nombre de la conexión dinámica para el tenant
     *
     * @var string
     */
    protected const TENANT_CONNECTION = 'pymes_tenant';

    /**
     * Caché en memoria del comercio activo para evitar consultas repetidas
     */
    protected ?Comercio $comercioCache = null;

    /**
     * Establece el comercio activo en la sesión
     *
     * Guarda el ID del comercio en la sesión y configura la conexión
     * de base de datos con el prefijo correspondiente.
     *
     * Se usa en LOGIN y CAMBIO DE COMERCIO (no en cada request).
     *
     * @param  int|Comercio  $comercio  ID del comercio o instancia de Comercio
     *
     * @throws \Exception Si el comercio no existe
     */
    public function setComercio($comercio): void
    {
        // Si ya recibimos una instancia de Comercio, usarla directamente
        if ($comercio instanceof Comercio) {
            $comercioModel = $comercio;
            $comercioId = $comercio->id;
        } else {
            // Solo hacer query si recibimos un ID
            $comercioId = $comercio;
            $comercioModel = Comercio::find($comercioId);

            if (! $comercioModel) {
                throw new \Exception("Comercio con ID {$comercioId} no encontrado");
            }
        }

        $prefix = $comercioModel->getTablePrefix();
        $databaseName = $comercioModel->database_name ?? 'pymes';

        // Guardar en sesión: ID + datos de conexión para evitar query en requests posteriores
        Session::put(self::SESSION_KEY, $comercioId);
        Session::put(self::SESSION_PREFIX_KEY, $prefix);
        Session::put(self::SESSION_DATABASE_KEY, $databaseName);

        // Cachear en memoria para evitar queries posteriores en este request
        $this->comercioCache = $comercioModel;

        // Guardar como último comercio usado para el usuario autenticado
        $user = Auth::user();
        if ($user && $user->ultimo_comercio_id !== $comercioId) {
            $user->setUltimoComercio($comercioId);
        }

        // Configurar la conexión con el prefijo
        $this->configureConnection($comercioModel);
    }

    /**
     * Restaura la conexión tenant desde datos de sesión (sin query a BD)
     *
     * Se usa en el middleware de cada request. Lee prefix y database
     * directamente de la sesión, evitando el Comercio::find() por request.
     *
     * @return bool True si se restauró correctamente, false si no hay datos en sesión
     */
    public function restoreConnection(): bool
    {
        $prefix = Session::get(self::SESSION_PREFIX_KEY);
        $databaseName = Session::get(self::SESSION_DATABASE_KEY);

        // Si no hay datos de conexión en sesión, no se puede restaurar
        if ($prefix === null || $databaseName === null) {
            return false;
        }

        // Configurar directamente sin query a BD
        $this->configureConnectionFromValues($prefix, $databaseName);

        return true;
    }

    /**
     * Obtiene el ID del comercio activo desde la sesión
     *
     * @return int|null ID del comercio activo o null si no hay comercio establecido
     */
    public function getComercioId(): ?int
    {
        return Session::get(self::SESSION_KEY);
    }

    /**
     * Obtiene la instancia del comercio activo
     *
     * @return Comercio|null Instancia del comercio activo o null si no hay comercio establecido
     */
    public function getComercio(): ?Comercio
    {
        // Si ya está en caché, devolverlo sin hacer query
        if ($this->comercioCache !== null) {
            return $this->comercioCache;
        }

        $comercioId = $this->getComercioId();

        if (! $comercioId) {
            return null;
        }

        // Hacer query solo si no está en caché
        $comercio = Comercio::find($comercioId);

        // Cachear para futuros accesos en este request
        if ($comercio) {
            $this->comercioCache = $comercio;
        }

        return $comercio;
    }

    /**
     * Verifica si hay un comercio activo establecido
     *
     * @return bool True si hay un comercio activo, false en caso contrario
     */
    public function hasComercio(): bool
    {
        return $this->getComercioId() !== null;
    }

    /**
     * Limpia el comercio activo de la sesión
     */
    public function clearComercio(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::SESSION_PREFIX_KEY);
        Session::forget(self::SESSION_DATABASE_KEY);
        $this->comercioCache = null;
        $this->resetConnection();
    }

    /**
     * Configura la conexión de base de datos con el prefijo y base de datos del comercio
     *
     * Establece dinámicamente:
     * - El prefijo de tablas en la conexión 'pymes_tenant'
     * - La base de datos a usar (pymes, pymes1, pymes2, resto, etc.)
     *
     * OPTIMIZACIÓN: Solo purga/reconecta si la configuración cambió
     *
     * @param  Comercio  $comercio  Instancia del comercio
     */
    protected function configureConnection(Comercio $comercio): void
    {
        $prefix = $comercio->getTablePrefix();
        $databaseName = $comercio->database_name ?? 'pymes';

        $this->configureConnectionFromValues($prefix, $databaseName);
    }

    /**
     * Configura la conexión desde valores directos (sin necesitar el modelo)
     *
     * Usado tanto por configureConnection() como por restoreConnection()
     */
    protected function configureConnectionFromValues(string $prefix, string $databaseName): void
    {
        // Obtener la configuración actual
        $currentPrefix = Config::get('database.connections.'.self::TENANT_CONNECTION.'.prefix', '');
        $currentDatabase = Config::get('database.connections.'.self::TENANT_CONNECTION.'.database', '');

        // OPTIMIZACIÓN: Solo reconfigurar si cambió el prefijo o la base de datos
        if ($currentPrefix === $prefix && $currentDatabase === $databaseName) {
            // Ya está configurado correctamente, no hacer nada
            return;
        }

        // Configurar el prefijo y la base de datos en la conexión pymes_tenant
        Config::set('database.connections.'.self::TENANT_CONNECTION.'.prefix', $prefix);
        Config::set('database.connections.'.self::TENANT_CONNECTION.'.database', $databaseName);

        // Purgar la conexión para que se reconfigure con el nuevo prefijo y BD
        DB::purge(self::TENANT_CONNECTION);

        // Reconectar con la nueva configuración
        DB::reconnect(self::TENANT_CONNECTION);

        // IMPORTANTE: Forzar la actualización del prefijo en la conexión ya instanciada
        // Esto es necesario porque Laravel puede cachear la instancia de la conexión
        $connection = DB::connection(self::TENANT_CONNECTION);
        $connection->setTablePrefix($prefix);
    }

    /**
     * Resetea la conexión de tenant a su estado inicial (sin prefijo)
     */
    protected function resetConnection(): void
    {
        Config::set('database.connections.'.self::TENANT_CONNECTION.'.prefix', '');
        Config::set('database.connections.'.self::TENANT_CONNECTION.'.database', env('DB_DATABASE', 'pymes'));
        DB::purge(self::TENANT_CONNECTION);
    }

    /**
     * Obtiene el prefijo de tablas del comercio activo
     *
     * @return string|null Prefijo del comercio activo o null si no hay comercio
     */
    public function getTablePrefix(): ?string
    {
        // Intentar desde sesión primero (sin query)
        $prefix = Session::get(self::SESSION_PREFIX_KEY);
        if ($prefix !== null) {
            return $prefix;
        }

        $comercio = $this->getComercio();

        return $comercio ? $comercio->getTablePrefix() : null;
    }

    /**
     * Cambia el comercio activo
     *
     * Similar a setComercio pero con validaciones adicionales para cambio de comercio
     *
     * @param  int|Comercio  $comercio  ID del comercio o instancia de Comercio
     * @param  int|null  $userId  ID del usuario (opcional, para validar acceso)
     * @return bool True si el cambio fue exitoso, false en caso contrario
     */
    public function switchComercio($comercio, ?int $userId = null): bool
    {
        try {
            $comercioId = $comercio instanceof Comercio ? $comercio->id : $comercio;
            $comercioModel = Comercio::find($comercioId);

            if (! $comercioModel) {
                return false;
            }

            // Si se proporciona userId, validar acceso
            if ($userId !== null) {
                if (! $comercioModel->users()->where('user_id', $userId)->exists()) {
                    return false;
                }
            }

            $this->setComercio($comercioModel);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene el nombre de la conexión del tenant
     *
     * @return string Nombre de la conexión
     */
    public function getTenantConnection(): string
    {
        return self::TENANT_CONNECTION;
    }
}

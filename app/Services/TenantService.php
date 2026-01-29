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
 * @package App\Services
 * @author BCN Pymes
 * @version 1.0.0
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
     * Nombre de la conexión dinámica para el tenant
     *
     * @var string
     */
    protected const TENANT_CONNECTION = 'pymes_tenant';

    /**
     * Caché en memoria del comercio activo para evitar consultas repetidas
     *
     * @var Comercio|null
     */
    protected ?Comercio $comercioCache = null;

    /**
     * Establece el comercio activo en la sesión
     *
     * Guarda el ID del comercio en la sesión y configura la conexión
     * de base de datos con el prefijo correspondiente.
     *
     * @param int|Comercio $comercio ID del comercio o instancia de Comercio
     * @return void
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

            if (!$comercioModel) {
                throw new \Exception("Comercio con ID {$comercioId} no encontrado");
            }
        }

        // Guardar en sesión
        Session::put(self::SESSION_KEY, $comercioId);

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

        if (!$comercioId) {
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
     *
     * @return void
     */
    public function clearComercio(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->comercioCache = null; // Limpiar caché
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
     * @param Comercio $comercio Instancia del comercio
     * @return void
     */
    protected function configureConnection(Comercio $comercio): void
    {
        $prefix = $comercio->getTablePrefix();
        $databaseName = $comercio->database_name ?? 'pymes';

        // Obtener la configuración actual
        $currentPrefix = Config::get('database.connections.' . self::TENANT_CONNECTION . '.prefix', '');
        $currentDatabase = Config::get('database.connections.' . self::TENANT_CONNECTION . '.database', '');

        // OPTIMIZACIÓN: Solo reconfigurar si cambió el prefijo o la base de datos
        if ($currentPrefix === $prefix && $currentDatabase === $databaseName) {
            // Ya está configurado correctamente, no hacer nada
            return;
        }

        // Configurar el prefijo y la base de datos en la conexión pymes_tenant
        Config::set('database.connections.' . self::TENANT_CONNECTION . '.prefix', $prefix);
        Config::set('database.connections.' . self::TENANT_CONNECTION . '.database', $databaseName);

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
     *
     * @return void
     */
    protected function resetConnection(): void
    {
        Config::set('database.connections.' . self::TENANT_CONNECTION . '.prefix', '');
        Config::set('database.connections.' . self::TENANT_CONNECTION . '.database', env('DB_DATABASE', 'pymes'));
        DB::purge(self::TENANT_CONNECTION);
    }

    /**
     * Obtiene el prefijo de tablas del comercio activo
     *
     * @return string|null Prefijo del comercio activo o null si no hay comercio
     */
    public function getTablePrefix(): ?string
    {
        $comercio = $this->getComercio();
        return $comercio ? $comercio->getTablePrefix() : null;
    }

    /**
     * Cambia el comercio activo
     *
     * Similar a setComercio pero con validaciones adicionales para cambio de comercio
     *
     * @param int|Comercio $comercio ID del comercio o instancia de Comercio
     * @param int|null $userId ID del usuario (opcional, para validar acceso)
     * @return bool True si el cambio fue exitoso, false en caso contrario
     */
    public function switchComercio($comercio, ?int $userId = null): bool
    {
        try {
            $comercioId = $comercio instanceof Comercio ? $comercio->id : $comercio;
            $comercioModel = Comercio::find($comercioId);

            if (!$comercioModel) {
                return false;
            }

            // Si se proporciona userId, validar acceso
            if ($userId !== null) {
                if (!$comercioModel->users()->where('user_id', $userId)->exists()) {
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

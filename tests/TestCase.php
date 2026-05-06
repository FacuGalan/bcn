<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Indica si las migraciones de testing ya se ejecutaron.
     */
    protected static bool $migrationsRun = false;

    /**
     * Indica si la guarda contra BDs reales ya verifico la configuracion.
     */
    protected static bool $databasesGuarded = false;

    /**
     * Setup base para todos los tests.
     */
    protected function setUp(): void
    {
        // CRITICO: defensa global. Debe correr ANTES de parent::setUp() porque ahi se
        // inicializan los traits (RefreshDatabase, DatabaseTransactions) que tocan BD.
        // Lee env() directamente porque la app aun no esta booted en este punto.
        // Whitelist explicita; el incidente del 2026-05-04 destruyo pymes real porque
        // la cache bootstrap/cache/config.php tenia valores de .env (no .env.testing).
        if (! static::$databasesGuarded) {
            $this->guardAgainstRealDatabases();
            static::$databasesGuarded = true;
        }

        parent::setUp();

        // Evitar que Vite busque manifest.json (no se compila en CI)
        $this->withoutVite();

        // Asegurar que las BDs de testing existen
        if (! static::$migrationsRun) {
            $this->ensureTestDatabases();
            $this->ensureSharedTablesExist();
            static::$migrationsRun = true;
        }
    }

    /**
     * Crea las tablas shared en pymes_test si faltan (menu_items, permissions, etc.)
     * Solo corre `migrate` (NO migrate:fresh) — no destructivo. Persiste entre ejecuciones.
     * Antes de quitar RefreshDatabase, este step lo cubria migrate:fresh.
     */
    private function ensureSharedTablesExist(): void
    {
        try {
            $exists = DB::connection('pymes')->select(
                'SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
                ['pymes_test', 'menu_items']
            );

            if (($exists[0]->cnt ?? 0) > 0) {
                return;
            }

            Artisan::call('migrate', ['--force' => true]);
        } catch (\Throwable $e) {
            // Si esto falla, el primer test que use menu_items dara error claro.
        }
    }

    /**
     * Aborta la suite si las variables de entorno apuntan a BDs que no son de testing.
     * Ultima linea de defensa contra el bug de config cache que destruyo datos
     * de pymes real el 2026-05-04. Whitelist explicita, no blacklist.
     *
     * Nota: usamos env() en vez de DB::connection()->getDatabaseName() porque este
     * metodo corre antes de parent::setUp(), donde la app aun no esta booted.
     */
    private function guardAgainstRealDatabases(): void
    {
        $expected = [
            'DB_DATABASE' => 'pymes_test',
            'DB_PYMES_DATABASE' => 'pymes_test',
            'DB_CONFIG_DATABASE' => 'config_test',
        ];

        foreach ($expected as $envVar => $expectedValue) {
            $actual = env($envVar);
            if ($actual !== $expectedValue) {
                $msg = "ABORT: la variable {$envVar} es '{$actual}' pero deberia ser '{$expectedValue}'. ".
                    'Esto suele indicar que phpunit.xml no tiene force="true" o que '.
                    'bootstrap/cache/config.php tiene valores de .env (no .env.testing). '.
                    'Ejecutar "php artisan config:clear" y volver a correr los tests.';
                fwrite(STDERR, "\n\n*** {$msg} ***\n\n");
                exit(1);
            }
        }
    }

    /**
     * Crea las bases de datos de testing si no existen.
     */
    private function ensureTestDatabases(): void
    {
        $host = env('DB_CONFIG_HOST', '127.0.0.1');
        $port = env('DB_CONFIG_PORT', '3306');
        $user = env('DB_CONFIG_USERNAME', 'root');
        $pass = env('DB_CONFIG_PASSWORD', '');

        try {
            $pdo = new \PDO("mysql:host={$host};port={$port}", $user, $pass);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `config_test`');
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `pymes_test`');
        } catch (\PDOException $e) {
            // Si no puede crear BDs, los tests fallarán con error claro
        }
    }
}

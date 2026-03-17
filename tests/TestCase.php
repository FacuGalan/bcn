<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Indica si las migraciones de testing ya se ejecutaron.
     */
    protected static bool $migrationsRun = false;

    /**
     * Setup base para todos los tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Asegurar que las BDs de testing existen
        if (! static::$migrationsRun) {
            $this->ensureTestDatabases();
            static::$migrationsRun = true;
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

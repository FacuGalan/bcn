<?php

namespace Tests\Traits;

use App\Models\Comercio;
use App\Services\TenantService;
use Illuminate\Support\Facades\DB;

/**
 * Trait para tests que necesitan contexto multi-tenant.
 *
 * Estrategia: comercio PERMANENTE + tablas PERSISTENTES + DELETE selectivo.
 * - El comercio de test se crea una vez y nunca se borra.
 * - Las tablas tenant se crean si no existen y persisten entre ejecuciones.
 * - Entre tests: DELETE solo de tablas que los tests usan (rápido).
 * - Si cambia el schema: TEST_FORCE_RECREATE=1 php artisan test
 */
trait WithTenant
{
    protected Comercio $comercio;

    protected string $tenantPrefix;

    protected static ?int $cachedComercioId = null;

    protected static ?string $cachedPrefix = null;

    protected static bool $tablesChecked = false;

    /** Tablas que los tests realmente usan — solo estas se limpian */
    protected static array $testTables = [
        'tipos_iva',
        'sucursales',
        'cajas',
        'articulos',
        'articulos_sucursales',
        'stock',
        'movimientos_stock',
        'ventas',
        'ventas_detalle',
        'venta_pagos',
        'venta_detalle_precios',
        'venta_detalle_opcionales',
        'clientes',
        'clientes_sucursales',
        'formas_pago',
        'conceptos_pago',
        'cobros',
        'cobro_ventas',
        'cobro_pagos',
        'movimientos_cuenta_corriente',
        'movimientos_caja',
        'recetas',
        'receta_ingredientes',
        'promociones',
        'promocion_condiciones',
        'promocion_escalas',
        'promociones_especiales',
        'listas_precios',
        'lista_precio_articulos',
        'lista_precio_condiciones',
        'historial_precios',
        'categorias',
        'grupos_opcionales',
        'opcionales',
        'articulo_grupo_opcional',
        'articulo_grupo_opcional_opcion',
        'forma_pago_conceptos',
        'forma_pago_integraciones',
        'formas_pago_sucursales',
        'venta_promociones',
        'venta_detalle_promociones',
        'venta_pago_ajustes',
        'tipos_cambio',
        'monedas',
        'pedidos_mostrador_pagos',
        'pedido_mostrador_promociones',
        'pedido_mostrador_detalle_promociones',
        'pedido_mostrador_detalle_opcionales',
        'pedidos_mostrador_detalle',
        'pedidos_mostrador',
        'movimientos_puntos',
        'configuracion_puntos',
        'configuracion_puntos_sucursales',
        'integraciones_pago_eventos',
        'integraciones_pago_transacciones',
        'integraciones_pago_sucursales',
        'integraciones_pago',
    ];

    protected function setUpTenant(): void
    {
        // 0. CRITICO: verificar que estamos en BDs de test, NUNCA en pymes/config reales.
        //    Si por algun motivo (cache de config envenenado, env mal cargado, etc.) las
        //    conexiones apuntan a las BDs reales, abortar inmediatamente antes de hacer
        //    cualquier DELETE/DROP. Incidente que justifica esta defensa: 2026-05-04.
        $this->guardAgainstRealDatabases();

        // 1. Encontrar o crear comercio permanente
        if (! static::$cachedComercioId) {
            $this->ensurePermanentComercio();
        }

        $this->comercio = Comercio::find(static::$cachedComercioId);
        $this->tenantPrefix = static::$cachedPrefix;

        // 2. Configurar conexión tenant
        app(TenantService::class)->setComercio($this->comercio);

        // 3. Asegurar que las tablas existen (una vez por clase)
        if (! static::$tablesChecked) {
            $this->ensureTenantTablesExist();
            static::$tablesChecked = true;
        }

        // 4. Limpiar solo las tablas que los tests usan (~0.3s vs ~3.5s de todas)
        $this->cleanTestData();
    }

    protected function tearDownTenant(): void
    {
        // No-op: limpieza se hace en setUpTenant() del siguiente test.
    }

    /**
     * Aborta la suite si las conexiones apuntan a BDs que no son las de testing.
     * Es la última línea de defensa contra el bug de config cache que destruyó
     * datos de pymes real el 2026-05-04. Whitelist explícita, no blacklist.
     */
    private function guardAgainstRealDatabases(): void
    {
        $allowed = [
            'pymes' => 'pymes_test',
            'pymes_tenant' => 'pymes_test',
            'config' => 'config_test',
        ];

        foreach ($allowed as $connection => $expectedDb) {
            $actualDb = DB::connection($connection)->getDatabaseName();
            if ($actualDb !== $expectedDb) {
                $msg = "ABORT: la conexion '{$connection}' apunta a '{$actualDb}' pero deberia ser '{$expectedDb}'. ".
                    'Esto suele indicar que bootstrap/cache/config.php tiene valores de .env (no .env.testing). '.
                    'Ejecutar "php artisan config:clear" y volver a correr los tests. '.
                    'Si persiste, verificar que phpunit.xml tenga DB_DATABASE/DB_PYMES_DATABASE/DB_CONFIG_DATABASE con force="true".';
                fwrite(STDERR, "\n\n*** {$msg} ***\n\n");
                exit(1);
            }
        }
    }

    /**
     * DELETE rápido de solo las tablas que los tests usan.
     *
     * NO se resetea AUTO_INCREMENT: el ALTER TABLE es DDL (commit implícito por
     * statement) y duplicaba los round-trips a MySQL (~33k ALTERs por corrida).
     * Ningún test depende de IDs predecibles desde 1 — los tests usan los IDs que
     * devuelven los modelos creados, no literales. Quitarlo ~mitad el tiempo de la suite.
     */
    private function cleanTestData(): void
    {
        $prefix = static::$cachedPrefix;

        DB::connection('pymes_tenant')->statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach (static::$testTables as $table) {
            try {
                DB::connection('pymes_tenant')->statement("DELETE FROM `{$prefix}{$table}`");
            } catch (\Exception $e) {
                // Tabla podría no existir si es nueva
            }
        }

        DB::connection('pymes_tenant')->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function ensurePermanentComercio(): void
    {
        $comercio = Comercio::where('cuit', 'TEST-PERMANENT')->first();

        if (! $comercio) {
            $comercio = Comercio::forceCreate([
                'nombre' => 'Test Fixture Permanente',
                'cuit' => 'TEST-PERMANENT',
                'email' => 'test@permanent.com',
                'database_name' => 'pymes_test',
                'prefijo' => null,
                'max_usuarios' => 5,
            ]);

            $prefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT);
            $comercio->update(['prefijo' => $prefix]);
        }

        static::$cachedComercioId = $comercio->id;
        static::$cachedPrefix = str_pad($comercio->id, 6, '0', STR_PAD_LEFT).'_';
    }

    private function ensureTenantTablesExist(): void
    {
        $dbName = DB::connection('pymes')->getDatabaseName();

        if (env('TEST_FORCE_RECREATE', false)) {
            $this->dropTenantTables();
            $this->createTenantTables();

            return;
        }

        $markerTable = static::$cachedPrefix.'sucursales';
        $exists = DB::connection('pymes')
            ->selectOne(
                "SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND TABLE_TYPE = 'BASE TABLE'",
                [$dbName, $markerTable]
            );

        if ($exists->cnt > 0) {
            return;
        }

        $this->dropTenantTables();
        $this->createTenantTables();
    }

    protected function createTenantTables(): void
    {
        $sqlPath = database_path('sql/tenant_tables.sql');

        if (! file_exists($sqlPath)) {
            $this->fail('tenant_tables.sql no encontrado en database/sql/');
        }

        $sql = file_get_contents($sqlPath);
        $sql = str_replace('{{PREFIX}}', $this->tenantPrefix, $sql);

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn ($s) => ! empty($s) && $s !== ''
        );

        DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($statements as $statement) {
            // Saltar fragmentos de vistas mysqldump (placeholders rotos por explode ';')
            if (preg_match('/^\s*\d+\s+AS\s+`/i', $statement)) {
                continue;
            }

            try {
                DB::connection('pymes')->statement($statement);
            } catch (\Exception $e) {
                if (! str_contains($e->getMessage(), 'already exists')) {
                    DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 1');
                    throw $e;
                }
            }
        }

        DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function dropTenantTables(): void
    {
        $dbName = DB::connection('pymes')->getDatabaseName();
        $prefix = static::$cachedPrefix;

        $views = DB::connection('pymes')
            ->select('SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?', [$dbName, $prefix.'%']);

        foreach ($views as $view) {
            DB::connection('pymes')->statement("DROP VIEW IF EXISTS `{$view->TABLE_NAME}`");
        }

        $tables = DB::connection('pymes')
            ->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? AND TABLE_TYPE = 'BASE TABLE'", [$dbName, $prefix.'%']);

        DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            DB::connection('pymes')->statement("DROP TABLE IF EXISTS `{$table->TABLE_NAME}`");
        }

        DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 1');
    }
}

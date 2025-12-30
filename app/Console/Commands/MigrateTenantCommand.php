<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Comando para ejecutar migraciones en un tenant específico
 *
 * Este comando configura el prefijo de tablas para un comercio específico
 * y ejecuta las migraciones pendientes en ese tenant.
 *
 * Uso: php artisan tenant:migrate {comercio_id} [--seed] [--fresh]
 *
 * @package App\Console\Commands
 */
class MigrateTenantCommand extends Command
{
    /**
     * Nombre y firma del comando de consola
     */
    protected $signature = 'tenant:migrate
                            {comercio_id : ID del comercio}
                            {--seed : Ejecutar seeders después de migrar}
                            {--fresh : Eliminar todas las tablas y re-migrar}
                            {--seeder= : Seeder específico a ejecutar}';

    /**
     * Descripción del comando de consola
     */
    protected $description = 'Ejecuta migraciones para un tenant específico (comercio con prefijo de tablas)';

    /**
     * Servicio de gestión de tenants
     */
    protected TenantService $tenantService;

    /**
     * Constructor del comando
     */
    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    /**
     * Ejecuta el comando de consola
     */
    public function handle(): int
    {
        $comercioId = $this->argument('comercio_id');

        // Si es "all", ejecutar para todos los comercios
        if ($comercioId === 'all') {
            return $this->migrateAllTenants();
        }

        // Verificar que el comercio existe
        $comercio = Comercio::find($comercioId);
        if (!$comercio) {
            $this->error("Comercio con ID {$comercioId} no encontrado.");
            return 1;
        }

        $this->info("Migrando tenant: {$comercio->nombre} (ID: {$comercioId})");

        return $this->migrateTenant($comercio);
    }

    /**
     * Ejecuta migraciones para todos los tenants
     */
    protected function migrateAllTenants(): int
    {
        $comercios = Comercio::where('activo', true)->get();

        if ($comercios->isEmpty()) {
            $this->warn("No hay comercios activos.");
            return 0;
        }

        $this->info("Migrando {$comercios->count()} tenants...");

        $errores = 0;
        foreach ($comercios as $comercio) {
            $this->newLine();
            $this->info("=== {$comercio->nombre} (ID: {$comercio->id}) ===");

            $resultado = $this->migrateTenant($comercio);
            if ($resultado !== 0) {
                $errores++;
            }
        }

        $this->newLine();
        if ($errores > 0) {
            $this->error("Completado con {$errores} errores.");
            return 1;
        }

        $this->info("Todas las migraciones completadas exitosamente.");
        return 0;
    }

    /**
     * Ejecuta migraciones para un tenant específico
     */
    protected function migrateTenant(Comercio $comercio): int
    {
        try {
            // Configurar el tenant
            $this->tenantService->setComercio($comercio);
            $prefix = $comercio->getTablePrefix();
            $databaseName = $comercio->database_name ?? 'pymes';

            $this->info("  Prefijo: {$prefix}");
            $this->info("  Base de datos: {$databaseName}");

            // Configurar la conexión para las migraciones
            Config::set('database.connections.pymes_tenant.prefix', $prefix);
            Config::set('database.connections.pymes_tenant.database', $databaseName);
            DB::purge('pymes_tenant');
            DB::reconnect('pymes_tenant');

            // Verificar conexión
            $connection = DB::connection('pymes_tenant');
            $connection->setTablePrefix($prefix);

            // Ejecutar migraciones
            $migrateOptions = [
                '--database' => 'pymes_tenant',
                '--path' => 'database/migrations',
                '--force' => true,
            ];

            if ($this->option('fresh')) {
                $this->warn("  ⚠ Ejecutando migrate:fresh (esto eliminará todas las tablas del tenant)");
                Artisan::call('migrate:fresh', $migrateOptions);
            } else {
                Artisan::call('migrate', $migrateOptions);
            }

            $this->info(Artisan::output());

            // Ejecutar seeders si se solicitó
            if ($this->option('seed') || $this->option('seeder')) {
                $seederOptions = [
                    '--database' => 'pymes_tenant',
                    '--force' => true,
                ];

                if ($seeder = $this->option('seeder')) {
                    $seederOptions['--class'] = $seeder;
                }

                $this->info("  Ejecutando seeders...");
                Artisan::call('db:seed', $seederOptions);
                $this->info(Artisan::output());
            }

            $this->info("  ✓ Migración completada para {$comercio->nombre}");
            return 0;

        } catch (\Exception $e) {
            $this->error("  ✗ Error: " . $e->getMessage());
            return 1;
        }
    }
}

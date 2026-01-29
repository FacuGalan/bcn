<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class MigrateTenants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenants:migrate {--fresh : Drop all tables and re-run migrations} {--seed : Seed after migration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta migraciones para todos los tenants (comercios)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Iniciando migraciones multi-tenant...');
        $this->newLine();

        // Obtener todos los comercios
        $comercios = Comercio::all();

        if ($comercios->isEmpty()) {
            $this->warn('⚠️  No hay comercios en el sistema.');
            return Command::SUCCESS;
        }

        $this->info("📦 Encontrados {$comercios->count()} comercio(s)");
        $this->newLine();

        $tenantService = app(TenantService::class);

        foreach ($comercios as $comercio) {
            $this->info("🏢 Procesando: {$comercio->nombre} (ID: {$comercio->id})");
            $this->info("   Prefijo: {$comercio->getTablePrefix()}");
            $this->info("   Base de datos: {$comercio->database_name}");

            try {
                // Configurar el comercio activo
                $tenantService->setComercio($comercio);

                // Ejecutar migraciones en la conexión pymes_tenant
                if ($this->option('fresh')) {
                    $this->warn('   ⚠️  Ejecutando migrate:fresh...');
                    Artisan::call('migrate:fresh', [
                        '--database' => 'pymes_tenant',
                        '--force' => true,
                        '--seed' => $this->option('seed'),
                    ]);
                } else {
                    $this->info('   ⏳ Ejecutando migraciones...');
                    Artisan::call('migrate', [
                        '--database' => 'pymes_tenant',
                        '--force' => true,
                    ]);
                }

                $output = Artisan::output();

                if (str_contains($output, 'Nothing to migrate')) {
                    $this->comment('   ✅ Sin migraciones pendientes');
                } elseif (str_contains($output, 'FAIL') || str_contains($output, 'error')) {
                    $this->error('   ❌ Error en migración');
                    $this->line($output);
                } else {
                    $this->info('   ✅ Migraciones completadas');
                }

            } catch (\Exception $e) {
                $this->error('   ❌ Error: ' . $e->getMessage());
            }

            $this->newLine();
        }

        $this->info('✨ Proceso completado!');
        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\TenantService;
use Database\Seeders\MenuItemSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;

/**
 * Comando para poblar menú, roles y permisos de un comercio
 *
 * Este comando ejecuta los seeders de menú y permisos para un comercio específico,
 * asegurando que el contexto del tenant esté correctamente establecido.
 *
 * Uso: php artisan comercio:seed-menu {comercio_id}
 *
 * @package App\Console\Commands
 * @version 1.0.0
 */
class SeedComercioMenuCommand extends Command
{
    /**
     * Nombre y firma del comando de consola
     *
     * @var string
     */
    protected $signature = 'comercio:seed-menu {comercio_id : ID del comercio a poblar}';

    /**
     * Descripción del comando de consola
     *
     * @var string
     */
    protected $description = 'Ejecuta los seeders de menú, roles y permisos para un comercio específico';

    /**
     * Servicio de gestión de tenants
     *
     * @var TenantService
     */
    protected TenantService $tenantService;

    /**
     * Constructor del comando
     *
     * @param TenantService $tenantService Servicio de tenant
     */
    public function __construct(TenantService $tenantService)
    {
        parent::__construct();
        $this->tenantService = $tenantService;
    }

    /**
     * Ejecuta el comando de consola
     *
     * @return int Código de retorno (0 = éxito, 1 = error)
     */
    public function handle(): int
    {
        $comercioId = $this->argument('comercio_id');

        $this->info("┌─────────────────────────────────────────────────────┐");
        $this->info("│ Poblando menú y permisos para comercio ID: {$comercioId}");
        $this->info("└─────────────────────────────────────────────────────┘");

        // Verificar que el comercio existe
        $comercio = Comercio::find($comercioId);
        if (!$comercio) {
            $this->error("✗ Comercio con ID {$comercioId} no encontrado.");
            return 1;
        }

        $this->info("\n✓ Comercio encontrado: {$comercio->nombre}");
        $this->info("✓ Prefijo de tablas: {$comercio->getTablePrefix()}");

        // Establecer el comercio activo
        $this->tenantService->setComercio($comercio);

        try {
            // 1. Ejecutar MenuItemSeeder
            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info(" PASO 1: Creando estructura del menú");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->call(MenuItemSeeder::class);

            // 2. Ejecutar RolePermissionSeeder
            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info(" PASO 2: Creando roles y permisos");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->call(RolePermissionSeeder::class);

            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("✅ PROCESO COMPLETADO EXITOSAMENTE");
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->newLine();

            $this->table(
                ['Información', 'Valor'],
                [
                    ['Comercio', $comercio->nombre],
                    ['Prefijo', $comercio->getTablePrefix()],
                    ['Items de Menú', '20 (4 padres + 16 hijos)'],
                    ['Permisos', '20 (menu.{slug})'],
                    ['Roles', '5 (Super Admin, Admin, Gerente, Vendedor, Visualizador)'],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->error("✗ ERROR AL EJECUTAR SEEDERS");
            $this->error("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->error("Mensaje: " . $e->getMessage());
            $this->error("Archivo: " . $e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Comercio;
use App\Models\Sucursal;
use App\Models\Tesoreria;
use App\Models\MenuItem;

/**
 * Comando para configurar el sistema de tesorería en bases de datos existentes
 *
 * Uso: php artisan tesoreria:setup
 */
class SetupTesoreriaCommand extends Command
{
    protected $signature = 'tesoreria:setup
                            {--comercio= : ID del comercio específico (opcional, por defecto todos)}
                            {--solo-tablas : Solo crear tablas, no crear tesorerías}
                            {--solo-menu : Solo crear items de menú}';

    protected $description = 'Configura el sistema de tesorería: crea tablas, tesorerías iniciales y menú';

    public function handle(): int
    {
        $this->info('=== Configuración del Sistema de Tesorería ===');
        $this->newLine();

        $comercioId = $this->option('comercio');
        $soloTablas = $this->option('solo-tablas');
        $soloMenu = $this->option('solo-menu');

        if ($soloMenu) {
            $this->crearMenu();
            return self::SUCCESS;
        }

        // Obtener comercios a procesar
        $comercios = $comercioId
            ? Comercio::where('id', $comercioId)->get()
            : Comercio::where('activo', true)->get();

        if ($comercios->isEmpty()) {
            $this->error('No se encontraron comercios para procesar');
            return self::FAILURE;
        }

        $this->info("Procesando {$comercios->count()} comercio(s)...");
        $this->newLine();

        foreach ($comercios as $comercio) {
            $this->procesarComercio($comercio, $soloTablas);
        }

        if (!$soloTablas) {
            $this->crearMenu();
        }

        $this->newLine();
        $this->info('=== Configuración completada ===');

        return self::SUCCESS;
    }

    protected function procesarComercio(Comercio $comercio, bool $soloTablas): void
    {
        $this->info("Procesando comercio: {$comercio->nombre} (ID: {$comercio->id})");

        $prefix = $comercio->database_prefix;
        $connectionName = 'pymes_tenant';

        // Configurar conexión temporal
        config(["database.connections.{$connectionName}.prefix" => $prefix]);
        DB::purge($connectionName);

        try {
            // 1. Crear/actualizar tablas
            $this->crearTablas($connectionName, $prefix);

            if (!$soloTablas) {
                // 2. Crear tesorerías para sucursales
                $this->crearTesoreriasSucursales($comercio);
            }

            $this->info("  ✓ Comercio {$comercio->nombre} procesado correctamente");

        } catch (\Exception $e) {
            $this->error("  ✗ Error procesando {$comercio->nombre}: {$e->getMessage()}");
        }

        $this->newLine();
    }

    protected function crearTablas(string $connection, string $prefix): void
    {
        $this->line('  Verificando/creando tablas...');

        // Verificar si ya existen las tablas
        $tablasNuevas = [
            'tesorerias',
            'movimientos_tesoreria',
            'cuentas_bancarias',
            'depositos_bancarios',
            'provision_fondos',
            'rendicion_fondos',
            'arqueos_tesoreria',
        ];

        foreach ($tablasNuevas as $tabla) {
            $tablaConPrefijo = $prefix . $tabla;
            if (!DB::connection($connection)->getSchemaBuilder()->hasTable($tabla)) {
                $this->line("    Creando tabla: {$tablaConPrefijo}");
            } else {
                $this->line("    Tabla ya existe: {$tablaConPrefijo}");
            }
        }

        // Verificar columnas en grupos_cierre
        $this->verificarColumnasGruposCierre($connection, $prefix);
    }

    protected function verificarColumnasGruposCierre(string $connection, string $prefix): void
    {
        $tabla = 'grupos_cierre';

        $columnas = DB::connection($connection)
            ->getSchemaBuilder()
            ->getColumnListing($tabla);

        if (!in_array('fondo_comun', $columnas)) {
            $this->line("    Agregando columna fondo_comun a {$prefix}{$tabla}");
            DB::connection($connection)->statement("
                ALTER TABLE `{$prefix}{$tabla}`
                ADD COLUMN `fondo_comun` tinyint(1) NOT NULL DEFAULT '0' AFTER `nombre`
            ");
        }

        if (!in_array('saldo_fondo_comun', $columnas)) {
            $this->line("    Agregando columna saldo_fondo_comun a {$prefix}{$tabla}");
            DB::connection($connection)->statement("
                ALTER TABLE `{$prefix}{$tabla}`
                ADD COLUMN `saldo_fondo_comun` decimal(14,2) DEFAULT '0.00' AFTER `fondo_comun`
            ");
        }

        if (!in_array('tesoreria_id', $columnas)) {
            $this->line("    Agregando columna tesoreria_id a {$prefix}{$tabla}");
            DB::connection($connection)->statement("
                ALTER TABLE `{$prefix}{$tabla}`
                ADD COLUMN `tesoreria_id` bigint(20) unsigned DEFAULT NULL AFTER `saldo_fondo_comun`
            ");
        }
    }

    protected function crearTesoreriasSucursales(Comercio $comercio): void
    {
        $this->line('  Creando tesorerías para sucursales...');

        // Configurar conexión para el comercio
        config(['database.connections.pymes_tenant.prefix' => $comercio->database_prefix]);
        DB::purge('pymes_tenant');

        $sucursales = Sucursal::where('activo', true)->get();

        foreach ($sucursales as $sucursal) {
            $tesoreria = Tesoreria::firstOrCreate(
                ['sucursal_id' => $sucursal->id],
                [
                    'nombre' => 'Tesorería Principal',
                    'saldo_actual' => 0,
                    'activo' => true,
                ]
            );

            if ($tesoreria->wasRecentlyCreated) {
                $this->line("    ✓ Tesorería creada para sucursal: {$sucursal->nombre}");
            } else {
                $this->line("    - Tesorería ya existe para sucursal: {$sucursal->nombre}");
            }
        }
    }

    protected function crearMenu(): void
    {
        $this->info('Verificando menú de tesorería...');

        // Verificar si ya existe el menú de tesorería
        $existeMenu = MenuItem::where('slug', 'tesoreria')->exists();

        if ($existeMenu) {
            $this->line('  - Menú de tesorería ya existe');
            return;
        }

        $this->line('  Creando menú de tesorería...');

        $tesoreria = MenuItem::create([
            'parent_id' => null,
            'nombre' => 'Tesorería',
            'slug' => 'tesoreria',
            'icono' => 'heroicon-o-banknotes',
            'route_type' => 'none',
            'route_value' => null,
            'orden' => 2,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $tesoreria->id,
            'nombre' => 'Gestión',
            'slug' => 'gestion-tesoreria',
            'icono' => 'heroicon-o-currency-dollar',
            'route_type' => 'route',
            'route_value' => 'tesoreria.index',
            'orden' => 1,
            'activo' => true,
        ]);

        MenuItem::create([
            'parent_id' => $tesoreria->id,
            'nombre' => 'Reportes',
            'slug' => 'reportes-tesoreria',
            'icono' => 'heroicon-o-chart-bar-square',
            'route_type' => 'route',
            'route_value' => 'tesoreria.reportes',
            'orden' => 2,
            'activo' => true,
        ]);

        $this->info('  ✓ Menú de tesorería creado');
    }
}

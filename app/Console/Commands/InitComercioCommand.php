<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Services\TenantService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comando para inicializar las tablas de un nuevo comercio
 *
 * Este comando crea todas las tablas necesarias con prefijo para un comercio específico
 * en la base de datos PYMES. Incluye tablas de roles, permisos, artículos, ventas, etc.
 *
 * Uso: php artisan comercio:init {comercio_id}
 *
 * @package App\Console\Commands
 * @author BCN Pymes
 * @version 1.0.0
 */
class InitComercioCommand extends Command
{
    /**
     * Nombre y firma del comando de consola
     *
     * @var string
     */
    protected $signature = 'comercio:init {comercio_id : ID del comercio a inicializar}';

    /**
     * Descripción del comando de consola
     *
     * @var string
     */
    protected $description = 'Inicializa las tablas necesarias para un nuevo comercio con su prefijo correspondiente';

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

        $this->info("Inicializando comercio ID: {$comercioId}");

        // Verificar que el comercio existe
        $comercio = Comercio::find($comercioId);
        if (!$comercio) {
            $this->error("Comercio con ID {$comercioId} no encontrado.");
            return 1;
        }

        $this->info("Comercio encontrado: {$comercio->nombre}");

        // Establecer el comercio activo
        $this->tenantService->setComercio($comercio);
        $prefix = $comercio->getTablePrefix();

        $this->info("Prefijo de tablas: {$prefix}");

        try {
            $connection = $this->tenantService->getTenantConnection();

            // Crear tablas de roles y permisos (Spatie)
            $this->createRolesTable($connection, $prefix);
            $this->createPermissionsTable($connection, $prefix);
            $this->createModelHasPermissionsTable($connection, $prefix);
            $this->createModelHasRolesTable($connection, $prefix);
            $this->createRoleHasPermissionsTable($connection, $prefix);

            // Crear tabla de menú dinámico
            $this->createMenuItemsTable($connection, $prefix);

            // Crear tablas del negocio
            $this->createArticulosTable($connection, $prefix);
            $this->createVentasEncabezadoTable($connection, $prefix);

            $this->info("✓ Tablas creadas exitosamente para el comercio {$comercio->nombre}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error al crear tablas: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Crea la tabla de roles con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createRolesTable(string $connection, string $prefix): void
    {
        $tableName = 'roles'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->id();
            $table->string('name')->comment('Nombre del rol');
            $table->string('guard_name')->comment('Guard de autenticación');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla de permisos con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createPermissionsTable(string $connection, string $prefix): void
    {
        $tableName = 'permissions'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->id();
            $table->string('name')->comment('Nombre del permiso');
            $table->string('guard_name')->comment('Guard de autenticación');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla pivot model_has_permissions con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createModelHasPermissionsTable(string $connection, string $prefix): void
    {
        $tableName = 'model_has_permissions'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions') // Sin prefijo, la conexión lo aplica
                ->onDelete('cascade');

            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_primary');
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla pivot model_has_roles con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createModelHasRolesTable(string $connection, string $prefix): void
    {
        $tableName = 'model_has_roles'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);

            $table->foreign('role_id')
                ->references('id')
                ->on('roles') // Sin prefijo, la conexión lo aplica
                ->onDelete('cascade');

            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_primary');
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla pivot role_has_permissions con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createRoleHasPermissionsTable(string $connection, string $prefix): void
    {
        $tableName = 'role_has_permissions'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')
                ->on('permissions') // Sin prefijo, la conexión lo aplica
                ->onDelete('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles') // Sin prefijo, la conexión lo aplica
                ->onDelete('cascade');

            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_primary');
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla de items del menú con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createMenuItemsTable(string $connection, string $prefix): void
    {
        $tableName = 'menu_items'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) use ($tableName) {
            $table->id();

            // Jerarquía
            $table->unsignedBigInteger('parent_id')->nullable()->comment('ID del item padre (null = raíz)');

            // Información básica
            $table->string('nombre', 100)->comment('Nombre visible en el menú');
            $table->string('slug', 100)->unique()->comment('Identificador único (ej: ventas.nueva-venta)');
            $table->string('icono', 100)->nullable()->comment('Icono de Heroicons (ej: heroicon-o-shopping-cart)');

            // Configuración de navegación
            $table->enum('route_type', ['route', 'component', 'none'])
                ->default('none')
                ->comment('Tipo: route=ruta Laravel, component=Livewire, none=solo agrupa');
            $table->string('route_value', 255)->nullable()->comment('Valor de la ruta o nombre del componente');

            // Visualización
            $table->integer('orden')->default(0)->comment('Orden de aparición en el menú');
            $table->boolean('activo')->default(true)->comment('Si está activo y visible en el menú');

            $table->timestamps();

            // Foreign key
            $table->foreign('parent_id')
                ->references('id')
                ->on($tableName)
                ->cascadeOnDelete();

            // Índices
            $table->index('parent_id');
            $table->index('activo');
            $table->index(['parent_id', 'orden']);
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla de artículos con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createArticulosTable(string $connection, string $prefix): void
    {
        $tableName = 'articulos'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->id();
            $table->string('codigo')->unique()->comment('Código del artículo');
            $table->string('nombre')->comment('Nombre del artículo');
            $table->text('descripcion')->nullable()->comment('Descripción del artículo');
            $table->decimal('precio', 10, 2)->default(0)->comment('Precio del artículo');
            $table->integer('stock')->default(0)->comment('Stock disponible');
            $table->boolean('activo')->default(true)->comment('Estado del artículo');
            $table->timestamps();
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }

    /**
     * Crea la tabla de ventas_encabezado con prefijo
     *
     * @param string $connection Nombre de la conexión
     * @param string $prefix Prefijo del comercio
     * @return void
     */
    protected function createVentasEncabezadoTable(string $connection, string $prefix): void
    {
        $tableName = 'ventas_encabezado'; // Sin prefijo, la conexión lo aplica automáticamente
        $fullTableName = $prefix . $tableName;

        if (Schema::connection($connection)->hasTable($tableName)) {
            $this->warn("  - Tabla {$fullTableName} ya existe, omitiendo...");
            return;
        }

        Schema::connection($connection)->create($tableName, function ($table) {
            $table->id();
            $table->string('numero_venta')->unique()->comment('Número de venta');
            $table->date('fecha')->comment('Fecha de la venta');
            $table->decimal('total', 10, 2)->default(0)->comment('Total de la venta');
            $table->string('cliente')->nullable()->comment('Cliente');
            $table->timestamps();
        });

        $this->info("  ✓ Tabla {$fullTableName} creada");
    }
}

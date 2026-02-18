<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\PermisoFuncional;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class ProvisionComercioCommand extends Command
{
    protected $signature = 'comercio:provision
        {--nombre= : Nombre del comercio}
        {--database=pymes : Base de datos donde crear las tablas tenant}
        {--mail= : Email del comercio (usado como login del admin)}';

    protected $description = 'Provisiona un comercio completo: tablas tenant, usuario admin, sucursal, caja, tipos IVA, formas de pago, roles y permisos';

    protected string $prefix;
    protected string $database;

    public function handle(): int
    {
        $nombre = $this->option('nombre');
        $mail = $this->option('mail');
        $this->database = $this->option('database');

        // ── Paso 1: Validar parámetros ──
        if (!$nombre || !$mail) {
            $this->error('Los parámetros --nombre y --mail son obligatorios.');
            return self::FAILURE;
        }

        if (Comercio::where('email', $mail)->exists()) {
            $this->error("Ya existe un comercio con el email '{$mail}'.");
            return self::FAILURE;
        }

        if (User::where('email', $mail)->exists()) {
            $this->error("Ya existe un usuario con el email '{$mail}'.");
            return self::FAILURE;
        }

        $this->info('═══════════════════════════════════════════');
        $this->info("  Provisionando comercio: {$nombre}");
        $this->info('═══════════════════════════════════════════');

        try {
            DB::connection('config')->beginTransaction();

            // ── Paso 2: Crear comercio ──
            $this->info('[1/10] Creando comercio...');
            $comercioId = DB::connection('config')->table('comercios')->insertGetId([
                'nombre' => $nombre,
                'email' => $mail,
                'cuit' => 'PROV-' . time(), // placeholder único
                'database_name' => $this->database,
                'max_usuarios' => 5,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $prefijo = str_pad($comercioId, 6, '0', STR_PAD_LEFT);
            DB::connection('config')->table('comercios')
                ->where('id', $comercioId)
                ->update(['prefijo' => $prefijo]);
            $comercio = Comercio::find($comercioId);
            $this->prefix = $prefijo . '_';
            $this->info("    Comercio #{$comercioId} — Prefijo: {$this->prefix}");

            // ── Paso 3: Crear usuario admin ──
            $this->info('[2/10] Creando usuario administrador...');
            $password = 'admin1234';
            $username = 'admin';
            $user = User::create([
                'name' => 'Administrador',
                'username' => $username,
                'email' => $mail,
                'password' => $password, // se hashea por cast 'hashed'
                'activo' => true,
                'email_verified_at' => now(),
            ]);
            $user->setPasswordVisible($password);
            $this->info("    Usuario: {$mail} (username: {$username}) / {$password}");

            // ── Paso 4: Asociar usuario ↔ comercio ──
            $this->info('[3/10] Asociando usuario al comercio...');
            $user->comercios()->attach($comercio->id);

            DB::connection('config')->commit();
        } catch (\Exception $e) {
            DB::connection('config')->rollBack();
            $this->error('Error creando comercio/usuario: ' . $e->getMessage());
            return self::FAILURE;
        }

        try {
            // ── Paso 5: Crear tablas tenant ──
            $this->info('[4/10] Creando tablas tenant...');
            $this->createTenantTables();
            $this->info("    Tablas creadas con prefijo {$this->prefix} en BD '{$this->database}'");

            // ── Paso 6: Configurar conexión tenant ──
            $this->info('[5/10] Configurando conexión tenant...');
            $this->configureTenantConnection();

            // ── Paso 7: Seed — Sucursal ──
            $this->info('[6/10] Creando sucursal principal...');
            $sucursalId = $this->seedSucursal();

            // ── Paso 8: Seed — Caja ──
            $this->info('[7/10] Creando caja...');
            $this->seedCaja($sucursalId);

            // ── Paso 9: Seed — Tipos IVA ──
            $this->info('[8/10] Creando tipos de IVA...');
            $this->seedTiposIva();

            // ── Paso 10-11: Seed — Conceptos y Formas de Pago ──
            $this->info('[9/10] Creando conceptos y formas de pago...');
            $this->seedConceptosYFormasPago();

            // ── Paso 12-14: Seed — Roles, Permisos y Asignación ──
            $this->info('[10/10] Creando roles, permisos y asignando rol admin...');
            $this->seedRolesYPermisos($user);

            // ── Marcar migraciones como ejecutadas ──
            $this->markMigrationsAsRun();

        } catch (\Exception $e) {
            $this->error('Error en provisioning tenant: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }

        // ── Resumen final ──
        $this->newLine();
        $this->info('═══════════════════════════════════════════');
        $this->info('  COMERCIO PROVISIONADO EXITOSAMENTE');
        $this->info('═══════════════════════════════════════════');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['Comercio ID', $comercio->id],
                ['Nombre', $nombre],
                ['Prefijo', $this->prefix],
                ['Base de datos', $this->database],
                ['Email (login)', $mail],
                ['Username', $username],
                ['Contraseña', $password],
                ['Sucursal', 'Sucursal Principal (SUC001)'],
                ['Caja', 'Caja 1 (CAJA001)'],
                ['Tipos IVA', '3 (Exento, 10.5%, 21%)'],
                ['Conceptos pago', '7'],
                ['Formas pago', '7 (5 + Cuenta Corriente + Mixta)'],
                ['Roles', '5 (Super Admin, Admin, Gerente, Vendedor, Visualizador)'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * Crea las tablas tenant ejecutando el template SQL
     */
    protected function createTenantTables(): void
    {
        $sqlPath = database_path('sql/tenant_tables.sql');

        if (!file_exists($sqlPath)) {
            throw new \RuntimeException("No se encontró el archivo SQL: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);

        // Reemplazar placeholder con prefijo real
        $sql = str_replace('{{PREFIX}}', $this->prefix, $sql);

        // Prefixar constraint names que no tienen el prefijo (evita colisiones entre tenants)
        $prefix = preg_quote($this->prefix, '/');
        $sql = preg_replace(
            '/CONSTRAINT `(?!' . $prefix . ')/',
            'CONSTRAINT `' . $this->prefix,
            $sql
        );

        // Prefixar UNIQUE KEY / KEY names que no tienen el prefijo
        $sql = preg_replace(
            '/UNIQUE KEY `(?!' . $prefix . ')/',
            'UNIQUE KEY `' . $this->prefix,
            $sql
        );

        // Limpiar AUTO_INCREMENT=N para que empiecen en 1
        $sql = preg_replace('/\s*AUTO_INCREMENT=\d+/', '', $sql);

        // Ejecutar contra la BD indicada
        DB::connection('pymes')->unprepared($sql);
    }

    /**
     * Configura la conexión pymes_tenant con el prefijo del nuevo comercio
     */
    protected function configureTenantConnection(): void
    {
        Config::set('database.connections.pymes_tenant.prefix', $this->prefix);
        Config::set('database.connections.pymes_tenant.database', $this->database);

        DB::purge('pymes_tenant');
        DB::reconnect('pymes_tenant');

        $connection = DB::connection('pymes_tenant');
        $connection->setTablePrefix($this->prefix);
    }

    /**
     * Crea la sucursal principal
     */
    protected function seedSucursal(): int
    {
        $now = now();

        DB::connection('pymes_tenant')->table('sucursales')->insert([
            'nombre' => 'Sucursal Principal',
            'codigo' => 'SUC001',
            'es_principal' => true,
            'activa' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::connection('pymes_tenant')->getPdo()->lastInsertId();
    }

    /**
     * Crea la caja principal
     */
    protected function seedCaja(int $sucursalId): void
    {
        $now = now();

        DB::connection('pymes_tenant')->table('cajas')->insert([
            'nombre' => 'Caja 1',
            'codigo' => 'CAJA001',
            'numero' => 1,
            'sucursal_id' => $sucursalId,
            'activo' => true,
            'estado' => 'cerrada',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Crea los tipos de IVA estándar
     */
    protected function seedTiposIva(): void
    {
        $now = now();

        DB::connection('pymes_tenant')->table('tipos_iva')->insert([
            [
                'nombre' => 'Exento',
                'porcentaje' => 0.00,
                'codigo' => '3',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'IVA 10.5%',
                'porcentaje' => 10.50,
                'codigo' => '4',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'nombre' => 'IVA 21%',
                'porcentaje' => 21.00,
                'codigo' => '5',
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Crea conceptos de pago y formas de pago con cuotas y conceptos para mixta
     */
    protected function seedConceptosYFormasPago(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        // ── Conceptos de Pago (7) ──
        $conceptos = [
            ['codigo' => 'efectivo',         'nombre' => 'Efectivo',          'permite_cuotas' => false, 'permite_vuelto' => true,  'orden' => 1],
            ['codigo' => 'tarjeta_debito',   'nombre' => 'Tarjeta de Débito', 'permite_cuotas' => false, 'permite_vuelto' => false, 'orden' => 2],
            ['codigo' => 'tarjeta_credito',  'nombre' => 'Tarjeta de Crédito','permite_cuotas' => true,  'permite_vuelto' => false, 'orden' => 3],
            ['codigo' => 'transferencia',    'nombre' => 'Transferencia',     'permite_cuotas' => false, 'permite_vuelto' => false, 'orden' => 4],
            ['codigo' => 'wallet',           'nombre' => 'Billetera Virtual', 'permite_cuotas' => false, 'permite_vuelto' => false, 'orden' => 5],
            ['codigo' => 'cheque',           'nombre' => 'Cheque',            'permite_cuotas' => false, 'permite_vuelto' => false, 'orden' => 6],
            ['codigo' => 'credito_cliente',  'nombre' => 'Crédito Cliente',   'permite_cuotas' => false, 'permite_vuelto' => false, 'orden' => 7],
        ];

        foreach ($conceptos as $c) {
            $db->table('conceptos_pago')->insert(array_merge($c, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Obtener IDs de conceptos por código
        $conceptoIds = $db->table('conceptos_pago')->pluck('id', 'codigo');

        // ── Formas de Pago (7) ──
        $formasPago = [
            [
                'nombre' => 'Efectivo',
                'codigo' => 'EFEC',
                'concepto_pago_id' => $conceptoIds['efectivo'],
                'concepto' => 'efectivo',
                'permite_cuotas' => false,
                'es_mixta' => false,
            ],
            [
                'nombre' => 'Tarjeta de Débito',
                'codigo' => 'TDEB',
                'concepto_pago_id' => $conceptoIds['tarjeta_debito'],
                'concepto' => 'tarjeta_debito',
                'permite_cuotas' => false,
                'es_mixta' => false,
            ],
            [
                'nombre' => 'Tarjeta de Crédito',
                'codigo' => 'TCRE',
                'concepto_pago_id' => $conceptoIds['tarjeta_credito'],
                'concepto' => 'tarjeta_credito',
                'permite_cuotas' => true,
                'es_mixta' => false,
            ],
            [
                'nombre' => 'Transferencia',
                'codigo' => 'TRAN',
                'concepto_pago_id' => $conceptoIds['transferencia'],
                'concepto' => 'transferencia',
                'permite_cuotas' => false,
                'es_mixta' => false,
            ],
            [
                'nombre' => 'MercadoPago',
                'codigo' => 'MPAG',
                'concepto_pago_id' => $conceptoIds['wallet'],
                'concepto' => 'wallet',
                'permite_cuotas' => false,
                'es_mixta' => false,
            ],
            [
                'nombre' => 'Cuenta Corriente',
                'codigo' => 'CTACTE',
                'concepto_pago_id' => $conceptoIds['credito_cliente'],
                'concepto' => 'otro',
                'permite_cuotas' => false,
                'es_mixta' => false,
            ],
            [
                'nombre' => 'Pago Mixto',
                'codigo' => 'MIXTO',
                'concepto_pago_id' => null,
                'concepto' => 'otro',
                'permite_cuotas' => false,
                'es_mixta' => true,
            ],
        ];

        foreach ($formasPago as $fp) {
            $db->table('formas_pago')->insert(array_merge($fp, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // Obtener IDs de formas de pago por código
        $formaIds = $db->table('formas_pago')->pluck('id', 'codigo');

        // ── Cuotas para Tarjeta de Crédito ──
        $cuotas = [1, 3, 6, 9, 12];
        foreach ($cuotas as $cant) {
            $db->table('formas_pago_cuotas')->insert([
                'forma_pago_id' => $formaIds['TCRE'],
                'cantidad_cuotas' => $cant,
                'recargo_porcentaje' => 0.00,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Conceptos permitidos para Pago Mixto ──
        $conceptosMixta = ['efectivo', 'tarjeta_debito', 'tarjeta_credito', 'transferencia', 'wallet'];
        foreach ($conceptosMixta as $codigoConcepto) {
            $db->table('forma_pago_conceptos')->insert([
                'forma_pago_id' => $formaIds['MIXTO'],
                'concepto_pago_id' => $conceptoIds[$codigoConcepto],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Crea roles, permisos y asigna rol Super Administrador al usuario admin
     */
    protected function seedRolesYPermisos(User $user): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        // ── Roles (5) ──
        $rolesData = [
            'Super Administrador',
            'Administrador',
            'Gerente',
            'Vendedor',
            'Visualizador',
        ];

        $roleIds = [];
        foreach ($rolesData as $roleName) {
            $db->table('roles')->insert([
                'name' => $roleName,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roleIds[$roleName] = (int) $db->getPdo()->lastInsertId();
        }

        // ── Permisos de menú (crear si no existen en tabla compartida) ──
        $menuItems = MenuItem::all();
        $permissionIds = [];

        foreach ($menuItems as $item) {
            $permName = $item->getPermissionName();
            $perm = Permission::firstOrCreate(
                ['name' => $permName, 'guard_name' => 'web'],
                ['created_at' => $now, 'updated_at' => $now]
            );
            $permissionIds[$permName] = $perm->id;
        }

        // ── Permisos funcionales (sincronizar con Spatie) ──
        PermisoFuncional::syncAllToSpatie();
        $funcPermissions = Permission::where('name', 'like', PermisoFuncional::PERMISSION_PREFIX . '%')
            ->pluck('id', 'name')
            ->toArray();

        // Todos los IDs de permisos
        $allPermIds = array_merge(array_values($permissionIds), array_values($funcPermissions));

        // ── Asignar permisos a roles ──

        // Super Administrador y Administrador: todos los permisos
        foreach (['Super Administrador', 'Administrador'] as $roleName) {
            $inserts = array_map(fn($pid) => [
                'permission_id' => $pid,
                'role_id' => $roleIds[$roleName],
            ], $allPermIds);
            $db->table('role_has_permissions')->insert($inserts);
        }

        // Gerente: ventas + cajas + artículos + empresa
        $gerentePerms = collect($permissionIds)->filter(function ($id, $name) {
            return str_starts_with($name, 'menu.ventas')
                || str_starts_with($name, 'menu.cajas')
                || str_starts_with($name, 'menu.turno-actual')
                || str_starts_with($name, 'menu.historial-turnos')
                || str_starts_with($name, 'menu.movimientos-manuales')
                || str_starts_with($name, 'menu.tesoreria')
                || str_starts_with($name, 'menu.reportes-cajas')
                || str_starts_with($name, 'menu.articulos')
                || $name === 'menu.listado-articulos'
                || $name === 'menu.categorias'
                || $name === 'menu.etiquetas'
                || $name === 'menu.listas-precios'
                || $name === 'menu.promociones'
                || $name === 'menu.promociones-especiales'
                || $name === 'menu.configuracion'
                || $name === 'menu.empresa';
        });
        if ($gerentePerms->isNotEmpty()) {
            $db->table('role_has_permissions')->insert(
                $gerentePerms->map(fn($pid) => [
                    'permission_id' => $pid,
                    'role_id' => $roleIds['Gerente'],
                ])->values()->toArray()
            );
        }

        // Vendedor: ventas (nueva y listado) + turno actual
        $vendedorPerms = collect($permissionIds)->filter(function ($id, $name) {
            return $name === 'menu.ventas'
                || $name === 'menu.nueva-venta'
                || $name === 'menu.listado-ventas'
                || $name === 'menu.cajas'
                || $name === 'menu.turno-actual';
        });
        if ($vendedorPerms->isNotEmpty()) {
            $db->table('role_has_permissions')->insert(
                $vendedorPerms->map(fn($pid) => [
                    'permission_id' => $pid,
                    'role_id' => $roleIds['Vendedor'],
                ])->values()->toArray()
            );
        }

        // Visualizador: reportes
        $visualizadorPerms = collect($permissionIds)->filter(function ($id, $name) {
            return $name === 'menu.ventas'
                || $name === 'menu.reportes-ventas';
        });
        if ($visualizadorPerms->isNotEmpty()) {
            $db->table('role_has_permissions')->insert(
                $visualizadorPerms->map(fn($pid) => [
                    'permission_id' => $pid,
                    'role_id' => $roleIds['Visualizador'],
                ])->values()->toArray()
            );
        }

        // ── Asignar rol Super Administrador al usuario admin ──
        $db->table('model_has_roles')->insert([
            'role_id' => $roleIds['Super Administrador'],
            'model_type' => User::class,
            'model_id' => $user->id,
            'sucursal_id' => 0, // acceso a todas las sucursales
        ]);

        $this->info("    Roles: " . implode(', ', array_keys($roleIds)));
        $this->info("    Permisos asignados: " . count($allPermIds) . " permisos × roles correspondientes");
        $this->info("    Usuario {$user->email} → Super Administrador");
    }

    /**
     * Marca todas las migraciones existentes como ejecutadas en la tabla {PREFIX}migrations
     * Esto evita que php artisan migrate intente re-ejecutarlas sobre este comercio.
     */
    protected function markMigrationsAsRun(): void
    {
        $migrationFiles = collect(scandir(database_path('migrations')))
            ->filter(fn($f) => str_ends_with($f, '.php'))
            ->map(fn($f) => str_replace('.php', '', $f))
            ->values();

        $db = DB::connection('pymes_tenant');

        foreach ($migrationFiles as $migration) {
            $db->table('migrations')->insert([
                'migration' => $migration,
                'batch' => 1,
            ]);
        }

        $this->info("    Migraciones marcadas: {$migrationFiles->count()}");
    }
}

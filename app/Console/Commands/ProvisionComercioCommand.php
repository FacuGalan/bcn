<?php

namespace App\Console\Commands;

use App\Models\Comercio;
use App\Models\MenuItem;
use App\Models\PermisoFuncional;
use App\Models\Permission;
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
        if (! $nombre || ! $mail) {
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
                'cuit' => 'PROV-'.time(), // placeholder único
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
            $this->prefix = $prefijo.'_';
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
            $user->save();
            $this->info("    Usuario: {$mail} (username: {$username}) / {$password}");

            // ── Paso 4: Asociar usuario ↔ comercio ──
            $this->info('[3/10] Asociando usuario al comercio...');
            $user->comercios()->attach($comercio->id);

            DB::connection('config')->commit();
        } catch (\Exception $e) {
            DB::connection('config')->rollBack();
            $this->error('Error creando comercio/usuario: '.$e->getMessage());

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
            $this->info('[6/13] Creando sucursal principal...');
            $sucursalId = $this->seedSucursal($comercioId);

            // ── Paso 8: Seed — Lista de Precios Base ──
            $this->info('[7/13] Creando lista de precios base...');
            $this->seedListaPreciosBase($sucursalId);

            // ── Paso 9: Seed — Caja ──
            $this->info('[8/13] Creando caja...');
            $this->seedCaja($sucursalId);

            // ── Paso 10: Seed — Tipos IVA ──
            $this->info('[9/13] Creando tipos de IVA...');
            $this->seedTiposIva();

            // ── Paso 11: Seed — Conceptos y Formas de Pago ──
            $this->info('[10/13] Creando conceptos y formas de pago...');
            $this->seedConceptosYFormasPago();

            // ── Paso 11b: Seed — Formas y Canales de Venta ──
            $this->info('[10b/13] Creando formas y canales de venta...');
            $this->seedFormasYCanalesVenta();

            // ── Paso 12: Seed — Monedas ──
            $this->info('[11/13] Creando monedas...');
            $this->seedMonedas();

            // ── Paso 13: Seed — Conceptos Movimiento Cuenta ──
            $this->info('[12/14] Creando conceptos de movimiento de cuenta...');
            $this->seedConceptosMovimientoCuenta();

            $this->seedImpuestos();

            $this->seedCostos();

            // ── Paso 14: Seed — Catálogo de Integraciones de Pago ──
            $this->info('[13/14] Sembrando catálogo de integraciones de pago...');
            $this->seedIntegracionesPago();

            // ── Paso 15: Seed — Roles, Permisos y Asignación ──
            $this->info('[14/14] Creando roles, permisos y asignando rol admin...');
            $this->seedRolesYPermisos($user);

            // ── Marcar migraciones como ejecutadas ──
            $this->markMigrationsAsRun();

        } catch (\Exception $e) {
            $this->error('Error en provisioning tenant: '.$e->getMessage());
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
                ['Lista de Precios', 'Lista Base (BASE)'],
                ['Tipos IVA', '3 (Exento, 10.5%, 21%)'],
                ['Conceptos pago', '7'],
                ['Formas pago', '8 (5 + Cuenta Corriente + Mixta + Canje Puntos)'],
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

        if (! file_exists($sqlPath)) {
            throw new \RuntimeException("No se encontró el archivo SQL: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);

        // Reemplazar placeholder con prefijo real
        $sql = str_replace('{{PREFIX}}', $this->prefix, $sql);

        // Prefixar constraint names que no tienen el prefijo (evita colisiones entre tenants)
        $prefix = preg_quote($this->prefix, '/');
        $sql = preg_replace(
            '/CONSTRAINT `(?!'.$prefix.')/',
            'CONSTRAINT `'.$this->prefix,
            $sql
        );

        // Prefixar UNIQUE KEY / KEY names que no tienen el prefijo
        $sql = preg_replace(
            '/UNIQUE KEY `(?!'.$prefix.')/',
            'UNIQUE KEY `'.$this->prefix,
            $sql
        );

        // Limpiar AUTO_INCREMENT=N para que empiecen en 1
        $sql = preg_replace('/\s*AUTO_INCREMENT=\d+/', '', $sql);

        // Desactivar FK checks: el dump está en orden alfabético y hay FKs
        // que apuntan a tablas que aún no fueron creadas en ese punto del SQL.
        $sql = "SET FOREIGN_KEY_CHECKS=0;\n".$sql."\nSET FOREIGN_KEY_CHECKS=1;\n";

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
    protected function seedSucursal(int $comercioId): int
    {
        $now = now();

        // Token público + código corto para las pantallas Clase B (llamador,
        // consultor de precios). Se escriben en la columna tenant Y en el índice
        // global config para resolver el tenant sin sesión.
        $token = \App\Models\PantallaPublicaToken::generarTokenUnico();
        $codigo = \App\Models\PantallaPublicaToken::generarCodigoUnico();

        DB::connection('pymes_tenant')->table('sucursales')->insert([
            'nombre' => 'Sucursal Principal',
            'codigo' => 'SUC001',
            'es_principal' => true,
            'activa' => true,
            'token_publico' => $token,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sucursalId = (int) DB::connection('pymes_tenant')->getPdo()->lastInsertId();

        \App\Models\PantallaPublicaToken::create([
            'token' => $token,
            'codigo_corto' => $codigo,
            'comercio_id' => $comercioId,
            'sucursal_id' => $sucursalId,
        ]);

        return $sucursalId;
    }

    /**
     * Crea la lista de precios base para la sucursal
     */
    protected function seedListaPreciosBase(int $sucursalId): void
    {
        $now = now();

        DB::connection('pymes_tenant')->table('listas_precios')->insert([
            'sucursal_id' => $sucursalId,
            'nombre' => 'Lista Base',
            'codigo' => 'BASE',
            'descripcion' => 'Lista de precios base de la sucursal',
            'ajuste_porcentaje' => 0.00,
            'redondeo' => 'ninguno',
            'aplica_promociones' => true,
            'promociones_alcance' => 'todos',
            'es_lista_base' => true,
            'prioridad' => 999,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->info("    Lista de precios base creada para sucursal #{$sucursalId}");
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
            ['codigo' => 'efectivo',         'nombre' => 'Efectivo',          'permite_cuotas' => false, 'permite_vuelto' => true,  'permite_integracion' => false, 'orden' => 1],
            ['codigo' => 'tarjeta_debito',   'nombre' => 'Tarjeta de Débito', 'permite_cuotas' => false, 'permite_vuelto' => false, 'permite_integracion' => false, 'orden' => 2],
            ['codigo' => 'tarjeta_credito',  'nombre' => 'Tarjeta de Crédito', 'permite_cuotas' => true,  'permite_vuelto' => false, 'permite_integracion' => false, 'orden' => 3],
            ['codigo' => 'transferencia',    'nombre' => 'Transferencia',     'permite_cuotas' => false, 'permite_vuelto' => false, 'permite_integracion' => true,  'orden' => 4],
            ['codigo' => 'wallet',           'nombre' => 'Billetera Virtual', 'permite_cuotas' => false, 'permite_vuelto' => false, 'permite_integracion' => true,  'orden' => 5],
            ['codigo' => 'cheque',           'nombre' => 'Cheque',            'permite_cuotas' => false, 'permite_vuelto' => false, 'permite_integracion' => false, 'orden' => 6],
            ['codigo' => 'credito_cliente',  'nombre' => 'Crédito Cliente',   'permite_cuotas' => false, 'permite_vuelto' => false, 'permite_integracion' => false, 'orden' => 7],
            // Concepto interno (no visible en selector de cobro): pagos hechos canjeando
            // puntos del programa de fidelización. Se usa con la FormaPago "CANJE_PUNTOS"
            // (solo_sistema=true) creada abajo. Mantener sincronizado con la migración
            // 2026_05_07_140000 si se cambia.
            ['codigo' => 'canje_puntos',     'nombre' => 'Canje de Puntos',   'permite_cuotas' => false, 'permite_vuelto' => false, 'permite_integracion' => false, 'orden' => 8],
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
                'orden' => 1,
            ],
            [
                'nombre' => 'Tarjeta de Débito',
                'codigo' => 'TDEB',
                'concepto_pago_id' => $conceptoIds['tarjeta_debito'],
                'concepto' => 'tarjeta_debito',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'orden' => 2,
            ],
            [
                'nombre' => 'Tarjeta de Crédito',
                'codigo' => 'TCRE',
                'concepto_pago_id' => $conceptoIds['tarjeta_credito'],
                'concepto' => 'tarjeta_credito',
                'permite_cuotas' => true,
                'es_mixta' => false,
                'orden' => 3,
            ],
            [
                'nombre' => 'Transferencia',
                'codigo' => 'TRAN',
                'concepto_pago_id' => $conceptoIds['transferencia'],
                'concepto' => 'transferencia',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'orden' => 4,
            ],
            [
                'nombre' => 'MercadoPago',
                'codigo' => 'MPAG',
                'concepto_pago_id' => $conceptoIds['wallet'],
                'concepto' => 'wallet',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'orden' => 5,
            ],
            [
                'nombre' => 'Cuenta Corriente',
                'codigo' => 'CTACTE',
                'concepto_pago_id' => $conceptoIds['credito_cliente'],
                'concepto' => 'otro',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'orden' => 6,
            ],
            [
                'nombre' => 'Pago Mixto',
                'codigo' => 'MIXTO',
                'concepto_pago_id' => null,
                'concepto' => 'otro',
                'permite_cuotas' => false,
                'es_mixta' => true,
                'orden' => 7,
            ],
            // Forma de pago "Canje Puntos" — solo_sistema=true. NO aparece en el selector
            // de cobro al cajero (CatalogoCache::formasPago la excluye). El sistema la usa
            // internamente al crear VentaPagos por canje de puntos (canje monto + canje
            // artículos), de modo que los reportes por forma de pago agrupen ese tráfico
            // correctamente. Mantener sincronizado con la migración 2026_05_07_140000.
            [
                'nombre' => 'Canje Puntos',
                'codigo' => 'CANJE_PUNTOS',
                'concepto_pago_id' => $conceptoIds['canje_puntos'],
                'concepto' => 'otro',
                'permite_cuotas' => false,
                'es_mixta' => false,
                'solo_sistema' => true,
                'orden' => 8,
            ],
        ];

        foreach ($formasPago as $fp) {
            $db->table('formas_pago')->insert(array_merge([
                'solo_sistema' => false,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $fp));
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
    /**
     * Formas de venta + canales de venta base. El motor de precios y las
     * promociones condicionan por forma de venta: LOCAL para mostrador/POS,
     * DELIVERY/TAKEAWAY para pedidos delivery (forma automática por tipo).
     * Canales: POS (default de los editores) + TIENDA (pedidos externos).
     * Idempotente por código (espejo de la migración
     * 2026_07_03_120000_add_pedidos_delivery_menu_permisos_y_seeds).
     */
    protected function seedFormasYCanalesVenta(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        $formas = [
            ['nombre' => 'Local', 'codigo' => 'LOCAL', 'descripcion' => 'Consumo en el local (mesas, salón)'],
            ['nombre' => 'Delivery', 'codigo' => 'DELIVERY', 'descripcion' => 'Entrega a domicilio'],
            ['nombre' => 'Take Away', 'codigo' => 'TAKEAWAY', 'descripcion' => 'Para llevar (retiro en el local)'],
        ];

        foreach ($formas as $forma) {
            if (! $db->table('formas_venta')->where('codigo', $forma['codigo'])->exists()) {
                $db->table('formas_venta')->insert(array_merge($forma, [
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }

        $canales = [
            ['nombre' => 'POS', 'codigo' => 'POS', 'descripcion' => 'Venta presencial en el punto de venta'],
            ['nombre' => 'Tienda Online', 'codigo' => 'TIENDA', 'descripcion' => 'Pedidos entrados desde la tienda online / API'],
        ];

        foreach ($canales as $canal) {
            if (! $db->table('canales_venta')->where('codigo', $canal['codigo'])->exists()) {
                $db->table('canales_venta')->insert(array_merge($canal, [
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }

    /**
     * Configuración de costos (fila única) + cuentas de compra iniciales
     * (spec compras-costos-precios, RF-02/RF-22). Idempotente.
     */
    protected function seedCostos(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        if (! $db->table('configuracion_costos')->exists()) {
            $db->table('configuracion_costos')->insert([
                'utilidad_default' => 30.00,
                'costo_rector' => 'ultimo',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (! $db->table('cuentas_compra')->exists()) {
            $orden = 0;
            foreach (['Mercadería', 'Insumos', 'Servicios', 'Gastos generales'] as $nombre) {
                $db->table('cuentas_compra')->insert([
                    'nombre' => $nombre,
                    'orden' => ++$orden,
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

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
        $funcPermissions = Permission::where('name', 'like', PermisoFuncional::PERMISSION_PREFIX.'%')
            ->pluck('id', 'name')
            ->toArray();

        // Todos los IDs de permisos
        $allPermIds = array_merge(array_values($permissionIds), array_values($funcPermissions));

        // ── Asignar permisos a roles ──

        // Super Administrador y Administrador: todos los permisos
        foreach (['Super Administrador', 'Administrador'] as $roleName) {
            $inserts = array_map(fn ($pid) => [
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
                || $name === 'menu.stock'
                || $name === 'menu.inventario'
                || $name === 'menu.movimientos-stock'
                || $name === 'menu.inventario-general'
                || $name === 'menu.recetas'
                || $name === 'menu.produccion'
                || $name === 'menu.bancos'
                || $name === 'menu.resumen-cuentas'
                || $name === 'menu.cuentas-empresa'
                || $name === 'menu.movimientos-cuenta'
                || $name === 'menu.transferencias-cuenta'
                || $name === 'menu.configuracion'
                || $name === 'menu.empresa'
                || $name === 'menu.monedas'
                || $name === 'menu.programa-puntos'
                || $name === 'menu.cupones';
        });
        if ($gerentePerms->isNotEmpty()) {
            $db->table('role_has_permissions')->insert(
                $gerentePerms->map(fn ($pid) => [
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
                || $name === 'menu.turno-actual'
                || $name === 'menu.bancos'
                || $name === 'menu.resumen-cuentas';
        });
        if ($vendedorPerms->isNotEmpty()) {
            $db->table('role_has_permissions')->insert(
                $vendedorPerms->map(fn ($pid) => [
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
                $visualizadorPerms->map(fn ($pid) => [
                    'permission_id' => $pid,
                    'role_id' => $roleIds['Visualizador'],
                ])->values()->toArray()
            );
        }

        // ── Asignar rol Super Administrador al usuario admin y al usuario 2 (system admin) ──
        $db->table('model_has_roles')->insert([
            [
                'role_id' => $roleIds['Super Administrador'],
                'model_type' => User::class,
                'model_id' => $user->id,
                'sucursal_id' => 0,
            ],
            [
                'role_id' => $roleIds['Super Administrador'],
                'model_type' => User::class,
                'model_id' => 2,
                'sucursal_id' => 0,
            ],
        ]);

        $this->info('    Roles: '.implode(', ', array_keys($roleIds)));
        $this->info('    Permisos asignados: '.count($allPermIds).' permisos × roles correspondientes');
        $this->info("    Usuario {$user->email} + Usuario #2 → Super Administrador");
    }

    /**
     * Crea las monedas base del sistema
     */
    protected function seedMonedas(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        $monedas = [
            ['codigo' => 'ARS', 'nombre' => 'Peso Argentino', 'simbolo' => '$', 'es_principal' => true, 'activo' => true, 'orden' => 1],
            ['codigo' => 'USD', 'nombre' => 'Dólar Estadounidense', 'simbolo' => 'US$', 'es_principal' => false, 'activo' => false, 'orden' => 2],
            ['codigo' => 'BRL', 'nombre' => 'Real Brasileño', 'simbolo' => 'R$', 'es_principal' => false, 'activo' => false, 'orden' => 3],
        ];

        foreach ($monedas as $moneda) {
            $db->table('monedas')->insert(array_merge($moneda, [
                'decimales' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->info('    Monedas creadas: '.count($monedas));
    }

    /**
     * Crea los conceptos de movimiento de cuenta empresa
     */
    protected function seedConceptosMovimientoCuenta(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        $conceptos = [
            ['codigo' => 'venta', 'nombre' => 'Cobro de Venta', 'tipo' => 'ingreso', 'es_sistema' => true, 'orden' => 1],
            ['codigo' => 'cobro', 'nombre' => 'Cobro de Cuenta Corriente', 'tipo' => 'ingreso', 'es_sistema' => true, 'orden' => 2],
            ['codigo' => 'comision_bancaria', 'nombre' => 'Comisión Bancaria', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 3],
            ['codigo' => 'interes', 'nombre' => 'Interés Bancario', 'tipo' => 'ingreso', 'es_sistema' => true, 'orden' => 4],
            ['codigo' => 'transferencia_entre_cuentas', 'nombre' => 'Transferencia entre Cuentas', 'tipo' => 'ambos', 'es_sistema' => true, 'orden' => 5],
            ['codigo' => 'deposito_tesoreria', 'nombre' => 'Depósito desde Tesorería', 'tipo' => 'ingreso', 'es_sistema' => true, 'orden' => 6],
            ['codigo' => 'retiro_tesoreria', 'nombre' => 'Retiro hacia Tesorería', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 7],
            ['codigo' => 'pago_proveedor', 'nombre' => 'Pago a Proveedor', 'tipo' => 'egreso', 'es_sistema' => false, 'orden' => 8],
            ['codigo' => 'devolucion', 'nombre' => 'Devolución', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 9],
            ['codigo' => 'ajuste', 'nombre' => 'Ajuste', 'tipo' => 'ambos', 'es_sistema' => false, 'orden' => 10],
            ['codigo' => 'otro', 'nombre' => 'Otro', 'tipo' => 'ambos', 'es_sistema' => false, 'orden' => 11],
            ['codigo' => 'cobro_integracion', 'nombre' => 'Cobro por integración de pago', 'tipo' => 'ingreso', 'es_sistema' => true, 'orden' => 12],
            ['codigo' => 'comision_integracion', 'nombre' => 'Comisión del proveedor de pago', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 13],
            ['codigo' => 'retiro_integracion', 'nombre' => 'Retiro a banco desde el proveedor', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 14],
            ['codigo' => 'devolucion_integracion', 'nombre' => 'Devolución/contracargo en el proveedor', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 15],
            ['codigo' => 'acreditacion_integracion', 'nombre' => 'Acreditación en el proveedor de pago', 'tipo' => 'ingreso', 'es_sistema' => true, 'orden' => 16],
            ['codigo' => 'ajuste_conciliacion', 'nombre' => 'Ajuste por conciliación', 'tipo' => 'ambos', 'es_sistema' => true, 'orden' => 17],
            ['codigo' => 'impuesto_integracion', 'nombre' => 'Impuestos y retenciones del proveedor de pago', 'tipo' => 'egreso', 'es_sistema' => true, 'orden' => 18],
        ];

        foreach ($conceptos as $concepto) {
            $db->table('conceptos_movimiento_cuenta')->insert(array_merge($concepto, [
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->info('    Conceptos de movimiento creados: '.count($conceptos));
    }

    /**
     * Siembra el catálogo de impuestos argentinos (sistema-impositivo RF-01).
     * El catálogo canónico vive en la migración create_impuestos_table.
     */
    protected function seedImpuestos(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        $migracion = require database_path('migrations/2026_06_12_150000_create_impuestos_table.php');
        $migracionArca = require database_path('migrations/2026_06_18_170000_add_codigo_arca_a_impuestos.php');

        foreach ($migracion::catalogo() as $impuesto) {
            $db->table('impuestos')->insert(array_merge($impuesto, [
                'codigo_arca' => $migracionArca::codigoArcaPara($impuesto['codigo']),
                'es_sistema' => true,
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->info('    Impuestos del catálogo creados: '.count($migracion::catalogo()));
    }

    /**
     * Marca todas las migraciones existentes como ejecutadas en la tabla {PREFIX}migrations
     * Esto evita que php artisan migrate intente re-ejecutarlas sobre este comercio.
     */
    protected function markMigrationsAsRun(): void
    {
        $migrationFiles = collect(scandir(database_path('migrations')))
            ->filter(fn ($f) => str_ends_with($f, '.php'))
            ->map(fn ($f) => str_replace('.php', '', $f))
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

    /**
     * Siembra el catálogo de integraciones de pago disponibles para el comercio.
     * Por ahora solo MercadoPago. Futuros proveedores (MODO, Cuenta DNI, PayPal)
     * se agregan acá + en migración + en el Gateway PHP.
     */
    protected function seedIntegracionesPago(): void
    {
        $now = now();
        $db = DB::connection('pymes_tenant');

        $integraciones = [
            [
                'codigo' => 'mercadopago_qr',
                'nombre' => 'Mercado Pago - QR',
                'descripcion' => 'Cobros con Mercado Pago: QR dinámico (se muestra en pantalla por venta), QR estático (QR impreso del POS, con el monto empujado a la caja) y QR de monto libre (el cliente ingresa el monto en su app; confirmación manual del cajero).',
                'modos_disponibles' => json_encode(['qr_dinamico', 'qr_estatico', 'qr_libre']),
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ],
            [
                'codigo' => 'mercadopago_point',
                'nombre' => 'Mercado Pago - Point',
                'descripcion' => 'Cobros con Mercado Pago Point: el monto se envía a la terminal física desde el sistema y el cliente paga con tarjeta o QR en el propio aparato.',
                'modos_disponibles' => json_encode(['point']),
                'gateway_class' => \App\Services\IntegracionesPago\MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 2,
            ],
        ];

        foreach ($integraciones as $integ) {
            $db->table('integraciones_pago')->insert(array_merge($integ, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        $this->info('    Integraciones cargadas: '.count($integraciones));
    }
}

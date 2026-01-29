<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Comercio;
use App\Models\Sucursal;
use App\Models\Articulo;
use App\Models\Stock;
use App\Models\Cliente;
use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\TipoIva;
use Carbon\Carbon;

/**
 * Seeder: Demo Comercio 1
 *
 * Crea un escenario completo de prueba para el comercio 1:
 * - 3 sucursales operativas
 * - Artículos con stock
 * - Clientes
 * - Cajas con movimientos
 * - Ventas realizadas
 * - Usuario admin1 con acceso a todas las sucursales
 */
class DemoComercio1Seeder extends Seeder
{
    private $comercioId = 1;
    private $sucursales = [];
    private $articulos = [];
    private $clientes = [];
    private $cajas = [];
    private $tiposIva = [];

    public function run(): void
    {
        echo "🚀 Iniciando seeder de demo para Comercio 1...\n\n";

        // Configurar tenant para comercio 1
        $this->configurarTenant();

        // 1. Crear sucursales adicionales
        $this->crearSucursales();

        // 2. Obtener tipos de IVA
        $this->obtenerTiposIva();

        // 3. Crear artículos
        $this->crearArticulos();

        // 4. Asignar artículos a sucursales y crear stock
        $this->configurarStockPorSucursal();

        // 5. Crear clientes
        $this->crearClientes();

        // 6. Crear cajas para cada sucursal
        $this->crearCajas();

        // 7. Crear movimientos de caja
        $this->crearMovimientosCaja();

        // 8. Crear ventas
        $this->crearVentas();

        // 9. Crear usuario admin1
        $this->crearUsuarioAdmin1();

        echo "\n✅ Seeder completado exitosamente!\n";
        echo "📊 Resumen:\n";
        echo "   - Sucursales creadas: " . count($this->sucursales) . "\n";
        echo "   - Artículos creados: " . count($this->articulos) . "\n";
        echo "   - Clientes creados: " . count($this->clientes) . "\n";
        echo "   - Cajas creadas: " . count($this->cajas) . "\n";
        echo "\n";
        echo "🔑 Credenciales de acceso:\n";
        echo "   Email comercio: comercio1@test.com\n";
        echo "   Username: admin1\n";
        echo "   Password: password\n";
    }

    private function configurarTenant(): void
    {
        echo "⚙️  Configurando tenant para comercio 1...\n";

        $comercio = Comercio::find($this->comercioId);
        $prefix = str_pad($this->comercioId, 6, '0', STR_PAD_LEFT) . '_';

        config([
            'database.connections.pymes_tenant.prefix' => $prefix,
            'database.connections.pymes_tenant.database' => $comercio->database_name ?? 'pymes'
        ]);

        DB::purge('pymes_tenant');
    }

    private function crearSucursales(): void
    {
        echo "🏢 Creando sucursales...\n";

        // Obtener Casa Central (ya existe)
        $this->sucursales['central'] = Sucursal::where('codigo', 'CENTRAL')->first();

        if (!$this->sucursales['central']) {
            $this->sucursales['central'] = Sucursal::create([
                'nombre' => 'Casa Central',
                'codigo' => 'CENTRAL',
                'direccion' => 'Av. Corrientes 1234, CABA',
                'telefono' => '011-4567-8901',
                'email' => 'central@comercio1.com',
                'es_principal' => true,
                'activa' => true,
            ]);
        }

        // Sucursal Norte
        $this->sucursales['norte'] = Sucursal::where('codigo', 'NORTE')->first();
        if (!$this->sucursales['norte']) {
            $this->sucursales['norte'] = Sucursal::create([
                'nombre' => 'Sucursal Norte',
                'codigo' => 'NORTE',
                'direccion' => 'Av. Cabildo 2345, Belgrano',
                'telefono' => '011-4567-8902',
                'email' => 'norte@comercio1.com',
                'es_principal' => false,
                'activa' => true,
            ]);
        }

        // Sucursal Sur
        $this->sucursales['sur'] = Sucursal::where('codigo', 'SUR')->first();
        if (!$this->sucursales['sur']) {
            $this->sucursales['sur'] = Sucursal::create([
                'nombre' => 'Sucursal Sur',
                'codigo' => 'SUR',
                'direccion' => 'Av. Avellaneda 3456, Avellaneda',
                'telefono' => '011-4567-8903',
                'email' => 'sur@comercio1.com',
                'es_principal' => false,
                'activa' => true,
            ]);
        }

        echo "   ✓ Casa Central (ID: {$this->sucursales['central']->id})\n";
        echo "   ✓ Sucursal Norte (ID: {$this->sucursales['norte']->id})\n";
        echo "   ✓ Sucursal Sur (ID: {$this->sucursales['sur']->id})\n\n";
    }

    private function obtenerTiposIva(): void
    {
        echo "💰 Obteniendo/Creando tipos de IVA...\n";

        // Verificar si ya existen tipos de IVA
        $count = TipoIva::count();

        if ($count == 0) {
            echo "   Creando tipos de IVA...\n";

            // Crear tipos de IVA
            $tiposIvaData = [
                [
                    'codigo' => 3,
                    'nombre' => 'Exento',
                    'porcentaje' => 0.00,
                    'activo' => true,
                ],
                [
                    'codigo' => 4,
                    'nombre' => 'IVA 10.5%',
                    'porcentaje' => 10.50,
                    'activo' => true,
                ],
                [
                    'codigo' => 5,
                    'nombre' => 'IVA 21%',
                    'porcentaje' => 21.00,
                    'activo' => true,
                ],
            ];

            foreach ($tiposIvaData as $tipoData) {
                TipoIva::create($tipoData);
            }
        }

        // Obtener tipos de IVA
        $this->tiposIva = [
            '21' => TipoIva::where('porcentaje', 21)->first(),
            '10.5' => TipoIva::where('porcentaje', 10.5)->first(),
            '0' => TipoIva::where('porcentaje', 0)->first(),
        ];

        echo "   ✓ IVA 21%\n";
        echo "   ✓ IVA 10.5%\n";
        echo "   ✓ IVA 0% (Exento)\n\n";
    }

    private function crearArticulos(): void
    {
        echo "📦 Creando artículos...\n";

        $articulos = [
            // Bebidas
            ['nombre' => 'Coca Cola 500ml', 'codigo' => 'BEB001', 'descripcion' => 'Bebida', 'precio' => 350, 'iva' => '21'],
            ['nombre' => 'Agua Mineral 500ml', 'codigo' => 'BEB002', 'descripcion' => 'Bebida', 'precio' => 200, 'iva' => '21'],
            ['nombre' => 'Cerveza Quilmes 1L', 'codigo' => 'BEB003', 'descripcion' => 'Bebida', 'precio' => 550, 'iva' => '21'],
            ['nombre' => 'Jugo Baggio 1L', 'codigo' => 'BEB004', 'descripcion' => 'Bebida', 'precio' => 380, 'iva' => '21'],

            // Snacks
            ['nombre' => 'Papas Lays 150g', 'codigo' => 'SNK001', 'descripcion' => 'Snack', 'precio' => 420, 'iva' => '21'],
            ['nombre' => 'Alfajor Jorgito', 'codigo' => 'SNK002', 'descripcion' => 'Snack', 'precio' => 180, 'iva' => '21'],
            ['nombre' => 'Galletitas Oreo', 'codigo' => 'SNK003', 'descripcion' => 'Snack', 'precio' => 450, 'iva' => '21'],

            // Limpieza
            ['nombre' => 'Detergente Magistral 500ml', 'codigo' => 'LIM001', 'descripcion' => 'Limpieza', 'precio' => 580, 'iva' => '21'],
            ['nombre' => 'Lavandina Ayudín 1L', 'codigo' => 'LIM002', 'descripcion' => 'Limpieza', 'precio' => 320, 'iva' => '21'],
            ['nombre' => 'Esponja Scotch Brite', 'codigo' => 'LIM003', 'descripcion' => 'Limpieza', 'precio' => 250, 'iva' => '21'],

            // Alimentos básicos
            ['nombre' => 'Arroz Gallo 1kg', 'codigo' => 'ALM001', 'descripcion' => 'Alimento', 'precio' => 680, 'iva' => '10.5'],
            ['nombre' => 'Fideos Marolio 500g', 'codigo' => 'ALM002', 'descripcion' => 'Alimento', 'precio' => 320, 'iva' => '10.5'],
            ['nombre' => 'Aceite Cocinero 900ml', 'codigo' => 'ALM003', 'descripcion' => 'Alimento', 'precio' => 1250, 'iva' => '10.5'],
        ];

        foreach ($articulos as $art) {
            // Verificar si ya existe
            $existing = Articulo::where('codigo', $art['codigo'])->first();
            if ($existing) {
                $this->articulos[$art['codigo']] = $existing;
                continue;
            }

            $articulo = Articulo::create([
                'nombre' => $art['nombre'],
                'codigo' => $art['codigo'],
                'descripcion' => $art['descripcion'],
                'precio_base' => $art['precio'],
                'activo' => true,
                'tipo_iva_id' => $this->tiposIva[$art['iva']]->id,
            ]);

            $this->articulos[$art['codigo']] = $articulo;
            echo "   ✓ {$art['nombre']} ({$art['codigo']})\n";
        }
        echo "\n";
    }

    private function configurarStockPorSucursal(): void
    {
        echo "📊 Configurando stock por sucursal...\n";

        $totalArticulos = count($this->articulos);

        foreach ($this->sucursales as $key => $sucursal) {
            echo "   Sucursal {$sucursal->nombre}:\n";

            foreach ($this->articulos as $codigo => $articulo) {
                // Verificar si ya está asignado
                $existeAsignacion = DB::connection('pymes_tenant')->table('articulos_sucursales')
                    ->where('articulo_id', $articulo->id)
                    ->where('sucursal_id', $sucursal->id)
                    ->exists();

                if (!$existeAsignacion) {
                    // Asignar artículo a sucursal
                    DB::connection('pymes_tenant')->table('articulos_sucursales')->insert([
                        'articulo_id' => $articulo->id,
                        'sucursal_id' => $sucursal->id,
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Verificar si ya existe stock
                $existeStock = Stock::where('articulo_id', $articulo->id)
                    ->where('sucursal_id', $sucursal->id)
                    ->exists();

                if (!$existeStock) {
                    // Crear stock con cantidades variables según sucursal
                    $cantidad = $this->generarCantidadStock($key);

                    Stock::create([
                        'articulo_id' => $articulo->id,
                        'sucursal_id' => $sucursal->id,
                        'cantidad' => $cantidad,
                        'cantidad_minima' => 10,
                        'cantidad_maxima' => 100,
                    ]);
                }
            }
            echo "     ✓ {$totalArticulos} artículos configurados\n";
        }
        echo "\n";
    }

    private function generarCantidadStock(string $sucursalKey): float
    {
        // Central tiene más stock, Norte medio, Sur menos
        return match($sucursalKey) {
            'central' => rand(50, 100),
            'norte' => rand(30, 60),
            'sur' => rand(20, 40),
            default => rand(10, 30),
        };
    }

    private function crearClientes(): void
    {
        echo "👥 Creando clientes...\n";

        $clientes = [
            [
                'nombre' => 'Juan Pérez',
                'cuit' => '20-12345678-5',
                'email' => 'juan.perez@email.com',
                'telefono' => '11-2345-6789',
            ],
            [
                'nombre' => 'María García',
                'cuit' => '27-23456789-3',
                'email' => 'maria.garcia@email.com',
                'telefono' => '11-3456-7890',
            ],
            [
                'nombre' => 'Empresa XYZ S.A.',
                'cuit' => '30-12345678-9',
                'razon_social' => 'Empresa XYZ S.A.',
                'email' => 'contacto@empresaxyz.com',
                'telefono' => '11-4567-8901',
            ],
            [
                'nombre' => 'Carlos López',
                'cuit' => '20-34567890-7',
                'email' => 'carlos.lopez@email.com',
                'telefono' => '11-5678-9012',
            ],
        ];

        foreach ($clientes as $cli) {
            $cliente = Cliente::create($cli);

            // Asignar cliente a todas las sucursales
            foreach ($this->sucursales as $sucursal) {
                DB::connection('pymes_tenant')->table('clientes_sucursales')->insert([
                    'cliente_id' => $cliente->id,
                    'sucursal_id' => $sucursal->id,
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->clientes[] = $cliente;
            echo "   ✓ {$cli['nombre']}\n";
        }
        echo "\n";
    }

    private function crearCajas(): void
    {
        echo "💵 Creando cajas...\n";

        foreach ($this->sucursales as $key => $sucursal) {
            $caja = Caja::create([
                'sucursal_id' => $sucursal->id,
                'nombre' => 'Caja Principal ' . $sucursal->nombre,
                'codigo' => 'CAJA-' . $sucursal->codigo,
                'saldo_inicial' => 5000,
                'saldo_actual' => 5000,
                'estado' => 'abierta',
                'fecha_apertura' => now()->subHours(8),
                'usuario_apertura_id' => 1, // Usuario que abre
            ]);

            $this->cajas[$key] = $caja;
            echo "   ✓ Caja {$caja->nombre} (Saldo: $5.000)\n";
        }
        echo "\n";
    }

    private function crearMovimientosCaja(): void
    {
        echo "💸 Creando movimientos de caja...\n";

        foreach ($this->cajas as $key => $caja) {
            // Movimiento de apertura (como ingreso)
            MovimientoCaja::create([
                'caja_id' => $caja->id,
                'tipo' => 'ingreso',
                'monto' => $caja->saldo_inicial,
                'concepto' => 'Apertura de caja',
                'usuario_id' => 1,
            ]);

            // Algunos ingresos
            for ($i = 0; $i < 3; $i++) {
                $monto = rand(500, 2000);
                MovimientoCaja::create([
                    'caja_id' => $caja->id,
                    'tipo' => 'ingreso',
                    'monto' => $monto,
                    'concepto' => 'Ingreso varios - ' . ($i + 1),
                    'usuario_id' => 1,
                ]);

                // Actualizar saldo de caja
                $caja->saldo_actual += $monto;
            }

            // Algunos egresos
            for ($i = 0; $i < 2; $i++) {
                $monto = rand(200, 800);
                MovimientoCaja::create([
                    'caja_id' => $caja->id,
                    'tipo' => 'egreso',
                    'monto' => $monto,
                    'concepto' => 'Gasto varios - ' . ($i + 1),
                    'usuario_id' => 1,
                ]);

                // Actualizar saldo de caja
                $caja->saldo_actual -= $monto;
            }

            $caja->save();
            echo "   ✓ Movimientos creados para {$caja->nombre}\n";
        }
        echo "\n";
    }

    private function crearVentas(): void
    {
        echo "🛒 Creando ventas...\n";

        $formasPago = ['efectivo', 'tarjeta', 'transferencia'];

        foreach ($this->sucursales as $key => $sucursal) {
            // Crear entre 5 y 8 ventas por sucursal
            $cantidadVentas = rand(5, 8);

            for ($i = 0; $i < $cantidadVentas; $i++) {
                $cliente = $this->clientes[array_rand($this->clientes)];
                $formaPago = $formasPago[array_rand($formasPago)];

                // Crear venta
                $venta = Venta::create([
                    'sucursal_id' => $sucursal->id,
                    'cliente_id' => $cliente->id,
                    'caja_id' => $this->cajas[$key]->id,
                    'usuario_id' => 1,
                    'fecha' => now()->subDays(rand(0, 7))->subHours(rand(0, 23)),
                    'numero' => str_pad(($sucursal->id * 1000 + $i), 8, '0', STR_PAD_LEFT),
                    'forma_pago' => $formaPago,
                    'subtotal' => 0,
                    'descuento' => 0,
                    'iva' => 0,
                    'total' => 0,
                    'estado' => 'completada',
                ]);

                // Agregar entre 2 y 5 items a la venta
                $cantidadItems = rand(2, 5);
                $articulosDisponibles = array_keys($this->articulos);
                shuffle($articulosDisponibles);
                $articulosVenta = array_slice($articulosDisponibles, 0, $cantidadItems);

                $subtotal = 0;
                $ivaTotal = 0;

                foreach ($articulosVenta as $codigo) {
                    $articulo = $this->articulos[$codigo];
                    $cantidad = rand(1, 3);

                    // Obtener precio del artículo (usamos precio_base)
                    $precioUnitario = $articulo->precio_base;
                    $subtotalItem = $precioUnitario * $cantidad;

                    // Calcular IVA
                    $tipoIva = $articulo->tipoIva;
                    $ivaItem = $subtotalItem * ($tipoIva->porcentaje / 100);

                    VentaDetalle::create([
                        'venta_id' => $venta->id,
                        'articulo_id' => $articulo->id,
                        'cantidad' => $cantidad,
                        'precio_unitario' => $precioUnitario,
                        'subtotal' => $subtotalItem,
                        'descuento_porcentaje' => 0,
                        'descuento_monto' => 0,
                        'iva_porcentaje' => $tipoIva->porcentaje,
                        'iva_monto' => $ivaItem,
                        'total' => $subtotalItem + $ivaItem,
                    ]);

                    $subtotal += $subtotalItem;
                    $ivaTotal += $ivaItem;

                    // Descontar del stock
                    $stock = Stock::where('articulo_id', $articulo->id)
                                  ->where('sucursal_id', $sucursal->id)
                                  ->first();
                    if ($stock) {
                        $stock->cantidad -= $cantidad;
                        $stock->save();
                    }
                }

                // Actualizar totales de venta
                $venta->subtotal = $subtotal;
                $venta->iva = $ivaTotal;
                $venta->total = $subtotal + $ivaTotal;
                $venta->save();

                // Si es efectivo, crear movimiento de ingreso en caja
                if ($formaPago === 'efectivo') {
                    MovimientoCaja::create([
                        'caja_id' => $this->cajas[$key]->id,
                        'tipo' => 'ingreso',
                        'monto' => $venta->total,
                        'concepto' => 'Venta ' . $venta->numero,
                        'usuario_id' => 1,
                        'referencia_tipo' => 'App\\Models\\Venta',
                        'referencia_id' => $venta->id,
                    ]);

                    // Actualizar saldo de caja
                    $this->cajas[$key]->saldo_actual += $venta->total;
                    $this->cajas[$key]->save();
                }
            }

            echo "   ✓ {$cantidadVentas} ventas creadas para {$sucursal->nombre}\n";
        }
        echo "\n";
    }

    private function crearUsuarioAdmin1(): void
    {
        echo "👤 Creando usuario admin1...\n";

        // Verificar si ya existe
        $existingUser = User::where('username', 'admin1')->first();
        if ($existingUser) {
            echo "   ⚠️  Usuario admin1 ya existe, actualizando...\n";
            $user = $existingUser;
        } else {
            // Crear usuario en BD config
            $user = User::create([
                'name' => 'Administrador Sucursales',
                'username' => 'admin1',
                'email' => 'admin1@comercio1.com',
                'password' => Hash::make('password'),
                'activo' => true,
            ]);
        }

        // Verificar si ya está asignado al comercio
        $comercioUser = DB::connection('config')->table('comercio_user')
            ->where('comercio_id', $this->comercioId)
            ->where('user_id', $user->id)
            ->first();

        if (!$comercioUser) {
            // Asignar al comercio
            DB::connection('config')->table('comercio_user')->insert([
                'comercio_id' => $this->comercioId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Asignar rol de Administrador en cada sucursal
        $rolAdmin = DB::connection('pymes_tenant')->table('roles')
            ->where('name', 'Administrador')
            ->first();

        if ($rolAdmin) {
            foreach ($this->sucursales as $sucursal) {
                // Verificar si ya tiene el rol en esta sucursal
                $existingRole = DB::connection('pymes_tenant')->table('model_has_roles')
                    ->where('role_id', $rolAdmin->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->where('model_id', $user->id)
                    ->where('sucursal_id', $sucursal->id)
                    ->first();

                if (!$existingRole) {
                    DB::connection('pymes_tenant')->table('model_has_roles')->insert([
                        'role_id' => $rolAdmin->id,
                        'model_type' => 'App\\Models\\User',
                        'model_id' => $user->id,
                        'sucursal_id' => $sucursal->id,
                    ]);
                }
            }
        }

        echo "   ✓ Usuario creado/actualizado con acceso a las 3 sucursales\n";
        echo "   ✓ Email: admin1@comercio1.com\n";
        echo "   ✓ Username: admin1\n";
        echo "   ✓ Password: password\n";
    }
}

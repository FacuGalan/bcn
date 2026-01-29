<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Promocion;
use App\Models\PromocionCondicion;
use App\Models\PromocionEscala;
use App\Models\Sucursal;
use App\Models\Articulo;
use App\Models\Categoria;
use App\Models\FormaPago;
use App\Models\FormaVenta;
use App\Models\CanalVenta;
use App\Models\Comercio;
use Carbon\Carbon;

/**
 * Seeder: Promociones
 *
 * Crea promociones de ejemplo que demuestran todos los tipos y condiciones:
 * - Descuentos porcentuales y en monto
 * - Precios fijos
 * - Recargos
 * - Descuentos escalonados por cantidad
 * - Condiciones por artículo, categoría, forma de pago, etc.
 * - Promociones con vigencias temporales
 * - Happy hours
 * - Cupones
 *
 * FASE 1 - Sistema de Precios Dinámico
 */
class PromocionesSeeder extends Seeder
{
    private $comercioId = 1;
    private $sucursales = [];
    private $articulos = [];
    private $categorias = [];
    private $formasPago = [];
    private $formasVenta = [];
    private $canalesVenta = [];

    public function run(): void
    {
        echo "🎉 Iniciando seeder de Promociones...\n\n";

        $this->configurarTenant();
        $this->cargarDatos();
        $this->crearPromociones();

        echo "\n✅ Seeder completado exitosamente!\n\n";
    }

    private function configurarTenant(): void
    {
        echo "⚙️  Configurando tenant para comercio {$this->comercioId}...\n";

        $comercio = Comercio::find($this->comercioId);
        $prefix = str_pad($this->comercioId, 6, '0', STR_PAD_LEFT) . '_';

        config([
            'database.connections.pymes_tenant.prefix' => $prefix,
            'database.connections.pymes_tenant.database' => $comercio->database_name ?? 'pymes'
        ]);

        DB::purge('pymes_tenant');
        echo "   ✓ Tenant configurado (prefix: {$prefix})\n\n";
    }

    private function cargarDatos(): void
    {
        echo "📥 Cargando datos necesarios...\n";

        $this->sucursales = Sucursal::all()->keyBy('codigo');
        $this->articulos = Articulo::all()->keyBy('codigo');
        $this->categorias = Categoria::all()->keyBy('codigo');
        $this->formasPago = FormaPago::all()->keyBy('concepto');
        $this->formasVenta = FormaVenta::all()->keyBy('codigo');
        $this->canalesVenta = CanalVenta::all()->keyBy('codigo');

        echo "   ✓ Datos cargados\n\n";
    }

    private function crearPromociones(): void
    {
        echo "🎁 Creando promociones...\n\n";

        // ============================================================
        // 1. DESCUENTO PORCENTUAL - 20% en bebidas
        // ============================================================
        if (isset($this->sucursales['CENTRAL']) && isset($this->categorias['BEB'])) {
            echo "   💧 Promoción 1: 20% OFF en Bebidas\n";

            $promo1 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => '20% OFF en Bebidas',
                'descripcion' => 'Todas las bebidas con 20% de descuento',
                'tipo' => 'descuento_porcentaje',
                'valor' => 20,
                'prioridad' => 10,
                'combinable' => false,
                'activo' => true,
            ]);

            if ($promo1) {
                PromocionCondicion::create([
                    'promocion_id' => $promo1->id,
                    'tipo_condicion' => 'por_categoria',
                    'categoria_id' => $this->categorias['BEB']->id,
                ]);
                echo "      ✓ Condición: Categoría Bebidas\n";
            }

            echo "\n";
        }

        // ============================================================
        // 2. DESCUENTO MONTO FIJO - $100 OFF en compras >$1000
        // ============================================================
        if (isset($this->sucursales['CENTRAL'])) {
            echo "   💵 Promoción 2: $100 OFF en compras >$1000\n";

            $promo2 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => '$100 OFF en compras mayores',
                'descripcion' => '$100 de descuento en compras superiores a $1000',
                'tipo' => 'descuento_monto',
                'valor' => 100,
                'prioridad' => 20,
                'combinable' => true,
                'activo' => true,
            ]);

            if ($promo2) {
                PromocionCondicion::create([
                    'promocion_id' => $promo2->id,
                    'tipo_condicion' => 'por_total_compra',
                    'monto_minimo' => 1000,
                ]);
                echo "      ✓ Condición: Compra mínima $1000\n";
            }

            echo "\n";
        }

        // ============================================================
        // 3. PRECIO FIJO - Coca Cola a $300
        // ============================================================
        if (isset($this->sucursales['NORTE']) && isset($this->articulos['BEB001'])) {
            echo "   🥤 Promoción 3: Coca Cola a precio fijo $300\n";

            $promo3 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['NORTE']->id,
                'nombre' => 'Coca Cola $300',
                'descripcion' => 'Coca Cola 500ml a precio promocional de $300',
                'tipo' => 'precio_fijo',
                'valor' => 300,
                'prioridad' => 5,
                'combinable' => false,
                'activo' => true,
                'vigencia_desde' => now(),
                'vigencia_hasta' => now()->addDays(15),
            ]);

            if ($promo3) {
                PromocionCondicion::create([
                    'promocion_id' => $promo3->id,
                    'tipo_condicion' => 'por_articulo',
                    'articulo_id' => $this->articulos['BEB001']->id,
                ]);
                echo "      ✓ Condición: Artículo Coca Cola\n";
                echo "      ✓ Vigencia: 15 días\n";
            }

            echo "\n";
        }

        // ============================================================
        // 4. DESCUENTO ESCALONADO - 2x1, 3x2 en Snacks
        // ============================================================
        if (isset($this->sucursales['CENTRAL']) && isset($this->categorias['SNK'])) {
            echo "   🍿 Promoción 4: Descuentos por cantidad en Snacks\n";

            $promo4 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => 'Descuentos por cantidad en Snacks',
                'descripcion' => 'Comprá más y ahorrá más en snacks',
                'tipo' => 'descuento_escalonado',
                'valor' => 0,
                'prioridad' => 15,
                'combinable' => false,
                'activo' => true,
            ]);

            if ($promo4) {
                PromocionCondicion::create([
                    'promocion_id' => $promo4->id,
                    'tipo_condicion' => 'por_categoria',
                    'categoria_id' => $this->categorias['SNK']->id,
                ]);

                // Escalas de descuento
                PromocionEscala::create([
                    'promocion_id' => $promo4->id,
                    'cantidad_desde' => 2,
                    'cantidad_hasta' => 2,
                    'tipo_descuento' => 'porcentaje',
                    'valor' => 15,
                ]);

                PromocionEscala::create([
                    'promocion_id' => $promo4->id,
                    'cantidad_desde' => 3,
                    'cantidad_hasta' => 4,
                    'tipo_descuento' => 'porcentaje',
                    'valor' => 25,
                ]);

                PromocionEscala::create([
                    'promocion_id' => $promo4->id,
                    'cantidad_desde' => 5,
                    'cantidad_hasta' => null,
                    'tipo_descuento' => 'porcentaje',
                    'valor' => 35,
                ]);

                echo "      ✓ Condición: Categoría Snacks\n";
                echo "      ✓ Escala 1: 2 unidades = 15% OFF\n";
                echo "      ✓ Escala 2: 3-4 unidades = 25% OFF\n";
                echo "      ✓ Escala 3: 5+ unidades = 35% OFF\n";
            }

            echo "\n";
        }

        // ============================================================
        // 5. HAPPY HOUR - 30% OFF de 17 a 20hs
        // ============================================================
        if (isset($this->sucursales['CENTRAL']) && isset($this->categorias['BEB'])) {
            echo "   🕔 Promoción 5: Happy Hour - 30% OFF Bebidas\n";

            $promo5 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => 'Happy Hour - Bebidas',
                'descripcion' => '30% de descuento en bebidas de 17 a 20hs',
                'tipo' => 'descuento_porcentaje',
                'valor' => 30,
                'prioridad' => 8,
                'combinable' => false,
                'activo' => true,
                'dias_semana' => [1, 2, 3, 4, 5], // Lunes a Viernes
                'hora_desde' => '17:00:00',
                'hora_hasta' => '20:00:00',
            ]);

            if ($promo5) {
                PromocionCondicion::create([
                    'promocion_id' => $promo5->id,
                    'tipo_condicion' => 'por_categoria',
                    'categoria_id' => $this->categorias['BEB']->id,
                ]);
                echo "      ✓ Condición: Categoría Bebidas\n";
                echo "      ✓ Horario: Lun-Vie 17:00-20:00\n";
            }

            echo "\n";
        }

        // ============================================================
        // 6. CUPÓN - VERANO2025 (15% OFF)
        // ============================================================
        if (isset($this->sucursales['CENTRAL'])) {
            echo "   🎫 Promoción 6: Cupón VERANO2025\n";

            $promo6 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => 'Cupón Verano 2025',
                'descripcion' => '15% OFF con cupón VERANO2025',
                'codigo_cupon' => 'VERANO2025',
                'tipo' => 'descuento_porcentaje',
                'valor' => 15,
                'prioridad' => 12,
                'combinable' => false,
                'activo' => true,
                'usos_maximos' => 100,
                'usos_por_cliente' => 3,
                'vigencia_desde' => now(),
                'vigencia_hasta' => now()->addMonths(2),
            ]);

            if ($promo6) {
                echo "      ✓ Código: VERANO2025\n";
                echo "      ✓ Límite: 100 usos totales, 3 por cliente\n";
                echo "      ✓ Vigencia: 2 meses\n";
            }

            echo "\n";
        }

        // ============================================================
        // 7. DESCUENTO POR FORMA DE PAGO - Efectivo 5% adicional
        // ============================================================
        if (isset($this->sucursales['SUR']) && isset($this->formasPago['efectivo'])) {
            echo "   💵 Promoción 7: 5% extra pagando en efectivo\n";

            $promo7 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['SUR']->id,
                'nombre' => 'Descuento efectivo adicional',
                'descripcion' => '5% de descuento extra pagando en efectivo',
                'tipo' => 'descuento_porcentaje',
                'valor' => 5,
                'prioridad' => 25,
                'combinable' => true,
                'activo' => true,
            ]);

            if ($promo7) {
                PromocionCondicion::create([
                    'promocion_id' => $promo7->id,
                    'tipo_condicion' => 'por_forma_pago',
                    'forma_pago_id' => $this->formasPago['efectivo']->id,
                ]);
                echo "      ✓ Condición: Pago en efectivo\n";
                echo "      ✓ Combinable con otras promociones\n";
            }

            echo "\n";
        }

        // ============================================================
        // 8. DESCUENTO DELIVERY - 10% OFF en pedidos delivery
        // ============================================================
        if (isset($this->sucursales['CENTRAL']) && isset($this->formasVenta['DELIVERY'])) {
            echo "   🚚 Promoción 8: 10% OFF Delivery\n";

            $promo8 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => 'Descuento Delivery',
                'descripcion' => '10% de descuento en pedidos delivery',
                'tipo' => 'descuento_porcentaje',
                'valor' => 10,
                'prioridad' => 18,
                'combinable' => true,
                'activo' => true,
            ]);

            if ($promo8) {
                PromocionCondicion::create([
                    'promocion_id' => $promo8->id,
                    'tipo_condicion' => 'por_forma_venta',
                    'forma_venta_id' => $this->formasVenta['DELIVERY']->id,
                ]);
                PromocionCondicion::create([
                    'promocion_id' => $promo8->id,
                    'tipo_condicion' => 'por_total_compra',
                    'monto_minimo' => 500,
                ]);
                echo "      ✓ Condición 1: Delivery\n";
                echo "      ✓ Condición 2: Compra mínima $500\n";
            }

            echo "\n";
        }

        // ============================================================
        // 9. PROMO WEB - Compra online 12% OFF
        // ============================================================
        if (isset($this->sucursales['NORTE']) && isset($this->canalesVenta['WEB'])) {
            echo "   🌐 Promoción 9: 12% OFF Compras Web\n";

            $promo9 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['NORTE']->id,
                'nombre' => 'Descuento Web',
                'descripcion' => '12% de descuento en compras por la web',
                'tipo' => 'descuento_porcentaje',
                'valor' => 12,
                'prioridad' => 14,
                'combinable' => false,
                'activo' => true,
            ]);

            if ($promo9) {
                PromocionCondicion::create([
                    'promocion_id' => $promo9->id,
                    'tipo_condicion' => 'por_canal',
                    'canal_venta_id' => $this->canalesVenta['WEB']->id,
                ]);
                echo "      ✓ Condición: Canal Web\n";
            }

            echo "\n";
        }

        // ============================================================
        // 10. FIN DE SEMANA - 25% OFF Sábados y Domingos
        // ============================================================
        if (isset($this->sucursales['CENTRAL']) && isset($this->categorias['ALM'])) {
            echo "   📅 Promoción 10: 25% OFF Fin de semana en Alimentos\n";

            $promo10 = $this->crearPromocion([
                'sucursal_id' => $this->sucursales['CENTRAL']->id,
                'nombre' => 'Promo Fin de Semana',
                'descripcion' => '25% OFF en alimentos sábados y domingos',
                'tipo' => 'descuento_porcentaje',
                'valor' => 25,
                'prioridad' => 11,
                'combinable' => false,
                'activo' => true,
                'dias_semana' => [0, 6], // Domingo y Sábado
            ]);

            if ($promo10) {
                PromocionCondicion::create([
                    'promocion_id' => $promo10->id,
                    'tipo_condicion' => 'por_categoria',
                    'categoria_id' => $this->categorias['ALM']->id,
                ]);
                echo "      ✓ Condición: Categoría Alimentos\n";
                echo "      ✓ Días: Sábados y Domingos\n";
            }

            echo "\n";
        }

        $total = Promocion::count();
        echo "📊 Total promociones creadas: {$total}\n";
    }

    private function crearPromocion(array $data): ?Promocion
    {
        // Verificar si ya existe
        $existing = Promocion::where('sucursal_id', $data['sucursal_id'])
                             ->where('nombre', $data['nombre'])
                             ->first();

        if ($existing) {
            echo "      ⚠️  Promoción ya existe\n";
            return null;
        }

        return Promocion::create($data);
    }
}

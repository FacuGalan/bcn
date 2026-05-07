<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\Articulo;
use App\Models\Cupon;
use App\Models\Promocion;
use App\Models\PromocionEspecial;
use App\Services\CuponService;
use App\Services\OpcionalService;
use App\Services\PuntosService;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Cobertura: items bonificados por cupón quedan EXCLUIDOS del cálculo de
 * promociones (comunes y especiales). Regla 2026-05-07: el cupón tiene
 * prioridad sobre cualquier otro descuento automático en su(s) item(s).
 *
 * También valida el cap del cupón: nunca puede recortar más allá del total
 * disponible — si el monto calculado supera el total_final, se recorta y
 * se notifica al cajero con un toast-warning.
 */
class NuevaVentaCuponExcluyePromosTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        $prefix = $this->tenantPrefix;
        DB::connection('pymes_tenant')->statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            DB::connection('pymes_tenant')->statement("DELETE FROM `{$prefix}promocion_especial_grupo_articulos`");
            DB::connection('pymes_tenant')->statement("DELETE FROM `{$prefix}promocion_especial_grupos`");
        } catch (\Exception $e) {
        }
        DB::connection('pymes_tenant')->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Item con cupón NO debe recibir promo común automática (5% OFF general).
     * El otro item del carrito sí la recibe.
     */
    public function test_item_con_cupon_no_recibe_promo_comun(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 1000,
        ]);
        $papas = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Papas', 'precio_base' => 500,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);
        // Promo automática 5% OFF a TODO el carrito (sin filtros de artículo)
        Promocion::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => '5% OFF general',
            'tipo' => 'descuento_porcentaje',
            'valor' => 5,
            'prioridad' => 1,
            'combinable' => true,
            'activo' => true,
            'usos_actuales' => 0,
        ]);

        $component = $this->prepararComponente();
        $component->items = [
            $this->itemCarritoBase($hamburguesa, 1),
            $this->itemCarritoBase($papas, 1),
        ];

        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        // Esperado: papas tiene 5% OFF aplicado, hamburguesa no.
        $itemHamburguesa = $component->resultado['items'][0];
        $itemPapas = $component->resultado['items'][1];

        $this->assertEquals(0.0, (float) ($itemHamburguesa['descuento_comun'] ?? 0), 'Hamburguesa con cupón no debe recibir promo común');
        $this->assertEmpty($itemHamburguesa['promociones_comunes'] ?? [], 'Hamburguesa no debe tener promos comunes asociadas');
        $this->assertEquals(25.0, (float) ($itemPapas['descuento_comun'] ?? 0), 'Papas (sin cupón) recibe el 5% sobre 500 = 25');
    }

    /**
     * Item con cupón NO debe entrar en una promo especial NxM ("3x2") aunque
     * sea uno de los artículos elegibles.
     */
    public function test_item_con_cupon_no_entra_en_promo_especial_nxm(): void
    {
        $bebida = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Bebida', 'precio_base' => 200,
        ]);
        // Cupón sin límite de cantidad: bonifica TODAS las unidades de la bebida
        $cupon = $this->crearCuponPorcentajeArticulo($bebida, 50, null);

        PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => '3x2 Bebida',
            'tipo' => PromocionEspecial::TIPO_NXM,
            'nxm_lleva' => 3, 'nxm_paga' => 2, 'nxm_bonifica' => 1,
            'beneficio_tipo' => PromocionEspecial::BENEFICIO_GRATIS,
            'nxm_articulo_id' => $bebida->id,
            'prioridad' => 1,
            'modo_aplicacion' => 'automatica',
            'activo' => true, 'usos_actuales' => 0,
        ]);

        $component = $this->prepararComponente();
        // 3 unidades de la misma bebida (qty=3) — sin cupón aplicaría 3x2 (1 gratis)
        $component->items = [$this->itemCarritoBase($bebida, 3)];

        // Aplicamos el cupón: las 3 unidades pasan a estar bonificadas → quedan fuera de la 3x2
        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        $promosEspeciales = $component->resultado['promociones_especiales_aplicadas'] ?? [];
        $this->assertEmpty(
            $promosEspeciales,
            'La promo 3x2 no debe aplicar porque sus unidades están bonificadas por el cupón'
        );

        // Cupón sí aplica: 50% sobre 600 (3 × 200)
        $this->assertEquals(300.0, (float) $component->cuponMontoDescuento);
    }

    /**
     * Cap activo: si el cupón calculado supera el total disponible (ej: hay otros
     * descuentos previos que dejaron poco margen), se recorta a ese máximo y
     * se dispara un toast-warning.
     */
    public function test_cupon_se_recorta_si_supera_total_disponible(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 1000,
        ]);

        // Cupón monto fijo de 5000 — mucho más alto que el total
        $cupon = Cupon::create([
            'codigo' => 'CUP-CAP-'.strtoupper(substr(uniqid(), -4)),
            'tipo' => 'promocional',
            'descripcion' => 'Cupón gigante',
            'modo_descuento' => 'monto_fijo',
            'valor_descuento' => 5000,
            'aplica_a' => 'total',
            'activo' => true,
            'created_by_usuario_id' => 1,
        ]);

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarritoBase($hamburguesa, 1)];
        $component->calcularVenta();

        // total_final pre-cupón = 1000
        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        $this->assertLessThanOrEqual(
            1000.0,
            (float) $component->cuponMontoDescuento,
            'Cupón debe estar capeado al total disponible'
        );
        $this->assertTrue($component->cuponRecortadoPorCap, 'Flag de recorte activo');
    }

    /**
     * Cap NO se activa cuando el cupón cabe dentro del total disponible.
     */
    public function test_cupon_no_se_recorta_si_cabe(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 1000,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarritoBase($hamburguesa, 1)];

        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        $this->assertEquals(500.0, (float) $component->cuponMontoDescuento);
        $this->assertFalse($component->cuponRecortadoPorCap, 'No debe marcar recorte cuando el cupón cabe');
    }

    // ==================== Helpers ====================

    private function prepararComponente(): NuevaVenta
    {
        $component = new NuevaVenta;
        $component->sucursalId = $this->sucursalId;
        $component->boot(
            app(VentaService::class),
            app(OpcionalService::class),
            app(CuponService::class),
            app(PuntosService::class)
        );

        return $component;
    }

    private function crearCuponPorcentajeArticulo(Articulo $articulo, float $porcentaje, ?int $cantidad = null): Cupon
    {
        $cupon = Cupon::create([
            'codigo' => 'CUP-'.strtoupper(substr(uniqid(), -6)),
            'tipo' => 'promocional',
            'descripcion' => "{$porcentaje}% off ".$articulo->nombre,
            'modo_descuento' => 'porcentaje',
            'valor_descuento' => $porcentaje,
            'aplica_a' => 'articulos',
            'activo' => true,
            'created_by_usuario_id' => 1,
        ]);
        $cupon->articulos()->attach($articulo->id, ['cantidad' => $cantidad]);

        return $cupon;
    }

    private function itemCarritoBase(Articulo $articulo, int $cantidad): array
    {
        return [
            'articulo_id' => $articulo->id,
            'nombre' => $articulo->nombre,
            'codigo' => $articulo->codigo,
            'categoria_id' => $articulo->categoria_id,
            'categoria_nombre' => null,
            'precio_base' => (float) $articulo->precio_base,
            'precio' => (float) $articulo->precio_base,
            'tiene_ajuste' => false,
            'cantidad' => $cantidad,
            'iva_codigo' => 5,
            'iva_porcentaje' => 21.0,
            'iva_nombre' => 'IVA 21%',
            'precio_iva_incluido' => true,
            'ajuste_manual_tipo' => null,
            'ajuste_manual_valor' => null,
            'ajuste_manual_origen' => null,
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
            'puntos_canje' => null,
            'pagado_con_puntos' => false,
        ];
    }
}

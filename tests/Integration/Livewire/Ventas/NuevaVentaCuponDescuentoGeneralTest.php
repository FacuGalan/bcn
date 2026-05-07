<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\Articulo;
use App\Models\Cupon;
use App\Services\CuponService;
use App\Services\OpcionalService;
use App\Services\PuntosService;
use App\Services\VentaService;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests del comportamiento del cupón cuando coexiste con descuento general %.
 *
 * Regla de negocio (decision 2026-05-07):
 *   El cupón TIENE PRIORIDAD sobre el descuento general % en los items que bonifica.
 *   El descuento general % NO se aplica a items bonificados por el cupón.
 *
 *   Si el usuario quiere acumular ambos sobre el mismo item, debe agregar después
 *   un ajuste manual % al item (queda con ajuste_manual_origen='manual').
 */
class NuevaVentaCuponDescuentoGeneralTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Escenario 1 — Cupón APLICADO PRIMERO, luego desc gral 10%.
     *   Hamburguesa precio_base = 6875. Cupón 50% off hamburguesa.
     *   Después se aplica desc gral 10% al carrito.
     *   Esperado: el item hamburguesa NO recibe el ajuste 10% porque está bonificado por el cupón.
     *   Cupón debe descontar sobre el precio_base completo.
     */
    public function test_cupon_aplicado_primero_protege_item_del_desc_gral(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 6875,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarritoBase($hamburguesa, 1)];

        // 1. Aplicar cupón primero
        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        $this->assertEquals(3437.50, $component->cuponMontoDescuento, 'Cupón debe calcular sobre precio_base 6875');

        // 2. Aplicar desc gral 10% (simulando aplicarDescuentoPorcentajeATodosLosItems)
        $this->aplicarDescuentoGeneralPorcentaje($component, 10);

        // 3. La hamburguesa NO debe tener ajuste manual porque está bonificada por cupón
        $itemHamburguesa = $component->items[0];
        $this->assertNull(
            $itemHamburguesa['ajuste_manual_tipo'] ?? null,
            'Hamburguesa bonificada por cupón NO debe recibir el descuento general'
        );
        $this->assertEquals(6875, $itemHamburguesa['precio'], 'Precio debe seguir siendo el original');

        // 4. Cupón sigue dando 3437.50 (50% de 6875), no 50% de 6187.50
        $this->assertEquals(3437.50, $component->cuponMontoDescuento);
    }

    /**
     * Escenario 2 — Desc gral aplicado PRIMERO, luego cupón.
     *   Items en el carrito ya tienen ajuste 10% por desc gral.
     *   Al aplicar el cupón, el item bonificado debe perder el ajuste y restaurar precio_base.
     */
    public function test_aplicar_cupon_quita_ajuste_desc_gral_del_item_bonificado(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 6875,
        ]);
        $papas = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Papas', 'precio_base' => 1000,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);

        $component = $this->prepararComponente();
        $component->items = [
            $this->itemCarritoBase($hamburguesa, 1),
            $this->itemCarritoBase($papas, 1),
        ];

        // 1. Aplicar desc gral 10% antes del cupón
        $this->aplicarDescuentoGeneralPorcentaje($component, 10);

        // Sanity: ambos items tienen ajuste 10%
        $this->assertEquals('porcentaje', $component->items[0]['ajuste_manual_tipo']);
        $this->assertEquals('porcentaje', $component->items[1]['ajuste_manual_tipo']);
        $this->assertEquals(6187.50, $component->items[0]['precio']);
        $this->assertEquals(900.00, $component->items[1]['precio']);

        // 2. Aplicar cupón ahora
        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        // 3. Hamburguesa debe haber perdido el ajuste descuento_general
        $itemHamburguesa = $component->items[0];
        $this->assertNull($itemHamburguesa['ajuste_manual_tipo'], 'Hamburguesa debe perder el ajuste por cupón');
        $this->assertEquals(6875, $itemHamburguesa['precio'], 'Hamburguesa restaura precio_base');

        // 4. Papas mantiene su ajuste (no está bonificada por cupón)
        $itemPapas = $component->items[1];
        $this->assertEquals('porcentaje', $itemPapas['ajuste_manual_tipo']);
        $this->assertEquals(900.00, $itemPapas['precio']);

        // 5. Cupón calcula sobre precio_base
        $this->assertEquals(3437.50, $component->cuponMontoDescuento);
    }

    /**
     * Escenario 3 — Quitar el cupón debe restaurar el ajuste de desc gral en el item liberado.
     */
    public function test_quitar_cupon_reaplica_desc_gral_a_item_liberado(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 6875,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarritoBase($hamburguesa, 1)];

        $this->aplicarDescuentoGeneralPorcentaje($component, 10);
        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        // Sanity: hamburguesa quedó sin ajuste
        $this->assertNull($component->items[0]['ajuste_manual_tipo']);

        // Quitar el cupón
        $component->quitarCupon();

        // El ajuste de desc gral debe reaplicarse
        $this->assertEquals('porcentaje', $component->items[0]['ajuste_manual_tipo']);
        $this->assertEquals(10, $component->items[0]['ajuste_manual_valor']);
        $this->assertEquals('descuento_general', $component->items[0]['ajuste_manual_origen']);
        $this->assertEquals(6187.50, $component->items[0]['precio']);
    }

    /**
     * Escenario 4 — Agregar al carrito un artículo bonificado por un cupón ya aplicado
     * + desc gral activo. El item nuevo NO debe heredar el ajuste de desc gral.
     */
    public function test_articulo_bonificado_por_cupon_no_hereda_desc_gral_al_agregarlo(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 6875,
        ]);
        $papas = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Papas', 'precio_base' => 1000,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);

        $component = $this->prepararComponente();
        // Empezamos solo con papas en el carrito
        $component->items = [$this->itemCarritoBase($papas, 1)];
        $this->aplicarDescuentoGeneralPorcentaje($component, 10);

        // Aplicamos cupón (que aún no tiene match en carrito; bonificados queda vacío)
        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        // Forzamos cuponArticulosBonificados manualmente para simular el flujo:
        // en la app real el cupón ya conoce su artículo objetivo aunque aún no esté en carrito.
        $component->cuponArticulosBonificados = [$hamburguesa->id];

        // Ahora agregamos la hamburguesa al carrito
        $component->agregarArticulo($hamburguesa->id);

        // Buscar el ítem hamburguesa
        $itemHamburguesa = collect($component->items)->firstWhere('articulo_id', $hamburguesa->id);
        $this->assertNotNull($itemHamburguesa);
        $this->assertNull(
            $itemHamburguesa['ajuste_manual_tipo'],
            'Hamburguesa recién agregada con cupón ya aplicado NO debe heredar el desc gral'
        );
        $this->assertEquals(6875, $itemHamburguesa['precio']);

        // Y las papas mantienen su ajuste 10%
        $itemPapas = collect($component->items)->firstWhere('articulo_id', $papas->id);
        $this->assertEquals('porcentaje', $itemPapas['ajuste_manual_tipo']);
    }

    /**
     * Escenario 5 — Si el usuario aplica un ajuste manual % AL ITEM bonificado por cupón
     * después del cupón, ese ajuste sí se mantiene (origen='manual', no 'descuento_general').
     * Esto da al usuario la opción de acumular cupón + descuento extra si quiere.
     */
    public function test_ajuste_manual_explicito_se_aplica_sobre_item_con_cupon(): void
    {
        $hamburguesa = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Hamburguesa', 'precio_base' => 6875,
        ]);
        $cupon = $this->crearCuponPorcentajeArticulo($hamburguesa, 50);

        $component = $this->prepararComponente();
        $component->items = [$this->itemCarritoBase($hamburguesa, 1)];

        $component->cuponCodigoInput = $cupon->codigo;
        $component->validarCupon();
        $component->aplicarCupon();

        // Usuario decide agregar manualmente 10% off al item bonificado
        $component->ajusteManualPopoverIndex = 0;
        $component->ajusteManualTipo = 'porcentaje';
        $component->ajusteManualValor = 10;
        $component->aplicarAjusteManual();

        // El ajuste manual quedó pegado al item con origen='manual'
        $itemHamburguesa = $component->items[0];
        $this->assertEquals('porcentaje', $itemHamburguesa['ajuste_manual_tipo']);
        $this->assertEquals('manual', $itemHamburguesa['ajuste_manual_origen']);
        $this->assertEquals(6187.50, $itemHamburguesa['precio']);

        // Cupón se recalcula sobre el precio actual (6187.50) — el ajuste manual sí afecta.
        $component->calcularVenta();
        $this->assertEquals(3093.75, $component->cuponMontoDescuento);
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

    private function crearCuponPorcentajeArticulo(Articulo $articulo, float $porcentaje, ?int $cantidad = 1): Cupon
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

    private function aplicarDescuentoGeneralPorcentaje(NuevaVenta $component, float $porcentaje): void
    {
        $component->descuentoGeneralActivo = true;
        $component->descuentoGeneralTipo = 'porcentaje';
        $component->descuentoGeneralValor = $porcentaje;
        $reflection = new \ReflectionMethod($component, 'aplicarDescuentoPorcentajeATodosLosItems');
        $reflection->setAccessible(true);
        $reflection->invoke($component, $porcentaje);
        $component->calcularVenta();
    }
}

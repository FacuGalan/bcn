<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\Articulo;
use App\Models\PromocionEspecial;
use App\Models\PromocionEspecialGrupo;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests del algoritmo de selección de promociones especiales en NuevaVenta.
 *
 * Reglas verificadas:
 * - Modo AUTOMATICA: el sistema elige la combinación que MÁS ahorra al cliente.
 * - Modo FORZADA: se aplica siempre por orden de prioridad, aunque sea sub-óptima.
 * - Forzadas se evalúan ANTES que automáticas (consumen unidades primero).
 */
class NuevaVentaMejorPromocionTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        // Limpiar tablas de grupos (no están en set default de WithTenant)
        $prefix = $this->tenantPrefix;
        DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 0');
        try {
            DB::connection('pymes')->statement("DELETE FROM `{$prefix}promocion_especial_grupo_articulos`");
            DB::connection('pymes')->statement("DELETE FROM `{$prefix}promocion_especial_grupos`");
        } catch (\Exception $e) {
        }
        DB::connection('pymes')->statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    /**
     * Escenario: menú vs 3x2 bebidas ambas AUTOMÁTICAS, compitiendo por bebidas.
     * El sistema debe elegir el menú (ahorro $4.500) vs 3x2 (ahorro $2.500).
     */
    public function test_modo_automatico_elige_la_promocion_que_mas_ahorra(): void
    {
        [$plato, $postre, $bebida] = $this->crearArticulosMenu();
        $this->crearPromoMenu($plato, $postre, $bebida, 'automatica', 10);
        $this->crearPromo3x2Bebidas($bebida, 'automatica', 1); // prioridad MAYOR

        $items = [
            $this->itemCarrito($plato, 1),
            $this->itemCarrito($postre, 1),
            $this->itemCarrito($bebida, 3),
        ];

        $resultado = $this->calcular($items);

        $this->assertEquals(13000, $resultado['subtotal']);
        $this->assertEquals(
            4500,
            $resultado['total_descuentos'],
            'El sistema debería elegir el menú ($4.500) sobre el 3x2 ($2.500).'
        );

        $nombres = array_column($resultado['promociones_especiales_aplicadas'], 'nombre');
        $this->assertContains('Menú del día', $nombres);
        $this->assertNotContains('3x2 Bebidas', $nombres);
    }

    /**
     * Escenario: misma configuración pero el 3x2 bebidas es FORZADA.
     * Como las forzadas se aplican primero y consumen bebidas, el menú
     * ya no puede aplicar.
     */
    public function test_modo_forzado_se_aplica_aunque_sea_suboptimo(): void
    {
        [$plato, $postre, $bebida] = $this->crearArticulosMenu();
        $this->crearPromoMenu($plato, $postre, $bebida, 'automatica', 10);
        $this->crearPromo3x2Bebidas($bebida, 'forzada', 1);

        $items = [
            $this->itemCarrito($plato, 1),
            $this->itemCarrito($postre, 1),
            $this->itemCarrito($bebida, 3),
        ];

        $resultado = $this->calcular($items);

        $nombres = array_column($resultado['promociones_especiales_aplicadas'], 'nombre');
        $this->assertContains('3x2 Bebidas', $nombres, 'La forzada debe aplicarse siempre.');
        $this->assertNotContains('Menú del día', $nombres, 'El menú no debe aplicar: sus bebidas fueron consumidas por la forzada.');
        $this->assertEquals(2500, $resultado['total_descuentos']);
    }

    /**
     * Escenario: dos promos automáticas que NO comparten artículos → ambas aplican.
     * Valida que el algoritmo no descarta promos compatibles entre sí.
     */
    public function test_automaticas_compatibles_se_apilan(): void
    {
        [$plato, $postre, $bebida] = $this->crearArticulosMenu();
        $alfajor = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Alfajor', 'precio_base' => 1000,
        ]);

        $this->crearPromoMenu($plato, $postre, $bebida, 'automatica', 10);
        // 3x2 en alfajores (no conflicta con menú)
        PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => '3x2 Alfajores',
            'tipo' => PromocionEspecial::TIPO_NXM,
            'nxm_lleva' => 3, 'nxm_paga' => 2, 'nxm_bonifica' => 1,
            'beneficio_tipo' => PromocionEspecial::BENEFICIO_GRATIS,
            'nxm_articulo_id' => $alfajor->id,
            'prioridad' => 5,
            'modo_aplicacion' => 'automatica',
            'activo' => true, 'usos_actuales' => 0,
        ]);

        $items = [
            $this->itemCarrito($plato, 1),
            $this->itemCarrito($postre, 1),
            $this->itemCarrito($bebida, 1),
            $this->itemCarrito($alfajor, 3),
        ];

        $resultado = $this->calcular($items);

        // Subtotal: 4000 + 1500 + 2500 + 3000 = 11.000
        // Menú: -$4.500 | 3x2 alfajores: -$1.000
        $this->assertEquals(11000, $resultado['subtotal']);
        $this->assertEquals(5500, $resultado['total_descuentos']);

        $nombres = array_column($resultado['promociones_especiales_aplicadas'], 'nombre');
        $this->assertContains('Menú del día', $nombres);
        $this->assertContains('3x2 Alfajores', $nombres);
    }

    // ==================== Helpers ====================

    private function crearArticulosMenu(): array
    {
        return [
            $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
                'nombre' => 'Milanesa', 'precio_base' => 4000,
            ]),
            $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
                'nombre' => 'Flan', 'precio_base' => 1500,
            ]),
            $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
                'nombre' => 'Gaseosa', 'precio_base' => 2500,
            ]),
        ];
    }

    private function crearPromoMenu(
        Articulo $plato,
        Articulo $postre,
        Articulo $bebida,
        string $modo,
        int $prioridad
    ): PromocionEspecial {
        $menu = PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Menú del día',
            'tipo' => PromocionEspecial::TIPO_MENU,
            'precio_tipo' => 'fijo',
            'precio_valor' => 3500, // Total normal $8.000 → descuento $4.500
            'prioridad' => $prioridad,
            'modo_aplicacion' => $modo,
            'activo' => true, 'usos_actuales' => 0,
        ]);

        foreach ([['Plato', $plato], ['Postre', $postre], ['Bebida', $bebida]] as [$nombre, $articulo]) {
            $grupo = PromocionEspecialGrupo::create([
                'promocion_especial_id' => $menu->id,
                'nombre' => $nombre,
                'cantidad' => 1,
                'orden' => 1,
                'es_trigger' => false,
                'es_reward' => false,
            ]);
            $grupo->articulos()->attach($articulo->id);
        }

        return $menu;
    }

    private function crearPromo3x2Bebidas(Articulo $bebida, string $modo, int $prioridad): PromocionEspecial
    {
        return PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => '3x2 Bebidas',
            'tipo' => PromocionEspecial::TIPO_NXM,
            'nxm_lleva' => 3, 'nxm_paga' => 2, 'nxm_bonifica' => 1,
            'beneficio_tipo' => PromocionEspecial::BENEFICIO_GRATIS,
            'nxm_articulo_id' => $bebida->id,
            'prioridad' => $prioridad,
            'modo_aplicacion' => $modo,
            'activo' => true, 'usos_actuales' => 0,
        ]);
    }

    private function calcular(array $items): array
    {
        $component = new NuevaVenta;
        $component->sucursalId = $this->sucursalId;
        $component->items = $items;
        $component->calcularVenta();

        return $component->resultado;
    }

    private function itemCarrito(Articulo $articulo, int $cantidad): array
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
            'precio_sin_ajuste_manual' => null,
            'opcionales' => [],
            'precio_opcionales' => 0,
            'puntos_canje' => null,
            'pagado_con_puntos' => false,
        ];
    }
}

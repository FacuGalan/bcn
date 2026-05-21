<?php

namespace Tests\Integration\Livewire\Ventas;

use App\Livewire\Ventas\NuevaVenta;
use App\Models\Articulo;
use App\Models\Cupon;
use App\Models\PromocionEspecial;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Tests integrados de invitaciones (cortesía) contra el motor de beneficios.
 *
 * Fase 10 del feature `invitaciones-pedidos-ventas`. Cubre los casos de
 * aceptación CA-13/14/15 del spec: items invitados deben quedar EXCLUIDOS
 * del motor de promociones especiales, cupones (monto mínimo) y descuento
 * general (RF-11). El helper `getItemsParaMotorBeneficios()` filtra los
 * invitados antes de evaluar cualquier beneficio.
 *
 * Patrón de ejecución (idéntico a NuevaVentaMejorPromocionTest):
 * instanciamos NuevaVenta como objeto plano, seteamos items vía
 * `itemCarrito()` y, cuando hace falta marcar invitación, llamamos a
 * `marcarItemComoInvitado()` (método protected del trait WithInvitaciones)
 * vía Reflection. Después corremos `calcularVenta()` y assertamos sobre
 * el `resultado`.
 */
class NuevaVentaInvitacionesIntegracionTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        $this->crearTiposIva();

        // Limpiar tablas de promos especiales (mismo cleanup que MejorPromoTest).
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
     * CA-13: 5 unidades de un mismo artículo. 2 invitadas. Promo "3 paga 2"
     * (NxM 3→1 gratis) sobre ese artículo → el motor evalúa SOLO las 3 no
     * invitadas, aplica al patrón. Las 2 invitadas quedan con
     * descuento_promocion=0 y tiene_promocion=false.
     */
    public function test_ca13_promo_nxm_no_aplica_sobre_items_invitados(): void
    {
        $bebida = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Coca', 'precio_base' => 200,
        ]);

        PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => '3x2 Coca',
            'tipo' => PromocionEspecial::TIPO_NXM,
            'nxm_lleva' => 3, 'nxm_paga' => 2, 'nxm_bonifica' => 1,
            'beneficio_tipo' => PromocionEspecial::BENEFICIO_GRATIS,
            'nxm_articulo_id' => $bebida->id,
            'prioridad' => 1,
            'modo_aplicacion' => 'automatica',
            'activo' => true,
            'usos_actuales' => 0,
        ]);

        // 5 líneas separadas del mismo artículo. Marcamos 2 como invitadas
        // para que el motor SOLO vea 3 unidades elegibles → 3 entran al
        // patrón 3x2 → 1 gratis. Si el filtro estuviese roto, vería 5 y
        // aplicaría 1 patrón con la unidad MÁS BARATA gratis (=200), pero
        // alguna de las "más baratas" candidatas sería invitada (precio=0),
        // y el resultado contaminaría las columnas de descuento.
        $items = array_fill(0, 5, $this->itemCarrito($bebida, 1));

        $componente = $this->instanciarNuevaVenta($items);

        // Marcar items [3] y [4] como invitados.
        $this->invocarMarcarInvitado($componente, 3, 'cortesía 1');
        $this->invocarMarcarInvitado($componente, 4, 'cortesía 2');

        $componente->calcularVenta();
        $resultado = $componente->resultado;

        // Subtotal incluye TODOS los items pero los invitados tienen precio=0,
        // así que aporta solo 3*200=600.
        $this->assertEqualsWithDelta(600, (float) $resultado['subtotal'], 0.01,
            'Subtotal cobrable: 3 unidades a 200 (2 invitadas tienen precio 0)');

        // El descuento de la promo es 200 (1 bebida gratis sobre las 3 elegibles).
        $this->assertEqualsWithDelta(200, (float) $resultado['total_descuentos'], 0.01,
            'Promo 3x2 sobre 3 unidades no invitadas: 1 unidad gratis');

        // Items invitados (indices 3 y 4): tiene_promocion=false, sin descuento.
        foreach ([3, 4] as $idxInvitado) {
            $itemRes = $resultado['items'][$idxInvitado] ?? null;
            $this->assertNotNull($itemRes, "Item invitado #{$idxInvitado} debe estar en resultado['items']");
            $this->assertEmpty($itemRes['promociones_especiales'] ?? [],
                "Item invitado #{$idxInvitado} no debe figurar en promociones_especiales");
            $this->assertEqualsWithDelta(0.0, (float) ($itemRes['descuento_comun'] ?? 0), 0.01,
                "Item invitado #{$idxInvitado} debe tener descuento_comun=0");
        }

        // Items no invitados (indices 0, 1, 2): al menos uno debe haber recibido
        // el descuento de la promo (la unidad bonificada).
        $descuentoEnNoInvitados = 0.0;
        foreach ([0, 1, 2] as $idxNoInv) {
            $itemRes = $resultado['items'][$idxNoInv] ?? [];
            foreach ($itemRes['promociones_especiales'] ?? [] as $p) {
                $descuentoEnNoInvitados += (float) ($p['descuento'] ?? 0);
            }
        }
        $this->assertEqualsWithDelta(200, $descuentoEnNoInvitados, 0.01,
            'La unidad gratis se debe atribuir a uno de los items NO invitados');
    }

    /**
     * CA-14: 4 items por $400 c/u (subtotal cobrable $1600). Marcamos 2
     * como invitados → subtotal cobrable cae a $800. Cupón con monto mínimo
     * $1000 → NO debe aplicar (el motor calcula contra el subtotal sin
     * invitados, no el subtotal "histórico").
     */
    public function test_ca14_cupon_monto_minimo_se_evalua_sobre_subtotal_sin_invitados(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto X', 'precio_base' => 400,
        ]);

        // Crear user system_admin para bypass de permisos.
        $user = \App\Models\User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        Auth::login($user);

        $codigoCupon = 'MIN1000-'.uniqid();
        Cupon::create([
            'codigo' => $codigoCupon,
            'descripcion' => 'Test monto mínimo',
            'tipo_descuento' => 'porcentaje',
            'valor_descuento' => 10,
            'monto_minimo' => 1000,
            'activo' => true,
            'usos_actuales' => 0,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addMonths(1),
            'aplicabilidad' => 'general',
            'created_by_usuario_id' => $user->id,
        ]);

        $items = array_fill(0, 4, $this->itemCarrito($articulo, 1));
        $componente = $this->instanciarNuevaVenta($items);

        // Marcar items 2 y 3 como invitados → cobrable cae a $800.
        $this->invocarMarcarInvitado($componente, 2, 'cortesía');
        $this->invocarMarcarInvitado($componente, 3, 'cortesía');

        // Aplicar cupón vía codigo (recorre el flow estándar de WithCupones).
        $componente->cuponCodigoInput = $codigoCupon;
        $componente->aplicarCupon();

        // El cupón NO debió aplicar: subtotal cobrable ($800) < monto mínimo ($1000).
        $this->assertFalse((bool) $componente->cuponAplicado,
            'Cupón con monto mínimo $1000 no debe aplicar si subtotal sin invitados es $800');
        $this->assertEqualsWithDelta(0.0, (float) $componente->cuponMontoDescuento, 0.01);
    }

    /**
     * CA-15: 3 items por $500 c/u. Aplicamos descuento general 10%. Marcamos
     * uno como invitado → el descuento general 10% se aplica SOLO a los 2
     * no invitados. El item invitado queda con descuento_general_aplicado=0
     * y precio cobrable=0.
     */
    public function test_ca15_descuento_general_se_aplica_solo_a_items_no_invitados(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 100, 'unitario', [
            'nombre' => 'Producto Y', 'precio_base' => 500,
        ]);

        $user = \App\Models\User::factory()->create(['is_system_admin' => true]);
        $user->comercios()->syncWithoutDetaching([$this->comercio->id]);
        Auth::login($user);

        $items = array_fill(0, 3, $this->itemCarrito($articulo, 1));
        $componente = $this->instanciarNuevaVenta($items);

        // Aplicar descuento general 10% ANTES de invitar (es el flujo real).
        $componente->descuentoGeneralInputTipo = 'porcentaje';
        $componente->descuentoGeneralInputValor = 10;
        $componente->aplicarDescuentoGeneral();

        // Sanity: descuento aplicado y los 3 items con precio 450 (500 - 10%).
        $this->assertTrue((bool) $componente->descuentoGeneralActivo);
        foreach (range(0, 2) as $idx) {
            $this->assertEqualsWithDelta(450, (float) $componente->items[$idx]['precio'], 0.01,
                "Item #{$idx} debe tener 10% de descuento aplicado");
        }

        // Marcar item 2 como invitado: debería restablecer su precio a 0 y
        // limpiar el ajuste manual del descuento general en esa línea.
        $this->invocarMarcarInvitado($componente, 2, 'cortesía');
        $componente->calcularVenta();

        // Items 0 y 1: siguen con el 10%.
        foreach ([0, 1] as $idxNoInv) {
            $this->assertEqualsWithDelta(450, (float) $componente->items[$idxNoInv]['precio'], 0.01,
                "Item no invitado #{$idxNoInv} mantiene el descuento general");
        }

        // Item 2: invitado, precio cobrable 0 y campos de descuento en cero.
        $invitado = $componente->items[2];
        $this->assertTrue((bool) $invitado['es_invitacion']);
        $this->assertEqualsWithDelta(0.0, (float) $invitado['precio'], 0.01,
            'Item invitado tiene precio cobrable 0');
        $this->assertEqualsWithDelta(0.0, (float) ($invitado['descuento_general_aplicado'] ?? 0), 0.01);
        $this->assertNull($invitado['ajuste_manual_tipo'] ?? null,
            'Al invitar se debe limpiar el ajuste_manual_tipo (defense in depth — RF-11)');
    }

    // ==================== HELPERS DE TEST ====================

    private function instanciarNuevaVenta(array $items): NuevaVenta
    {
        $componente = new NuevaVenta;
        $componente->sucursalId = $this->sucursalId;
        $componente->cajaSeleccionada = $this->cajaId;
        $componente->items = $items;

        return $componente;
    }

    private function invocarMarcarInvitado(NuevaVenta $componente, int $index, string $motivo): void
    {
        // marcarItemComoInvitado es protected en el trait WithInvitaciones.
        $ref = new \ReflectionMethod($componente, 'marcarItemComoInvitado');
        $ref->setAccessible(true);
        $ref->invoke($componente, $index, $motivo);

        // Recalcular total_invitado y refrescar arrays (lo hace confirmarInvitarItem).
        $refRec = new \ReflectionMethod($componente, 'recalcularTotalInvitado');
        $refRec->setAccessible(true);
        $refRec->invoke($componente);
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

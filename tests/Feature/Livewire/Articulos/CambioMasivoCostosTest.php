<?php

namespace Tests\Feature\Livewire\Articulos;

use App\Livewire\Articulos\CambioMasivoPrecios;
use App\Models\Articulo;
use App\Models\ArticuloCosto;
use App\Models\HistorialCosto;
use App\Models\HistorialPrecio;
use App\Models\User;
use App\Services\CostoService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Bloque C del spec hardening-circuito-precios: cambio masivo extendido a
 * COSTOS (RF-C1/C2/C3) sobre el costo último (rector) de la sucursal activa,
 * vía la puerta única CostoService::actualizarManual (origen 'masivo').
 */
class CambioMasivoCostosTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $this->actingAs(User::factory()->create(['is_system_admin' => true]));
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Livewire::withoutLazyLoading();
        $this->crearTiposIva();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearArticuloConCosto(float $costo, array $overrides = []): Articulo
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', $overrides);

        ArticuloCosto::create([
            'articulo_id' => $articulo->id,
            'sucursal_id' => $this->sucursalId,
            'costo_ultimo' => $costo,
            'fecha_costo_ultimo' => now(),
        ]);

        return $articulo;
    }

    private function costoDe(Articulo $articulo): ?float
    {
        $valor = ArticuloCosto::where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('costo_ultimo');

        return $valor !== null ? (float) $valor : null;
    }

    private function overrideDe(Articulo $articulo): ?float
    {
        $valor = DB::connection('pymes_tenant')->table('articulos_sucursales')
            ->where('articulo_id', $articulo->id)
            ->where('sucursal_id', $this->sucursalId)
            ->value('precio_base');

        return $valor !== null ? (float) $valor : null;
    }

    public function test_modo_costo_actualiza_costo_ultimo_con_historial_masivo(): void
    {
        // RF-C2: +10% sobre costo_ultimo 100 ⇒ 110 con historial 'masivo';
        // el precio de venta NO se toca (sub-opción default 'no').
        $articulo = $this->crearArticuloConCosto(100.0, ['precio_base' => 500]);

        Livewire::test(CambioMasivoPrecios::class)
            ->set('tipoAjuste', 'recargo')
            ->set('valorAjuste', 10)
            ->set('objetivoCambio', 'costo')
            ->call('siguientePaso')
            ->call('aplicarCambios')
            ->assertOk();

        $this->assertEquals(110.0, $this->costoDe($articulo));
        $this->assertTrue(
            HistorialCosto::where('articulo_id', $articulo->id)
                ->where('tipo_costo', 'ultimo')
                ->where('origen', 'masivo')
                ->exists()
        );

        // Precio intacto: ni el base ni un override nuevo.
        $this->assertEquals(500.0, (float) $articulo->fresh()->precio_base);
        $this->assertNull($this->overrideDe($articulo));
    }

    public function test_modo_costo_automatico_repricea_solo_los_opt_in(): void
    {
        // RF-C2 sub-opción automática: tras el costo nuevo, SOLO los artículos
        // con precio_administrado_por_utilidad se repricean con la fórmula del
        // sugerido (RF-C4, misma que compras), origen 'utilidad_automatica'.
        $optIn = $this->crearArticuloConCosto(100.0, [
            'precio_base' => 200,
            'utilidad_porcentaje' => 50,
            'precio_administrado_por_utilidad' => true,
        ]);
        $noOptIn = $this->crearArticuloConCosto(100.0, ['precio_base' => 200]);

        Livewire::test(CambioMasivoPrecios::class)
            ->set('tipoAjuste', 'recargo')
            ->set('valorAjuste', 10)
            ->set('objetivoCambio', 'costo')
            ->set('actualizarPrecioTrasCosto', 'automatico')
            ->call('siguientePaso')
            ->call('aplicarCambios')
            ->assertOk();

        $this->assertEquals(110.0, $this->costoDe($optIn));
        $this->assertEquals(110.0, $this->costoDe($noOptIn));

        // El sugerido depende de la alícuota efectiva del entorno de test
        // (CUIT residual RI o no): assert contra la cuenta del service.
        $sugerido = round((float) app(CostoService::class)->precioSugerido($optIn->fresh(), $this->sucursalId), 2);
        $this->assertEquals($sugerido, (float) $optIn->fresh()->precio_base);
        $this->assertTrue(
            HistorialPrecio::where('articulo_id', $optIn->id)->where('origen', 'utilidad_automatica')->exists()
        );

        // El no-opt-in no se toca.
        $this->assertEquals(200.0, (float) $noOptIn->fresh()->precio_base);
        $this->assertNull($this->overrideDe($noOptIn));
    }

    public function test_modo_ambos_aplica_el_mismo_porcentaje_a_costo_y_precio(): void
    {
        // RF-C3: el MISMO % a costo_ultimo y al precio efectivo, con dos
        // historiales (costos 'masivo' + precios 'masivo_sucursal').
        $articulo = $this->crearArticuloConCosto(100.0, ['precio_base' => 500]);

        Livewire::test(CambioMasivoPrecios::class)
            ->set('tipoAjuste', 'recargo')
            ->set('valorAjuste', 10)
            ->set('objetivoCambio', 'ambos')
            ->call('siguientePaso')
            ->call('aplicarCambios')
            ->assertOk();

        $this->assertEquals(110.0, $this->costoDe($articulo));
        $this->assertEquals(550.0, $this->overrideDe($articulo));

        $this->assertTrue(
            HistorialCosto::where('articulo_id', $articulo->id)->where('origen', 'masivo')->exists()
        );
        $this->assertTrue(
            HistorialPrecio::where('articulo_id', $articulo->id)->where('origen', 'masivo_sucursal')->exists()
        );
    }

    public function test_sin_permiso_el_selector_vuelve_a_precio(): void
    {
        // RF-C1: sin func.costos.editar los modos de costo no están disponibles
        // (defensa server-side además del gate de la vista).
        $this->actingAs(User::factory()->create());

        Livewire::test(CambioMasivoPrecios::class)
            ->set('objetivoCambio', 'costo')
            ->assertSet('objetivoCambio', 'precio');
    }

    public function test_articulo_sin_costo_se_saltea(): void
    {
        // RF-C2: sin costo alguno (ni sucursal ni consolidado) no hay base
        // sobre la cual aplicar el % — la fila se marca y se saltea.
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['precio_base' => 500]);

        $editor = Livewire::test(CambioMasivoPrecios::class)
            ->set('tipoAjuste', 'recargo')
            ->set('valorAjuste', 10)
            ->set('objetivoCambio', 'costo')
            ->call('siguientePaso');

        $this->assertTrue((bool) ($editor->get('articulosPreview')[$articulo->id]['sin_costo'] ?? false));

        $editor->call('aplicarCambios')->assertOk();

        $this->assertNull($this->costoDe($articulo));
        $this->assertFalse(
            HistorialCosto::where('articulo_id', $articulo->id)->where('origen', 'masivo')->exists()
        );
    }
}

<?php

namespace Tests\Feature\Livewire\Puntos;

use App\Livewire\Puntos\ProgramaPuntos;
use App\Models\ConfiguracionPuntos;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Sección "Canje de artículos" del programa de puntos (RF-T58/RF-T60):
 * switch de restricción, habilitación por artículo (pivot canje_tienda),
 * puntos fijos, modo de opcionales y acciones masivas — todo con guardado
 * inmediato.
 */
class ProgramaPuntosCanjeTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->crearTiposIva();

        $user = User::factory()->create(['is_system_admin' => true]);
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        ConfiguracionPuntos::updateOrCreate([], [
            'activo' => true,
            'modo_acumulacion' => 'global',
            'monto_por_punto' => 100,
            'valor_punto_canje' => 50,
            'minimo_canje' => 10,
            'redondeo' => 'floor',
        ]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    protected function canjeTienda(int $articuloId): bool
    {
        return (bool) DB::connection('pymes_tenant')
            ->table('articulos_sucursales')
            ->where('articulo_id', $articuloId)
            ->where('sucursal_id', $this->sucursalId)
            ->value('canje_tienda');
    }

    public function test_toggle_restringir_canje_persiste_al_instante(): void
    {
        $this->assertFalse((bool) ConfiguracionPuntos::first()->restringir_canje_articulos);

        Livewire::test(ProgramaPuntos::class)
            ->call('toggleRestringirCanje')
            ->assertDispatched('toast-success');

        $this->assertTrue((bool) ConfiguracionPuntos::first()->fresh()->restringir_canje_articulos);
    }

    public function test_toggle_canje_articulo_prende_el_pivot_de_la_sucursal(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);
        $this->assertFalse($this->canjeTienda($articulo->id));

        $componente = Livewire::test(ProgramaPuntos::class);
        $componente->call('toggleCanjeArticulo', $articulo->id);
        $this->assertTrue($this->canjeTienda($articulo->id));

        $componente->call('toggleCanjeArticulo', $articulo->id);
        $this->assertFalse($this->canjeTienda($articulo->id));
    }

    public function test_masivo_habilita_y_quita_todos(): void
    {
        // UNA sola instancia: el componente es #[Lazy] y withoutLazyLoading()
        // se agota tras el primer ciclo — una segunda Livewire::test montaría
        // el placeholder (sin mount ni acciones).
        $a = $this->crearArticuloConStock($this->sucursalId);
        $b = $this->crearArticuloConStock($this->sucursalId);

        $componente = Livewire::test(ProgramaPuntos::class)
            ->assertSet('canjeSucursalId', (int) $this->sucursalId);

        $componente->call('habilitarTodosCanje')->assertDispatched('toast-success');
        $this->assertTrue($this->canjeTienda($a->id));
        $this->assertTrue($this->canjeTienda($b->id));

        $componente->call('quitarTodosCanje')->assertDispatched('toast-success');
        $this->assertFalse($this->canjeTienda($a->id));
        $this->assertFalse($this->canjeTienda($b->id));
    }

    public function test_puntos_fijos_y_modo_de_opcionales_persisten(): void
    {
        $articulo = $this->crearArticuloConStock($this->sucursalId);

        $componente = Livewire::test(ProgramaPuntos::class);

        $componente->call('guardarPuntosCanjeArticulo', $articulo->id, '12');
        $this->assertSame(12, $articulo->fresh()->puntos_canje);

        $componente->call('guardarPuntosCanjeArticulo', $articulo->id, '');
        $this->assertNull($articulo->fresh()->puntos_canje);

        $componente->call('guardarCanjeOpcionales', $articulo->id, 'en_plata');
        $this->assertSame('en_plata', $articulo->fresh()->canje_opcionales);

        // Modo inválido: se ignora sin tocar el guardado.
        $componente->call('guardarCanjeOpcionales', $articulo->id, 'gratis');
        $this->assertSame('en_plata', $articulo->fresh()->canje_opcionales);
    }
}

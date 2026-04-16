<?php

namespace Tests\Feature\Livewire\Cajas;

use App\Livewire\Cajas\AjustesPostCierre;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class AjustesPostCierreTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create();
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_componente_renderiza_sin_errores(): void
    {
        Livewire::test(AjustesPostCierre::class)->assertOk();
    }

    public function test_componente_muestra_titulo_del_reporte(): void
    {
        \Livewire\Livewire::withoutLazyLoading();

        Livewire::test(AjustesPostCierre::class)
            ->assertSeeText('Reporte de ajustes post-cierre');
    }

    public function test_filtros_se_resetean_correctamente(): void
    {
        Livewire::test(AjustesPostCierre::class)
            ->set('filtroFechaDesde', '2026-01-01')
            ->set('filtroTipoOperacion', 'cambio_pago')
            ->call('limpiarFiltros')
            ->assertSet('filtroFechaDesde', '')
            ->assertSet('filtroTipoOperacion', '');
    }

    public function test_render_con_ajuste_post_cierre_no_explota_por_relaciones(): void
    {
        \Livewire\Livewire::withoutLazyLoading();
        $this->crearTiposIva();

        // Crear venta real para satisfacer FK
        $venta = $this->crearVentaBasica();

        $cierre = \App\Models\CierreTurno::create([
            'sucursal_id' => $this->sucursalId,
            'usuario_id' => auth()->id(),
            'fecha_apertura' => now()->subHours(8),
            'fecha_cierre' => now()->subHour(),
            'tipo' => 'individual',
            'estado' => 'cerrado',
        ]);

        \App\Models\VentaPagoAjuste::create([
            'venta_id' => $venta->id,
            'sucursal_id' => $this->sucursalId,
            'tipo_operacion' => \App\Models\VentaPagoAjuste::TIPO_CAMBIO,
            'delta_total' => -200,
            'delta_fiscal' => false,
            'es_post_cierre' => true,
            'turno_original_id' => $cierre->id,
            'motivo' => 'Test motivo con mas de 10 caracteres',
            'descripcion_auto' => 'Cambió FP X $500 por FP Y $300',
            'usuario_id' => auth()->id(),
        ]);

        // Si el componente intenta resolver una relación inexistente al renderizar, explota
        Livewire::test(AjustesPostCierre::class)
            ->assertOk()
            ->assertSeeText('Cambió FP X');
    }
}

<?php

namespace Tests\Feature\Livewire\Stock;

use App\Livewire\Stock\InventarioGeneral;
use App\Livewire\Stock\Produccion;
use App\Livewire\Stock\ProduccionLote;
use App\Livewire\Stock\StockInventario;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Componentes ya cubiertos: MovimientosStock.
 */
class SmokeStockTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        $user = User::factory()->create();
        $this->actingAs($user);
        session(['comercio_activo_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId]);

        Livewire::withoutLazyLoading();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_inventario_general_monta(): void
    {
        Livewire::test(InventarioGeneral::class)->assertOk();
    }

    public function test_produccion_monta(): void
    {
        Livewire::test(Produccion::class)->assertOk();
    }

    public function test_produccion_lote_monta(): void
    {
        Livewire::test(ProduccionLote::class)->assertOk();
    }

    public function test_stock_inventario_monta(): void
    {
        Livewire::test(StockInventario::class)->assertOk();
    }
}

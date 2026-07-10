<?php

namespace Tests\Feature\Livewire\Compras;

use App\Livewire\Compras\Compras;
use App\Livewire\Compras\EditorCompra;
use App\Livewire\Compras\GestionarPagosProveedores;
use App\Livewire\Compras\GestionarProveedores;
use App\Models\Proveedor;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Smoke tests del módulo Compras (spec compras-costos, Fase 5): detectan
 * errores de mount, sintaxis Blade y variables indefinidas.
 */
class SmokeComprasTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->setUpCaja();
        Livewire::withoutLazyLoading();
        $this->actingAs(\App\Models\User::first() ?? \App\Models\User::factory()->create());
    }

    protected function tearDown(): void
    {
        $this->tearDownTenant();
        parent::tearDown();
    }

    public function test_gestionar_proveedores_monta(): void
    {
        Livewire::test(GestionarProveedores::class)->assertOk();
    }

    public function test_gestionar_proveedores_abre_modales(): void
    {
        $proveedor = Proveedor::create(['nombre' => 'Prov Smoke '.uniqid(), 'activo' => true, 'tiene_cuenta_corriente' => true]);

        Livewire::test(GestionarProveedores::class)
            ->call('create')
            ->assertSet('showModal', true)
            ->call('cancel')
            ->call('openCuentasModal')
            ->assertSet('showCuentasModal', true)
            ->call('verExtracto', $proveedor->id)
            ->assertSet('showExtractoModal', true)
            ->assertOk();
    }

    public function test_gestionar_pagos_proveedores_monta(): void
    {
        Livewire::test(GestionarPagosProveedores::class)->assertOk();
    }

    public function test_gestionar_pagos_proveedores_abre_modal_pago(): void
    {
        $proveedor = Proveedor::create(['nombre' => 'Prov Pago Smoke '.uniqid(), 'activo' => true, 'tiene_cuenta_corriente' => true]);

        Livewire::test(GestionarPagosProveedores::class)
            ->call('abrirPago', $proveedor->id)
            ->assertSet('showPagoModal', true)
            ->call('verExtracto', $proveedor->id)
            ->assertSet('showExtractoModal', true)
            ->assertOk();
    }

    // ==================== Fase 6: listado + editor ====================

    public function test_compras_monta(): void
    {
        Livewire::test(Compras::class)->assertOk();
    }

    public function test_compras_abre_editor_nueva_compra(): void
    {
        Livewire::test(Compras::class)
            ->call('abrirNuevaCompra')
            ->assertSet('editorAbierto', true)
            ->assertSet('compraIdEnEdicion', null)
            ->assertOk();
    }

    public function test_compras_abre_editor_nc_suelta(): void
    {
        Livewire::test(Compras::class)
            ->call('abrirNuevaNC')
            ->assertSet('editorAbierto', true)
            ->assertSet('editorEsNC', true)
            ->assertOk();
    }

    public function test_editor_compra_monta_en_alta(): void
    {
        Livewire::test(EditorCompra::class)->assertOk();
    }

    public function test_editor_compra_monta_como_nc(): void
    {
        Livewire::test(EditorCompra::class, ['esNC' => true])->assertOk();
    }

    public function test_editor_compra_agrega_y_quita_renglones(): void
    {
        Livewire::test(EditorCompra::class)
            ->call('agregarRenglon')
            ->call('quitarRenglon', 1)
            ->call('agregarConcepto')
            ->call('agregarPercepcion')
            ->assertOk();
    }
}

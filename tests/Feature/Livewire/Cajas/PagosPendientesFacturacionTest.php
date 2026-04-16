<?php

namespace Tests\Feature\Livewire\Cajas;

use App\Livewire\Cajas\PagosPendientesFacturacion;
use App\Models\User;
use App\Models\VentaPago;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

class PagosPendientesFacturacionTest extends TestCase
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
        Livewire::test(PagosPendientesFacturacion::class)->assertOk();
    }

    public function test_componente_muestra_titulo(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(PagosPendientesFacturacion::class)
            ->assertSeeText('Pagos pendientes de facturar');
    }

    public function test_filtros_se_resetean_correctamente(): void
    {
        Livewire::test(PagosPendientesFacturacion::class)
            ->set('filtroFechaDesde', '2026-01-01')
            ->set('filtroEstado', 'error_arca')
            ->call('limpiarFiltros')
            ->assertSet('filtroFechaDesde', '')
            ->assertSet('filtroEstado', 'todos');
    }

    public function test_lista_pagos_pendientes_de_facturar(): void
    {
        Livewire::withoutLazyLoading();
        $this->crearTiposIva();

        $venta = $this->crearVentaBasica();
        $concepto = \App\Models\ConceptoPago::firstOrCreate(
            ['codigo' => 'TARJETA_DEBITO'],
            ['nombre' => 'Tarjeta débito', 'activo' => true]
        );
        $fp = \App\Models\FormaPago::create([
            'nombre' => 'Débito test',
            'codigo' => 'DEB_TEST',
            'concepto_pago_id' => $concepto->id,
            'factura_fiscal' => true,
            'activo' => true,
        ]);
        $fp->sucursales()->attach($this->sucursalId);
        VentaPago::create([
            'venta_id' => $venta->id,
            'forma_pago_id' => $fp->id,
            'monto_base' => 1000,
            'monto_final' => 1000,
            'estado' => VentaPago::ESTADO_ACTIVO,
            'estado_facturacion' => VentaPago::ESTADO_FACT_PENDIENTE,
        ]);

        Livewire::test(PagosPendientesFacturacion::class)
            ->assertOk()
            ->assertSeeText('#'.$venta->numero);
    }

    public function test_estado_inicial_filtra_por_pendientes_y_error(): void
    {
        // Default: muestra pagos pendientes de facturar + con error ARCA
        Livewire::test(PagosPendientesFacturacion::class)
            ->assertSet('filtroEstado', 'todos');
    }

    public function test_modales_no_se_muestran_al_render_inicial(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(PagosPendientesFacturacion::class)
            ->assertSet('showReintentarModal', false)
            ->assertSet('showMarcarErrorModal', false)
            ->assertDontSeeText('Reintentar facturación')
            ->assertDontSeeText('Marcar como error ARCA');
    }

    public function test_abrir_modal_reintentar_solo_muestra_ese_modal(): void
    {
        Livewire::withoutLazyLoading();

        Livewire::test(PagosPendientesFacturacion::class)
            ->call('abrirReintentar', 1)
            ->assertSet('showReintentarModal', true)
            ->assertSet('pagoAReintentarId', 1)
            ->assertSeeText('Reintentar facturación')
            ->call('cerrarReintentarModal')
            ->assertSet('showReintentarModal', false)
            ->assertSet('pagoAReintentarId', null);
    }
}

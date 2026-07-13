<?php

namespace Tests\Feature\Livewire\Compras;

use App\Livewire\Compras\Compras;
use App\Livewire\Compras\EditorCompra;
use App\Livewire\Compras\GestionarPagosProveedores;
use App\Livewire\Compras\GestionarProveedores;
use App\Livewire\Compras\ReportesCompras;
use App\Livewire\Compras\RevisionPreciosCompra;
use App\Models\Compra;
use App\Models\CondicionIva;
use App\Models\Cuit;
use App\Models\Proveedor;
use App\Services\CompraService;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\WithCaja;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Smoke tests del módulo Compras (spec compras-costos, Fase 5): detectan
 * errores de mount, sintaxis Blade y variables indefinidas.
 */
class SmokeComprasTest extends TestCase
{
    use WithCaja, WithSucursal, WithTenant, WithVentaHelpers;

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

    public function test_editor_compra_carga_borrador_y_completada_en_correccion(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $proveedor = Proveedor::create(['nombre' => 'Prov Editor Smoke '.uniqid(), 'activo' => true]);

        $borrador = app(CompraService::class)->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 2, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);

        // Borrador: edición directa.
        Livewire::test(EditorCompra::class, ['compraId' => $borrador->id])
            ->assertSet('modoCorreccion', false)
            ->assertOk();

        // Completada: se reabre en modo corrección (D7 #12).
        $completada = app(CompraService::class)->confirmarCompra($borrador, 1);

        Livewire::test(EditorCompra::class, ['compraId' => $completada->id])
            ->assertSet('modoCorreccion', true)
            ->assertSet('correccionDeId', $completada->id)
            ->assertSet('compraId', null)
            ->assertOk();
    }

    // ==================== Ronda UX 2026-07-13 (editor) ====================

    public function test_editor_sugiere_tipo_comprobante_por_proveedor_y_cuit(): void
    {
        $ri = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'Responsable Inscripto']);
        $mono = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_MONOTRIBUTO], ['nombre' => 'Monotributo']);

        $cuitRi = Cuit::create([
            'numero_cuit' => '20'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT).'1',
            'razon_social' => 'CUIT UX Smoke '.uniqid(),
            'condicion_iva_id' => $ri->id,
            'activo' => true,
        ]);
        $provRi = Proveedor::create(['nombre' => 'Prov RI '.uniqid(), 'condicion_iva_id' => $ri->id, 'activo' => true]);
        $provMono = Proveedor::create(['nombre' => 'Prov Mono '.uniqid(), 'condicion_iva_id' => $mono->id, 'activo' => true]);

        Livewire::test(EditorCompra::class)
            ->set('cuitId', $cuitRi->id)
            ->call('seleccionarProveedor', $provRi->id)
            ->assertSet('tipoComprobante', Compra::TIPO_FACTURA_A)
            ->call('seleccionarProveedor', $provMono->id)
            ->assertSet('tipoComprobante', Compra::TIPO_FACTURA_C)
            // Editable a mano después de la sugerencia
            ->set('tipoComprobante', Compra::TIPO_FACTURA_B)
            ->assertSet('tipoComprobante', Compra::TIPO_FACTURA_B)
            ->assertOk();
    }

    public function test_editor_compone_y_descompone_numero_comprobante(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $proveedor = Proveedor::create(['nombre' => 'Prov Numero '.uniqid(), 'activo' => true]);

        $editor = Livewire::test(EditorCompra::class)
            ->call('seleccionarProveedor', $proveedor->id)
            ->call('seleccionarArticuloFila', 0, $articulo->id)
            ->set('renglones.0.precio_unitario', '100')
            ->set('numeroPv', '0003')
            ->set('numeroCbte', '00012345')
            ->call('guardarBorrador')
            ->assertOk();

        $compra = Compra::findOrFail($editor->get('compraId'));
        $this->assertSame('0003-00012345', $compra->numero_comprobante_proveedor);

        // Al recargar el borrador, las 2 partes vuelven separadas.
        Livewire::test(EditorCompra::class, ['compraId' => $compra->id])
            ->assertSet('numeroPv', '0003')
            ->assertSet('numeroCbte', '00012345')
            ->assertOk();
    }

    public function test_editor_busca_selecciona_y_enfoca_cantidad(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['nombre' => 'Yerba UX Smoke '.uniqid()]);

        Livewire::test(EditorCompra::class)
            ->set('renglones.0.busqueda', 'Yerba UX Smoke')
            ->call('buscarArticuloFila', 0)
            ->assertSet('renglones.0.resultados', fn ($resultados) => count($resultados) > 0)
            ->call('seleccionarArticuloFila', 0, $articulo->id)
            ->assertSet('renglones.0.articulo_id', $articulo->id)
            ->assertDispatched('foco-celda', fila: 0, col: 'cantidad')
            ->assertOk();
    }

    public function test_editor_precarga_precio_desde_costo_del_proveedor(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $proveedor = Proveedor::create(['nombre' => 'Prov Costo '.uniqid(), 'activo' => true]);

        // Costo por unidad de stock (post-descuentos): 90. Con factor 10 y
        // descuento habitual 10%, el precio de lista reconstruido es
        // 90 × 10 / 0.9 = 1000 → el unitario efectivo vuelve a caer en 90/u.
        \App\Models\ArticuloProveedor::create([
            'articulo_id' => $articulo->id,
            'proveedor_id' => $proveedor->id,
            'codigo_proveedor' => 'PC-1',
            'factor_conversion' => 10,
            'descuentos_habituales' => [10],
            'costo_ultimo' => 90,
            'activo' => true,
        ]);

        Livewire::test(EditorCompra::class)
            ->call('seleccionarProveedor', $proveedor->id)
            ->call('seleccionarArticuloFila', 0, $articulo->id)
            ->assertSet('renglones.0.precio_unitario', '1000')
            ->assertSet('renglones.0.factor_conversion', '10')
            ->assertSet('renglones.0.descuentos_texto', '10')
            ->assertOk();
    }

    public function test_editor_busqueda_avanzada_selecciona_en_la_fila(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);

        Livewire::test(EditorCompra::class)
            ->call('abrirBusquedaAvanzada', 0)
            ->assertSet('mostrarModalBusquedaArticulos', true)
            ->call('seleccionarArticuloModal', $articulo->id)
            ->assertSet('mostrarModalBusquedaArticulos', false)
            ->assertSet('renglones.0.articulo_id', $articulo->id)
            ->assertOk();
    }

    public function test_editor_alta_rapida_de_proveedor_crea_y_selecciona(): void
    {
        $ri = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'Responsable Inscripto']);
        $nombre = 'Distribuidora UX '.uniqid();

        $editor = Livewire::test(EditorCompra::class)
            ->call('abrirProveedorRapido', $nombre)
            ->assertSet('mostrarModalProveedorRapido', true)
            ->assertSet('provRapidoNombre', $nombre)
            ->set('provRapidoCondicionIvaId', $ri->id)
            ->call('guardarProveedorRapido')
            ->assertSet('mostrarModalProveedorRapido', false)
            ->assertDispatched('proveedor-creado')
            ->assertOk();

        $proveedor = Proveedor::where('nombre', $nombre)->first();
        $this->assertNotNull($proveedor);
        $this->assertSame($proveedor->id, $editor->get('proveedorId'));
    }

    // ==================== Fase 8: revisión de precios + reportes ====================

    public function test_reportes_compras_monta_y_genera(): void
    {
        Livewire::test(ReportesCompras::class)
            ->call('generarReporte')
            ->assertOk();
    }

    public function test_revision_precios_monta_sobre_compra_completada(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $proveedor = Proveedor::create(['nombre' => 'Prov Revision Smoke '.uniqid(), 'activo' => true]);

        $borrador = app(CompraService::class)->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 2, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);
        $completada = app(CompraService::class)->confirmarCompra($borrador, 1);

        Livewire::test(RevisionPreciosCompra::class, ['compraId' => $completada->id])
            ->assertSet('cargada', true)
            ->call('recalcular')
            ->assertOk();
    }
}

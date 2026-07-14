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

    // ==================== Incrementos D23/D24 (factura de servicio + percepciones habituales) ====================

    public function test_editor_servicio_guarda_borrador_sin_renglones(): void
    {
        $cuenta = \App\Models\CuentaCompra::create(['nombre' => 'Servicios Smoke '.uniqid(), 'orden' => 98, 'activo' => true]);
        $proveedor = Proveedor::create([
            'nombre' => 'EDESUR Smoke '.uniqid(),
            'activo' => true,
            'es_servicio' => true,
            'cuenta_compra_id' => $cuenta->id,
        ]);
        $this->crearTiposIva();

        $editor = Livewire::test(EditorCompra::class)
            ->call('seleccionarProveedor', $proveedor->id)
            // D23: el flag del proveedor sugiere la modalidad + su cuenta default.
            ->assertSet('esServicio', true)
            ->assertSet('cuentaCompraId', $cuenta->id)
            ->call('agregarConcepto')
            ->set('conceptos.0.descripcion', 'Energía eléctrica')
            ->set('conceptos.0.monto', '1000')
            ->set('conceptos.0.tipo_iva_id', $this->tiposIva[5]->id)
            ->call('guardarBorrador')
            ->assertOk();

        $compra = Compra::findOrFail($editor->get('compraId'));
        $this->assertTrue($compra->esServicio());
        $this->assertCount(0, $compra->detalles);
        $this->assertCount(1, $compra->conceptos);
        $this->assertEquals(1000.0, (float) $compra->conceptos->first()->monto);
    }

    public function test_editor_precarga_percepciones_habituales_del_proveedor(): void
    {
        $impuesto = \App\Models\Impuesto::firstOrCreate(
            ['codigo' => 'perc_iibb_smoke'],
            ['nombre' => 'Perc IIBB Smoke', 'tipo' => \App\Models\Impuesto::TIPO_IIBB, 'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR-B', 'es_sistema' => true, 'activo' => true],
        );
        $proveedor = Proveedor::create([
            'nombre' => 'Prov Percepciones '.uniqid(),
            'activo' => true,
            'percepciones_habituales' => [['impuesto_id' => $impuesto->id, 'alicuota' => 3.5]],
        ]);

        Livewire::test(EditorCompra::class)
            ->call('seleccionarProveedor', $proveedor->id)
            ->assertSet('percepciones.0.impuesto_id', $impuesto->id)
            ->assertSet('percepciones.0.alicuota', '3.5')
            ->assertSet('percepciones.0.monto', '')
            ->assertOk();
    }

    public function test_editor_percepcion_coeficiente_default_y_monto_sugerido(): void
    {
        // D25: la precarga trae el coeficiente de la config del CUIT y la base
        // gravada sugiere base/monto hasta que el usuario los pise a mano.
        $ri = CondicionIva::firstOrCreate(['codigo' => CondicionIva::RESPONSABLE_INSCRIPTO], ['nombre' => 'Responsable Inscripto']);
        $cuit = Cuit::create([
            'numero_cuit' => '20'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT).'3',
            'razon_social' => 'CUIT D25 Smoke '.uniqid(),
            'condicion_iva_id' => $ri->id,
            'activo' => true,
        ]);
        $impuesto = \App\Models\Impuesto::firstOrCreate(
            ['codigo' => 'perc_iibb_smoke'],
            ['nombre' => 'Perc IIBB Smoke', 'tipo' => \App\Models\Impuesto::TIPO_IIBB, 'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR-B', 'es_sistema' => true, 'activo' => true],
        );
        \App\Models\CuitImpuestoConfig::firstOrCreate(
            ['cuit_id' => $cuit->id, 'impuesto_id' => $impuesto->id],
            ['inscripto' => true, 'coeficiente_computable' => 0.5],
        );
        $proveedor = Proveedor::create([
            'nombre' => 'Prov Coef '.uniqid(),
            'condicion_iva_id' => $ri->id,
            'activo' => true,
            'percepciones_habituales' => [['impuesto_id' => $impuesto->id, 'alicuota' => 3]],
        ]);
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', ['nombre' => 'Perc D25 '.uniqid()]);

        Livewire::test(EditorCompra::class)
            ->set('cuitId', $cuit->id)
            ->call('seleccionarProveedor', $proveedor->id)
            // Precarga D24 + coeficiente D25 desde la config del CUIT.
            ->assertSet('percepciones.0.coeficiente', '0.5')
            ->assertSet('percepciones.0.monto', '')
            // Con un renglón cargado, la base gravada sugiere base y monto.
            ->set('renglones.0.busqueda', 'Perc D25')
            ->call('buscarArticuloFila', 0)
            ->call('seleccionarArticuloFila', 0, $articulo->id)
            ->set('renglones.0.cantidad_comprada', '10')
            ->set('renglones.0.precio_unitario', '100')
            ->assertSet('percepciones.0.base_imponible', '1000')
            ->assertSet('percepciones.0.monto', '30')
            // Pisar el monto a mano saca al renglón del modo auto.
            ->set('percepciones.0.monto', '28')
            ->set('renglones.0.cantidad_comprada', '20')
            ->assertSet('percepciones.0.monto', '28')
            ->assertOk();
    }

    public function test_gestionar_proveedores_guarda_servicio_y_perfil_fiscal_percepciones(): void
    {
        $impuesto = \App\Models\Impuesto::firstOrCreate(
            ['codigo' => 'perc_iibb_smoke'],
            ['nombre' => 'Perc IIBB Smoke', 'tipo' => \App\Models\Impuesto::TIPO_IIBB, 'naturaleza_default' => 'percepcion', 'jurisdiccion' => 'AR-B', 'es_sistema' => true, 'activo' => true],
        );
        $nombre = 'Metrogas Smoke '.uniqid();

        Livewire::test(GestionarProveedores::class)
            ->call('create')
            ->set('nombre', $nombre)
            ->set('es_servicio', true)
            ->call('save')
            ->assertSet('showModal', false)
            ->assertOk();

        $proveedor = Proveedor::where('nombre', $nombre)->first();
        $this->assertTrue((bool) $proveedor->es_servicio);

        // D24: las percepciones habituales se cargan desde el perfil fiscal
        // del proveedor (componente aparte, espejo de ClienteImpuestos).
        Livewire::test(\App\Livewire\Compras\ProveedorImpuestos::class)
            ->call('abrir', $proveedor->id)
            ->assertSet('mostrarModal', true)
            ->call('agregarImpuesto', $impuesto->id)
            ->set('filas.0.alicuota', '2.5')
            ->call('guardar')
            ->assertSet('mostrarModal', false)
            ->assertOk();

        $this->assertEquals(
            [['impuesto_id' => $impuesto->id, 'alicuota' => 2.5]],
            $proveedor->fresh()->percepciones_habituales,
        );

        // Quitar la percepción deja el perfil vacío (JSON null).
        Livewire::test(\App\Livewire\Compras\ProveedorImpuestos::class)
            ->call('abrir', $proveedor->id)
            ->assertSet('filas.0.impuesto_id', $impuesto->id)
            ->call('quitarImpuesto', $impuesto->id)
            ->call('guardar')
            ->assertOk();

        $this->assertNull($proveedor->fresh()->percepciones_habituales);
    }

    public function test_proveedor_impuestos_monta(): void
    {
        Livewire::test(\App\Livewire\Compras\ProveedorImpuestos::class)->assertOk();
    }

    /**
     * Los descuentos del renglón de la ÚLTIMA compra completada al proveedor
     * PISAN a los habituales del catálogo al precargar el artículo (2026-07-13).
     */
    public function test_editor_precarga_descuentos_de_la_ultima_compra_al_proveedor(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0);
        $proveedor = Proveedor::create(['nombre' => 'Prov Ult Desc '.uniqid(), 'activo' => true]);

        \App\Models\ArticuloProveedor::create([
            'articulo_id' => $articulo->id,
            'proveedor_id' => $proveedor->id,
            'descuentos_habituales' => [3],
            'factor_conversion' => 1,
            'activo' => true,
        ]);

        $compra = app(CompraService::class)->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 2, 'factor_conversion' => 1, 'precio_unitario' => 100, 'descuentos' => [10, 5]],
        ]);
        app(CompraService::class)->confirmarCompra($compra, 1);

        Livewire::test(EditorCompra::class)
            ->call('seleccionarProveedor', $proveedor->id)
            ->call('seleccionarArticuloFila', 0, $articulo->id)
            ->assertSet('renglones.0.descuentos_texto', '10+5')
            ->assertOk();
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

    /**
     * RF-10: la revisión lista solo artículos bajo el objetivo, aplica en lote
     * (precio global sin override de sucursal) y registra HistorialPrecio con
     * origen 'revision_compra'; al recalcular, el artículo ya no aparece.
     */
    public function test_revision_precios_aplica_en_lote_y_registra_historial(): void
    {
        // aplicar() está gateado por func.compras.revisar_precios: system admin
        // dedicado (tiene todos los permisos sin depender del seed del fixture).
        $this->actingAs(\App\Models\User::factory()->create(['is_system_admin' => true]));

        $this->crearTiposIva();

        // Precio 100 con costo 100 ⇒ margen 0% < objetivo 50% (sin CUIT: alícuota
        // efectiva 0, sugerido = costo × 1,5 = 150).
        $articulo = $this->crearArticuloConStock($this->sucursalId, 0, 'unitario', [
            'precio_base' => 100,
            'utilidad_porcentaje' => 50,
        ]);
        $proveedor = Proveedor::create(['nombre' => 'Prov Revision Aplica '.uniqid(), 'activo' => true]);

        $borrador = app(CompraService::class)->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 1, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);
        app(CompraService::class)->confirmarCompra($borrador, 1);

        $revision = Livewire::test(RevisionPreciosCompra::class, ['compraId' => $borrador->id])
            ->assertSet('filas', fn ($filas) => count($filas) === 1 && $filas[0]['articulo_id'] === $articulo->id);

        // El sugerido depende de la alícuota efectiva del fixture (con CUIT RI
        // incluye el factor de IVA): el assert es contra la fila calculada.
        $sugerido = (float) $revision->get('filas')[0]['sugerido'];

        $revision->call('aplicar')
            // Retomable: aplicado el precio, el margen alcanza el objetivo y sale de la lista.
            ->assertSet('filas', [])
            ->assertOk();

        $this->assertEquals($sugerido, (float) $articulo->fresh()->precio_base);
        $this->assertTrue(
            \App\Models\HistorialPrecio::where('articulo_id', $articulo->id)
                ->where('origen', 'revision_compra')
                ->exists()
        );
    }

    /**
     * RF-22: el reporte por cuenta agrupa las completadas del período y las NC
     * RESTAN, tanto en el resumen como en el corte por cuenta.
     */
    public function test_reporte_por_cuenta_resta_las_nc(): void
    {
        $this->crearTiposIva();
        $articulo = $this->crearArticuloConStock($this->sucursalId, 10);
        $proveedor = Proveedor::create(['nombre' => 'Prov Reporte '.uniqid(), 'activo' => true]);
        $cuenta = \App\Models\CuentaCompra::create(['nombre' => 'Mercadería UX '.uniqid(), 'orden' => 99, 'activo' => true]);

        $servicio = app(CompraService::class);

        $compra = $servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NO_FISCAL,
            'cuenta_compra_id' => $cuenta->id,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 10, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);
        $servicio->confirmarCompra($compra, 1); // total 1000

        $nc = $servicio->crearBorrador([
            'sucursal_id' => $this->sucursalId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => 1,
            'tipo_comprobante' => Compra::TIPO_NC_NO_FISCAL,
            'compra_origen_id' => $compra->id,
            'cuenta_compra_id' => $cuenta->id,
        ], [
            ['articulo_id' => $articulo->id, 'cantidad_comprada' => 3, 'factor_conversion' => 1, 'precio_unitario' => 100],
        ]);
        $servicio->confirmarCompra($nc, 1); // NC 300

        Livewire::test(ReportesCompras::class)
            ->set('tipoReporte', 'cuenta')
            ->set('fechaDesde', now()->toDateString())
            ->set('fechaHasta', now()->toDateString())
            ->call('generarReporte')
            ->assertSet('resumen', function ($resumen) {
                return $resumen['compras'] >= 1000.0
                    && $resumen['notas_credito'] >= 300.0
                    && abs($resumen['neto'] - ($resumen['compras'] - $resumen['notas_credito'])) < 0.01;
            })
            ->assertSet('datosReporte', function ($datos) use ($cuenta) {
                $grupo = collect($datos)->firstWhere('etiqueta', $cuenta->nombre);

                return $grupo !== null
                    && abs($grupo['compras'] - 1000.0) < 0.01
                    && abs($grupo['notas_credito'] - 300.0) < 0.01
                    && abs($grupo['neto'] - 700.0) < 0.01;
            })
            ->assertOk();
    }
}

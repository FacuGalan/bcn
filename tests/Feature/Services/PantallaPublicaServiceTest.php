<?php

namespace Tests\Feature\Services;

use App\Models\PantallaPublicaToken;
use App\Models\Sucursal;
use App\Models\TipoIva;
use App\Services\PantallaPublicaService;
use App\Services\TenantService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * Multi-PWA Clase B — Fase 1: resolución de tenant por token vía índice global,
 * canje de código corto y regeneración (rotación). Cubre también el endpoint
 * público de vinculación y el 404 genérico ante token/código inválido.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-01, RF-02, RF-02b).
 */
class PantallaPublicaServiceTest extends TestCase
{
    use WithSucursal, WithTenant, WithVentaHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        // Asegurar comercio activo en TenantService (para getComercio()).
        app(TenantService::class)->usarComercioParaProceso($this->comercio->id);
    }

    protected function tearDown(): void
    {
        PantallaPublicaToken::query()->where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function service(): PantallaPublicaService
    {
        return app(PantallaPublicaService::class);
    }

    private function crearToken(): PantallaPublicaToken
    {
        return PantallaPublicaToken::create([
            'token' => PantallaPublicaToken::generarTokenUnico(),
            'codigo_corto' => PantallaPublicaToken::generarCodigoUnico(),
            'comercio_id' => $this->comercio->id,
            'sucursal_id' => $this->sucursalId,
        ]);
    }

    public function test_resuelve_la_sucursal_por_token(): void
    {
        $index = $this->crearToken();

        $res = $this->service()->resolverPorToken($index->token);

        $this->assertNotNull($res);
        $this->assertInstanceOf(Sucursal::class, $res['sucursal']);
        $this->assertSame($this->sucursalId, $res['sucursal']->id);
    }

    public function test_token_inexistente_devuelve_null(): void
    {
        $this->assertNull($this->service()->resolverPorToken('token-que-no-existe'));
    }

    public function test_canjea_codigo_corto_por_token_case_insensitive(): void
    {
        $index = $this->crearToken();

        $this->assertSame($index->token, $this->service()->canjearCodigoCorto($index->codigo_corto));
        // El código se normaliza a mayúsculas.
        $this->assertSame($index->token, $this->service()->canjearCodigoCorto(strtolower($index->codigo_corto)));
        $this->assertNull($this->service()->canjearCodigoCorto('ZZZZZZ'));
    }

    public function test_regenerar_token_rota_y_sincroniza_indice_y_columna(): void
    {
        $index = $this->crearToken();
        $tokenViejo = $index->token;
        $codigoViejo = $index->codigo_corto;

        $sucursal = Sucursal::find($this->sucursalId);
        $nuevo = $this->service()->regenerarToken($sucursal);

        $this->assertNotSame($tokenViejo, $nuevo['token']);
        $this->assertNotSame($codigoViejo, $nuevo['codigo_corto']);

        // El token viejo ya no resuelve (dispositivos viejos desvinculados).
        $this->assertNull($this->service()->resolverPorToken($tokenViejo));
        $this->assertNotNull($this->service()->resolverPorToken($nuevo['token']));

        // La columna tenant quedó sincronizada con el índice global.
        $this->assertSame($nuevo['token'], Sucursal::find($this->sucursalId)->token_publico);
        $this->assertSame(
            $nuevo['token'],
            PantallaPublicaToken::where('sucursal_id', $this->sucursalId)->value('token')
        );
    }

    public function test_endpoint_de_vinculacion_canjea_codigo(): void
    {
        $index = $this->crearToken();

        $this->get(route('clase-b.vincular', ['codigo' => $index->codigo_corto]))
            ->assertOk()
            ->assertExactJson(['token' => $index->token]);
    }

    public function test_endpoint_de_vinculacion_404_si_codigo_invalido(): void
    {
        $this->get(route('clase-b.vincular', ['codigo' => 'NOPE12']))
            ->assertNotFound();
    }

    // ── Consultor de precios (Fase 3) ──

    public function test_buscar_precios_requiere_al_menos_dos_caracteres(): void
    {
        $sucursal = Sucursal::find($this->sucursalId);

        $this->assertSame([], $this->service()->buscarPreciosPublico($sucursal, 'a'));
        $this->assertSame([], $this->service()->buscarPreciosPublico($sucursal, ' '));
    }

    public function test_buscar_precios_devuelve_articulo_activo_con_precio(): void
    {
        TipoIva::firstOrCreate(['codigo' => 5], ['nombre' => 'IVA 21%', 'porcentaje' => 21.00, 'activo' => true]);

        $this->crearArticuloConStock($this->sucursalId, 10, 'ninguno', [
            'nombre' => 'Coca Cola Test '.uniqid(),
            'precio_base' => 1500.00,
        ]);

        $resultados = $this->service()->buscarPreciosPublico(Sucursal::find($this->sucursalId), 'Coca');

        $this->assertNotEmpty($resultados);
        $this->assertStringContainsString('Coca Cola', $resultados[0]['nombre']);
        $this->assertNotNull($resultados[0]['precio']);
        $this->assertArrayHasKey('promos', $resultados[0]);
        // Payload mínimo: NO expone costo/stock/listas internas.
        $this->assertSame(['nombre', 'unidad', 'precio', 'promos'], array_keys($resultados[0]));
    }

    public function test_buscar_precios_incluye_promo_especial_nxm_por_lista(): void
    {
        TipoIva::firstOrCreate(['codigo' => 5], ['nombre' => 'IVA 21%', 'porcentaje' => 21.00, 'activo' => true]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'ninguno', [
            'nombre' => 'Coca NxM Test '.uniqid(),
            'precio_base' => 1000.00,
        ]);

        \App\Models\PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Bebidas 3x2 Test',
            'tipo' => \App\Models\PromocionEspecial::TIPO_NXM,
            'nxm_lleva' => 3, 'nxm_paga' => 2, 'nxm_bonifica' => 1,
            'beneficio_tipo' => \App\Models\PromocionEspecial::BENEFICIO_GRATIS,
            'nxm_articulos_ids' => [$articulo->id],
            'prioridad' => 1, 'modo_aplicacion' => 'automatica',
            'activo' => true, 'usos_actuales' => 0,
        ]);

        $resultados = $this->service()->buscarPreciosPublico(Sucursal::find($this->sucursalId), 'Coca NxM');

        $this->assertNotEmpty($resultados);
        $this->assertContains('Bebidas 3x2 Test', $resultados[0]['promos']);
    }

    public function test_buscar_precios_incluye_combo_por_grupo(): void
    {
        TipoIva::firstOrCreate(['codigo' => 5], ['nombre' => 'IVA 21%', 'porcentaje' => 21.00, 'activo' => true]);

        $articulo = $this->crearArticuloConStock($this->sucursalId, 10, 'ninguno', [
            'nombre' => 'Coca Combo Test '.uniqid(),
            'precio_base' => 1000.00,
        ]);

        $combo = \App\Models\PromocionEspecial::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Coca + Alfajor Test',
            'tipo' => \App\Models\PromocionEspecial::TIPO_COMBO,
            'precio_tipo' => \App\Models\PromocionEspecial::PRECIO_FIJO,
            'precio_valor' => 1500,
            'prioridad' => 1, 'modo_aplicacion' => 'automatica',
            'activo' => true, 'usos_actuales' => 0,
        ]);

        $grupo = \App\Models\PromocionEspecialGrupo::create([
            'promocion_especial_id' => $combo->id,
            'nombre' => 'Bebida', 'cantidad' => 1,
            'es_trigger' => true, 'es_reward' => false, 'orden' => 1,
        ]);
        $grupo->articulos()->attach($articulo->id);

        $resultados = $this->service()->buscarPreciosPublico(Sucursal::find($this->sucursalId), 'Coca Combo');

        $this->assertNotEmpty($resultados);
        $this->assertContains('Coca + Alfajor Test', $resultados[0]['promos']);
    }

    public function test_endpoints_consultor_gateados_por_usa_consultor_precios(): void
    {
        $index = $this->crearToken();

        // Apagado → 404 (los precios no quedan consultables).
        Sucursal::where('id', $this->sucursalId)->update(['usa_consultor_precios' => false]);
        $this->get(route('clase-b.precios.config', ['token' => $index->token]))->assertNotFound();
        $this->get(route('clase-b.precios.buscar', ['token' => $index->token, 'q' => 'coca']))->assertNotFound();

        // Encendido → 200.
        Sucursal::where('id', $this->sucursalId)->update(['usa_consultor_precios' => true]);
        $this->get(route('clase-b.precios.config', ['token' => $index->token]))
            ->assertOk()
            ->assertJsonStructure(['sucursal' => ['nombre'], 'config' => ['titulo', 'color_acento']]);
        $this->get(route('clase-b.precios.buscar', ['token' => $index->token, 'q' => 'x']))
            ->assertOk()
            ->assertJsonStructure(['resultados']);
    }
}

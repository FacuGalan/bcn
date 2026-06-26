<?php

namespace Tests\Feature\Services;

use App\Models\PantallaPublicaToken;
use App\Models\Sucursal;
use App\Services\PantallaPublicaService;
use App\Services\TenantService;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Multi-PWA Clase B — Fase 1: resolución de tenant por token vía índice global,
 * canje de código corto y regeneración (rotación). Cubre también el endpoint
 * público de vinculación y el 404 genérico ante token/código inválido.
 *
 * Ref: .claude/specs/multi-pwa-clase-b.md (RF-01, RF-02, RF-02b).
 */
class PantallaPublicaServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

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
}

<?php

namespace Tests\Integration\Services;

use App\Models\Caja;
use App\Models\IntegracionPago;
use App\Models\IntegracionPagoSucursal;
use App\Models\MercadoPagoCollectorIndex;
use App\Models\Sucursal;
use App\Services\IntegracionesPago\MercadoPagoGateway;
use App\Services\IntegracionesPago\SincronizacionMercadoPagoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;

/**
 * Tests Fase 3.5 — SincronizacionMercadoPagoService.
 *
 * Verifica que el service orquesta correctamente crear/actualizar Store/POS
 * y persiste los IDs/URLs devueltos por MP en la BD tenant.
 */
class SincronizacionMercadoPagoServiceTest extends TestCase
{
    use WithSucursal, WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();

        if (! IntegracionPago::porCodigo('mercadopago')->exists()) {
            IntegracionPago::create([
                'codigo' => 'mercadopago',
                'nombre' => 'Mercado Pago',
                'modos_disponibles' => ['qr_dinamico', 'qr_estatico'],
                'gateway_class' => MercadoPagoGateway::class,
                'activo' => true,
                'orden' => 1,
            ]);
        }

        MercadoPagoCollectorIndex::query()->delete();
    }

    protected function tearDown(): void
    {
        MercadoPagoCollectorIndex::query()->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    private function crearConfig(): IntegracionPagoSucursal
    {
        return IntegracionPagoSucursal::create([
            'integracion_pago_id' => IntegracionPago::porCodigo('mercadopago')->value('id'),
            'sucursal_id' => $this->sucursalId,
            'modo' => 'test',
            'access_token_test' => 'TEST-SVC-TOK',
            'user_id_externo' => '555111',
        ]);
    }

    private function crearSucursalConCoordenadas(): Sucursal
    {
        $sucursal = Sucursal::find($this->sucursalId);
        $sucursal->update([
            'direccion' => 'Calle Test 123',
            'localidad' => 'CABA',
            'provincia' => 'AR-B',
            'latitud' => -34.6,
            'longitud' => -58.4,
        ]);

        return $sucursal->refresh();
    }

    public function test_sincronizar_sucursal_la_primera_vez_crea_store_en_mp(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores' => Http::response([
                'id' => 8888888,
                'external_id' => 'BCN-'.$this->comercio->id.'-'.$this->sucursalId,
            ], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();

        $resultado = SincronizacionMercadoPagoService::sincronizarSucursal($config, $sucursal, $this->comercio->id);

        $this->assertSame('8888888', $resultado->mp_store_id);
        $this->assertSame('BCN-'.$this->comercio->id.'-'.$this->sucursalId, $resultado->mp_store_external_id);
    }

    public function test_sincronizar_sucursal_segunda_vez_llama_a_actualizar(): void
    {
        Http::fake([
            'api.mercadopago.com/users/*/stores/8888888' => Http::response([
                'id' => 8888888,
                'external_id' => 'BCN-existente',
            ], 200),
            'api.mercadopago.com/users/*/stores' => Http::response(['id' => 99], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();
        $sucursal->update([
            'mp_store_id' => '8888888',
            'mp_store_external_id' => 'BCN-existente',
        ]);

        $resultado = SincronizacionMercadoPagoService::sincronizarSucursal($config, $sucursal->refresh(), $this->comercio->id);

        // El ID no cambia (PUT no crea)
        $this->assertSame('8888888', $resultado->mp_store_id);

        Http::assertSent(fn ($req) => $req->method() === 'PUT' && str_contains($req->url(), '/stores/8888888'));
        Http::assertNotSent(fn ($req) => $req->method() === 'POST');
    }

    public function test_sincronizar_caja_persiste_qr_urls(): void
    {
        Http::fake([
            'api.mercadopago.com/pos' => Http::response([
                'id' => 111222,
                'qr' => [
                    'image' => 'https://mp.com/qr/111222/uuid.png',
                    'template_document' => 'https://mp.com/qr/111222/uuid.pdf',
                ],
                'external_id' => 'BCN-'.$this->comercio->id.'-POS-1',
            ], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();
        $sucursal->update([
            'mp_store_id' => '8888888',
            'mp_store_external_id' => 'BCN-'.$this->comercio->id.'-'.$this->sucursalId,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Caja Test',
            'codigo' => 'CT',
            'tipo' => 'efectivo',
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'estado' => 'cerrada',
            'activo' => true,
        ]);

        $resultado = SincronizacionMercadoPagoService::sincronizarCaja(
            $config,
            $caja,
            $sucursal->refresh(),
            null,
            $this->comercio->id
        );

        $this->assertSame('111222', $resultado->mp_pos_id);
        $this->assertStringContainsString('.png', $resultado->mp_pos_qr_url);
        $this->assertStringContainsString('.pdf', $resultado->mp_pos_qr_pdf_url);
    }

    public function test_sincronizar_caja_segunda_vez_llama_a_actualizar(): void
    {
        Http::fake([
            'api.mercadopago.com/pos/111222' => Http::response(['id' => 111222], 200),
            'api.mercadopago.com/pos' => Http::response(['id' => 99], 201),
        ]);

        $config = $this->crearConfig();
        $sucursal = $this->crearSucursalConCoordenadas();
        $sucursal->update([
            'mp_store_id' => '8888888',
            'mp_store_external_id' => 'BCN-'.$this->comercio->id.'-'.$this->sucursalId,
        ]);

        $caja = Caja::create([
            'sucursal_id' => $this->sucursalId,
            'nombre' => 'Caja Test',
            'codigo' => 'CT',
            'tipo' => 'efectivo',
            'saldo_actual' => 0,
            'saldo_inicial' => 0,
            'estado' => 'cerrada',
            'activo' => true,
            'mp_pos_id' => '111222',
            'mp_pos_external_id' => 'BCN'.$this->comercio->id.'POS1',
        ]);

        $resultado = SincronizacionMercadoPagoService::sincronizarCaja(
            $config,
            $caja->refresh(),
            $sucursal->refresh(),
            null,
            $this->comercio->id
        );

        $this->assertSame('111222', $resultado->mp_pos_id);

        Http::assertSent(fn ($req) => $req->method() === 'PUT' && str_contains($req->url(), '/pos/111222'));
        Http::assertNotSent(fn ($req) => $req->method() === 'POST');
    }
}

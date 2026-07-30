<?php

namespace Tests\Feature\Api;

use App\Mail\Consumidores\RecuperarPasswordConsumidor;
use App\Mail\Consumidores\VerificarEmailConsumidor;
use App\Models\Consumidor;
use App\Models\ConsumidorComercio;
use App\Models\PedidoDelivery;
use App\Models\Tienda;
use App\Services\Consumidores\ConsumidorTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\WithPedidoDeliveryHelpers;
use Tests\Traits\WithSucursal;
use Tests\Traits\WithTenant;
use Tests\Traits\WithVentaHelpers;

/**
 * API v1 de consumidores (Fase 0 del spec tienda-online, RF-T1/T2/T3):
 * registro/login/verificación/recuperación + direcciones + historial
 * cross-comercio. La tabla config.consumidores PERSISTE entre corridas:
 * emails únicos por test y limpieza explícita en tearDown.
 */
class ApiV1ConsumidoresTest extends TestCase
{
    use WithPedidoDeliveryHelpers, WithSucursal, WithTenant, WithVentaHelpers;

    /** @var array<int, Consumidor> consumidores creados por el test (limpieza) */
    protected array $consumidores = [];

    protected ?Tienda $tienda = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenant();
        $this->setUpSucursal();
        $this->habilitarDelivery();

        // El throttle y los snapshots del marketplace usan el cache array
        // compartido entre tests del mismo proceso: limpiar para que el
        // throttle agresivo (registro 5/min) no dé 429 entre tests.
        Cache::flush();
        Mail::fake();

        $this->tienda = Tienda::updateOrCreate(
            ['comercio_id' => $this->comercio->id, 'sucursal_id' => $this->sucursalId],
            ['slug' => 'tienda-test', 'habilitada' => true],
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->consumidores as $consumidor) {
            \App\Models\PersonalAccessToken::where('tokenable_type', 'Consumidor')
                ->where('tokenable_id', $consumidor->id)->delete();
            ConsumidorComercio::where('consumidor_id', $consumidor->id)->delete();
            $consumidor->direcciones()->delete();
            $consumidor->delete();
        }
        $this->consumidores = [];

        Tienda::where('comercio_id', $this->comercio->id)->delete();
        $this->tearDownTenant();
        parent::tearDown();
    }

    protected function crearConsumidor(array $overrides = []): Consumidor
    {
        $consumidor = Consumidor::create(array_merge([
            'nombre' => 'Con Sumidor',
            'email' => 'consumidor-'.uniqid().'@test.com',
            'password' => 'secreto123',
            'telefono' => '1144440000',
        ], $overrides));

        return $this->consumidores[] = $consumidor;
    }

    protected function bearer(Consumidor $consumidor): array
    {
        return ['Authorization' => 'Bearer '.$consumidor->createToken('tienda')->plainTextToken];
    }

    // ==================== RF-T1: REGISTRO / LOGIN ====================

    public function test_registro_crea_cuenta_devuelve_token_y_manda_verificacion(): void
    {
        $email = 'registro-'.uniqid().'@test.com';

        $respuesta = $this->postJson('/api/v1/consumidores/registro', [
            'nombre' => 'Nueva Cuenta',
            'email' => $email,
            'password' => 'secreto123',
            'telefono' => '1155550000',
        ])->assertCreated()
            ->assertJsonPath('data.consumidor.email', $email)
            ->assertJsonPath('data.consumidor.email_verificado', false);

        $this->assertNotEmpty($respuesta->json('data.token'));

        $consumidor = Consumidor::where('email', $email)->first();
        $this->assertNotNull($consumidor);
        $this->consumidores[] = $consumidor;

        Mail::assertSent(VerificarEmailConsumidor::class, fn ($mail) => $mail->hasTo($email));

        // El token emitido sirve YA (decisión RF-T1: puede operar sin verificar).
        $this->withHeaders(['Authorization' => 'Bearer '.$respuesta->json('data.token')])
            ->getJson('/api/v1/consumidores/me')
            ->assertOk()
            ->assertJsonPath('data.email', $email);
    }

    public function test_registro_con_email_duplicado_da_422(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/registro', [
            'nombre' => 'Duplicado',
            'email' => $consumidor->email,
            'password' => 'secreto123',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validacion');
    }

    public function test_login_ok_y_credenciales_invalidas(): void
    {
        $consumidor = $this->crearConsumidor();

        $respuesta = $this->postJson('/api/v1/consumidores/login', [
            'email' => $consumidor->email,
            'password' => 'secreto123',
        ])->assertOk();
        $this->assertNotEmpty($respuesta->json('data.token'));

        $this->postJson('/api/v1/consumidores/login', [
            'email' => $consumidor->email,
            'password' => 'incorrecto',
        ])->assertStatus(422)->assertJsonPath('error.code', 'validacion');
    }

    public function test_me_requiere_bearer_de_consumidor(): void
    {
        $this->getJson('/api/v1/consumidores/me')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'no_autenticado');

        // Un token de INTEGRACIÓN (comercio) no es una cuenta de consumidor.
        $tokenComercio = $this->comercio->createToken('integracion', ['pedidos:read'])->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$tokenComercio])
            ->getJson('/api/v1/consumidores/me')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'sin_permiso');

        \App\Models\PersonalAccessToken::where('tokenable_type', 'Comercio')
            ->where('tokenable_id', $this->comercio->id)->delete();
    }

    public function test_logout_revoca_el_token_actual(): void
    {
        $consumidor = $this->crearConsumidor();
        $headers = $this->bearer($consumidor);

        $this->withHeaders($headers)->postJson('/api/v1/consumidores/logout')->assertOk();

        // El guard memoiza el usuario dentro del mismo proceso de test:
        // olvidarlo para que el segundo request re-resuelva el Bearer.
        $this->app['auth']->forgetGuards();

        $this->withHeaders($headers)->getJson('/api/v1/consumidores/me')->assertStatus(401);
    }

    // ==================== RF-T1: VERIFICACIÓN DE EMAIL ====================

    public function test_verificar_con_token_valido_marca_el_email(): void
    {
        $consumidor = $this->crearConsumidor();
        $token = app(ConsumidorTokenService::class)->generarTokenVerificacion($consumidor);

        $this->postJson('/api/v1/consumidores/verificar', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.email_verificado', true);

        $this->assertNotNull($consumidor->fresh()->email_verified_at);

        // Idempotente: verificar de nuevo no rompe.
        $this->postJson('/api/v1/consumidores/verificar', ['token' => $token])->assertOk();
    }

    public function test_verificar_con_token_invalido_da_422(): void
    {
        $this->postJson('/api/v1/consumidores/verificar', ['token' => 'basura.invalida'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'operacion_invalida');
    }

    public function test_reenviar_verificacion_manda_email_solo_si_falta_verificar(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->withHeaders($this->bearer($consumidor))
            ->postJson('/api/v1/consumidores/reenviar-verificacion')
            ->assertOk();
        Mail::assertSent(VerificarEmailConsumidor::class, 1);

        $consumidor->forceFill(['email_verified_at' => now()])->save();
        $this->app['auth']->forgetGuards(); // re-resolver el usuario fresco
        $this->withHeaders($this->bearer($consumidor))
            ->postJson('/api/v1/consumidores/reenviar-verificacion')
            ->assertOk();
        Mail::assertSent(VerificarEmailConsumidor::class, 1); // sin reenvío extra
    }

    // ==================== RF-T1: RECUPERACIÓN DE PASSWORD ====================

    public function test_recuperar_manda_email_y_no_revela_si_existe(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/recuperar', ['email' => $consumidor->email])
            ->assertOk();
        Mail::assertSent(RecuperarPasswordConsumidor::class, fn ($mail) => $mail->hasTo($consumidor->email));

        // Email inexistente: misma respuesta, sin email (no enumeración).
        $this->postJson('/api/v1/consumidores/recuperar', ['email' => 'no-existe-'.uniqid().'@test.com'])
            ->assertOk();
        Mail::assertSent(RecuperarPasswordConsumidor::class, 1);
    }

    public function test_restablecer_cambia_password_revoca_tokens_y_es_single_use(): void
    {
        $consumidor = $this->crearConsumidor();
        $headersViejos = $this->bearer($consumidor);
        $token = app(ConsumidorTokenService::class)->generarTokenReset($consumidor);

        $this->postJson('/api/v1/consumidores/restablecer', [
            'token' => $token,
            'password' => 'nuevoPassword9',
        ])->assertOk();

        $this->assertTrue(Hash::check('nuevoPassword9', $consumidor->fresh()->getAuthPassword()));

        // Las sesiones viejas quedaron cerradas.
        $this->withHeaders($headersViejos)->getJson('/api/v1/consumidores/me')->assertStatus(401);

        // El mismo token ya no sirve (el hash de password cambió).
        $this->postJson('/api/v1/consumidores/restablecer', [
            'token' => $token,
            'password' => 'otroPassword10',
        ])->assertStatus(422)->assertJsonPath('error.code', 'operacion_invalida');
    }

    // ==================== RF-T2: DIRECCIONES ====================

    public function test_direcciones_crud_completo_con_manejo_de_default(): void
    {
        $consumidor = $this->crearConsumidor();
        $headers = $this->bearer($consumidor);

        // La primera dirección queda default aunque no lo pida.
        $primera = $this->withHeaders($headers)->postJson('/api/v1/consumidores/direcciones', [
            'alias' => 'Casa',
            'direccion' => 'Av. Siempreviva 742',
            'referencia' => 'Timbre 3B',
            'latitud' => -34.6037,
            'longitud' => -58.3816,
        ])->assertCreated()->assertJsonPath('data.es_default', true);

        // La segunda con es_default=true desplaza a la primera.
        $segunda = $this->withHeaders($headers)->postJson('/api/v1/consumidores/direcciones', [
            'alias' => 'Trabajo',
            'direccion' => 'Corrientes 1234',
            'es_default' => true,
        ])->assertCreated()->assertJsonPath('data.es_default', true);

        $lista = $this->withHeaders($headers)->getJson('/api/v1/consumidores/direcciones')->assertOk();
        $this->assertCount(2, $lista->json('data'));
        $this->assertSame('Trabajo', $lista->json('data.0.alias'), 'Default primero');
        $this->assertFalse($lista->json('data.1.es_default'));

        // PATCH edita campos sueltos.
        $this->withHeaders($headers)
            ->patchJson('/api/v1/consumidores/direcciones/'.$primera->json('data.id'), ['alias' => 'Casa de mamá'])
            ->assertOk()
            ->assertJsonPath('data.alias', 'Casa de mamá');

        // Borrar la default promueve a la restante.
        $this->withHeaders($headers)
            ->deleteJson('/api/v1/consumidores/direcciones/'.$segunda->json('data.id'))
            ->assertOk();

        $lista = $this->withHeaders($headers)->getJson('/api/v1/consumidores/direcciones')->assertOk();
        $this->assertCount(1, $lista->json('data'));
        $this->assertTrue($lista->json('data.0.es_default'));
    }

    public function test_direcciones_de_otro_consumidor_son_inaccesibles(): void
    {
        $duenio = $this->crearConsumidor();
        $intruso = $this->crearConsumidor();

        $direccion = $duenio->direcciones()->create([
            'direccion' => 'Privada 123',
            'es_default' => true,
        ]);

        $this->withHeaders($this->bearer($intruso))
            ->patchJson('/api/v1/consumidores/direcciones/'.$direccion->id, ['alias' => 'Hackeada'])
            ->assertNotFound();

        $this->withHeaders($this->bearer($intruso))
            ->deleteJson('/api/v1/consumidores/direcciones/'.$direccion->id)
            ->assertNotFound();
    }

    // ==================== RF-T3: HISTORIAL ====================

    public function test_historial_requiere_email_verificado(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/pedidos')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'sin_permiso');
    }

    public function test_historial_lista_pedidos_del_consumidor_con_tienda_y_token(): void
    {
        $consumidor = $this->crearConsumidor();
        $consumidor->forceFill(['email_verified_at' => now()])->save();

        $pedido = PedidoDelivery::create($this->datosBaseDelivery(overrides: [
            'consumidor_id' => $consumidor->id,
            'origen' => PedidoDelivery::ORIGEN_TIENDA,
            'estado_pedido' => PedidoDelivery::ESTADO_CONFIRMADO,
        ]));

        // Pedido de OTRO consumidor: no debe aparecer.
        $otro = $this->crearConsumidor();
        PedidoDelivery::create($this->datosBaseDelivery(overrides: [
            'consumidor_id' => $otro->id,
            'origen' => PedidoDelivery::ORIGEN_TIENDA,
            'estado_pedido' => PedidoDelivery::ESTADO_CONFIRMADO,
        ]));

        $respuesta = $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/pedidos')
            ->assertOk();

        $this->assertSame(1, $respuesta->json('meta.total'));
        $this->assertCount(1, $respuesta->json('data'));
        $this->assertSame('tienda-test', $respuesta->json('data.0.tienda.slug'));
        $this->assertSame($pedido->token_seguimiento, $respuesta->json('data.0.token_seguimiento'));
        $this->assertSame('confirmado', $respuesta->json('data.0.estado'));
        $this->assertSame(1000.0, (float) $respuesta->json('data.0.total_final'));
    }

    public function test_historial_traduce_facturado_a_entregado(): void
    {
        $consumidor = $this->crearConsumidor();
        $consumidor->forceFill(['email_verified_at' => now()])->save();

        PedidoDelivery::create($this->datosBaseDelivery(overrides: [
            'consumidor_id' => $consumidor->id,
            'origen' => PedidoDelivery::ORIGEN_TIENDA,
            'estado_pedido' => PedidoDelivery::ESTADO_FACTURADO,
        ]));

        $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/pedidos')
            ->assertOk()
            ->assertJsonPath('data.0.estado', PedidoDelivery::ESTADO_ENTREGADO);
    }

    // ==================== RF-T39: EDITAR PERFIL ====================

    public function test_actualizar_perfil_edita_datos_pero_no_email(): void
    {
        $consumidor = $this->crearConsumidor();
        $emailOriginal = $consumidor->email;

        $this->withHeaders($this->bearer($consumidor))
            ->patchJson('/api/v1/consumidores/me', [
                'nombre' => 'Nombre Nuevo',
                'telefono' => '1199998888',
                'fecha_nacimiento' => '1990-05-15',
                'email' => 'hackeado@test.com', // se IGNORA: no es fillable del PATCH
            ])->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre Nuevo')
            ->assertJsonPath('data.telefono', '1199998888')
            ->assertJsonPath('data.fecha_nacimiento', '1990-05-15')
            ->assertJsonPath('data.email', $emailOriginal);

        $this->assertSame($emailOriginal, $consumidor->fresh()->email);
    }

    public function test_actualizar_perfil_valida_cumpleanios_futuro(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->withHeaders($this->bearer($consumidor))
            ->patchJson('/api/v1/consumidores/me', [
                'fecha_nacimiento' => now()->addYear()->format('Y-m-d'),
            ])->assertStatus(422);
    }

    // ==================== RF-T40: PLAZO DE VERIFICACIÓN ====================

    public function test_verificacion_vence_el_viaja_en_el_perfil_y_se_apaga_al_verificar(): void
    {
        $consumidor = $this->crearConsumidor();

        $vence = $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/me')
            ->assertOk()
            ->json('data.verificacion_vence_el');

        $this->assertNotNull($vence);
        $this->assertEqualsWithDelta(
            now()->addDays(Consumidor::DIAS_GRACIA_VERIFICACION)->timestamp,
            \Illuminate\Support\Carbon::parse($vence)->timestamp,
            60,
        );

        // Verificado ⇒ se apaga. A nivel modelo: el guard de Sanctum cachea
        // el usuario resuelto entre requests del MISMO test (un segundo /me
        // vería el modelo en memoria, sin el email_verified_at nuevo).
        $consumidor->forceFill(['email_verified_at' => now()])->save();
        $this->assertNull($consumidor->fresh()->verificacionVenceEl());
        $this->assertFalse($consumidor->fresh()->verificacionVencida());
    }

    // ==================== RF-T41: FAVORITOS ====================

    public function test_favoritos_marcar_listar_y_desmarcar_idempotente(): void
    {
        $consumidor = $this->crearConsumidor();

        // Marcar dos veces no duplica (PUT idempotente).
        $this->withHeaders($this->bearer($consumidor))
            ->putJson('/api/v1/consumidores/favoritos/tienda-test')
            ->assertOk()->assertJsonPath('data.favorito', true);
        $this->withHeaders($this->bearer($consumidor))
            ->putJson('/api/v1/consumidores/favoritos/tienda-test')
            ->assertOk();
        $this->assertSame(1, \App\Models\ConsumidorFavorito::where('consumidor_id', $consumidor->id)->count());

        $lista = $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/favoritos')
            ->assertOk();
        $this->assertCount(1, $lista->json('data'));
        $this->assertSame('tienda-test', $lista->json('data.0.slug'));

        // Desmarcar (y desmarcar de nuevo: idempotente, sin error).
        $this->withHeaders($this->bearer($consumidor))
            ->deleteJson('/api/v1/consumidores/favoritos/tienda-test')
            ->assertOk()->assertJsonPath('data.favorito', false);
        $this->withHeaders($this->bearer($consumidor))
            ->deleteJson('/api/v1/consumidores/favoritos/tienda-test')
            ->assertOk();
        $this->assertSame(0, \App\Models\ConsumidorFavorito::where('consumidor_id', $consumidor->id)->count());

        $this->withHeaders($this->bearer($consumidor))
            ->putJson('/api/v1/consumidores/favoritos/no-existe')
            ->assertStatus(404);
    }

    // ==================== RF-T42: PUNTOS CROSS-COMERCIO ====================

    public function test_puntos_cross_comercio_sin_mappings_o_sin_programa_es_vacio(): void
    {
        // Sin mapping: [].
        $consumidor = $this->crearConsumidor();
        $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/puntos')
            ->assertOk()->assertJsonCount(0, 'data');

        // Con mapping pero SIN programa de puntos: tampoco viaja (una tienda
        // sin programa no es "0 puntos", es nada).
        $cliente = \App\Models\Cliente::create(['nombre' => 'Cliente Puntos CC', 'activo' => true]);
        ConsumidorComercio::create([
            'consumidor_id' => $consumidor->id,
            'comercio_id' => $this->comercio->id,
            'cliente_id' => $cliente->id,
        ]);
        $this->withHeaders($this->bearer($consumidor))
            ->getJson('/api/v1/consumidores/puntos')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    // ==================== RF-T43: TRAZABILIDAD INVERSA ====================

    public function test_consumidor_de_cliente_resuelve_en_ambos_sentidos(): void
    {
        $consumidor = $this->crearConsumidor();
        $cliente = \App\Models\Cliente::create(['nombre' => 'Cliente Trazable', 'activo' => true]);
        $local = \App\Models\Cliente::create(['nombre' => 'Cliente Local', 'activo' => true]);
        // config_test persiste entre corridas: mappings viejos de otros runs
        // pueden apuntar a los cliente_id RECICLADOS del tenant de test.
        ConsumidorComercio::where('comercio_id', $this->comercio->id)
            ->whereIn('cliente_id', [$cliente->id, $local->id])
            ->delete();
        ConsumidorComercio::create([
            'consumidor_id' => $consumidor->id,
            'comercio_id' => $this->comercio->id,
            'cliente_id' => $cliente->id,
        ]);

        // Ida (consumidor → cliente del comercio).
        $this->assertSame($cliente->id, $consumidor->clienteIdEn($this->comercio->id));

        // Vuelta (cliente tenant → cuenta BCN).
        $resuelto = ConsumidorComercio::consumidorDeCliente($this->comercio->id, $cliente->id);
        $this->assertNotNull($resuelto);
        $this->assertSame($consumidor->id, $resuelto->id);

        // Cliente sin cuenta: null (cliente local de mostrador).
        $this->assertNull(ConsumidorComercio::consumidorDeCliente($this->comercio->id, $local->id));
    }
}

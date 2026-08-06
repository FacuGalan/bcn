<?php

namespace Tests\Feature\Api;

use App\Models\Consumidor;
use App\Models\ConsumidorDispositivo;
use App\Services\Consumidores\ConsumidorTokenService;
use App\Services\Consumidores\DispositivoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * RF-T66/T73/T74 (spec tienda-sesion-persistente): dispositivos recordados
 * (remember-token rotativo selector/validator) + lockout por email.
 * Solo BD config (sin tenant). La tabla PERSISTE entre corridas: emails
 * únicos por test y limpieza explícita en tearDown.
 */
class ApiV1ConsumidorDispositivosTest extends TestCase
{
    /** @var array<int, Consumidor> */
    protected array $consumidores = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Throttle por IP + lockout por email usan el cache compartido del
        // proceso: limpiar para no arrastrar contadores entre tests.
        Cache::flush();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        foreach ($this->consumidores as $consumidor) {
            \App\Models\PersonalAccessToken::where('tokenable_type', 'Consumidor')
                ->where('tokenable_id', $consumidor->id)->delete();
            $consumidor->dispositivos()->delete();
            $consumidor->delete();
        }
        $this->consumidores = [];

        parent::tearDown();
    }

    protected function crearConsumidor(array $overrides = []): Consumidor
    {
        $consumidor = Consumidor::create(array_merge([
            'nombre' => 'Con Sumidor',
            'email' => 'dispositivo-'.uniqid().'@test.com',
            'password' => 'secreto123',
        ], $overrides));

        return $this->consumidores[] = $consumidor;
    }

    // ==================== RF-T66: EMISIÓN ====================

    public function test_login_con_recordarme_emite_dispositivo_con_validator_hasheado(): void
    {
        $consumidor = $this->crearConsumidor();

        $respuesta = $this->postJson('/api/v1/consumidores/login', [
            'email' => $consumidor->email,
            'password' => 'secreto123',
            'recordarme' => true,
        ])->assertOk();

        $par = $respuesta->json('data.dispositivo');
        $this->assertNotEmpty($par['selector']);
        $this->assertNotEmpty($par['validator']);

        $dispositivo = ConsumidorDispositivo::where('selector', $par['selector'])->first();
        $this->assertNotNull($dispositivo);
        $this->assertSame($consumidor->id, (int) $dispositivo->consumidor_id);
        // El validator se persiste SOLO hasheado (sha256), nunca plano.
        $this->assertSame(hash('sha256', $par['validator']), $dispositivo->validator_hash);
        $this->assertTrue($dispositivo->expira_at->isFuture());
    }

    public function test_login_sin_recordarme_no_emite_dispositivo(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/login', [
            'email' => $consumidor->email,
            'password' => 'secreto123',
        ])->assertOk()
            ->assertJsonPath('data.dispositivo', null);

        $this->assertSame(0, $consumidor->dispositivos()->count());
    }

    public function test_registro_con_recordarme_emite_dispositivo(): void
    {
        $email = 'registro-disp-'.uniqid().'@test.com';

        $respuesta = $this->postJson('/api/v1/consumidores/registro', [
            'nombre' => 'Nueva Cuenta',
            'email' => $email,
            'password' => 'secreto123',
            'recordarme' => true,
        ])->assertCreated();

        $consumidor = Consumidor::where('email', $email)->first();
        $this->consumidores[] = $consumidor;

        $this->assertNotEmpty($respuesta->json('data.dispositivo.selector'));
        $this->assertSame(1, $consumidor->dispositivos()->count());
    }

    public function test_emitir_el_11vo_dispositivo_poda_el_menos_usado(): void
    {
        $consumidor = $this->crearConsumidor();
        $service = app(DispositivoService::class);

        $primero = $service->emitir($consumidor, 'UA-test', '127.0.0.1');
        for ($i = 0; $i < DispositivoService::MAX_DISPOSITIVOS - 1; $i++) {
            $service->emitir($consumidor, 'UA-test', '127.0.0.1');
        }

        $this->assertSame(DispositivoService::MAX_DISPOSITIVOS, $consumidor->dispositivos()->count());

        $service->emitir($consumidor, 'UA-test', '127.0.0.1');

        $this->assertSame(DispositivoService::MAX_DISPOSITIVOS, $consumidor->dispositivos()->count());
        // El podado es el más viejo sin uso: el primero emitido.
        $this->assertNull(ConsumidorDispositivo::where('selector', $primero['selector'])->first());
    }

    public function test_store_emite_un_par_extra_para_pairing_y_el_canje_corrige_el_nombre(): void
    {
        $consumidor = $this->crearConsumidor();

        // El navegador real (logueado) pide un segundo dispositivo (RF-T68).
        $respuesta = $this->withHeaders([
            'Authorization' => 'Bearer '.$consumidor->createToken('tienda')->plainTextToken,
            'User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Chrome/126',
        ])->postJson('/api/v1/consumidores/dispositivos')
            ->assertCreated();

        $par = $respuesta->json('data.dispositivo');
        $this->assertNotEmpty($par['selector']);
        $this->assertNotEmpty($par['validator']);

        // El webview lo canjea con SU user-agent: el nombre se corrige.
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone) Instagram 300.0'])
            ->postJson('/api/v1/consumidores/auth/recordar', $par)
            ->assertOk();

        $this->assertSame(
            'Instagram · iPhone',
            ConsumidorDispositivo::where('selector', $par['selector'])->first()->nombre,
        );
    }

    // ==================== RF-T66: CANJE Y ROTACIÓN ====================

    public function test_recordar_canjea_rota_el_validator_e_invalida_el_viejo(): void
    {
        $consumidor = $this->crearConsumidor();
        $par = app(DispositivoService::class)->emitir($consumidor, 'UA-test', '127.0.0.1');

        $respuesta = $this->postJson('/api/v1/consumidores/auth/recordar', $par)
            ->assertOk()
            ->assertJsonPath('data.consumidor.email', $consumidor->email);

        // Bearer nuevo funcional.
        $this->withHeaders(['Authorization' => 'Bearer '.$respuesta->json('data.token')])
            ->getJson('/api/v1/consumidores/me')
            ->assertOk();

        // Mismo selector, validator ROTADO.
        $nuevo = $respuesta->json('data.dispositivo');
        $this->assertSame($par['selector'], $nuevo['selector']);
        $this->assertNotSame($par['validator'], $nuevo['validator']);

        // El par nuevo canjea; el flujo normal sigue vivo.
        $this->postJson('/api/v1/consumidores/auth/recordar', $nuevo)->assertOk();
    }

    public function test_recordar_con_validator_viejo_revoca_la_familia_completa(): void
    {
        $consumidor = $this->crearConsumidor();
        $service = app(DispositivoService::class);

        $robado = $service->emitir($consumidor, 'UA-test', '127.0.0.1');
        $otro = $service->emitir($consumidor, 'UA-test', '127.0.0.1');

        // El "ladrón" canjea la copia robada: rota el validator.
        $this->postJson('/api/v1/consumidores/auth/recordar', $robado)->assertOk();

        // El dueño legítimo llega con el validator viejo ⇒ reuso detectado:
        // 401 y TODOS los dispositivos del consumidor revocados.
        $this->postJson('/api/v1/consumidores/auth/recordar', $robado)
            ->assertUnauthorized()
            ->assertJsonPath('codigo', 'dispositivo_invalido');

        $this->assertSame(0, $consumidor->dispositivos()->count());

        // El otro dispositivo (inocente) también quedó muerto: familia comprometida.
        $this->postJson('/api/v1/consumidores/auth/recordar', $otro)->assertUnauthorized();
    }

    public function test_recordar_con_selector_inexistente_da_401(): void
    {
        $this->postJson('/api/v1/consumidores/auth/recordar', [
            'selector' => str_repeat('x', 24),
            'validator' => str_repeat('y', 48),
        ])->assertUnauthorized()
            ->assertJsonPath('codigo', 'dispositivo_invalido');
    }

    public function test_recordar_dispositivo_vencido_da_401_y_lo_borra(): void
    {
        $consumidor = $this->crearConsumidor();
        $par = app(DispositivoService::class)->emitir($consumidor, 'UA-test', '127.0.0.1');

        ConsumidorDispositivo::where('selector', $par['selector'])
            ->update(['expira_at' => now()->subDay()]);

        $this->postJson('/api/v1/consumidores/auth/recordar', $par)->assertUnauthorized();

        $this->assertNull(ConsumidorDispositivo::where('selector', $par['selector'])->first());
    }

    public function test_restablecer_password_revoca_los_dispositivos(): void
    {
        $consumidor = $this->crearConsumidor();
        app(DispositivoService::class)->emitir($consumidor, 'UA-test', '127.0.0.1');

        $token = app(ConsumidorTokenService::class)->generarTokenReset($consumidor);

        $this->postJson('/api/v1/consumidores/restablecer', [
            'token' => $token,
            'password' => 'nuevoSecreto123',
        ])->assertOk();

        $this->assertSame(0, $consumidor->dispositivos()->count());
    }

    // ==================== RF-T73: LOCKOUT POR EMAIL ====================

    public function test_lockout_tras_5_fallos_bloquea_incluso_con_password_correcto(): void
    {
        $consumidor = $this->crearConsumidor();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/consumidores/login', [
                'email' => $consumidor->email,
                'password' => 'password-malo',
            ])->assertUnprocessable();
        }

        // Bloqueado ⇒ el password CORRECTO recibe el MISMO error genérico de
        // credenciales (shape custom de la API: error.details), sin revelar
        // cuenta ni lockout.
        $this->postJson('/api/v1/consumidores/login', [
            'email' => $consumidor->email,
            'password' => 'secreto123',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validacion')
            ->assertJsonPath('error.details.email.0', __('Email o password incorrectos'));
    }

    public function test_login_exitoso_limpia_el_contador_de_lockout(): void
    {
        $consumidor = $this->crearConsumidor();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/consumidores/login', [
                'email' => $consumidor->email,
                'password' => 'password-malo',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/consumidores/login', [
            'email' => $consumidor->email,
            'password' => 'secreto123',
        ])->assertOk();

        $clave = 'login-email:'.hash('sha256', mb_strtolower($consumidor->email));
        $this->assertSame(0, RateLimiter::attempts($clave));
    }

    // ==================== RF-T74: MIS DISPOSITIVOS ====================

    public function test_index_lista_marca_el_actual_y_no_expone_credenciales(): void
    {
        $consumidor = $this->crearConsumidor();
        $service = app(DispositivoService::class);

        $actual = $service->emitir($consumidor, 'Mozilla/5.0 (Linux; Android 14) Chrome/126', '10.0.0.1');
        $service->emitir($consumidor, 'Mozilla/5.0 (iPhone) Instagram 300.0', '10.0.0.2');

        $respuesta = $this->withHeaders([
            'Authorization' => 'Bearer '.$consumidor->createToken('tienda')->plainTextToken,
            'X-Dispositivo' => $actual['selector'],
        ])->getJson('/api/v1/consumidores/dispositivos')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $filas = collect($respuesta->json('data'));
        $this->assertSame(1, $filas->where('actual', true)->count());
        // El selector/validator NUNCA viajan en el listado.
        $this->assertArrayNotHasKey('selector', $filas->first());
        $this->assertArrayNotHasKey('validator_hash', $filas->first());
        // Nombre amigable derivado del UA.
        $this->assertContains('Chrome · Android', $filas->pluck('nombre'));
        $this->assertContains('Instagram · iPhone', $filas->pluck('nombre'));
    }

    public function test_destroy_revoca_uno_y_404_si_es_ajeno(): void
    {
        $consumidor = $this->crearConsumidor();
        $ajeno = $this->crearConsumidor();
        $service = app(DispositivoService::class);

        $par = $service->emitir($consumidor, 'UA-test', '127.0.0.1');
        $parAjeno = $service->emitir($ajeno, 'UA-test', '127.0.0.1');

        $id = ConsumidorDispositivo::where('selector', $par['selector'])->first()->id;
        $idAjeno = ConsumidorDispositivo::where('selector', $parAjeno['selector'])->first()->id;

        $headers = ['Authorization' => 'Bearer '.$consumidor->createToken('tienda')->plainTextToken];

        $this->withHeaders($headers)->deleteJson("/api/v1/consumidores/dispositivos/{$idAjeno}")
            ->assertNotFound();

        $this->withHeaders($headers)->deleteJson("/api/v1/consumidores/dispositivos/{$id}")
            ->assertOk();

        $this->assertSame(0, $consumidor->dispositivos()->count());
        $this->assertSame(1, $ajeno->dispositivos()->count());
    }

    public function test_destroy_all_revoca_todos_menos_el_actual(): void
    {
        $consumidor = $this->crearConsumidor();
        $service = app(DispositivoService::class);

        $actual = $service->emitir($consumidor, 'UA-test', '127.0.0.1');
        $service->emitir($consumidor, 'UA-test', '127.0.0.2');
        $service->emitir($consumidor, 'UA-test', '127.0.0.3');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$consumidor->createToken('tienda')->plainTextToken,
            'X-Dispositivo' => $actual['selector'],
        ])->deleteJson('/api/v1/consumidores/dispositivos')
            ->assertOk()
            ->assertJsonPath('data.revocados', 2);

        $this->assertSame(1, $consumidor->dispositivos()->count());
        $this->assertSame($actual['selector'], $consumidor->dispositivos()->first()->selector);
    }
}

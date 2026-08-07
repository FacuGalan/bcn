<?php

namespace Tests\Feature\Api;

use App\Mail\Consumidores\MagicLinkConsumidor;
use App\Models\Consumidor;
use App\Services\Consumidores\ConsumidorTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * RF-T69/T70/T72 (spec tienda-sesion-persistente): magic link de login
 * (single-use, verifica email, respuesta neutra) + Turnstile en los
 * endpoints blanco de bots. Solo BD config (sin tenant).
 */
class ApiV1ConsumidorMagicLinkTest extends TestCase
{
    /** @var array<int, Consumidor> */
    protected array $consumidores = [];

    protected function setUp(): void
    {
        parent::setUp();
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
            'email' => 'magic-'.uniqid().'@test.com',
            'password' => 'secreto123',
        ], $overrides));

        return $this->consumidores[] = $consumidor;
    }

    // ==================== RF-T69: MAGIC LINK ====================

    public function test_magic_link_responde_neutro_y_manda_mail_solo_si_la_cuenta_existe(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/auth/magic-link', ['email' => $consumidor->email])
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Mail::assertSent(MagicLinkConsumidor::class, fn ($mail) => $mail->hasTo($consumidor->email));

        // Email SIN cuenta: misma respuesta, ningún mail (anti-enumeración).
        $this->postJson('/api/v1/consumidores/auth/magic-link', ['email' => 'nadie-'.uniqid().'@test.com'])
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        Mail::assertSentCount(1);
    }

    public function test_magic_link_respeta_el_tope_de_un_mail_cada_diez_minutos(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/auth/magic-link', ['email' => $consumidor->email])->assertOk();
        $this->postJson('/api/v1/consumidores/auth/magic-link', ['email' => $consumidor->email])->assertOk();

        // El segundo pedido dentro de la ventana NO manda otro mail (pero la
        // respuesta sigue siendo la neutra: nadie detecta el tope).
        Mail::assertSentCount(1);
    }

    public function test_el_link_lleva_volver_y_pairing_como_query_opacos(): void
    {
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/auth/magic-link', [
            'email' => $consumidor->email,
            'volver' => '/tienda-test/checkout',
            'pairing' => 'abc123pairing',
        ])->assertOk();

        Mail::assertSent(MagicLinkConsumidor::class, function (MagicLinkConsumidor $mail) {
            $url = $mail->content()->with['url'];

            return str_contains($url, config('tienda.url').'/entrar?token=')
                && str_contains($url, 'volver='.urlencode('/tienda-test/checkout'))
                && str_contains($url, 'pairing=abc123pairing');
        });
    }

    public function test_magic_login_canjea_verifica_el_email_y_emite_dispositivo(): void
    {
        $consumidor = $this->crearConsumidor();
        $this->assertNull($consumidor->email_verified_at);

        $token = app(ConsumidorTokenService::class)->generarTokenMagic($consumidor);

        $respuesta = $this->postJson('/api/v1/consumidores/auth/magic-login', [
            'token' => $token,
            'recordarme' => true,
        ])->assertOk()
            // Probar control de la casilla VERIFICA el email (RF-T40).
            ->assertJsonPath('data.consumidor.email_verificado', true);

        $this->assertNotNull($consumidor->fresh()->email_verified_at);
        $this->assertNotEmpty($respuesta->json('data.dispositivo.selector'));

        // El Bearer emitido funciona.
        $this->withHeaders(['Authorization' => 'Bearer '.$respuesta->json('data.token')])
            ->getJson('/api/v1/consumidores/me')
            ->assertOk();
    }

    public function test_magic_login_es_single_use(): void
    {
        $consumidor = $this->crearConsumidor();
        $token = app(ConsumidorTokenService::class)->generarTokenMagic($consumidor);

        $this->postJson('/api/v1/consumidores/auth/magic-login', ['token' => $token])->assertOk();

        $this->postJson('/api/v1/consumidores/auth/magic-login', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'operacion_invalida');
    }

    public function test_magic_login_con_token_vencido_o_basura_da_422(): void
    {
        $consumidor = $this->crearConsumidor();
        $token = app(ConsumidorTokenService::class)->generarTokenMagic($consumidor);

        $this->travel(ConsumidorTokenService::TTL_MAGIC_MINUTOS + 1)->minutes();

        $this->postJson('/api/v1/consumidores/auth/magic-login', ['token' => $token])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'operacion_invalida');

        $this->travelBack();

        $this->postJson('/api/v1/consumidores/auth/magic-login', ['token' => 'basura.invalida'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'operacion_invalida');
    }

    // ==================== RF-T72: TURNSTILE ====================

    public function test_registro_con_turnstile_configurado_exige_token_valido(): void
    {
        config(['services.turnstile.secret' => 'secret-test']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        // Sin token ⇒ 422 antes de tocar la BD.
        $this->postJson('/api/v1/consumidores/registro', [
            'nombre' => 'Bot Malo',
            'email' => 'bot-'.uniqid().'@test.com',
            'password' => 'secreto123',
        ])->assertStatus(422)
            ->assertJsonPath('codigo', 'turnstile_invalido');

        // Token que Cloudflare rechaza ⇒ mismo 422.
        $this->postJson('/api/v1/consumidores/registro', [
            'nombre' => 'Bot Malo',
            'email' => 'bot-'.uniqid().'@test.com',
            'password' => 'secreto123',
            'turnstile_token' => 'token-rechazado',
        ])->assertStatus(422)
            ->assertJsonPath('codigo', 'turnstile_invalido');
    }

    public function test_registro_con_turnstile_valido_pasa(): void
    {
        config(['services.turnstile.secret' => 'secret-test']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $email = 'humano-'.uniqid().'@test.com';

        $this->postJson('/api/v1/consumidores/registro', [
            'nombre' => 'Humano Real',
            'email' => $email,
            'password' => 'secreto123',
            'turnstile_token' => 'token-ok',
        ])->assertCreated();

        $consumidor = Consumidor::where('email', $email)->first();
        $this->assertNotNull($consumidor);
        $this->consumidores[] = $consumidor;
    }

    public function test_recuperar_con_turnstile_invalido_da_422(): void
    {
        config(['services.turnstile.secret' => 'secret-test']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/recuperar', [
            'email' => $consumidor->email,
            'turnstile_token' => 'token-rechazado',
        ])->assertStatus(422)
            ->assertJsonPath('codigo', 'turnstile_invalido');

        Mail::assertNothingSent();
    }

    public function test_sin_turnstile_configurado_todo_funciona_sin_el_campo(): void
    {
        // El default de test no tiene TURNSTILE_SECRET_KEY: degradación honesta.
        $consumidor = $this->crearConsumidor();

        $this->postJson('/api/v1/consumidores/recuperar', ['email' => $consumidor->email])
            ->assertOk();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['config', 'pymes'];

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.username', $user->username)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('comercio.selector', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $component = Volt::test('pages.auth.login')
            ->set('form.username', $user->username)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_dashboard_redirects_to_comercio_selector_without_tenant(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('comercio.selector'));
    }

    public function test_app_home_redirects_to_login_when_guest(): void
    {
        // start_url "/app" de la PWA: sin sesión → login (dentro del scope /app).
        $this->get('/app')->assertRedirect(route('login'));
    }

    public function test_app_home_redirects_to_dashboard_when_authenticated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/app')->assertRedirect(route('dashboard'));
    }

    public function test_legacy_urls_redirect_to_app_prefix(): void
    {
        // Redirects de cortesía: URLs viejas → /app/* (no romper bookmarks).
        $this->get('/dashboard')->assertRedirect('/app/dashboard');
        $this->get('/login')->assertRedirect('/app/login');
        $this->get('/ventas/nueva')->assertRedirect('/app/ventas/nueva');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}

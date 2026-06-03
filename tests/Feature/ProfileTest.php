<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['config', 'pymes'];

    public function test_profile_redirects_to_comercio_selector_without_tenant(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile'));

        $response->assertRedirect(route('comercio.selector'));
    }
}

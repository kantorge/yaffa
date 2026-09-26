<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class ViewApiDocsGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_none_denies_guest(): void
    {
        config(['yaffa.scramble_prod_auth' => 'none']);

        $this->get('/docs/api')->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_none_denies_verified_user(): void
    {
        config(['yaffa.scramble_prod_auth' => 'none']);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/docs/api')->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_guest_mode_allows_unauthenticated_visitor(): void
    {
        config(['yaffa.scramble_prod_auth' => 'guest']);

        $this->get('/docs/api')->assertStatus(Response::HTTP_OK);
    }

    public function test_user_mode_denies_unauthenticated_visitor(): void
    {
        config(['yaffa.scramble_prod_auth' => 'user']);

        $this->get('/docs/api')->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_mode_denies_unverified_user(): void
    {
        config(['yaffa.scramble_prod_auth' => 'user']);

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)->get('/docs/api')->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_mode_allows_verified_user(): void
    {
        config(['yaffa.scramble_prod_auth' => 'user']);

        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/docs/api')->assertStatus(Response::HTTP_OK);
    }
}

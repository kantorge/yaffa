<?php

namespace Tests\Feature\API;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTokenApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(route('api.v1.users.me.tokens.index'));

        $this->assertUserNotAuthorized($response);
    }

    public function test_can_create_and_list_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.users.me.tokens.store'), [
            'name' => 'My script',
            'abilities' => ['accounts:read'],
        ]);

        $response->assertStatus(Response::HTTP_CREATED);
        $response->assertJsonStructure(['id', 'name', 'abilities', 'expires_at', 'token']);
        $this->assertNotEmpty($response->json('token'));

        $listResponse = $this->actingAs($user)->getJson(route('api.v1.users.me.tokens.index'));

        $listResponse->assertStatus(Response::HTTP_OK);
        $listResponse->assertJsonCount(1, 'data');
        $listResponse->assertJsonMissingPath('data.0.token');
        $this->assertSame('My script', $listResponse->json('data.0.name'));
    }

    public function test_creating_token_with_empty_abilities_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.users.me.tokens.store'), [
            'name' => 'My script',
            'abilities' => [],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('abilities');
    }

    public function test_creating_token_with_unknown_ability_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.users.me.tokens.store'), [
            'name' => 'My script',
            'abilities' => ['not-a-real-ability'],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('abilities.0');
    }

    public function test_creating_token_with_expires_at_in_the_past_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('api.v1.users.me.tokens.store'), [
            'name' => 'My script',
            'abilities' => ['accounts:read'],
            'expires_at' => Carbon::now()->subDay()->toDateTimeString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('expires_at');
    }

    public function test_creating_token_with_expires_at_beyond_max_lifetime_is_rejected(): void
    {
        $user = User::factory()->create();
        $maxLifetimeDays = (int) config('yaffa.api_token_max_lifetime_days');

        $response = $this->actingAs($user)->postJson(route('api.v1.users.me.tokens.store'), [
            'name' => 'My script',
            'abilities' => ['accounts:read'],
            'expires_at' => Carbon::now()->addDays($maxLifetimeDays + 1)->toDateTimeString(),
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors('expires_at');
    }

    public function test_can_revoke_own_token(): void
    {
        $user = User::factory()->create();
        $newToken = $user->createToken('mine', ['accounts:read']);

        $response = $this->actingAs($user)->deleteJson(route('api.v1.users.me.tokens.destroy', ['id' => $newToken->accessToken->id]));

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $newToken->accessToken->id]);
    }

    public function test_cannot_revoke_other_users_token(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $newToken = $otherUser->createToken('theirs', ['accounts:read']);

        $response = $this->actingAs($user)->deleteJson(route('api.v1.users.me.tokens.destroy', ['id' => $newToken->accessToken->id]));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $newToken->accessToken->id]);
    }

    public function test_bearer_token_cannot_manage_tokens_even_with_full_abilities(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson(route('api.v1.users.me.tokens.index'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_narrow_bearer_token_cannot_create_a_broader_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['accounts:read']);

        $response = $this->postJson(route('api.v1.users.me.tokens.store'), [
            'name' => 'Escalation attempt',
            'abilities' => ['settings:write'],
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'Escalation attempt']);
    }
}

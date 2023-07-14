<?php

namespace Tests\Unit\Http\Controller\API;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AccountEntityApiControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function it_updates_the_active_status_of_an_account_entity()
    {
        // Create a user and an account entity, which also needs a currency and an account group
        $user = User::factory()->create();
        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        $accountEntity = AccountEntity::factory()
            ->account($user)
            ->create([
                'active' => false,
                'user_id' => $user->id,
            ]);

        $this->actingAs($user);
        $response = $this->put(route('api.accountentity.updateActive', [
            'accountEntity' => $accountEntity->id,
            'active' => true,
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals($accountEntity->fresh()->active, 1);
    }

    /**
     * @test
     */
    public function it_throws_an_authorization_exception_if_user_is_not_authorized_to_update_an_account_entity()
    {
        // Create a user and an account entity, which also needs a currency and an account group
        $user = User::factory()->create();
        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        $accountEntity = AccountEntity::factory()
            ->account($user)
            ->create([
                'active' => false,
                'user_id' => $user->id,
            ]);

        // Try to update the account entity as an unauthenticated user
        $response = $this->put(
            route(
                'api.accountentity.updateActive',
                [
                    'accountEntity' => $accountEntity->id,
                    'active' => 1,
                ]
            ),
            [],
            [
                'Accept' => 'application/json'
            ]
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertEquals($accountEntity->fresh()->active, false);

        // Try to update the account entity as a different user
        $user2 = User::factory()->create();

        $this->actingAs($user2);
        $response = $this->put(route('api.accountentity.updateActive', [
            'accountEntity' => $accountEntity->id,
            'active' => 1,
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertEquals($accountEntity->fresh()->active, $accountEntity->active);
    }
}

<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AccountEntityApiControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_active_status_of_an_account_entity(): void
    {
        // Create a user and an account entity, which also needs a currency and an account group
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        $accountEntity = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'active' => false,
            ]);

        $this->actingAs($user);
        $response = $this->put(route('api.accountentity.updateActive', [
            'accountEntity' => $accountEntity->id,
            'active' => true,
        ]));

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals(1, $accountEntity->fresh()->active);
    }

    public function test_it_throws_an_authorization_exception_if_user_is_not_authorized_to_update_an_account_entity(): void
    {
        // Create a user and an account entity, which also needs a currency and an account group
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        /** @var AccountEntity $accountEntity */
        $accountEntity = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'active' => false,
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

        $this->assertEquals(false, $accountEntity->fresh()->active);

        // Try to update the account entity as a different user
        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->actingAs($user2);
        $response = $this->put(route('api.accountentity.updateActive', [
            'accountEntity' => $accountEntity->id,
            'active' => 1,
        ]));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertEquals($accountEntity->fresh()->active, $accountEntity->active);
    }

    public function test_user_can_delete_an_existing_payee(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $account */
        $payee = AccountEntity::factory()
            ->for($user)
            ->for(
                Payee::factory()->withUser($user),
                'config'
            )
            ->create();

        $payee->load('config');

        $response = $this->actingAs($user)
            ->deleteJson(route("api.accountentity.destroy", $payee));

        // Response should be 200 OK
        $response->assertStatus(Response::HTTP_OK);

        // Check if model was deleted
        $this->assertDatabaseMissing($payee->getTable(), $payee->attributesToArray());

        // Check if config was also deleted
        $this->assertDatabaseMissing($payee->config->getTable(), [
            'id' => $payee->config->id,
        ]);
    }

    public function test_user_can_delete_an_existing_account(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        /** @var AccountEntity $account */
        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create([
                'active' => false,
            ]);

        $account->load('config');

        $response = $this->actingAs($user)
            ->deleteJson(route("api.accountentity.destroy", $account));

        // Response should be 200 OK
        $response->assertStatus(Response::HTTP_OK);

        // Check if model was deleted
        $this->assertDatabaseMissing($account->getTable(), $account->attributesToArray());

        // Check if config was also deleted
        $this->assertDatabaseMissing($account->config->getTable(), [
            'id' => $account->config->id,
        ]);
    }

    public function test_user_cannot_delete_an_already_used_payee_or_account(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'language' => 'en',
        ]);

        // Create a transaction for this category, which also needs other models:
        // account group, currency, account, payee
        AccountGroup::factory()
            ->for($user)
            ->create();

        Currency::factory()
            ->for($user)
            ->create();

        $account = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(Payee::factory()->withUser($user), 'config')
            ->create();

        // Create a standard transaction with specific data
        Transaction::factory()
            ->for($user)
            ->for(
                TransactionDetailStandard::factory()->create([
                    'amount_from' => 1,
                    'amount_to' => 1,
                    'account_from_id' => $account->id,
                    'account_to_id' => $payee->id,
                ]),
                'config'
            )
            ->withdrawal($user)
            ->create();

        // Try to delete the payee
        $response = $this->actingAs($user)
            ->deleteJson(route("api.accountentity.destroy", $payee));

        // Response should be 422 Unprocessable Entity
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Error message should be returned
        $response->assertJsonFragment([
            'error' => __('Payee is in use, cannot be deleted'),
        ]);

        // Payee should be in the database
        $this->assertDatabaseHas($payee->getTable(), $payee->attributesToArray());

        // Try to delete the account
        $response = $this->actingAs($user)
            ->deleteJson(route("api.accountentity.destroy", $account));

        // Response should be 422 Unprocessable Entity
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        // Error message should be returned
        $response->assertJsonFragment([
            'error' => __('Account is in use, cannot be deleted'),
        ]);

        // Account should be in the database
        $this->assertDatabaseHas($account->getTable(), $account->attributesToArray());
    }
}

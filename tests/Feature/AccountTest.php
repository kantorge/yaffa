<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    private function createAccountAndUser(): AccountEntity
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $account */
        $account = AccountEntity::factory()
            ->for($user)
            ->for(
                Account::factory()->withUser($user),
                'config'
            )
            ->create();

        return $account;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-entity');
        $this->setBaseModel(AccountEntity::class);
    }

    public function test_user_cannot_create_new_account_without_an_account_group(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, Currency::class);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.create", ['type' => 'account']));

        // User is redirected to create an account group first
        $response->assertRedirectToRoute('account-group.create');
    }

    public function test_user_cannot_create_new_account_without_a_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.create", ['type' => 'account']));

        // User is redirected to create a currency first
        $response->assertRedirectToRoute('currency.create');
    }

    public function test_guest_cannot_access_resource(): void
    {
        // Unauthenticated user cannot access any actions of the resource
        $this->get(route("{$this->base_route}.index", ['type' => 'account']))->assertRedirectToRoute('login');
        $this->get(route("{$this->base_route}.create", ['type' => 'account']))->assertRedirectToRoute('login');
        $this->post(route("{$this->base_route}.store", ['type' => 'account']))->assertRedirectToRoute('login');

        // Create a user and the related resources
        $account = $this->createAccountAndUser();

        $this->get(route("{$this->base_route}.edit", ['type' => 'account', 'account_entity' => $account->id]))
            ->assertRedirectToRoute('login');
        $this->patch(route("{$this->base_route}.update", ['type' => 'account', 'account_entity' => $account->id]))
            ->assertRedirectToRoute('login');
    }

    public function test_user_cannot_access_other_users_resource(): void
    {
        $account = $this->createAccountAndUser();

        /** @var User $user2 */
        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", [
            'type' => 'account',
            'account_entity' => $account->id
        ]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", [
            'type' => 'account',
            'account_entity' => $account->id
        ]))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_user_can_view_list_of_accounts(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);
        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->count(5)
            ->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index", ['type' => 'account']));

        $response->assertStatus(200);
        $response->assertViewIs('account.index');
    }

    public function test_user_can_access_create_form(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create", ['type' => 'account']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('account.form');
    }

    public function test_user_cannot_create_an_account_with_missing_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $accountGroup = $this->createForUser($user, AccountGroup::class);
        $currency = $this->createForUser($user, Currency::class);
        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                [
                    'name' => '',
                    'active' => 1,
                    'config_type' => 'account',
                    'config' => [
                        'opening_balance' => 0,
                        'account_group_id' => $accountGroup->id,
                        'currency_id' => $currency->id,
                    ],
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_create_an_account(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $attributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $attributes['config_type'] = 'account';
        $attributes['config'] = Account::factory()->withUser($user)->raw();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                $attributes
            );

        $response->assertRedirectToRoute("{$this->base_route}.index", ['type' => 'account']);

        $model = new $this->base_model();

        $this->assertDatabaseHas($model->getTable(), $baseAttributes);
    }

    public function test_user_can_edit_an_existing_account(): void
    {
        $account = $this->createAccountAndUser();
        $user = $account->user;

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    ['type' => 'account', 'account_entity' => $account->id]
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs('account.form');
    }

    public function test_user_cannot_update_an_account_with_missing_data(): void
    {
        $account = $this->createAccountAndUser();
        $user = $account->user;

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['type' => 'account', 'account_entity' => $account->id]
                ),
                [
                    'id' => $account->id,
                    'name' => '',
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_an_account_with_proper_data(): void
    {
        $account = $this->createAccountAndUser();
        $user = $account->user;

        $attributes = AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user), 'config')
            ->raw();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['type' => 'account', 'account_entity' => $account->id]
                ),
                [
                    'name' => $attributes['name'],
                    'active' => $account->active,
                    'config_type' => 'account',
                    'config' => [
                        'opening_balance' => $account->config->opening_balance,
                        'account_group_id' => $account->config->account_group_id,
                        'currency_id' => $account->config->currency_id,
                    ],
                ]
            );

        $response->assertRedirectToRoute("{$this->base_route}.index", ['type' => 'account']);
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    public function test_form_request_enforces_opening_balance_boundaries(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $attributes = AccountEntity::factory()->for($user)->raw();
        $attributes['config_type'] = 'account';
        $attributes['config'] = Account::factory()->withUser($user)->raw();

        // Opening balance is too high
        $attributes['config']['opening_balance'] = 10 ** 21;
        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                $attributes
            );

        $response->assertStatus(422);

        // Opening balance is too low
        $attributes['config']['opening_balance'] = -10 ** 21;
        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                $attributes
            );

        $response->assertStatus(422);

        // Opening balance is within the boundaries
        $attributes['config']['opening_balance'] = 10 ** 10 + 0.0000000001;
        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                $attributes
            );

        $response->assertRedirectToRoute("{$this->base_route}.index", ['type' => 'account']);

        $this->assertDatabaseHas('accounts', $attributes['config']);
    }
}

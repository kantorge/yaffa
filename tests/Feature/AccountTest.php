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

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-entity');
        $this->setBaseModel(AccountEntity::class);
    }

    /** @test */
    public function user_cannot_create_new_account_without_an_account_group()
    {
        $user = User::factory()->create();

        $this->createForUser($user, Currency::class);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.create", ['type' => 'account']));
        $response->assertRedirect(route('account-group.create'));
    }

    /** @test */
    public function user_cannot_create_new_account_without_a_currency()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.create", ['type' => 'account']));
        $response->assertRedirect(route('currencies.create'));
    }

    /** @test */
    public function guest_cannot_access_resource()
    {
        $this->get(route("{$this->base_route}.index", ['type' => 'account']))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create", ['type' => 'account']))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store", ['type' => 'account']))->assertRedirect(route('login'));

        $this->create(AccountGroup::class);
        $this->create(Currency::class);
        $account = AccountEntity::factory()->account()->create();

        $this->get(route("{$this->base_route}.edit", ['type' => 'account', 'account_entity' => $account->id]))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", ['type' => 'account', 'account_entity' => $account->id]))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", ['type' => 'account', 'account_entity' => $account->id]))->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource()
    {
        $user1 = User::factory()->create();
        $this->createForUser($user1, AccountGroup::class);
        $this->createForUser($user1, Currency::class);
        $account = AccountEntity::factory()->for($user1)->account($user1)->create();

        $user2 = User::factory()->create();
        /** @var \Illuminate\Contracts\Auth\Authenticatable $user2 */
        $this->actingAs($user2)->get(route("{$this->base_route}.edit", ['type' => 'account', 'account_entity' => $account->id]))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", ['type' => 'account', 'account_entity' => $account->id]))->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", ['type' => 'account', 'account_entity' => $account->id]))->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_accounts()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);
        AccountEntity::factory()->for($user)->account($user)->count(5)->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index", ['type' => 'account']));

        $response->assertStatus(200);
        $response->assertViewIs('account.index');
    }

    /** @test */
    public function user_can_access_create_form()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create", ['type' => 'account']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('account.form');
    }

    /** @test */
    public function user_cannot_create_an_account_with_missing_data()
    {
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

    /** @test */
    public function user_can_create_an_account()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);

        $attributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $attributes['config_type'] = 'account';
        $attributes['config'] = Account::factory()->raw();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                $attributes
            );

        $response->assertRedirect(route("{$this->base_route}.index", ['type' => 'account']));

        $model = new $this->base_model;

        $this->assertDatabaseHas($model->getTable(), $baseAttributes);
    }

    /** @test */
    public function user_can_edit_an_existing_account()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);
        $account = AccountEntity::factory()->for($user)->account($user)->create();

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

    /** @test */
    public function user_cannot_update_an_account_with_missing_data()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);
        $account = AccountEntity::factory()->for($user)->account($user)->create();

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

    /** @test */
    public function user_can_update_an_account_with_proper_data()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);
        $account = AccountEntity::factory()->for($user)->account($user)->create();

        $attributes = AccountEntity::factory()->for($user)->account($user)->raw();

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

        $response->assertRedirect(route("{$this->base_route}.index", ['type' => 'account']));
        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
    }

    /** @test */
    public function user_can_delete_an_existing_account()
    {
        $user = User::factory()->create();

        $this->createForUser($user, AccountGroup::class);
        $this->createForUser($user, Currency::class);
        $account = AccountEntity::factory()->for($user)->account($user)->create();
        $accountConfig = $account->config;

        $this->actingAs($user)->deleteJson(route("{$this->base_route}.destroy", $account->id));

        // Check if model was deleted
        $this->assertDatabaseMissing($account->getTable(), $account->makeHidden('config')->toArray());

        // Check if config was also deleted
        $this->assertDatabaseMissing('accounts', $accountConfig->toArray());
    }
}

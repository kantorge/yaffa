<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class InvestmentTest extends TestCase
{
    use RefreshDatabase;

    private function createInvestmentAndUser(): AccountEntity
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $investment */
        $investment = AccountEntity::factory()
            ->for($user)
            ->for(
                Investment::factory()->withUser($user),
                'config'
            )
            ->create();

        return $investment;
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('account-entity');
        $this->setBaseModel(AccountEntity::class);
    }

    /** @test */
    public function user_cannot_create_new_investment_without_an_investment_group()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, Currency::class);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.create", ['type' => 'investment']));

        // User is redirected to create an investment group first
        $response->assertRedirect(route('investment-group.create'));
    }

    /** @test */
    public function user_cannot_create_new_investment_without_a_currency()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createForUser($user, InvestmentGroup::class);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.create", ['type' => 'investment']));

        // User is redirected to create a currency first
        $response->assertRedirect(route('currencies.create'));
    }

    /** @test */
    public function guest_cannot_access_resource()
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));

        /** @var AccountEntity $investment */
        $investment = AccountEntity::factory()->investment()->create();

        $this->get(route("{$this->base_route}.edit", $investment->id))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $investment->id))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $investment->id))->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource()
    {
        /** @var AccountEntity $investment */
        $investment = AccountEntity::factory()->investment()->create();

        /** @var User $otherUser */
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get(route("{$this->base_route}.edit", $investment->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($otherUser)->patch(route("{$this->base_route}.update", $investment->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($otherUser)->delete(route("{$this->base_route}.destroy", $investment->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_investments()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        AccountEntity::factory()
            ->investment()
            ->count(5)
            ->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index", ['type' => 'investment']));

        $response->assertStatus(200);
        $response->assertViewIs('investment.index');
    }

    /** @test */
    public function user_can_access_create_form()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->createPrerequisites($user);

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create", ['type' => 'investment']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('investment.form');
    }

    /** @test */
    public function user_cannot_create_an_investment_with_missing_data()
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store"),
                [
                    'name' => '',
                    'active' => 1,
                    'config.symbol' => '',
                    'config.isin' => '',
                    'config.investment_group_id' => $investmentGroup->id,
                    'config.currency_id' => $currency->id,
                    'config.auto_update' => 0,
                    'config.investent_price_provider_id' => null,
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_create_an_investment()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);

        $attributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $attributes['config_type'] = 'investment';
        $attributes['config'] = Investment::factory()->withUser($user)->raw();

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'investment']),
                $attributes
            );

        $response->assertRedirect(route("{$this->base_route}.index", ['type' => 'investment']));

        $model = new $this->base_model();

        $this->assertDatabaseHas($model->getTable(), $baseAttributes);
    }

    /** @test */
    public function user_can_edit_an_existing_investment()
    {
        $investment = $this->createInvestmentAndUser();
        $user = $investment->user;

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    ['type' => 'investment', 'account_entity' => $investment->id]
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs('investment.form');
    }

    /** @test */
    public function user_cannot_update_an_investment_with_missing_data()
    {
        $investment = $this->createInvestmentAndUser();
        $user = $investment->user;

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $investment->id
                ),
                [
                    'name' => '',
                    'active' => $investment->active,
                    'symbol' => $investment->symbol,
                    'investment_group_id' => $investment->investment_group_id,
                    'currency_id' => $investment->currency_id,
                    'auto_update' => $investment->auto_update,
                    'investent_price_provider' => $investment->investment_price_provider,
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    /** @test */
    public function user_can_update_an_investment_with_proper_data()
    {
        $investment = $this->createInvestmentAndUser();
        $user = $investment->user;

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $investment->id
                ),
                [
                    'name' => 'Updated investment name',
                    'active' => $investment->active,
                    'config_type' => 'investment',
                    'config' => [
                        'symbol' => $investment->config->symbol,
                        'investment_group_id' => $investment->config->investment_group_id,
                        'currency_id' => $investment->config->currency_id,
                        'auto_update' => $investment->config->auto_update,
                        'investent_price_provider' => $investment->config->investment_price_provider,
                    ],
                ]
            );

        $response->assertRedirect(route("{$this->base_route}.index", ['type' => 'investment']));
        //TODO: make this dynamic instead of fixed 1st element
        $response->assertSessionHas('notification_collection.0.type', 'success');
    }

    /** @test */
    public function user_can_delete_an_existing_investment()
    {
        $investment = $this->createInvestmentAndUser();
        $user = $investment->user;
        $investment->load('config');

        $this->actingAs($user)
            ->deleteJson(route("{$this->base_route}.destroy", $investment->id));

        // Check if model was deleted
        $this->assertDatabaseMissing($investment->getTable(), $investment->attributesToArray());

        // Check if config model was deleted
        $this->assertDatabaseMissing($investment->config->getTable(), [
            'id' => $investment->config->id,
        ]);
    }

    /** @test */
    public function account_entity_timestamp_is_updated_when_investment_properties_change()
    {
        $this->markTestIncomplete();
    }

    /** @test */
    public function name_isin_and_symbol_are_unique_for_a_given_user()
    {
        // User can create an investment with a given name, symbol and isin
        // even if an other user has an investment with the same name, symbol or isin
        /** @var User $otherUser */
        $otherUser = User::factory()->create();
        $this->createPrerequisites($otherUser);
        AccountEntity::factory()
            ->for($otherUser)
            ->for(
                Investment::factory()->withUser($otherUser)->create([
                    'symbol' => 'SYMBOL',
                    'isin' => '1234567890',
                ]),
                'config'
            )
            ->create([
                'name' => 'ABC',
            ]);

        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);

        $attributes = AccountEntity::factory()
            ->for($user)
            ->raw([
                'name' => 'ABC',
            ]);
        $attributes['config_type'] = 'investment';
        $attributes['config'] = Investment::factory()
            ->withUser($user)
            ->raw([
                'symbol' => 'SYMBOL',
                'isin' => '1234567890',
            ]);

        $this->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'investment']),
                $attributes
            );

        // Load the newly created investment from the database for the user
        $investment = AccountEntity::where('name', 'ABC')
            ->where('config_type', 'investment')
            ->where('user_id', $user->id)
            ->first();

        // The same user cannot create another investment with the same name, symbol or isin
        $response = $this->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'investment']),
                $attributes
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'config.symbol', 'config.isin']);

        // The user can create an account or payee with the same name
        $this->createForUser($user, AccountGroup::class);
        $accountAttributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $accountAttributes['config_type'] = 'account';
        $accountAttributes['config'] = Account::factory()->withUser($user)->raw();

        $response = $this->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'account']),
                $accountAttributes
            );

        $response->assertStatus(302);
        $this->assertDatabaseHas('account_entities', $baseAttributes);

        $payeeAttributes = $baseAttributes = AccountEntity::factory()->for($user)->raw();
        $payeeAttributes['config_type'] = 'payee';
        $payeeAttributes['config'] = Payee::factory()->withUser($user)->raw([
            'category_id' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'payee']),
                $payeeAttributes
            );

        $response->assertStatus(302);
        $this->assertDatabaseHas('account_entities', $baseAttributes);

        // The same user can create an investment with other name, symbol or isin
        $attributes = $baseAttributes = AccountEntity::factory()->for($user)->raw([
            'name' => 'DEF',
        ]);
        $attributes['config_type'] = 'investment';
        $attributes['config'] = Investment::factory()->withUser($user)->raw([
            'symbol' => 'DEF',
            'isin' => '987654321098',
        ]);

        $response = $this->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store", ['type' => 'investment']),
                $attributes
            );

        $response->assertStatus(302);
        $this->assertDatabaseHas('account_entities', $baseAttributes);

        // The same user can make updates to an investment with the same name, symbol or isin
        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    ['type' => 'account', 'account_entity' => $investment->id]
                ),
                [
                    'name' => $investment->name,
                    'active' => ! $investment->active,
                    'config_type' => 'investment',
                    'config' => $investment->config->toArray(),
                ]
            );

        $response->assertStatus(302);
        $this->assertDatabaseHas('account_entities', [
            'id' => $investment->id,
            'name' => $investment->name,
            'active' => ! $investment->active,
        ]);
    }

    private function createPrerequisites(User $user): array
    {
        return [
            Currency::factory()->withUser($user)->create(),
            InvestmentGroup::factory()->withUser($user)->create(),
        ];
    }
}

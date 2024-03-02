<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class InvestmentTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('investment');
        $this->setBaseModel(Investment::class);
    }

    /** @test */
    public function guest_cannot_access_resource()
    {
        $this->get(route("{$this->base_route}.index"))->assertRedirect(route('login'));
        $this->get(route("{$this->base_route}.create"))->assertRedirect(route('login'));
        $this->post(route("{$this->base_route}.store"))->assertRedirect(route('login'));

        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create();

        $this->get(route("{$this->base_route}.edit", $investment->id))->assertRedirect(route('login'));
        $this->patch(route("{$this->base_route}.update", $investment->id))->assertRedirect(route('login'));
        $this->delete(route("{$this->base_route}.destroy", $investment->id))->assertRedirect(route('login'));
    }

    /** @test */
    public function user_cannot_access_other_users_resource()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create();

        $user2 = User::factory()->create();

        $this->actingAs($user2)->get(route("{$this->base_route}.edit", $investment->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->patch(route("{$this->base_route}.update", $investment->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
        $this->actingAs($user2)->delete(route("{$this->base_route}.destroy", $investment->id))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /** @test */
    public function user_can_view_list_of_investments()
    {
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        Investment::factory()->for($user)->count(5)->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.index");
    }

    /** @test */
    public function user_can_access_create_form()
    {
        /** @var User $user */
        $user = User::factory()->create();

        // Create an investment group and a currency, which are prerequisites for creating an investment
        InvestmentGroup::factory()->for($user)->create();
        Currency::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function investment_form_requires_investment_group_and_currency()
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        // Assert that the user is redirected to the investment group creation page
        $response->assertRedirect(route('investment-group.create'));

        // Create the investment group
        InvestmentGroup::factory()->for($user)->create();

        // Assert that the user is redirected to the currency creation page
        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertRedirect(route('currency.create'));

        // Create the currency
        Currency::factory()->for($user)->create();

        // Assert that the user can access the investment creation page
        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
    }

    /** @test */
    public function user_cannot_create_an_investment_with_missing_data()
    {
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        $response = $this
            ->actingAs($user)
            ->postJson(
                route("{$this->base_route}.store"),
                [
                    'name' => '',
                    'active' => 1,
                    'symbol' => '',
                    'isin' => '',
                    'investment_group_id' => $investmentGroup->id,
                    'currency_id' => $currency->id,
                    'auto_update' => null,
                    'investent_price_provider_id' => null,
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

        $this->assertCreateForUser($user);
    }

    /** @test */
    public function user_can_edit_an_existing_investment()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    $investment->id
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs("{$this->base_route}.form");
    }

    /** @test */
    public function user_cannot_update_an_investment_with_missing_data()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create();

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
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create();

        $attributes = Investment::factory()->for($user)->raw();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $investment->id
                ),
                [
                    'name' => $attributes['name'],
                    'active' => $investment->active,
                    'symbol' => $investment->symbol,
                    'investment_group_id' => $investment->investment_group_id,
                    'currency_id' => $investment->currency_id,
                    'auto_update' => $investment->auto_update,
                    'investent_price_provider' => $investment->investment_price_provider,
                ]
            );

        $response->assertRedirect(route("{$this->base_route}.index"));
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    /** @test */
    public function user_can_delete_an_existing_investment()
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        $this->assertDestroyWithUser($user);
    }

    private function createPrerequisites(User $user = null): array
    {
        if ($user) {
            $investmentGroup = $this->createForUser($user, InvestmentGroup::class);
            $currency = $this->createForUser($user, Currency::class);
        } else {
            $investmentGroup = $this->create(InvestmentGroup::class);
            $currency = $this->create(Currency::class);
        }

        return [
            $currency,
            $investmentGroup,
        ];
    }
}

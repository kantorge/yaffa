<?php

namespace Tests\Feature;

use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ScheduleInstance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\Feature\Concerns\AuthorizesResourceCrud;
use Tests\TestCase;

class InvestmentTest extends TestCase
{
    use AuthorizesResourceCrud;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setBaseRoute('investments');
        $this->setBaseModel(Investment::class);
    }

    protected function createResourceForAuthTest(User $user): Investment
    {
        $this->createPrerequisites($user);

        return Investment::factory()->for($user)->withUser($user)->create();
    }

    /**
     * Delete moved to InvestmentApiController (api.v1.investments.destroy) - the web destroy
     * route/action was removed as dead code. See InvestmentApiControllerTest for delete
     * behavior coverage.
     */
    protected function resourceAuthSupportsDestroy(): bool
    {
        return false;
    }

    public function test_user_can_view_list_of_investments(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        Investment::factory()->for($user)->withUser($user)->count(5)->create();

        $response = $this->actingAs($user)->get(route("{$this->base_route}.index"));

        $response->assertStatus(200);
        $response->assertViewIs('investments.index');
    }

    public function test_user_can_access_create_form(): void
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
        $response->assertViewIs('investments.form');
        $response->assertSee('investmentProviderFormApp', false);
    }

    public function test_investment_form_requires_investment_group_and_currency(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        // Assert that the user is redirected to the investment group creation page
        $response->assertRedirectToRoute('investment-groups.create');

        // Create the investment group
        InvestmentGroup::factory()->for($user)->create();

        // Assert that the user is redirected to the currency creation page
        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertRedirectToRoute('currencies.create');

        // Create the currency
        Currency::factory()->for($user)->create();

        // Assert that the user can access the investment creation page
        $response = $this
            ->actingAs($user)
            ->get(route("{$this->base_route}.create"));

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_user_cannot_create_an_investment_with_missing_data(): void
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
                    'symbol' => '',
                    'isin' => '',
                    'investment_group_id' => $investmentGroup->id,
                    'currency_id' => $currency->id,
                    'auto_update' => null,
                    'investment_price_provider_id' => null,
                ]
            );
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_create_an_investment(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        $this->assertCreateForUser(
            $user,
            [
                'investment_group_id' => $investmentGroup->id,
                'currency_id' => $currency->id,
            ]
        );
    }

    public function test_user_can_create_web_scraping_investment_with_provider_settings(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        $response = $this->actingAs($user)->post(route('investments.store'), [
            'name' => 'Scraped investment',
            'symbol' => 'SCRAPE',
            'isin' => 'US1234567890',
            'comment' => 'Provider settings test',
            'active' => 1,
            'auto_update' => 1,
            'investment_group_id' => $investmentGroup->id,
            'currency_id' => $currency->id,
            'investment_price_provider' => 'web_scraping',
            'provider_settings' => [
                'url' => 'https://example.com/price',
                'selector' => '.price',
                'decimal_separator' => ',',
            ],
        ]);

        $response->assertRedirectToRoute('investments.index');

        $investment = Investment::query()
            ->where('user_id', $user->id)
            ->where('name', 'Scraped investment')
            ->firstOrFail();

        $this->assertSame('web_scraping', $investment->investment_price_provider);
        $this->assertSame('https://example.com/price', $investment->provider_settings['url']);
        $this->assertSame('.price', $investment->provider_settings['selector']);
        $this->assertSame(',', $investment->provider_settings['decimal_separator']);
    }

    public function test_user_can_edit_an_existing_investment(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->withUser($user)->create();

        $response = $this
            ->actingAs($user)
            ->get(
                route(
                    "{$this->base_route}.edit",
                    $investment->id
                )
            );

        $response->assertStatus(200);
        $response->assertViewIs('investments.form');
        $response->assertSee('investmentProviderFormApp', false);
    }

    public function test_user_cannot_update_an_investment_with_missing_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->withUser($user)->create();

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
                    'investment_price_provider' => $investment->investment_price_provider,
                ]
            );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_user_can_update_an_investment_with_proper_data(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create([
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);

        // Assert that the investment has valid foreign keys
        $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('investment_groups', ['id' => $investmentGroup->id, 'user_id' => $user->id]);
        $this->assertDatabaseHas('investments', [
            'id' => $investment->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
            'user_id' => $user->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $investment->id
                ),
                [
                    'name' => $investment['name'] . ' updated',
                    'active' => $investment->active,
                    'symbol' => $investment->symbol,
                    'investment_group_id' => $investment->investment_group_id,
                    'currency_id' => $investment->currency_id,
                    'auto_update' => $investment->auto_update,
                    'investment_price_provider' => $investment->investment_price_provider,
                ]
            );

        $response->assertRedirectToRoute("{$this->base_route}.index");
        $notifications = session('notification_collection');
        $successNotificationExists = collect($notifications)
            ->contains(fn ($notification) => $notification['type'] === 'success');
        $this->assertTrue($successNotificationExists);
    }

    public function test_switching_provider_without_provider_settings_keeps_existing_provider_settings(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create([
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
            'investment_price_provider' => 'web_scraping',
            'provider_settings' => [
                'url' => 'https://example.com/price',
                'selector' => '.price',
                'decimal_separator' => ',',
            ],
        ]);

        $response = $this->actingAs($user)->patch(route('investments.update', $investment), [
            'name' => $investment->name,
            'symbol' => $investment->symbol,
            'isin' => $investment->isin,
            'comment' => $investment->comment,
            'active' => $investment->active,
            'auto_update' => $investment->auto_update,
            'investment_group_id' => $investment->investment_group_id,
            'currency_id' => $investment->currency_id,
            'investment_price_provider' => 'alpha_vantage',
        ]);

        $response->assertRedirectToRoute('investments.index');

        $investment->refresh();

        $this->assertSame('alpha_vantage', $investment->investment_price_provider);
        $this->assertSame('https://example.com/price', $investment->provider_settings['url']);
        $this->assertSame('.price', $investment->provider_settings['selector']);
        $this->assertSame(',', $investment->provider_settings['decimal_separator']);
    }

    public function test_omitting_provider_settings_preserves_existing_settings(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);

        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create([
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
            'investment_price_provider' => 'alpha_vantage',
            'provider_settings' => [
                'url' => 'https://example.com/price',
                'selector' => '.price',
                'decimal_separator' => ',',
            ],
        ]);

        $response = $this->actingAs($user)->patch(route('investments.update', $investment), [
            'name' => $investment->name,
            'symbol' => $investment->symbol,
            'isin' => $investment->isin,
            'comment' => $investment->comment,
            'active' => $investment->active,
            'auto_update' => $investment->auto_update,
            'investment_group_id' => $investment->investment_group_id,
            'currency_id' => $investment->currency_id,
            'investment_price_provider' => 'alpha_vantage',
        ]);

        $response->assertRedirectToRoute('investments.index');

        $investment->refresh();

        $this->assertSame('alpha_vantage', $investment->investment_price_provider);
        $this->assertSame('https://example.com/price', $investment->provider_settings['url']);
        $this->assertSame('.price', $investment->provider_settings['selector']);
        $this->assertSame(',', $investment->provider_settings['decimal_separator']);
    }

    public function test_investment_show_derives_scheduled_instances_from_next_date(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'end_date' => '2026-12-31',
        ]);
        [$currency, $investmentGroup] = $this->createPrerequisites($user);
        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create([
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);

        /** @var Transaction $scheduledTransaction */
        $scheduledTransaction = Transaction::factory()
            ->for($user)
            ->buy_schedule($user, [
                'investment_id' => $investment->id,
            ])
            ->create();

        $scheduledTransaction->transactionSchedule()->update([
            'start_date' => '2026-01-01',
            'next_date' => '2026-03-01',
            'end_date' => '2026-04-01',
            'frequency' => 'MONTHLY',
            'interval' => 1,
            'count' => null,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('investments.show', $investment));

        $response->assertOk();

        /** @var \Illuminate\Support\Collection<int, Transaction|ScheduleInstance> $transactions */
        $transactions = $response->viewData('transactions');

        $scheduledInstances = $transactions
            ->filter(fn (Transaction|ScheduleInstance $transaction): bool => $transaction->schedule)
            ->values();

        $scheduledDates = $scheduledInstances
            ->pluck('date')
            ->map(fn (Carbon $date): string => $date->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-03-01', '2026-04-01'], $scheduledDates);
        $this->assertTrue((bool) $scheduledInstances->first()?->schedule_first_instance);
        $this->assertFalse((bool) $scheduledInstances->last()?->schedule_first_instance);
    }

    /**
     * An investment transaction's price is cast to the investment's currency (MoneyCast) -
     * changing it after the investment is already used in a transaction would silently
     * mismatch every existing transaction's stored price against a currency it was never
     * recorded in.
     */
    public function test_currency_cannot_be_changed_once_used_in_a_transaction(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        [$currency, $investmentGroup] = $this->createPrerequisites($user);
        $otherCurrency = Currency::factory()->for($user)->create();

        /** @var Investment $investment */
        $investment = Investment::factory()->for($user)->create([
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);

        $accountEntity = AccountEntity::factory()->asAccount($user, ['currency_id' => $currency->id])->create();

        Transaction::factory()
            ->for($user)
            ->buy($user, [
                'account_id' => $accountEntity->id,
                'investment_id' => $investment->id,
            ])
            ->create();

        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $investment->id
                ),
                [
                    'name' => $investment->name,
                    'active' => $investment->active,
                    'symbol' => $investment->symbol,
                    'investment_group_id' => $investment->investment_group_id,
                    'currency_id' => $otherCurrency->id,
                    'auto_update' => $investment->auto_update,
                    'investment_price_provider' => $investment->investment_price_provider,
                ]
            );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['currency_id']);

        // Leaving the currency unchanged must still be allowed.
        $response = $this
            ->actingAs($user)
            ->patchJson(
                route(
                    "{$this->base_route}.update",
                    $investment->id
                ),
                [
                    'name' => $investment->name,
                    'active' => $investment->active,
                    'symbol' => $investment->symbol,
                    'investment_group_id' => $investment->investment_group_id,
                    'currency_id' => $investment->currency_id,
                    'auto_update' => $investment->auto_update,
                    'investment_price_provider' => $investment->investment_price_provider,
                ]
            );

        $response->assertRedirectToRoute("{$this->base_route}.index");
    }

    private function createPrerequisites(?User $user = null): array
    {
        if ($user) {
            $investmentGroup = InvestmentGroup::factory()->for($user)->create();
            $currency = Currency::factory()->for($user)->create();
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

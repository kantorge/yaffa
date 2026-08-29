<?php

namespace Tests\Feature\API;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_ACCOUNT_NAME = 'Same account name';

    public function test_account_list_with_query_applies_all_provided_filters(): void
    {
        // Primary user with test data covering various use cases
        /** @var User $user */
        $user = User::factory()->create();

        // Multiple (2) currencies
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('USD'));
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('EUR'));
        $currencies = $user->currencies()->get();

        // One account with base name
        AccountEntity::factory()->asAccount($user, ['currency_id' => $currencies->first()->id])->create([
            'active' => true,
            'name' => self::BASE_ACCOUNT_NAME
        ]);

        // Other user with dummy data and same account name
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createForUser($user2, AccountGroup::class);
        $this->createForUser($user2, Currency::class);

        AccountEntity::factory()->asAccount($user2)->create([
            'active' => true,
            'name' => self::BASE_ACCOUNT_NAME
        ]);

        Sanctum::actingAs($user, ['*']);

        // Query string is applied
        $response = $this->getJson('/api/v1/accounts?q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_ACCOUNT_NAME);

        // Only items of the specific user are returned even if criteria matches items of other user(s)
        $response->assertJsonPath('0.user_id', $user->id);

        // Only active items are returned by default
        // We create a new item for primary user with similar name, but not active, other currency
        AccountEntity::factory()->asAccount($user, ['currency_id' => $currencies->last()->id])->create([
            'active' => false,
            'name' => self::BASE_ACCOUNT_NAME . " - inactive",
        ]);

        $response = $this->getJson('/api/v1/accounts?q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_ACCOUNT_NAME);

        // Inactive items can be requested
        $response = $this->getJson('/api/v1/accounts?withInactive=1&q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);

        // Currency can be specified
        $response = $this->getJson('/api/v1/accounts?withInactive=1&currency_id=' . $currencies->first()->id . '&q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_ACCOUNT_NAME);
        $response->assertJsonPath('0.config.currency_id', $currencies->first()->id);

        // Default limit is applied for number of results
        for ($i = 1; $i <= 20; $i++) {
            AccountEntity::factory()->asAccount($user)->create([
                'active' => true,
                'name' => self::BASE_ACCOUNT_NAME . " - clone - " . $i,
            ]);
        }

        $response = $this->getJson('/api/v1/accounts?q=clone');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(10);

        // Custom limit is applied for number of results
        $response = $this->getJson('/api/v1/accounts?q=clone&limit=15');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(15);

        // All items can be requested to be returned
        $response = $this->getJson('/api/v1/accounts?q=clone&limit=0');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(20);
    }

    public function test_transaction_type_must_be_valid_if_provided(): void
    {
        // New user, no data needed
        /** @var User $user */
        $user = User::factory()->create();

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/accounts?transaction_type=invalid_type');
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('message', 'The transaction_type parameter is required and must be valid.');

        $response = $this->getJson('/api/v1/accounts?transaction_type=withdrawal');
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_recalculate_monthly_summary_uses_post_and_returns_accepted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $accountEntity */
        $accountEntity = AccountEntity::factory()->asAccount($user)->create();

        Artisan::shouldReceive('call')
            ->once()
            ->with('app:cache:account-monthly-summaries', [
                'accountEntityId' => $accountEntity->id,
            ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/accounts/' . $accountEntity->id . '/monthly-summary');

        $response->assertStatus(Response::HTTP_ACCEPTED);
        $response->assertJsonPath('message', __('The monthly summary for this account is being updated.'));
    }

    public function test_recalculate_monthly_summary_returns_bad_request_for_non_account_entity(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        /** @var AccountEntity $payeeEntity */
        $payeeEntity = AccountEntity::factory()->asPayee($user)->create();

        Artisan::shouldReceive('call')->never();

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/accounts/' . $payeeEntity->id . '/monthly-summary');

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('message', __('This account entity is not an account.'));
    }
}

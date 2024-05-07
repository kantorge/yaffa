<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class AccountApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_ACCOUNT_NAME = 'Same account name';

    /** @test */
    public function test_account_list_with_query_applies_all_provided_filters()
    {
        // Primary user with test data covering various use cases
        /** @var User $user */
        $user = User::factory()->create();

        // Multiple (2) currencies
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('USD'));
        $this->createForUser($user, Currency::class, CurrencyData::getCurrencyByIsoCode('EUR'));
        $currencies = $user->currencies()->get();

        // One account with base name
        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(['currency_id' => $currencies->first()->id]), 'config')
            ->create([
                'active' => true,
                'name' => self::BASE_ACCOUNT_NAME
            ]);

        // Other user with dummy data and same account name
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createForUser($user2, AccountGroup::class);
        $this->createForUser($user2, Currency::class);

        AccountEntity::factory()
            ->for($user2)
            ->for(Account::factory()->withUser($user2), 'config')
            ->create([
                'active' => true,
                'name' => self::BASE_ACCOUNT_NAME
            ]);

        // Query string is applied
        $response = $this->actingAs($user)->getJson('/api/assets/account?q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_ACCOUNT_NAME);

        // Only items of the specific user are returned even if criteria matches items of other user(s)
        $response->assertJsonPath('0.user_id', $user->id);

        // Only active items are returned by default
        // We create a new item for primary user with similar name, but not active, other currency
        AccountEntity::factory()
            ->for($user)
            ->for(Account::factory()->withUser($user)->create(['currency_id' => $currencies->last()->id]), 'config')
            ->create([
                'active' => false,
                'name' => self::BASE_ACCOUNT_NAME . " - inactive",
            ]);

        $response = $this->actingAs($user)->getJson('/api/assets/account?q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_ACCOUNT_NAME);

        // Inactive items can be requested
        $response = $this->actingAs($user)
            ->getJson('/api/assets/account?withInactive=1&q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);

        // Currency can be specified
        $response = $this->actingAs($user)
            ->getJson('/api/assets/account?withInactive=1&currency_id=' . $currencies->first()->id . '&q=' . self::BASE_ACCOUNT_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_ACCOUNT_NAME);
        $response->assertJsonPath('0.config.currency_id', $currencies->first()->id);

        // Default limit is applied for number of results
        for ($i = 1; $i <= 20; $i++) {
            AccountEntity::factory()
                ->for($user)
                ->for(Account::factory()->withUser($user), 'config')
                ->create([
                    'active' => true,
                    'name' => self::BASE_ACCOUNT_NAME . " - clone - " . $i,
                ]);
        }

        $response = $this->actingAs($user)->getJson('/api/assets/account?q=clone');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(10);

        // Custom limit is applied for number of results
        $response = $this->actingAs($user)->getJson('/api/assets/account?q=clone&limit=15');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(15);

        // All items can be requested to be returned
        $response = $this->actingAs($user)->getJson('/api/assets/account?q=clone&limit=0');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(20);
    }
}

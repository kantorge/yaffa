<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class PayeeApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_PAYEE_NAME = 'Same payee name';

    protected function setUp(): void
    {
        parent::setUp();

        // Load the transaction types into the config, used by some of the tests
        config()->set('transaction_types', TransactionType::all()->keyBy('name')->toArray());
    }

    public function test_payee_list_with_query_applies_all_provided_filters(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // One payee with base name
        AccountEntity::factory()
            ->for($user)
            ->for(\App\Models\Payee::factory()->withUser($user)->create(), 'config')
            ->create([
                'active' => true,
                'name' => self::BASE_PAYEE_NAME
            ]);

        // Other user with dummy data and same payee name
        /** @var User $user2 */
        $user2 = User::factory()->create();
        $this->createForUser($user2, AccountGroup::class);
        $this->createForUser($user2, Currency::class);

        AccountEntity::factory()
            ->for($user2)
            ->for(\App\Models\Payee::factory()->withUser($user2), 'config')
            ->create([
                'active' => true,
                'name' => self::BASE_PAYEE_NAME
            ]);

        // Query string is applied
        $response = $this->actingAs($user)->getJson('/api/assets/payee?q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_PAYEE_NAME);

        // Only items of the specific user are returned even if criteria matches items of other user(s)
        $response->assertJsonPath('0.user_id', $user->id);

        // Only active items are returned by default
        // We create a new item for primary user with similar name, but not active, other currency
        AccountEntity::factory()
            ->for($user)
            ->for(\App\Models\Payee::factory()->withUser($user)->create(), 'config')
            ->create([
                'active' => false,
                'name' => self::BASE_PAYEE_NAME . " - inactive",
            ]);

        $response = $this->actingAs($user)->getJson('/api/assets/payee?q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_PAYEE_NAME);

        // Inactive items can be requested
        $response = $this->actingAs($user)
            ->getJson('/api/assets/payee?withInactive=1&q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);

        // Default limit is applied for number of results
        for ($i = 1; $i <= 20; $i++) {
            AccountEntity::factory()
                ->for($user)
                ->for(\App\Models\Payee::factory()->withUser($user), 'config')
                ->create([
                    'active' => true,
                    'name' => self::BASE_PAYEE_NAME . " - clone - " . $i,
                ]);
        }

        $response = $this->actingAs($user)->getJson('/api/assets/payee?q=clone');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(10);
    }

    public function test_transaction_type_must_be_valid_if_provided_for_account_related_call(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // When account_entity_id is provided, controller validates transaction_type
        $response = $this->actingAs($user)->getJson('/api/assets/payee?account_entity_id=1&transaction_type=invalid_type');
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('message', 'The transaction_type parameter is required and must be valid.');

        // A valid transaction type should pass (use 'withdrawal' as in account tests)
        $response = $this->actingAs($user)->getJson('/api/assets/payee?account_entity_id=1&transaction_type=withdrawal');
        $response->assertStatus(Response::HTTP_OK);
    }
}

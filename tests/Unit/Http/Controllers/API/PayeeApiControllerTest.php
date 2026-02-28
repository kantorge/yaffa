<?php

namespace Tests\Unit\Http\Controllers\API;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class PayeeApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_PAYEE_NAME = 'Same payee name';

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
        $response = $this->actingAs($user)->getJson('/api/v1/payees?q=' . self::BASE_PAYEE_NAME);
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

        $response = $this->actingAs($user)->getJson('/api/v1/payees?q=' . self::BASE_PAYEE_NAME);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.name', self::BASE_PAYEE_NAME);

        // Inactive items can be requested
        $response = $this->actingAs($user)
            ->getJson('/api/v1/payees?withInactive=1&q=' . self::BASE_PAYEE_NAME);
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

        $response = $this->actingAs($user)->getJson('/api/v1/payees?q=clone');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(10);
    }

    public function test_transaction_type_must_be_valid_if_provided_for_account_related_call(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        // When account_entity_id is provided, controller validates transaction_type
        $response = $this->actingAs($user)->getJson('/api/v1/payees?account_entity_id=1&transaction_type=invalid_type');
        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonPath('message', 'The transaction_type parameter is required and must be valid.');

        // A valid transaction type should pass (use 'withdrawal' as in account tests)
        $response = $this->actingAs($user)->getJson('/api/v1/payees?account_entity_id=1&transaction_type=withdrawal');
        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_get_payee_default_category_suggestion_returns_dominant_category_for_payee(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $payee = AccountEntity::factory()
            ->for($user)
            ->for(\App\Models\Payee::factory()->withUser($user)->create(['category_id' => null]), 'config')
            ->create([
                'active' => true,
                'config_type' => 'payee',
                'name' => 'Dominant Payee',
            ]);

        $account = AccountEntity::factory()
            ->for($user)
            ->for(\App\Models\Account::factory()->withUser($user), 'config')
            ->create([
                'active' => true,
                'config_type' => 'account',
            ]);

        $dominantCategory = Category::factory()->for($user)->create(['active' => true]);
        $otherCategory = Category::factory()->for($user)->create(['active' => true]);

        foreach (range(1, 6) as $index) {
            $this->createTransactionWithCategory($user, $account->id, $payee->id, $dominantCategory->id, now()->subDays($index));
        }

        foreach (range(1, 2) as $index) {
            $this->createTransactionWithCategory($user, $account->id, $payee->id, $otherCategory->id, now()->subDays(10 + $index));
        }

        $response = $this->actingAs($user)
            ->getJson('/api/v1/payees/category-suggestions/default');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('payee_id', $payee->id);
        $response->assertJsonPath('max_category_id', $dominantCategory->id);
        $response->assertJsonPath('sum', 8);
        $response->assertJsonPath('max', 6);
    }

    private function createTransactionWithCategory(
        User $user,
        int $accountId,
        int $payeeId,
        int $categoryId,
        Carbon $date
    ): void {
        $detail = TransactionDetailStandard::query()->create([
            'account_from_id' => $accountId,
            'account_to_id' => $payeeId,
            'amount_from' => 10,
            'amount_to' => 10,
        ]);

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'date' => $date,
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'comment' => null,
            'config_type' => 'standard',
            'config_id' => $detail->id,
        ]);

        TransactionItem::query()->create([
            'transaction_id' => $transaction->id,
            'category_id' => $categoryId,
            'amount' => 10,
            'comment' => 'Test item',
        ]);
    }
}

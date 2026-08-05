<?php

namespace Tests\Feature\API;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Category;
use App\Models\CategoryLearning;
use App\Models\Currency;
use App\Events\TransactionUpdated;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Services\CategoryLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Test that unauthenticated requests are rejected
     */
    public function test_unauthenticated_request_is_rejected(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.transactions.show', $transaction));
        $this->assertUserNotAuthorized($response);


        $response = $this->patchJson(route('api.v1.transactions.reconcile', $transaction), ['reconciled' => true]);
        $this->assertUserNotAuthorized($response);
    }

    /**
     * Test getting a single transaction via API
     */
    public function test_can_get_transaction_details(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.transactions.show', $transaction));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'transaction' => [
                'id',
                'date',
                'transaction_type',
                'config_type',
                'schedule',
                'budget',
                'reconciled',
            ],
        ]);
        $response->assertJson([
            'transaction' => [
                'id' => $transaction->id,
            ],
        ]);
    }

    /**
     * Test that user cannot access other user's transaction via API
     */
    public function test_cannot_access_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        // Attempting to access should fail due to route model binding with user scope
        $response = $this->getJson(route('api.v1.transactions.show', $transaction));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test reconciling a transaction
     */
    public function test_can_reconcile_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'reconciled' => false,
            ]);

        $response = $this->patchJson(route('api.v1.transactions.reconcile', $transaction), ['reconciled' => true]);

        $response->assertStatus(Response::HTTP_OK);

        // Verify transaction was reconciled
        $this->assertTrue($transaction->fresh()->reconciled);
    }

    /**
     * Test unreconciling a transaction
     */
    public function test_can_unreconcile_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'reconciled' => true,
            ]);

        $response = $this->patchJson(route('api.v1.transactions.reconcile', $transaction), ['reconciled' => false]);

        $response->assertStatus(Response::HTTP_OK);

        // Verify transaction was unreconciled
        $this->assertFalse($transaction->fresh()->reconciled);
    }

    public function test_cannot_update_other_users_standard_transaction(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->patchJson(
            route('api.v1.transactions.update-standard', $transaction),
            $this->standardTransactionPayload($transaction)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors([
            'config.account_from_id',
            'config.account_to_id',
        ]);

        $this->assertSame(
            $transaction->comment,
            $transaction->fresh()->comment
        );
    }

    public function test_cannot_update_other_users_investment_transaction(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()
            ->buy($this->user)
            ->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($otherUser);

        $response = $this->patchJson(
            route('api.v1.transactions.update-investment', $transaction),
            $this->investmentTransactionPayload($transaction)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors([
            'config.account_id',
            'config.investment_id',
        ]);

        $this->assertSame(
            $transaction->comment,
            $transaction->fresh()->comment
        );
    }

    /**
     * Test that user cannot reconcile other user's transaction
     */
    public function test_cannot_reconcile_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'reconciled' => false,
            ]);

        $response = $this->patchJson(route('api.v1.transactions.reconcile', $transaction), ['reconciled' => true]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        // Verify transaction was not reconciled
        $this->assertFalse($transaction->fresh()->reconciled);
    }

    /**
     * Test deleting a transaction owned by the authenticated user
     */
    public function test_can_delete_own_transaction(): void
    {
        Sanctum::actingAs($this->user);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson(route('api.v1.transactions.destroy', $transaction));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([
            'transaction' => [
                'id' => $transaction->id,
            ],
        ]);

        $this->assertDatabaseMissing('transactions', [
            'id' => $transaction->id,
        ]);
    }

    /**
     * Test that user cannot delete other user's transaction
     */
    public function test_cannot_delete_other_users_transaction(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson(route('api.v1.transactions.destroy', $transaction));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_cannot_skip_other_users_scheduled_transaction(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);
        $originalNextDate = $transaction->transactionSchedule->next_date;

        Sanctum::actingAs($otherUser);

        $response = $this->patchJson(route('api.v1.transactions.skip', $transaction));

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        $this->assertEquals(
            optional($originalNextDate)?->toDateString(),
            optional($transaction->fresh()->transactionSchedule->next_date)?->toDateString()
        );
    }

    public function test_store_standard_rejects_other_users_source_transaction_id(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($otherUser)
            ->create(['user_id' => $otherUser->id]);

        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();
        $category = Category::factory()->for($this->user)->create(['active' => true]);

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);
        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        $response = $this->postJson(route('api.v1.transactions.store-standard'), [
            'action' => 'enter',
            'id' => $sourceTransaction->id,
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 10,
                'amount_to' => 10,
            ],
            'items' => [
                [
                    'amount' => 10,
                    'category_id' => $category->id,
                    'tags' => [],
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['id']);
    }

    /**
     * Build a standard "enter" payload for $sourceTransaction, with fresh account/payee/category
     * entities owned by $this->user, merging any $overrides on top.
     */
    private function buildEnterStandardPayload(Transaction $sourceTransaction, array $overrides = []): array
    {
        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();
        $category = Category::factory()->for($this->user)->create(['active' => true]);

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);
        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        return array_merge([
            'action' => 'enter',
            'id' => $sourceTransaction->id,
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 10,
                'amount_to' => 10,
            ],
            'items' => [
                [
                    'amount' => 10,
                    'category_id' => $category->id,
                    'tags' => [],
                ],
            ],
        ], $overrides);
    }

    public function test_store_standard_enter_without_catch_up_skips_only_one_instance(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $sourceTransaction->transactionSchedule->update([
            'start_date' => now()->subDays(30),
            'next_date' => now()->subDays(30),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
            'automatic_recording' => false,
        ]);

        $expectedNextDate = $sourceTransaction->transactionSchedule->getNextInstance();

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildEnterStandardPayload($sourceTransaction, [
                'date' => now()->subDays(30)->format('Y-m-d'),
                'catch_up_schedule' => false,
            ])
        );

        $response->assertStatus(Response::HTTP_OK);

        $sourceTransaction->transactionSchedule->refresh();
        $this->assertNotNull($sourceTransaction->transactionSchedule->next_date);
        $this->assertTrue($sourceTransaction->transactionSchedule->next_date->eq($expectedNextDate));
    }

    public function test_store_standard_enter_with_catch_up_advances_to_today_or_later(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $sourceTransaction->transactionSchedule->update([
            'start_date' => now()->subDays(30),
            'next_date' => now()->subDays(30),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
            'automatic_recording' => false,
        ]);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildEnterStandardPayload($sourceTransaction, [
                'date' => now()->subDays(30)->format('Y-m-d'),
                'catch_up_schedule' => true,
            ])
        );

        $response->assertStatus(Response::HTTP_OK);

        $sourceTransaction->transactionSchedule->refresh();
        $this->assertNotNull($sourceTransaction->transactionSchedule->next_date);
        $this->assertTrue($sourceTransaction->transactionSchedule->next_date->gte(now()->startOfDay()));
    }

    public function test_store_standard_enter_with_catch_up_deactivates_exhausted_schedule(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $sourceTransaction->transactionSchedule->update([
            'start_date' => now()->subDays(30),
            'next_date' => now()->subDays(30),
            'end_date' => now()->subDays(10),
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
            'automatic_recording' => false,
        ]);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildEnterStandardPayload($sourceTransaction, [
                'date' => now()->subDays(20)->format('Y-m-d'),
                'catch_up_schedule' => true,
            ])
        );

        $response->assertStatus(Response::HTTP_OK);

        $sourceTransaction->transactionSchedule->refresh();
        $this->assertNull($sourceTransaction->transactionSchedule->next_date);
        $this->assertFalse($sourceTransaction->transactionSchedule->active);
    }

    public function test_store_standard_enter_dispatches_transaction_updated_event_regardless_of_catch_up(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $sourceTransaction->transactionSchedule->update([
            'start_date' => now()->subDays(30),
            'next_date' => now()->subDays(30),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
            'automatic_recording' => false,
        ]);

        $payload = $this->buildEnterStandardPayload($sourceTransaction, [
            'date' => now()->subDays(30)->format('Y-m-d'),
            'catch_up_schedule' => true,
        ]);

        Event::fake([TransactionUpdated::class]);

        $response = $this->postJson(route('api.v1.transactions.store-standard'), $payload);

        $response->assertStatus(Response::HTTP_OK);

        Event::assertDispatched(
            TransactionUpdated::class,
            fn (TransactionUpdated $event) => $event->transaction->id === $sourceTransaction->id
                && array_key_exists('schedule_config', $event->changedAttributes)
        );
    }

    public function test_store_investment_enter_with_catch_up_advances_schedule(): void
    {
        Sanctum::actingAs($this->user);

        // Pre-create a matching account/investment/currency so buy_schedule()'s internal
        // TransactionDetailInvestmentFactory::withUser() finds them and reuses them at
        // random, instead of creating its own (which can collide on the user-scoped
        // unique currency name - see CalculateAccountMonthlySummaryTest for precedent).
        InvestmentGroup::factory()->for($this->user)->create();
        $currency = Currency::factory()->for($this->user)->create();
        $accountEntity = AccountEntity::factory()
            ->for($this->user)
            ->for(Account::factory()->withUser($this->user)->create(['currency_id' => $currency->id]), 'config')
            ->create();
        $investment = Investment::factory()
            ->for($this->user)
            ->create(['currency_id' => $currency->id]);

        $sourceTransaction = Transaction::factory()
            ->buy_schedule($this->user, [
                'account_id' => $accountEntity->id,
                'investment_id' => $investment->id,
            ])
            ->create(['user_id' => $this->user->id]);

        $sourceTransaction->transactionSchedule->update([
            'start_date' => now()->subDays(30),
            'next_date' => now()->subDays(30),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
            'automatic_recording' => false,
        ]);

        $response = $this->postJson(route('api.v1.transactions.store-investment'), [
            'action' => 'enter',
            'id' => $sourceTransaction->id,
            'transaction_type' => 'buy',
            'config_type' => 'investment',
            'date' => now()->subDays(30)->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'catch_up_schedule' => true,
            'config' => [
                'account_id' => $accountEntity->id,
                'investment_id' => $investment->id,
                'price' => 10,
                'quantity' => 1,
                'commission' => 0,
                'tax' => 0,
            ],
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $sourceTransaction->transactionSchedule->refresh();
        $this->assertNotNull($sourceTransaction->transactionSchedule->next_date);
        $this->assertTrue($sourceTransaction->transactionSchedule->next_date->gte(now()->startOfDay()));
    }

    public function test_store_standard_rejects_other_users_category_id(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $foreignCategory = Category::factory()->create([
            'active' => true,
        ]);
        $foreignCategory->user_id = $otherUser->id;
        $foreignCategory->save();

        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);
        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        $response = $this->postJson(route('api.v1.transactions.store-standard'), [
            'action' => 'create',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 10,
                'amount_to' => 10,
            ],
            'items' => [
                [
                    'amount' => 10,
                    'category_id' => $foreignCategory->id,
                    'tags' => [],
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['items.0.category_id']);
    }

    public function test_store_standard_rejects_other_users_account_entity_id(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $foreignAccount = Account::factory()->withUser($otherUser)->create();
        $foreignAccountEntity = AccountEntity::factory()->create([
            'user_id' => $otherUser->id,
            'config_type' => 'account',
            'config_id' => $foreignAccount->id,
            'active' => true,
        ]);

        $ownPayee = Payee::factory()->withUser($this->user)->create();
        $ownCategory = Category::factory()->for($this->user)->create(['active' => true]);
        $ownPayeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $ownPayee->id,
            'active' => true,
        ]);

        $response = $this->postJson(route('api.v1.transactions.store-standard'), [
            'action' => 'create',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $foreignAccountEntity->id,
                'account_to_id' => $ownPayeeEntity->id,
                'amount_from' => 10,
                'amount_to' => 10,
            ],
            'items' => [
                [
                    'amount' => 10,
                    'category_id' => $ownCategory->id,
                    'tags' => [],
                ],
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['config.account_from_id']);
    }

    public function test_store_investment_rejects_other_users_investment_id(): void
    {
        Sanctum::actingAs($this->user);

        $otherUser = User::factory()->create();
        $currency = Currency::factory()->for($otherUser)->create();
        $investmentGroup = InvestmentGroup::factory()->for($otherUser)->create();
        $foreignInvestment = Investment::factory()->create([
            'user_id' => $otherUser->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);
        $foreignInvestment->user_id = $otherUser->id;
        $foreignInvestment->save();

        $account = Account::factory()->withUser($this->user)->create();
        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);

        $response = $this->postJson(route('api.v1.transactions.store-investment'), [
            'action' => 'create',
            'transaction_type' => 'buy',
            'config_type' => 'investment',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_id' => $accountEntity->id,
                'investment_id' => $foreignInvestment->id,
                'price' => 10,
                'quantity' => 1,
                'commission' => 0,
                'tax' => 0,
            ],
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['config.investment_id']);
    }

    /**
     * Test getting scheduled items returns valid response
     */
    public function test_can_get_scheduled_items(): void
    {
        Sanctum::actingAs($this->user);

        // Create a scheduled transaction
        Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->getJson(route('api.v1.transactions.scheduled-items'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'transactions' => [
                '*' => [
                    'id',
                    'date',
                    'transaction_type',
                ],
            ],
        ]);
    }

    /**
     * Test getting scheduled items with category filter
     */
    public function test_get_scheduled_items_returns_empty_when_category_required_but_not_provided(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson(route('api.v1.transactions.scheduled-items') . '?category_required=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([]);
    }

    public function test_store_standard_finalization_updates_category_learning(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);

        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        $categoryExact = Category::factory()->for($this->user)->create(['active' => true]);
        $categoryAi = Category::factory()->for($this->user)->create(['active' => true]);

        $existingLearning = new CategoryLearning();
        $existingLearning->forceFill([
            'user_id' => $this->user->id,
            'item_description' => 'coffee',
            'category_id' => $categoryExact->id,
            'usage_count' => 2,
        ]);
        $existingLearning->save();

        $aiDocument = AiDocument::factory()->for($this->user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'transaction_items' => [
                    [
                        'amount' => 5,
                        'description' => 'Coffee',
                        'recommended_category_id' => $categoryExact->id,
                        'match_type' => 'exact',
                        'confidence_score' => 1.0,
                    ],
                    [
                        'amount' => 3,
                        'description' => 'Snack',
                        'recommended_category_id' => $categoryAi->id,
                        'match_type' => 'ai',
                        'confidence_score' => 0.8,
                    ],
                ],
            ],
        ]);

        $payload = [
            'action' => 'finalize',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 8,
                'amount_to' => 8,
            ],
            'items' => [
                [
                    'amount' => 5,
                    'category_id' => $categoryExact->id,
                    'description' => 'Coffee',
                    'tags' => [],
                    'learnRecommendation' => true,
                ],
                [
                    'amount' => 3,
                    'category_id' => $categoryAi->id,
                    'description' => 'Snack',
                    'tags' => [],
                    'learnRecommendation' => true,
                ],
            ],
            'ai_document_id' => $aiDocument->id,
        ];

        $response = $this->postJson(route('api.v1.transactions.store-standard'), $payload);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonPath('category_learning_summary.created', 1)
            ->assertJsonPath('category_learning_summary.incremented', 1);

        $existingLearning->refresh();
        $this->assertSame(3, $existingLearning->usage_count);

        $learningService = new CategoryLearningService($this->user);
        $this->assertDatabaseHas('category_learning', [
            'user_id' => $this->user->id,
            'item_description' => $learningService->normalize('Snack'),
            'category_id' => $categoryAi->id,
            'usage_count' => 1,
        ]);
    }

    public function test_store_standard_finalization_resets_usage_when_category_changes(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);

        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        $originalCategory = Category::factory()->for($this->user)->create(['active' => true]);
        $newCategory = Category::factory()->for($this->user)->create(['active' => true]);

        $existingLearning = new CategoryLearning();
        $existingLearning->forceFill([
            'user_id' => $this->user->id,
            'item_description' => 'lunch',
            'category_id' => $originalCategory->id,
            'usage_count' => 5,
        ]);
        $existingLearning->save();

        $aiDocument = AiDocument::factory()->for($this->user)->create([
            'status' => 'ready_for_review',
        ]);

        $payload = [
            'action' => 'finalize',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 10,
                'amount_to' => 10,
            ],
            'items' => [
                [
                    'amount' => 10,
                    'category_id' => $newCategory->id,
                    'description' => 'Lunch',
                    'tags' => [],
                    'learnRecommendation' => true,
                ],
            ],
            'ai_document_id' => $aiDocument->id,
        ];

        $response = $this->postJson(route('api.v1.transactions.store-standard'), $payload);

        $response->assertStatus(Response::HTTP_OK);

        $learningService = new CategoryLearningService($this->user);
        $this->assertDatabaseHas('category_learning', [
            'user_id' => $this->user->id,
            'item_description' => $learningService->normalize('Lunch'),
            'category_id' => $newCategory->id,
            'usage_count' => 1,
        ]);
    }

    public function test_store_standard_finalization_respects_dont_learn_flag(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);

        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        $category = Category::factory()->for($this->user)->create(['active' => true]);

        $aiDocument = AiDocument::factory()->for($this->user)->create([
            'status' => 'ready_for_review',
            'processed_transaction_data' => [
                'transaction_items' => [
                    [
                        'amount' => 4,
                        'description' => 'Tea',
                        'recommended_category_id' => $category->id,
                        'match_type' => 'ai',
                        'confidence_score' => 0.7,
                    ],
                ],
            ],
        ]);

        $payload = [
            'action' => 'finalize',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 4,
                'amount_to' => 4,
            ],
            'items' => [
                [
                    'amount' => 4,
                    'category_id' => $category->id,
                    'description' => 'Tea',
                    'tags' => [],
                    'learnRecommendation' => false,
                ],
            ],
            'ai_document_id' => $aiDocument->id,
        ];

        $response = $this->postJson(route('api.v1.transactions.store-standard'), $payload);

        $response->assertStatus(Response::HTTP_OK);

        $learningService = new CategoryLearningService($this->user);
        $this->assertDatabaseMissing('category_learning', [
            'user_id' => $this->user->id,
            'item_description' => $learningService->normalize('Tea'),
            'category_id' => $category->id,
        ]);
    }

    public function test_store_investment_finalization_does_not_require_items_array(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->withUser($this->user)->create();
        $currency = $this->user->currencies()->first() ?: Currency::factory()->for($this->user)->create();
        $investmentGroup = $this->user->investmentGroups()->first() ?: InvestmentGroup::factory()->for($this->user)->create();

        $investment = Investment::factory()->create([
            'user_id' => $this->user->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);

        $aiDocument = AiDocument::factory()->for($this->user)->create([
            'status' => 'ready_for_review',
        ]);

        $payload = [
            'action' => 'finalize',
            'transaction_type' => 'buy',
            'config_type' => 'investment',
            'date' => now()->format('Y-m-d'),
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config' => [
                'account_id' => $accountEntity->id,
                'investment_id' => $investment->id,
                'price' => 12.5,
                'quantity' => 2,
                'commission' => 0,
                'tax' => 0,
            ],
            'ai_document_id' => $aiDocument->id,
        ];

        $response = $this->postJson(route('api.v1.transactions.store-investment'), $payload);

        $response->assertStatus(Response::HTTP_OK);

        $transactionId = $response->json('transaction.id');

        $this->assertNotNull($transactionId);
        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'ai_document_id' => $aiDocument->id,
            'config_type' => 'investment',
        ]);

        $aiDocument->refresh();
        $this->assertSame('finalized', $aiDocument->status);
    }

    /**
     * Build a standard "create" payload for a scheduled withdrawal, with fresh
     * account/payee/category entities owned by $this->user, merging any
     * $overrides on top.
     */
    private function buildCreateScheduledStandardPayload(array $overrides = []): array
    {
        $account = Account::factory()->withUser($this->user)->create();
        $payee = Payee::factory()->withUser($this->user)->create();
        $category = Category::factory()->for($this->user)->create(['active' => true]);

        $accountEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'account',
            'config_id' => $account->id,
            'active' => true,
        ]);
        $payeeEntity = AccountEntity::factory()->create([
            'user_id' => $this->user->id,
            'config_type' => 'payee',
            'config_id' => $payee->id,
            'active' => true,
        ]);

        return array_merge([
            'action' => 'create',
            'transaction_type' => 'withdrawal',
            'config_type' => 'standard',
            'reconciled' => false,
            'schedule' => true,
            'budget' => false,
            'config' => [
                'account_from_id' => $accountEntity->id,
                'account_to_id' => $payeeEntity->id,
                'amount_from' => 10,
                'amount_to' => 10,
            ],
            'items' => [
                [
                    'amount' => 10,
                    'category_id' => $category->id,
                    'tags' => [],
                ],
            ],
            'schedule_config' => [
                'start_date' => now()->format('Y-m-d'),
                'next_date' => now()->format('Y-m-d'),
                'frequency' => 'MONTHLY',
                'interval' => 1,
            ],
        ], $overrides);
    }

    public function test_store_standard_schedule_rejects_by_day_with_incompatible_frequency(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => now()->format('Y-m-d'),
                    'next_date' => now()->format('Y-m-d'),
                    'frequency' => 'WEEKLY',
                    'interval' => 1,
                    'by_day' => '1MO',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['schedule_config.by_day']);
    }

    public function test_store_standard_schedule_rejects_yearly_by_day_without_by_month(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => now()->format('Y-m-d'),
                    'next_date' => now()->format('Y-m-d'),
                    'frequency' => 'YEARLY',
                    'interval' => 1,
                    'by_day' => '-1FR',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['schedule_config.by_month']);
    }

    public function test_store_standard_schedule_accepts_valid_monthly_nth_weekday_rule(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => now()->format('Y-m-d'),
                    // next_date intentionally omitted: "today" is essentially
                    // never the last Friday of the month, and next_date must
                    // now be a genuine occurrence of the configured rule.
                    'frequency' => 'MONTHLY',
                    'interval' => 1,
                    'by_day' => '-1FR',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_OK);

        $transactionId = $response->json('transaction.id');
        $this->assertNotNull($transactionId);
        $this->assertDatabaseHas('transaction_schedules', [
            'transaction_id' => $transactionId,
            'frequency' => 'MONTHLY',
            'by_day' => '-1FR',
            'by_month' => null,
        ]);
    }

    public function test_store_standard_schedule_accepts_valid_yearly_weekday_and_month_rule(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => now()->format('Y-m-d'),
                    // next_date intentionally omitted, see the monthly test above.
                    'frequency' => 'YEARLY',
                    'interval' => 1,
                    'by_day' => '-1FR',
                    'by_month' => 11,
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_OK);

        $transactionId = $response->json('transaction.id');
        $this->assertNotNull($transactionId);
        $this->assertDatabaseHas('transaction_schedules', [
            'transaction_id' => $transactionId,
            'frequency' => 'YEARLY',
            'by_day' => '-1FR',
            'by_month' => 11,
        ]);
    }

    public function test_store_standard_schedule_rejects_next_date_that_is_not_a_rule_occurrence(): void
    {
        Sanctum::actingAs($this->user);

        // 2026-01-01 is a Thursday, not the first Wednesday of January 2026 -
        // next_date must be a genuine occurrence of the configured rule, not
        // just any date on/after start_date.
        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => '2026-01-01',
                    'next_date' => '2026-01-01',
                    'frequency' => 'MONTHLY',
                    'interval' => 1,
                    'by_day' => '1WE',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['schedule_config.next_date']);
    }

    public function test_store_standard_schedule_accepts_next_date_that_is_a_rule_occurrence(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => '2026-01-01',
                    'next_date' => '2026-01-07',
                    'frequency' => 'MONTHLY',
                    'interval' => 1,
                    'by_day' => '1WE',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_OK);
    }

    public function test_store_standard_schedule_rejects_next_date_misaligned_with_plain_interval(): void
    {
        Sanctum::actingAs($this->user);

        // No by_day involved: the same next_date-must-be-a-real-occurrence check
        // applies to plain frequency/interval schedules, since next_date is
        // trusted verbatim wherever a transaction actually gets recorded.
        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildCreateScheduledStandardPayload([
                'schedule_config' => [
                    'start_date' => '2026-01-01',
                    'next_date' => '2026-01-08',
                    'frequency' => 'WEEKLY',
                    'interval' => 2,
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['schedule_config.next_date']);
    }

    /**
     * Build a "replace" payload for $sourceTransaction: a full create-standard
     * payload for the replacement transaction, plus the "id" of the schedule
     * being replaced and its original_schedule_config. Merges any $overrides
     * on top, so overriding 'original_schedule_config' replaces that whole
     * sub-array.
     */
    private function buildReplaceStandardPayload(Transaction $sourceTransaction, array $overrides = []): array
    {
        return $this->buildCreateScheduledStandardPayload(array_merge([
            'action' => 'replace',
            'id' => $sourceTransaction->id,
            'original_schedule_config' => [
                'start_date' => now()->subMonth()->format('Y-m-d'),
                'frequency' => 'MONTHLY',
                'interval' => 1,
            ],
        ], $overrides));
    }

    public function test_replace_rejects_original_schedule_by_day_with_incompatible_frequency(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildReplaceStandardPayload($sourceTransaction, [
                'original_schedule_config' => [
                    'start_date' => now()->subMonth()->format('Y-m-d'),
                    'frequency' => 'WEEKLY',
                    'interval' => 1,
                    'by_day' => '1WE',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['original_schedule_config.by_day']);
    }

    public function test_replace_rejects_yearly_original_schedule_missing_by_month(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildReplaceStandardPayload($sourceTransaction, [
                'original_schedule_config' => [
                    'start_date' => now()->subMonth()->format('Y-m-d'),
                    'frequency' => 'YEARLY',
                    'interval' => 1,
                    'by_day' => '-1FR',
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['original_schedule_config.by_month']);
    }

    /**
     * Exercises the deliberate design decision that "replace" allows a full
     * rewrite of the original schedule's recurrence rule (not just its
     * end_date), consistent with the already-existing ability to rewrite
     * frequency/interval via "edit" or "replace".
     */
    public function test_replace_rewrites_original_schedule_pattern(): void
    {
        Sanctum::actingAs($this->user);

        $sourceTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $sourceTransaction->transactionSchedule->update([
            'frequency' => 'DAILY',
            'interval' => 1,
            'by_day' => null,
            'by_month' => null,
        ]);

        $response = $this->postJson(
            route('api.v1.transactions.store-standard'),
            $this->buildReplaceStandardPayload($sourceTransaction, [
                'original_schedule_config' => [
                    'start_date' => now()->subMonth()->format('Y-m-d'),
                    'frequency' => 'YEARLY',
                    'interval' => 1,
                    'by_day' => '-1FR',
                    'by_month' => 11,
                ],
            ])
        );

        $response->assertStatus(Response::HTTP_OK);

        $this->assertDatabaseHas('transaction_schedules', [
            'id' => $sourceTransaction->transactionSchedule->id,
            'frequency' => 'YEARLY',
            'by_day' => '-1FR',
            'by_month' => 11,
        ]);
    }

    private function standardTransactionPayload(Transaction $transaction): array
    {
        $transaction->loadMissing(['config', 'transactionItems']);

        return [
            'action' => 'edit',
            'transaction_type' => $transaction->transaction_type->value,
            'config_type' => $transaction->config_type,
            'date' => $transaction->date?->format('Y-m-d'),
            'comment' => $transaction->comment,
            'reconciled' => $transaction->reconciled,
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'config' => [
                'account_from_id' => $transaction->config->account_from_id,
                'account_to_id' => $transaction->config->account_to_id,
                'amount_from' => $transaction->config->amount_from,
                'amount_to' => $transaction->config->amount_to,
            ],
            'items' => $transaction->transactionItems->map(fn ($item) => [
                'amount' => $item->amount,
                'category_id' => $item->category_id,
                'comment' => $item->comment,
                'tags' => [],
            ])->values()->all(),
        ];
    }

    private function investmentTransactionPayload(Transaction $transaction): array
    {
        $transaction->loadMissing(['config']);

        return [
            'action' => 'edit',
            'transaction_type' => $transaction->transaction_type->value,
            'config_type' => $transaction->config_type,
            'date' => $transaction->date?->format('Y-m-d'),
            'comment' => $transaction->comment,
            'reconciled' => $transaction->reconciled,
            'schedule' => $transaction->schedule,
            'budget' => $transaction->budget,
            'config' => [
                'account_id' => $transaction->config->account_id,
                'investment_id' => $transaction->config->investment_id,
                'price' => $transaction->config->price,
                'quantity' => $transaction->config->quantity,
                'commission' => $transaction->config->commission,
                'tax' => $transaction->config->tax,
            ],
        ];
    }
}

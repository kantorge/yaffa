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
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\Payee;
use App\Services\CategoryLearningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
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

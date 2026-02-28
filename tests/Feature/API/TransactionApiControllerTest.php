<?php

namespace Tests\Feature\API;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AiDocument;
use App\Models\Category;
use App\Models\CategoryLearning;
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

        $response = $this->getJson("/api/transaction/{$transaction->id}");
        $this->assertUserNotAuthorized($response);


        $response = $this->putJson("/api/transaction/{$transaction->id}/reconciled/1");
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

        $response = $this->getJson("/api/transaction/{$transaction->id}");

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
        $response = $this->getJson("/api/transaction/{$transaction->id}");

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

        $response = $this->putJson("/api/transaction/{$transaction->id}/reconciled/1");

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

        $response = $this->putJson("/api/transaction/{$transaction->id}/reconciled/0");

        $response->assertStatus(Response::HTTP_OK);

        // Verify transaction was unreconciled
        $this->assertFalse($transaction->fresh()->reconciled);
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

        $response = $this->putJson("/api/transaction/{$transaction->id}/reconciled/1");

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        // Verify transaction was not reconciled
        $this->assertFalse($transaction->fresh()->reconciled);
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

        $response = $this->getJson('/api/transactions/get_scheduled_items/schedule');

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

        $response = $this->getJson('/api/transactions/get_scheduled_items/schedule?category_required=1');

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

        $response = $this->postJson('/api/transactions/standard', $payload);

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

        $response = $this->postJson('/api/transactions/standard', $payload);

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

        $response = $this->postJson('/api/transactions/standard', $payload);

        $response->assertStatus(Response::HTTP_OK);

        $learningService = new CategoryLearningService($this->user);
        $this->assertDatabaseMissing('category_learning', [
            'user_id' => $this->user->id,
            'item_description' => $learningService->normalize('Tea'),
            'category_id' => $category->id,
        ]);
    }
}

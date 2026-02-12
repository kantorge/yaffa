<?php

namespace Tests\Feature\API;

use App\Models\Transaction;
use App\Models\User;
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

        $this->getJson("/api/transaction/{$transaction->id}")
            ->assertStatus(Response::HTTP_UNAUTHORIZED);

        $this->putJson("/api/transaction/{$transaction->id}/reconciled/1")
            ->assertStatus(Response::HTTP_UNAUTHORIZED);
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

        $response->assertStatus(Response::HTTP_NOT_FOUND);
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

        $response->assertStatus(Response::HTTP_NOT_FOUND);

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

        $response = $this->getJson('/api/transactions/get_scheduled_items/standard');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            '*' => [
                'id',
                'date',
                'transaction_type',
            ],
        ]);
    }

    /**
     * Test getting scheduled items with category filter
     */
    public function test_get_scheduled_items_returns_empty_when_category_required_but_not_provided(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/transactions/get_scheduled_items/standard?category_required=1');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson([]);
    }
}

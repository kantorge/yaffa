<?php

namespace Tests\Feature;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * FR-1: byScheduleType() is removed entirely in favor of a single isSchedule() scope
     * (where('schedule', true)); the schedule=false case is expressed as a plain inline
     * where('schedule', false) at call sites that need it. Neither depends on (or is affected
     * by) the removed transactions.budget column any more, unlike the old
     * 'budget'/'budget_only'/'both'/'any' branches this replaces - standalone budgets are now a
     * separate `Budget` entity entirely, not a Transaction variant.
     */
    public function test_is_schedule_scope_filters_transactions_with_schedule_true(): void
    {
        $transactions = $this->createTransactionsForScheduleTypeScope();
        $otherUser = User::factory()->create();

        Transaction::factory()
            ->deposit($otherUser)
            ->create([
                'user_id' => $otherUser->id,
                'schedule' => true,
            ]);

        $this->assertSame(
            [$transactions['schedule_only']->id],
            Transaction::query()
                ->where('user_id', $this->user->id)
                ->isSchedule()
                ->orderBy('id')
                ->pluck('id')
                ->all()
        );
    }

    public function test_inline_schedule_false_where_clause_filters_transactions_with_schedule_false(): void
    {
        $transactions = $this->createTransactionsForScheduleTypeScope();

        $this->assertSame(
            [$transactions['regular']->id],
            Transaction::query()
                ->where('user_id', $this->user->id)
                ->where('schedule', false)
                ->orderBy('id')
                ->pluck('id')
                ->all()
        );
    }

    private function createTransactionsForScheduleTypeScope(): array
    {
        return [
            'regular' => Transaction::factory()
                ->deposit($this->user)
                ->create([
                    'user_id' => $this->user->id,
                    'schedule' => false,
                ]),
            'schedule_only' => Transaction::factory()
                ->deposit($this->user)
                ->create([
                    'user_id' => $this->user->id,
                    'schedule' => true,
                ]),
        ];
    }

    /**
     * Test that guest users cannot access transaction pages
     */
    public function test_guest_cannot_access_transaction_pages(): void
    {
        $this->get(route('transaction.create', ['type' => 'standard']))
            ->assertRedirectToRoute('login');

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $this->get(route('transaction.open', ['transaction' => $transaction->id, 'action' => 'show']))
            ->assertRedirectToRoute('login');

        $this->get(route('transaction.open', ['transaction' => $transaction->id, 'action' => 'edit']))
            ->assertRedirectToRoute('login');
    }

    /**
     * Test that users cannot access other users' transactions
     */
    public function test_user_cannot_access_other_users_transactions(): void
    {
        $otherUser = User::factory()->create();

        $transaction = Transaction::factory()
            ->withdrawal($otherUser)
            ->create(['user_id' => $otherUser->id]);

        $this->actingAs($this->user)
            ->get(route('transaction.open', ['transaction' => $transaction->id, 'action' => 'show']))
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $this->actingAs($this->user)
            ->get(route('transaction.open', ['transaction' => $transaction->id, 'action' => 'edit']))
            ->assertStatus(Response::HTTP_FORBIDDEN);
    }

    /**
     * Test that user is redirected when trying to create transaction without accounts
     */
    public function test_user_redirected_when_no_accounts_exist(): void
    {
        // User has no accounts (RefreshDatabase ensures clean state)
        $response = $this->actingAs($this->user)
            ->get(route('transaction.create', ['type' => 'standard']));

        $response->assertRedirect(route('account-entity.create', ['type' => 'account']));
    }

    /**
     * Test that user is redirected when trying to create investment transaction without investments
     */
    public function test_user_redirected_when_no_investments_exist(): void
    {
        // Create an active account so we pass the first check
        AccountEntity::factory()
            ->for($this->user)
            ->for(
                \App\Models\Account::factory()->withUser($this->user),
                'config'
            )
            ->create(['active' => true]);

        // User has no investments (RefreshDatabase ensures clean state)
        $response = $this->actingAs($this->user)
            ->get(route('transaction.create', ['type' => 'investment']));

        $response->assertRedirect(route('investments.create'));
    }

    /**
     * Test that user can access standard transaction create form
     */
    public function test_user_can_access_standard_transaction_create_form(): void
    {
        // Create necessary active account
        AccountEntity::factory()
            ->for($this->user)
            ->for(
                \App\Models\Account::factory()->withUser($this->user),
                'config'
            )
            ->create(['active' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.create', ['type' => 'standard']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.form');
        $response->assertViewHas('type', 'standard');
        $response->assertViewHas('action', 'create');
    }

    /**
     * Test that user can access investment transaction create form
     */
    public function test_user_can_access_investment_transaction_create_form(): void
    {
        // Create necessary active account and active investment
        AccountEntity::factory()
            ->for($this->user)
            ->for(
                \App\Models\Account::factory()->withUser($this->user),
                'config'
            )
            ->create(['active' => true]);

        \App\Models\Investment::factory()
            ->for($this->user)
            ->create(['active' => true]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.create', ['type' => 'investment']));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.form');
        $response->assertViewHas('type', 'investment');
        $response->assertViewHas('action', 'create');
    }

    /**
     * Test that user can view their own transaction (show action)
     */
    public function test_user_can_view_own_transaction(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.open', [
                'transaction' => $transaction->id,
                'action' => 'show'
            ]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.show');
    }

    /**
     * Test that user can access edit form for their own transaction
     */
    public function test_user_can_access_edit_form_for_own_transaction(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.open', [
                'transaction' => $transaction->id,
                'action' => 'edit'
            ]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.form');
        $response->assertViewHas('action', 'edit');
    }

    /**
     * Test that user can access clone form for their own transaction
     */
    public function test_user_can_access_clone_form_for_own_transaction(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.open', [
                'transaction' => $transaction->id,
                'action' => 'clone'
            ]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.form');
        $response->assertViewHas('action', 'clone');
    }

    /**
     * Test that user can access enter form for scheduled transaction
     */
    public function test_user_can_access_enter_form_for_scheduled_transaction(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        // Ensure schedule has a next_date
        $transaction->transactionSchedule->update([
            'start_date' => now(),
            'next_date' => now()->addDays(7),
            'end_date' => null,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'count' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.open', [
                'transaction' => $transaction->id,
                'action' => 'enter'
            ]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.form');
        $response->assertViewHas('action', 'enter');
    }

    /**
     * Test that invalid action returns 404
     */
    public function test_invalid_action_returns_404(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->get(route('transaction.open', [
                'transaction' => $transaction->id,
                'action' => 'invalid'
            ]));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * Test that user can skip a scheduled transaction instance
     */
    public function test_user_can_skip_scheduled_transaction_instance(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        $originalNextDate = now()->addDays(7);
        $transaction->transactionSchedule->update([
            'start_date' => now()->subWeek(),
            'next_date' => $originalNextDate,
            'end_date' => null,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'count' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->patch(route('transactions.skipScheduleInstance', ['transaction' => $transaction->id]));

        $response->assertRedirect();

        // Verify next_date was updated
        $transaction->transactionSchedule->refresh();
        $this->assertNotEquals(
            $originalNextDate->format('Y-m-d'),
            $transaction->transactionSchedule->next_date->format('Y-m-d')
        );
    }

    public function test_user_cannot_skip_other_users_scheduled_transaction_instance(): void
    {
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()
            ->withdrawal_schedule($otherUser)
            ->create(['user_id' => $otherUser->id]);

        $originalNextDate = now()->addDays(7);
        $transaction->transactionSchedule->update([
            'start_date' => now()->subWeek(),
            'next_date' => $originalNextDate,
            'end_date' => null,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->actingAs($this->user)
            ->patch(route('transactions.skipScheduleInstance', ['transaction' => $transaction->id]))
            ->assertStatus(Response::HTTP_FORBIDDEN);

        $transaction->transactionSchedule->refresh();
        $this->assertEquals(
            $originalNextDate->format('Y-m-d'),
            $transaction->transactionSchedule->next_date->format('Y-m-d')
        );
    }

    /**
     * Test that user can create transaction from draft
     */
    public function test_user_can_create_transaction_from_draft(): void
    {
        // Create necessary active account
        $account = AccountEntity::factory()
            ->for($this->user)
            ->for(
                \App\Models\Account::factory()->withUser($this->user),
                'config'
            )
            ->create(['active' => true]);

        $payee = AccountEntity::factory()
            ->for($this->user)
            ->for(
                \App\Models\Payee::factory()->withUser($this->user),
                'config'
            )
            ->create(['active' => true]);

        $expenseCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
            'name' => 'Groceries',
        ]);

        $recommendedCategory = Category::factory()->create([
            'user_id' => $this->user->id,
            'active' => true,
            'name' => 'Food',
        ]);

        $draftData = [
            'config_type' => 'standard',
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'date' => now()->format('Y-m-d'),
            'config' => [
                'account_from_id' => $account->id,
                'account_to_id' => $payee->id,
                'amount_from' => 100,
                'amount_to' => 100,
            ],
            'transaction_items' => [
                [
                    'amount' => 100,
                    'category_id' => $expenseCategory->id,
                    'recommended_category_id' => $recommendedCategory->id,
                    'description' => 'milk',
                    'match_type' => 'ai',
                    'confidence_score' => 0.82,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('transactions.createFromDraft'), [
                'transaction' => json_encode($draftData),
            ]);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertViewIs('transactions.form');
        $response->assertViewHas('action', 'finalize');

        /** @var Transaction $transaction */
        $transaction = $response->viewData('transaction');
        $this->assertCount(1, $transaction->transactionItems);

        $item = $transaction->transactionItems->first();
        $this->assertSame($recommendedCategory->id, $item->getAttribute('recommended_category_id'));
        $this->assertSame('milk', $item->getAttribute('description'));
        $this->assertSame('ai', $item->getAttribute('match_type'));
        $this->assertSame(0.82, $item->getAttribute('confidence_score'));
        $this->assertSame($recommendedCategory->full_name, $item->getAttribute('recommended_category_full_name'));
    }

    public function test_create_from_draft_does_not_leak_other_users_account_entity(): void
    {
        // The draft preview must still render (falling back to the acting user's base
        // currency) even when the referenced account can't be resolved - give the
        // acting user a currency to fall back to, same as every other test's fixtures.
        \App\Models\Currency::factory()->for($this->user)->create(['base' => true]);

        $otherUser = User::factory()->create();
        $otherAccount = AccountEntity::factory()
            ->for($otherUser)
            ->for(
                \App\Models\Account::factory()->withUser($otherUser),
                'config'
            )
            ->create(['active' => true, 'name' => 'Other User Secret Account']);

        $draftData = [
            'config_type' => 'standard',
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'date' => now()->format('Y-m-d'),
            'config' => [
                'account_from_id' => $otherAccount->id,
                'account_to_id' => null,
                'amount_from' => 100,
                'amount_to' => 100,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('transactions.createFromDraft'), [
                'transaction' => json_encode($draftData),
            ]);

        $response->assertStatus(Response::HTTP_OK);

        /** @var Transaction $transaction */
        $transaction = $response->viewData('transaction');
        $this->assertNull($transaction->config->getRelation('accountFrom'));
        $response->assertDontSee('Other User Secret Account');
    }

    public function test_create_from_draft_ignores_spoofed_user_id(): void
    {
        \App\Models\Currency::factory()->for($this->user)->create(['base' => true]);

        $otherUser = User::factory()->create();
        \App\Models\Currency::factory()->for($otherUser)->create([
            'base' => true,
            'name' => 'Other User Secret Currency',
            'iso_code' => 'ZZZ',
        ]);

        $draftData = [
            'config_type' => 'standard',
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'date' => now()->format('Y-m-d'),
            'user_id' => $otherUser->id,
            'config' => [
                'account_from_id' => null,
                'account_to_id' => null,
                'amount_from' => 100,
                'amount_to' => 100,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('transactions.createFromDraft'), [
                'transaction' => json_encode($draftData),
            ]);

        $response->assertStatus(Response::HTTP_OK);

        /** @var Transaction $transaction */
        $transaction = $response->viewData('transaction');
        $this->assertNotSame($otherUser->id, $transaction->user_id);
        $response->assertDontSee('Other User Secret Currency');
        $response->assertDontSee('ZZZ');
    }
}

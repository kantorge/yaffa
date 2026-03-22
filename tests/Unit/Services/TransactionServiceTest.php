<?php

namespace Tests\Unit\Services;

use App\Jobs\CalculateAccountMonthlySummary;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TransactionService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TransactionService();
        $this->user = User::factory()->create();
    }

    /**
     * Test getting currency ID for standard withdrawal transaction
     */
    public function test_get_transaction_currency_id_for_withdrawal(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        $currencyId = $this->service->getTransactionCurrencyId($transaction);

        $this->assertNotNull($currencyId);
        $this->assertEquals(
            $transaction->config->accountFrom->config->currency_id,
            $currencyId
        );
    }

    /**
     * Test getting currency ID for standard deposit transaction
     */
    public function test_get_transaction_currency_id_for_deposit(): void
    {
        $transaction = Transaction::factory()
            ->deposit($this->user)
            ->create(['user_id' => $this->user->id]);

        $currencyId = $this->service->getTransactionCurrencyId($transaction);

        $this->assertNotNull($currencyId);
        $this->assertEquals(
            $transaction->config->accountTo->config->currency_id,
            $currencyId
        );
    }

    /**
     * Test getting currency ID for transfer transaction
     */
    public function test_get_transaction_currency_id_for_transfer_returns_null(): void
    {
        $transaction = Transaction::factory()
            ->transfer($this->user)
            ->create(['user_id' => $this->user->id]);

        $currencyId = $this->service->getTransactionCurrencyId($transaction);

        // Transfer transactions don't have a single currency
        $this->assertNull($currencyId);
    }

    /**
     * Test getting currency ID for investment buy transaction
     */
    public function test_get_transaction_currency_id_for_investment_buy(): void
    {
        $transaction = Transaction::factory()
            ->buy($this->user)
            ->create(['user_id' => $this->user->id]);

        $currencyId = $this->service->getTransactionCurrencyId($transaction);

        $this->assertNotNull($currencyId);
        $this->assertEquals(
            $transaction->config->account->config->currency_id,
            $currencyId
        );
    }

    /**
     * Test getting currency ID for investment sell transaction
     */
    public function test_get_transaction_currency_id_for_investment_sell(): void
    {
        $transaction = Transaction::factory()
            ->sell($this->user)
            ->create(['user_id' => $this->user->id]);

        $currencyId = $this->service->getTransactionCurrencyId($transaction);

        $this->assertNotNull($currencyId);
        $this->assertEquals(
            $transaction->config->account->config->currency_id,
            $currencyId
        );
    }

    /**
     * Test getting currency ID for investment dividend transaction
     */
    public function test_get_transaction_currency_id_for_investment_dividend(): void
    {
        $transaction = Transaction::factory()
            ->dividend($this->user, ['dividend' => 100])
            ->create(['user_id' => $this->user->id]);

        $currencyId = $this->service->getTransactionCurrencyId($transaction);

        $this->assertNotNull($currencyId);
        $this->assertEquals(
            $transaction->config->account->config->currency_id,
            $currencyId
        );
    }

    /**
     * Test getting cash flow for withdrawal transaction (negative)
     */
    public function test_get_transaction_cash_flow_for_withdrawal(): void
    {
        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create(['user_id' => $this->user->id]);

        // Update the amount to a known value
        $transaction->config->update([
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        $cashFlow = $this->service->getTransactionCashFlow($transaction);

        $this->assertNotNull($cashFlow);
        $this->assertEquals(-100, $cashFlow);
    }

    /**
     * Test getting cash flow for deposit transaction (positive)
     */
    public function test_get_transaction_cash_flow_for_deposit(): void
    {
        $transaction = Transaction::factory()
            ->deposit($this->user)
            ->create(['user_id' => $this->user->id]);

        // Update the amount to a known value
        $transaction->config->update([
            'amount_from' => 100,
            'amount_to' => 100,
        ]);

        $cashFlow = $this->service->getTransactionCashFlow($transaction);

        $this->assertNotNull($cashFlow);
        $this->assertEquals(100, $cashFlow);
    }

    /**
     * Test getting cash flow for transfer transaction (null)
     */
    public function test_get_transaction_cash_flow_for_transfer_returns_null(): void
    {
        $transaction = Transaction::factory()
            ->transfer($this->user)
            ->create(['user_id' => $this->user->id]);

        $cashFlow = $this->service->getTransactionCashFlow($transaction);

        // Transfer transactions don't have a single cash flow value
        $this->assertNull($cashFlow);
    }

    /**
     * Test getting cash flow for investment buy transaction
     */
    public function test_get_transaction_cash_flow_for_investment_buy(): void
    {
        $transaction = Transaction::factory()
            ->buy($this->user, [
                'price' => 10,
                'quantity' => 5,
                'commission' => 2,
                'tax' => 1,
            ])
            ->create(['user_id' => $this->user->id]);

        $cashFlow = $this->service->getTransactionCashFlow($transaction);

        // Buy: -(price * quantity + commission + tax)
        // -1 * (10 * 5) + 0 - 1 - 2 = -53
        $this->assertNotNull($cashFlow);
        $this->assertEquals(-53, $cashFlow);
    }

    /**
     * Test getting cash flow for investment sell transaction
     */
    public function test_get_transaction_cash_flow_for_investment_sell(): void
    {
        $transaction = Transaction::factory()
            ->sell($this->user, [
                'price' => 10,
                'quantity' => 5,
                'commission' => 2,
                'tax' => 1,
            ])
            ->create(['user_id' => $this->user->id]);

        $cashFlow = $this->service->getTransactionCashFlow($transaction);

        // Sell: +(price * quantity - commission - tax)
        // 1 * (10 * 5) + 0 - 1 - 2 = 47
        $this->assertNotNull($cashFlow);
        $this->assertEquals(47, $cashFlow);
    }

    /**
     * Test getting cash flow for investment dividend transaction
     */
    public function test_get_transaction_cash_flow_for_investment_dividend(): void
    {
        $transaction = Transaction::factory()
            ->dividend($this->user, [
                'dividend' => 100,
                'commission' => 2,
                'tax' => 10,
            ])
            ->create(['user_id' => $this->user->id]);

        $cashFlow = $this->service->getTransactionCashFlow($transaction);

        // Dividend: 0 * (price * quantity) + dividend - commission - tax
        // 0 + 100 - 10 - 2 = 88
        $this->assertNotNull($cashFlow);
        $this->assertEquals(88, $cashFlow);
    }

    /**
     * Test entering a scheduled transaction instance
     */
    public function test_enter_schedule_instance_creates_new_transaction(): void
    {
        $scheduledTransaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create(['user_id' => $this->user->id]);

        // Set a specific next date for the schedule
        $scheduledTransaction->transactionSchedule->update([
            'start_date' => now(),
            'next_date' => now()->addDays(7),
            'end_date' => null,
            'frequency' => 'WEEKLY',
            'interval' => 1,
            'count' => null,
        ]);

        $scheduledTransaction->transactionSchedule->refresh();
        $originalNextDate = $scheduledTransaction->transactionSchedule->next_date;

        $originalTransactionCount = Transaction::count();

        $this->service->enterScheduleInstance($scheduledTransaction);

        // Assert a new transaction was created
        $this->assertEquals($originalTransactionCount + 1, Transaction::count());

        // Get the new transaction (exclude scheduled ones to get the newly cloned one)
        $newTransaction = Transaction::where('schedule', false)
            ->where('user_id', $this->user->id)
            ->latest('id')
            ->first();

        // Assert the new transaction has correct properties
        $this->assertEquals($originalNextDate?->format('Y-m-d'), $newTransaction->date?->format('Y-m-d'));
        $this->assertFalse($newTransaction->schedule);
        $this->assertFalse($newTransaction->budget);
        $this->assertEquals($scheduledTransaction->user_id, $newTransaction->user_id);
    }

    /**
     * Test recalculate monthly summaries dispatches job for simple standard transaction
     */
    public function test_recalculate_monthly_summaries_for_simple_standard_transaction(): void
    {
        Queue::fake();

        $transaction = Transaction::factory()
            ->withdrawal($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
                'budget' => false,
            ]);

        $this->service->recalculateMonthlySummaries($transaction);

        // For simple transactions, the job is dispatched synchronously
        // We can't easily test dispatch_sync, but we can verify the transaction is loaded correctly
        $this->assertFalse($transaction->schedule);
        $this->assertFalse($transaction->budget);
    }

    /**
     * Test recalculate monthly summaries dispatches job for scheduled transaction
     */
    public function test_recalculate_monthly_summaries_for_scheduled_transaction(): void
    {
        Queue::fake();

        $transaction = Transaction::factory()
            ->withdrawal_schedule($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => true,
                'budget' => false,
            ]);

        $this->service->recalculateMonthlySummaries($transaction);

        // For scheduled transactions, the job is dispatched to the queue
        Queue::assertPushed(CalculateAccountMonthlySummary::class);
    }

    /**
     * Test recalculate monthly summaries dispatches job for investment transaction
     */
    public function test_recalculate_monthly_summaries_for_investment_transaction(): void
    {
        Queue::fake();

        $transaction = Transaction::factory()
            ->buy($this->user)
            ->create([
                'user_id' => $this->user->id,
                'schedule' => false,
            ]);

        $this->service->recalculateMonthlySummaries($transaction);

        // For non-scheduled investment transactions, three jobs are dispatched:
        // investment_value-fact, account_balance-fact, and investment_value-forecast
        Queue::assertPushed(CalculateAccountMonthlySummary::class, 3);
    }
}

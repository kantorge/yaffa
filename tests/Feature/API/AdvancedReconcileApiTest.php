<?php

namespace Tests\Feature\API;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Payee;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedReconcileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private AccountEntity $account;
    private AccountEntity $payee;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-15');

        $this->user = User::factory()->create();
        $currency = Currency::factory()
            ->for($this->user)
            ->fromIsoCodes(['USD'])
            ->create(['base' => true]);

        $this->account = AccountEntity::factory()
            ->for($this->user)
            ->for(Account::factory()->withUser($this->user)->create([
                'currency_id' => $currency->id,
                'opening_balance' => 100,
            ]), 'config')
            ->create(['active' => true]);

        $this->payee = AccountEntity::factory()
            ->for($this->user)
            ->for(Payee::factory()->withUser($this->user), 'config')
            ->create(['active' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_user_can_summarize_cash_reconciliation_and_save_matching_checkpoint(): void
    {
        Sanctum::actingAs($this->user);

        $this->createWithdrawal('2026-07-03', 30);
        $this->createDeposit('2026-07-05', 50);

        $summaryResponse = $this->getJson(route('api.v1.accounts.advanced-reconcile.show', [
            'accountEntity' => $this->account,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $summaryResponse->assertOk();
        $summaryResponse->assertJsonPath('cash.opening_balance', 100);
        $summaryResponse->assertJsonPath('cash.total_withdrawals', 30);
        $summaryResponse->assertJsonPath('cash.total_deposits', 50);
        $summaryResponse->assertJsonPath('cash.balance', 120);
        $summaryResponse->assertJsonPath('cash.status', 'no_checkpoint');

        $checkpointResponse = $this->postJson(route('api.v1.accounts.balance-checkpoints.store', [
            'accountEntity' => $this->account,
        ]), [
            'checkpoint_date' => '2026-07-31',
            'checkpoint_type' => 'cash',
            'balance' => 120,
            'note' => 'July statement',
        ]);

        $checkpointResponse->assertCreated();
        $this->assertDatabaseHas('account_balance_checkpoints', [
            'account_entity_id' => $this->account->id,
            'checkpoint_type' => 'cash',
            'balance' => 120,
            'note' => 'July statement',
        ]);

        $this->getJson(route('api.v1.accounts.advanced-reconcile.show', [
            'accountEntity' => $this->account,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]))
            ->assertOk()
            ->assertJsonPath('cash.status', 'matched')
            ->assertJsonPath('cash.variance', 0);
    }

    public function test_dashboard_marks_variance_as_reconcile_required(): void
    {
        Sanctum::actingAs($this->user);

        $this->createDeposit('2026-07-05', 50);

        $this->postJson(route('api.v1.accounts.balance-checkpoints.store', [
            'accountEntity' => $this->account,
        ]), [
            'checkpoint_date' => '2026-07-31',
            'checkpoint_type' => 'cash',
            'balance' => 200,
        ])->assertCreated();

        $response = $this->getJson(route('api.v1.reports.advanced-reconcile', [
            'checkpoint_type' => 'cash',
            'display' => 'status',
        ]));

        $response->assertOk();
        $response->assertJsonPath('rows.0.months.2026-07.status', 'reconcile_required');
        $response->assertJsonPath('rows.0.months.2026-07.variance', 50);
    }

    public function test_investment_holdings_include_statement_price_editing_metadata(): void
    {
        Sanctum::actingAs($this->user);

        $investment = Investment::factory()->withUser($this->user)->create([
            'currency_id' => $this->account->config->currency_id,
        ]);

        $this->createInvestmentBuy($investment, '2026-06-15', 10);

        $openingPrice = InvestmentPrice::factory()->create([
            'investment_id' => $investment->id,
            'date' => '2026-07-01',
            'price' => 12.34,
        ]);
        InvestmentPrice::factory()->create([
            'investment_id' => $investment->id,
            'date' => '2026-07-20',
            'price' => 15.67,
        ]);

        $response = $this->getJson(route('api.v1.accounts.advanced-reconcile.show', [
            'accountEntity' => $this->account,
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
        ]));

        $response->assertOk();
        $response->assertJsonPath('investment.holdings.0.investment_id', $investment->id);
        $response->assertJsonPath('investment.holdings.0.open_quantity', 10);
        $response->assertJsonPath('investment.holdings.0.close_quantity', 10);
        $response->assertJsonPath('investment.holdings.0.open_price', 12.34);
        $response->assertJsonPath('investment.holdings.0.close_price', 15.67);
        $response->assertJsonPath('investment.holdings.0.open_stored_price_id', $openingPrice->id);
        $response->assertJsonPath('investment.holdings.0.close_stored_price_id', null);
    }

    public function test_user_cannot_save_checkpoint_for_another_users_account(): void
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson(route('api.v1.accounts.balance-checkpoints.store', [
            'accountEntity' => $this->account,
        ]), [
            'checkpoint_date' => '2026-07-31',
            'checkpoint_type' => 'cash',
            'balance' => 120,
        ]);

        $response->assertForbidden();
    }

    private function createWithdrawal(string $date, float $amount): void
    {
        $detail = TransactionDetailStandard::create([
            'account_from_id' => $this->account->id,
            'account_to_id' => $this->payee->id,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ]);

        Transaction::create([
            'date' => $date,
            'transaction_type' => TransactionType::WITHDRAWAL,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config_type' => 'standard',
            'config_id' => $detail->id,
            'user_id' => $this->user->id,
        ]);
    }

    private function createDeposit(string $date, float $amount): void
    {
        $detail = TransactionDetailStandard::create([
            'account_from_id' => $this->payee->id,
            'account_to_id' => $this->account->id,
            'amount_from' => $amount,
            'amount_to' => $amount,
        ]);

        Transaction::create([
            'date' => $date,
            'transaction_type' => TransactionType::DEPOSIT,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config_type' => 'standard',
            'config_id' => $detail->id,
            'user_id' => $this->user->id,
        ]);
    }

    private function createInvestmentBuy(Investment $investment, string $date, float $quantity): void
    {
        $detail = TransactionDetailInvestment::create([
            'account_id' => $this->account->id,
            'investment_id' => $investment->id,
            'quantity' => $quantity,
            'price' => 10,
            'commission' => 0,
            'tax' => 0,
            'dividend' => null,
        ]);

        Transaction::create([
            'date' => $date,
            'transaction_type' => TransactionType::BUY,
            'reconciled' => false,
            'schedule' => false,
            'budget' => false,
            'config_type' => 'investment',
            'config_id' => $detail->id,
            'cashflow_value' => -100,
            'user_id' => $this->user->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Database;

use App\Models\AccountEntity;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\InvestmentGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionDetailsInvestmentPriceScaleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * transaction_details_investment.price now shares investment_prices.price's
     * DECIMAL(20,10) scale, so a 10-decimal-place value must round-trip exactly.
     */
    public function test_price_column_accepts_ten_decimal_places_without_truncation(): void
    {
        $user = User::factory()->create();
        $currency = Currency::factory()->for($user)->create();
        $investmentGroup = InvestmentGroup::factory()->for($user)->create();
        $investment = Investment::factory()->create([
            'user_id' => $user->id,
            'currency_id' => $currency->id,
            'investment_group_id' => $investmentGroup->id,
        ]);
        $accountEntity = AccountEntity::factory()->asAccount($user, ['currency_id' => $currency->id])->create();

        $id = DB::table('transaction_details_investment')->insertGetId([
            'account_id' => $accountEntity->id,
            'investment_id' => $investment->id,
            'price' => '9999999999.9999999999',
            'quantity' => 1,
            'commission' => 0,
            'tax' => 0,
        ]);

        $stored = DB::table('transaction_details_investment')->where('id', $id)->value('price');

        $this->assertSame('9999999999.9999999999', $stored);
    }

    public function test_price_column_definition_matches_investment_prices_scale(): void
    {
        $precisionAndScale = fn (string $table) => DB::selectOne(
            'SELECT numeric_precision, numeric_scale FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$table, 'price']
        );

        $this->assertEquals(
            $precisionAndScale('investment_prices'),
            $precisionAndScale('transaction_details_investment'),
        );
    }
}

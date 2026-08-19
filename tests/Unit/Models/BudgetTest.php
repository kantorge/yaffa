<?php

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_resolves_to_base_currency_when_account_agnostic(): void
    {
        $user = User::factory()->create();
        $baseCurrency = Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => null,
        ]);

        $this->assertSame($baseCurrency->id, $budget->currency()?->id);
    }

    public function test_currency_resolves_to_account_currency_when_account_scoped(): void
    {
        $user = User::factory()->create();
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);
        $accountCurrency = Currency::factory()->for($user)->fromIsoCodes(['EUR'])->create();
        $category = Category::factory()->for($user)->create();

        AccountGroup::factory()->for($user)->create();
        $account = Account::factory()->withUser($user)->create(['currency_id' => $accountCurrency->id]);
        $accountEntity = AccountEntity::factory()->for($user)->for($account, 'config')->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $accountEntity->id,
        ]);

        $this->assertSame($accountCurrency->id, $budget->currency()?->id);
    }

    public function test_currency_updates_automatically_when_account_currency_changes(): void
    {
        $user = User::factory()->create();
        Currency::factory()->for($user)->fromIsoCodes(['USD'])->create(['base' => true]);
        $originalCurrency = Currency::factory()->for($user)->fromIsoCodes(['EUR'])->create();
        $newCurrency = Currency::factory()->for($user)->fromIsoCodes(['PLN'])->create();
        $category = Category::factory()->for($user)->create();

        AccountGroup::factory()->for($user)->create();
        $account = Account::factory()->withUser($user)->create(['currency_id' => $originalCurrency->id]);
        $accountEntity = AccountEntity::factory()->for($user)->for($account, 'config')->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'account_id' => $accountEntity->id,
        ]);

        $this->assertSame($originalCurrency->id, $budget->currency()?->id);

        $account->update(['currency_id' => $newCurrency->id]);
        $budget->refresh();

        $this->assertSame($newCurrency->id, $budget->currency()?->id);
    }

    public function test_active_is_computed_automatically_and_ignores_client_input(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'active' => false, // not fillable, must be ignored in favor of the computed value
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($budget->active);
    }

    public function test_active_is_false_when_recurrence_is_exhausted(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => Carbon::now()->subDay(),
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertFalse($budget->active);
    }

    public function test_active_is_recomputed_on_update(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        $budget = Budget::factory()->create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'start_date' => Carbon::now()->subDays(10),
            'end_date' => null,
            'frequency' => 'DAILY',
            'interval' => 1,
            'count' => null,
        ]);

        $this->assertTrue($budget->active);

        $budget->update(['end_date' => Carbon::now()->subDay()]);

        $this->assertFalse($budget->fresh()->active);
    }
}

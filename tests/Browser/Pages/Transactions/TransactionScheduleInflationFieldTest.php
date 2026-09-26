<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

/**
 * Regression coverage for a documentation bug: the schedule form's "Yearly inflation"
 * field was gated behind isBudget, so a real Transaction schedule could never have its
 * inflation rate set through the UI even though the backend fully supported it. No
 * Feature test can catch this, since it's a Vue template v-if, not backend behavior.
 */
#[Group('critical')]
class TransactionScheduleInflationFieldTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }

        $this->user = User::where('email', $this::USER_EMAIL)->firstOrFail();
    }

    public function test_standard_transaction_schedule_exposes_inflation_field(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser
                ->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'standard'])
                ->waitFor('#transactionFormStandard')
                ->click('label[dusk="checkbox-transaction-schedule"]')
                ->waitFor('input[id^="schedule_inflation_"]')
                ->assertVisible('input[id^="schedule_inflation_"]')
                ->type('input[id^="schedule_inflation_"]', '3.5')
                ->assertInputValue('input[id^="schedule_inflation_"]', '3.5');
        });
    }

    public function test_investment_transaction_schedule_exposes_inflation_field(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser
                ->loginAs($this->user)
                ->visitRoute('transaction.create', ['type' => 'investment'])
                ->waitFor('#transactionFormInvestment')
                ->click('label[dusk="checkbox-transaction-schedule"]')
                ->waitFor('input[id^="schedule_inflation_"]')
                ->assertVisible('input[id^="schedule_inflation_"]');
        });
    }
}

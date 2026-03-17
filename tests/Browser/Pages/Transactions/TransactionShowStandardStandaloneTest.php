<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\Transaction;
use App\Models\User;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('critical')]
class TransactionShowStandardStandaloneTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }
    }

    public function test_user_can_load_the_standard_transaction_details(): void
    {
        $user = User::firstWhere('email', $this::USER_EMAIL);

        // Create a standard transaction with specific data
        $transaction = Transaction::factory()
            ->for($user)
            ->withdrawal($user)
            ->create([
                'comment' => 'Test comment',
                'reconciled' => true,
            ]);

        $transaction->load(['config.accountFrom', 'config.accountTo']);

        $this->browse(function (Browser $browser) use ($user, $transaction) {
            $browser->loginAs($user)
                // Load the transaction page
                ->visitRoute('transaction.open', ['transaction' => $transaction->id, 'action' => 'show'])
                ->waitFor('#transactionShowStandard')

                // Account and payee names are visible
                ->assertSeeIn('@label-account-from-name', $transaction->config->accountFrom->name)
                ->assertSeeIn('@label-account-to-name', $transaction->config->accountTo->name)
                // Account and payee names link to their respective screens
                ->assertPresent('@label-account-from-name a')
                ->assertPresent('@label-account-to-name a')
                ->assertAttribute(
                    '@label-account-from-name a',
                    'href',
                    route('account-entity.show', ['account_entity' => $transaction->config->accountFrom->id])
                )
                ->assertAttribute('@label-account-from-name a', 'title', __('Go to account'))
                ->assertAttribute(
                    '@label-account-to-name a',
                    'href',
                    route('account-entity.edit', [
                        'type' => 'payee',
                        'account_entity' => $transaction->config->accountTo->id,
                    ])
                )
                ->assertAttribute(
                    '@label-account-to-name a',
                    'title',
                    __('Open payee edit form (payees do not have a details view yet)')
                )

                // Action button bar is present
                ->assertPresent('@action-bar')
                // Close and open button is not available in the action bar
                ->assertMissing('@button-action-bar-close')
                ->assertMissing('@button-action-bar-open')
                // Skip and enter instance buttons are not available in the action bar
                ->assertMissing('@button-action-bar-skip')
                ->assertMissing('@button-action-bar-enter-instance');
        });
    }
}

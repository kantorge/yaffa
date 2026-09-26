<?php

namespace Tests\Browser\Pages\Transactions;

use App\Models\User;
use Facebook\WebDriver\WebDriverKeys;
use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\Group;
use Tests\DuskTestCase;

#[Group('critical')]
class TransactionFormStandardModalTest extends DuskTestCase
{
    protected static bool $migrationRun = false;

    private const string TRANSACTION_ITEM_ROW_SELECTOR = '#transaction_item_container .transaction_item_row';

    private const string TRANSACTION_ITEM_CATEGORY_SELECTOR = '#transaction_item_container .transaction_item_row select.category';

    private const string TRANSACTION_ITEM_CATEGORY_SELECT2_SELECTOR = '#transaction_item_container .transaction_item_row select.category + .select2';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Migrate and seed only once for this file
        if (!static::$migrationRun) {
            $this->artisan('migrate:fresh');
            $this->artisan('db:seed');
            static::$migrationRun = true;
        }

        $this->user = User::firstWhere('email', $this::USER_EMAIL);
    }

    private function getTransactionItemRowCount(Browser $browser): int
    {
        $rowCount = $browser->script(
            'return document.querySelectorAll(' . json_encode(self::TRANSACTION_ITEM_ROW_SELECTOR) . ').length;'
        )[0] ?? 0;

        return (int) $rowCount;
    }

    private function addTransactionItemAndWaitForCategorySelect2(Browser $browser, int $timeout = 10): Browser
    {
        $expectedRowCount = $this->getTransactionItemRowCount($browser) + 1;

        return $browser
            ->click('@button-add-transaction-item')
            ->waitUsing(
                $timeout,
                100,
                fn () => $this->getTransactionItemRowCount($browser) >= $expectedRowCount
            )
            ->tap(fn (Browser $browser): Browser => $this->waitForTransactionItemCategorySelect2($browser, $timeout));
    }

    private function waitForTransactionItemCategorySelect2(Browser $browser, int $timeout = 10): Browser
    {
        return $browser
            ->waitFor(self::TRANSACTION_ITEM_ROW_SELECTOR, $timeout)
            ->waitUsing(
                $timeout,
                100,
                fn () => ($browser->script(
                    'const select = document.querySelector(' . json_encode(self::TRANSACTION_ITEM_CATEGORY_SELECTOR) . ');' .
                    'if (!select) { return false; }' .
                    'const select2Container = select.nextElementSibling;' .
                    'return select.classList.contains("select2-hidden-accessible") && !!select2Container && select2Container.matches(".select2");'
                )[0] ?? false) === true
            )
            ->assertPresent(self::TRANSACTION_ITEM_CATEGORY_SELECT2_SELECTOR);
    }

    public function test_user_can_load_the_standard_transaction_form_in_a_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Finalize transaction draft')
                // The modal should be visible
                ->assertVisible('#modal-transaction-form-standard')
                // The form should be visible
                ->assertVisible('#transactionFormStandard')
                // The save button should be visible
                ->assertVisible('#transactionFormStandard-Save')
                // The "after save" button group should not be present
                ->assertNotPresent('@action-after-save-desktop-button-group');
        });
    }

    public function test_add_new_payee_button_is_never_visible_in_the_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Finalize transaction draft')

                // Verify that the add new payee button is visible next to the account to dropdown
                ->assertNotPresent('#account_to_container > button[data-coreui-target="#newPayeeModal"]')

                // Switch to deposit and confirm dialog
                ->click('@transaction-type-deposit')
                ->waitFor('.swal2-popup', 5)
                ->click('.swal2-confirm')
                // Verify that the add new payee button is not visible next to the account from dropdown
                ->assertNotPresent('#account_from_container > button[data-coreui-target="#newPayeeModal"]')

                // Switch to transfer and confirm dialog
                ->click('@transaction-type-transfer')
                ->waitFor('.swal2-popup', 5)
                ->click('.swal2-confirm')
                // Verify that the add new payee button is not visible
                ->assertNotPresent('#account_to_container > button[data-coreui-target="#newPayeeModal"]')
                ->assertNotPresent('#account_from_container > button[data-coreui-target="#newPayeeModal"]');
        });
    }

    public function test_user_can_interact_with_reconciled_date_and_comment_fields_in_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for a random account
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')

                // Test the reconciled checkbox with prefixed ID
                ->assertPresent('#checkbox-standard-transaction-reconciled')
                ->click('label[for="checkbox-standard-transaction-reconciled"]')
                ->assertChecked('#checkbox-standard-transaction-reconciled')
                ->click('label[for="checkbox-standard-transaction-reconciled"]')
                ->assertNotChecked('#checkbox-standard-transaction-reconciled')

                // Test the date field with prefixed ID
                ->assertPresent('#standard-date');

            $this->setDateInput($browser, '#standard-date', '2025-01-15');

            $browser
                ->assertInputValue('#standard-date', '2025-01-15')

                // Test the comment field with prefixed ID
                ->assertPresent('#standard-comment')
                ->type('#standard-comment', 'Test comment from modal')
                ->assertInputValue('#standard-comment', 'Test comment from modal');
        });
    }

    public function test_user_can_submit_withdrawal_transaction_form_in_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                // Load the view for an account of the user
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                // Wait for the page to load
                ->waitForText('Account details')
                // Click the "new transaction" button
                ->click('#create-standard-transaction-button')
                // Wait for the modal to load
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')
                ->waitFor('#account_to', 10)

                // Fill the form
                // Account (account from) is pre-selected (account_entity 1)
                // Select payee (account to) by searching for a known payee from the fixed seeder
                ->select2('#account_to', 'Auchan', 10)
                // Add amount
                ->type('#transaction_amount_from', '100')
                // Allocate the same amount to a random category by adding one new item
                ->tap(fn (Browser $browser): Browser => $this->addTransactionItemAndWaitForCategorySelect2($browser))
                // Set the first category input
                ->select2(self::TRANSACTION_ITEM_CATEGORY_SELECTOR, null, 10)
                // Set the first amount to the same amount as the transaction
                ->type(self::TRANSACTION_ITEM_ROW_SELECTOR . ' input.transaction_item_amount', '100')

                // Submit form
                ->click('#transactionFormStandard-Save')
                // Wait for the modal to close
                ->waitUntilMissing('#modal-transaction-form-standard', 10)
                // A success message should be available
                ->waitForTextIn('.toast-container .toast.bg-success.show', 'Transaction added', 10);

            // Verify the transaction was saved in the database
            $transaction = \App\Models\Transaction::orderByDesc('id')->first();
            $this->assertNotNull($transaction);
            $this->assertEquals(100, $transaction->config->amount_from->getAmount()->toFloat());
        });
    }

    public function test_transaction_type_confirm_dialog_keeps_focus_off_the_modal(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                ->waitForText('Account details')
                ->click('#create-standard-transaction-button')
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')
                // Wait for the preselected account (loaded async via select2) to settle -
                // clicking transaction-type-deposit while it's still in flight can race with
                // select2's own focus handling while it applies the account value.
                ->waitUsing(
                    10,
                    100,
                    fn () => ($browser->script(
                        "return document.querySelector('#account_from')?.value || null;"
                    )[0] ?? null) !== null
                )
                // Switch to deposit and let the SweetAlert2 confirm dialog open
                ->click('@transaction-type-deposit')
                ->waitFor('.swal2-popup', 5);

            // The CoreUI modal's focus trap only refocuses elements it doesn't
            // .contains() - the dialog must render inside the modal for focus to
            // stay put, otherwise the trap steals it back onto the last-clicked
            // button and a keyboard confirm/cancel would hit the form instead.
            $focusInsidePopup = $browser->script(
                "return !!(document.activeElement && document.activeElement.closest('.swal2-popup'));"
            )[0] ?? false;

            $this->assertTrue(
                $focusInsidePopup,
                'Focus should stay inside the SweetAlert2 popup instead of being pulled back into the modal.'
            );

            $browser->click('.swal2-confirm')
                ->waitUntilMissing('.swal2-popup', 5);
        });
    }

    public function test_modal_close_does_not_confirm_when_form_is_untouched(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                ->waitForText('Account details')
                ->click('#create-standard-transaction-button')
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')
                // Wait for the preselected account (loaded async via select2) to settle,
                // so the isDirty() baseline snapshot has actually been taken.
                ->waitUsing(
                    10,
                    100,
                    fn () => ($browser->script(
                        "return document.querySelector('#account_from')?.value || null;"
                    )[0] ?? null) !== null
                )
                // Close via the modal's own dismiss button (not the form's Cancel button)
                ->click('#modal-transaction-form-standard .btn-close')
                ->waitUntilMissing('#modal-transaction-form-standard', 5)
                ->assertNotPresent('.swal2-popup');
        });
    }

    public function test_modal_close_confirms_discard_when_form_is_dirty(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                ->waitForText('Account details')
                ->click('#create-standard-transaction-button')
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')
                ->type('#standard-comment', 'dirty comment')
                ->click('#modal-transaction-form-standard .btn-close')
                ->waitFor('.swal2-popup', 5)
                ->click('.swal2-confirm')
                ->waitUntilMissing('#modal-transaction-form-standard', 5);
        });
    }

    public function test_escape_key_closes_modal_when_form_is_untouched(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                ->waitForText('Account details')
                ->click('#create-standard-transaction-button')
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')
                // Wait for the preselected account (loaded async via select2) to settle,
                // so the isDirty() baseline snapshot has actually been taken.
                ->waitUsing(
                    10,
                    100,
                    fn () => ($browser->script(
                        "return document.querySelector('#account_from')?.value || null;"
                    )[0] ?? null) !== null
                );

            // CoreUI's FocusTrap.activate() calls trapElement.focus() on show - this only
            // works if the modal root has tabindex="-1" (it's a plain <div> otherwise, and
            // .focus() on a non-focusable element is a silent no-op). Confirm focus actually
            // landed inside the modal, since that's what lets a bubbled Escape keydown reach
            // CoreUI's own dismiss listener (bound on the modal root) in the first place.
            $focusInsideModal = $browser->script(
                "return !!(document.activeElement && document.activeElement.closest('#modal-transaction-form-standard'));"
            )[0] ?? false;
            $this->assertTrue(
                $focusInsideModal,
                'Focus should land inside the modal on open, otherwise a bubbled Escape keydown never reaches it.'
            );

            // Send Escape to whatever currently has focus (not to a specific selector),
            // to prove the key actually reaches the modal via normal focus/bubbling.
            $browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);

            $browser->waitUntilMissing('#modal-transaction-form-standard', 5)
                ->assertNotPresent('.swal2-popup');
        });
    }

    public function test_escape_key_confirms_discard_when_form_is_dirty(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('account-entity.show', ['account_entity' => 1])
                ->waitForText('Account details')
                ->click('#create-standard-transaction-button')
                ->waitForText('Finalize transaction draft')
                ->waitFor('#transactionFormStandard')
                ->type('#standard-comment', 'dirty comment');

            $browser->driver->getKeyboard()->sendKeys(WebDriverKeys::ESCAPE);

            $browser->waitFor('.swal2-popup', 5)
                ->click('.swal2-confirm')
                ->waitUntilMissing('#modal-transaction-form-standard', 5);
        });
    }
}

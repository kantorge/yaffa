<?php

use App\Models\AccountEntity;
use App\Models\Transaction;
use App\Models\User;

/*
 * Converted from the Dusk test of the same name (tests/Browser/Pages/Transactions), one it() per method.
 * The investment form opens in a modal from an investment account's page, with the account preset.
 */

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
    $this->account = AccountEntity::firstWhere('name', 'Investment account USD');
});

const INVESTMENT_MODAL = '#modal-transaction-form-investment';

// Open the new investment transaction modal and wait until the account preset has loaded (the isDirty() baseline is
// taken then). Requiring an empty investment also rules out the previous opening's state when reopening.
function investmentModalOpen(object $page, AccountEntity $account): object
{
    // The button's click handler is bound right after this app is mounted, at the end of the page script
    test()->waitUntil($page, "document.querySelector('#account-date-range-filter').__vue_app__");
    $page->click('#create-investment-transaction-button');
    test()->waitUntil($page, "document.querySelector('" . INVESTMENT_MODAL . "').classList.contains('show')
        && document.querySelector('#transactionFormInvestment').dataset.loaded === 'true'
        && document.querySelector('#investment').tomselect.getValue() === ''
        && document.querySelector('#account').tomselect.getValue() === '{$account->id}'");

    return $page->assertSee('Finalize transaction draft')
        ->assertSeeIn('#account + .ts-wrapper .ts-control', $account->name);
}

function investmentModalIsClosed(): string
{
    return "!document.querySelector('" . INVESTMENT_MODAL . "').classList.contains('show')";
}

it('loads the investment transaction form in a modal', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    investmentModalOpen($page, $this->account);

    $page->assertVisible(INVESTMENT_MODAL)
        ->assertVisible('#transactionFormInvestment')
        ->assertVisible('#transactionFormInvestment-Save')
        ->assertNotPresent('[dusk="action-after-save-desktop-button-group"]');
})->group('critical');

it('lets the user use the reconciled, date and comment fields in the modal', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    investmentModalOpen($page, $this->account);

    $page->assertPresent('#checkbox-investment-transaction-reconciled')
        ->click('label[for="checkbox-investment-transaction-reconciled"]')
        ->assertChecked('#checkbox-investment-transaction-reconciled')
        ->click('label[for="checkbox-investment-transaction-reconciled"]')
        ->assertNotChecked('#checkbox-investment-transaction-reconciled')
        ->assertPresent('#investment-date')
        ->script("() => {
            const el = document.querySelector('#investment-date');
            el.value = '2025-01-15';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }");

    $page->assertValue('#investment-date', '2025-01-15')
        ->assertPresent('#investment-comment')
        ->type('#investment-comment', 'Test comment from modal')
        ->assertValue('#investment-comment', 'Test comment from modal');
})->group('critical');

it('submits a buy transaction form in the modal and clears the investment for the next one', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    investmentModalOpen($page, $this->account);

    $this->chooseTomSelectOption($page, 'investment', 'Test investment USD', 'Test investment USD (TIUSD)');
    $page->select('#transaction_type', 'buy')
        ->type('#transaction_quantity', '10')
        ->type('#transaction_price', '20')
        ->type('#transaction_commission', '30')
        ->type('#transaction_tax', '40')
        ->click('#transactionFormInvestment-Save');

    $this->waitUntil($page, investmentModalIsClosed());
    $this->waitForTextIn($page, '.toast-container .toast.bg-success.show', 'Transaction added');

    $transaction = Transaction::orderByDesc('id')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->config->quantity->toFloat())->toEqual(10)
        ->and($transaction->config->price->getAmount()->toFloat())->toEqual(20);

    // Reopened: the investment is cleared, the account is preset again
    investmentModalOpen($page, $this->account);
    $page->assertDontSeeIn('#investment + .ts-wrapper .ts-control', 'Test investment USD');
})->group('critical');

it('clears the investment after cancel', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    investmentModalOpen($page, $this->account);

    $this->chooseTomSelectOption($page, 'investment', 'Test investment USD', 'Test investment USD (TIUSD)');

    // The form is dirty (an investment was selected), so closing asks to discard the changes
    $page->click(INVESTMENT_MODAL . ' .modal-header .btn-close');
    $this->confirmSwal($page);
    $this->waitUntil($page, investmentModalIsClosed());

    // Reopened: the investment is cleared, the account is preset again
    investmentModalOpen($page, $this->account);
    $page->assertDontSeeIn('#investment + .ts-wrapper .ts-control', 'Test investment USD');
})->group('critical');

it('closes the modal with the Escape key when the form is untouched', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    investmentModalOpen($page, $this->account);

    // Focus must land inside the modal on open, otherwise a bubbled Escape keydown never reaches CoreUI's
    // dismiss listener on the modal root
    $page->assertScript("!!document.activeElement?.closest('" . INVESTMENT_MODAL . "')", true);

    // Sent to whatever has focus, to prove the key reaches the modal through normal bubbling
    $page->keys(':focus', 'Escape');

    $this->waitUntil($page, investmentModalIsClosed());
    $page->assertNotPresent('.swal2-popup');
})->group('critical');

it('confirms discarding the changes when pressing Escape with a dirty form', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    investmentModalOpen($page, $this->account);

    $this->chooseTomSelectOption($page, 'investment', 'Test investment USD', 'Test investment USD (TIUSD)');
    $page->keys(':focus', 'Escape');

    $this->confirmSwal($page);
    $this->waitUntil($page, investmentModalIsClosed());
})->group('critical');

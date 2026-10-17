<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

/*
 * Converted from the Dusk test of the same name (tests/Browser/Pages/Transactions), one it() per method.
 * The standard form opens in a modal from an account's page.
 */

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
    $this->account = $this->user->accounts()->orderBy('id')->first();
});

const STANDARD_MODAL = '#modal-transaction-form-standard';

// Open the new transaction modal and wait until its presets have loaded (the isDirty() baseline is taken then)
function standardModalOpen(object $page): object
{
    // The button's click handler is bound right after this app is mounted, at the end of the page script
    test()->waitUntil($page, "document.querySelector('#account-date-range-filter').__vue_app__");
    $page->click('#create-standard-transaction-button');
    test()->waitUntil($page, "document.querySelector('" . STANDARD_MODAL . "').classList.contains('show') && document.querySelector('#transactionFormStandard').dataset.loaded === 'true'");

    return $page->assertSee('Finalize transaction draft');
}

function standardModalIsClosed(): string
{
    return "!document.querySelector('" . STANDARD_MODAL . "').classList.contains('show')";
}

it('loads the standard transaction form in a modal', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->assertVisible(STANDARD_MODAL)
        ->assertVisible('#transactionFormStandard')
        ->assertVisible('#transactionFormStandard-Save')
        ->assertNotPresent('[dusk="action-after-save-desktop-button-group"]');
})->group('critical');

it('never shows the add new payee button in the modal', function () {
    $button = fn (string $side) => "#account_{$side}_container > button[data-coreui-target=\"#newPayeeModal\"]";

    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);
    $page->assertNotPresent($button('to'));

    $page->click('[dusk="transaction-type-deposit"]');
    $this->confirmSwal($page);
    $page->assertNotPresent($button('from'));

    $page->click('[dusk="transaction-type-transfer"]');
    $this->confirmSwal($page);
    $page->assertNotPresent($button('to'))
        ->assertNotPresent($button('from'));
})->group('critical');

it('lets the user use the reconciled, date and comment fields in the modal', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->assertPresent('#checkbox-standard-transaction-reconciled')
        ->click('label[for="checkbox-standard-transaction-reconciled"]')
        ->assertChecked('#checkbox-standard-transaction-reconciled')
        ->click('label[for="checkbox-standard-transaction-reconciled"]')
        ->assertNotChecked('#checkbox-standard-transaction-reconciled')
        ->assertPresent('#standard-date')
        ->script("() => {
            const el = document.querySelector('#standard-date');
            el.value = '2025-01-15';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        }");

    $page->assertValue('#standard-date', '2025-01-15')
        ->assertPresent('#standard-comment')
        ->type('#standard-comment', 'Test comment from modal')
        ->assertValue('#standard-comment', 'Test comment from modal');
})->group('critical');

it('submits a withdrawal transaction form in the modal', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    // Account from is preset to the page's account
    $this->assertTomSelectValues($page, 'account_from', [$this->account->id]);
    $this->chooseTomSelectOption($page, 'account_to', 'Auchan', 'Auchan');
    $page->type('#transaction_amount_from', '100');

    $categorySelectId = $this->addTransactionItem($page);
    $this->chooseTomSelectOption($page, $categorySelectId, 'Groceries', Category::firstWhere('name', 'Groceries')->full_name);
    $page->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100')
        ->click('#transactionFormStandard-Save');

    $this->waitUntil($page, standardModalIsClosed());
    $this->waitForTextIn($page, '.toast-container .toast.bg-success.show', 'Transaction added');

    $transaction = Transaction::orderByDesc('id')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->config->amount_from->getAmount()->toFloat())->toEqual(100);
})->group('critical');

it('keeps the focus inside the transaction type confirm dialog', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->click('[dusk="transaction-type-deposit"]');
    $this->waitUntil($page, "document.querySelector('.swal2-popup')");

    // The CoreUI modal's focus trap only leaves focus alone inside elements it contains: the dialog must
    // render inside the modal, or a keyboard confirm/cancel would hit the form instead.
    $page->assertScript("!!document.activeElement?.closest('.swal2-popup')", true);

    $this->confirmSwal($page);
})->group('critical');

it('closes the modal without confirmation when the form is untouched', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->click(STANDARD_MODAL . ' .btn-close');
    $this->waitUntil($page, standardModalIsClosed());
    $page->assertNotPresent('.swal2-popup');
})->group('critical');

it('confirms discarding the changes when closing the modal with a dirty form', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->type('#standard-comment', 'dirty comment')
        ->click(STANDARD_MODAL . ' .btn-close');
    $this->confirmSwal($page);
    $this->waitUntil($page, standardModalIsClosed());
})->group('critical');

it('closes the modal with the Escape key when the form is untouched', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    // Focus must land inside the modal on open, otherwise a bubbled Escape keydown never reaches CoreUI's
    // dismiss listener on the modal root
    $page->assertScript("!!document.activeElement?.closest('" . STANDARD_MODAL . "')", true);

    // Sent to whatever has focus, to prove the key reaches the modal through normal bubbling
    $page->keys(':focus', 'Escape');

    $this->waitUntil($page, standardModalIsClosed());
    $page->assertNotPresent('.swal2-popup');
})->group('critical');

it('confirms discarding the changes when pressing Escape with a dirty form', function () {
    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->type('#standard-comment', 'dirty comment')
        ->keys(':focus', 'Escape');

    $this->confirmSwal($page);
    $this->waitUntil($page, standardModalIsClosed());
})->group('critical');

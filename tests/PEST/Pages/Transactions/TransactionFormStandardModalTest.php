<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;

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

    // The payee side is still empty, so nothing is lost and no confirmation is asked
    $page->click('[dusk="transaction-type-transfer"]');
    $this->waitUntil($page, "document.querySelector('[dusk=\"transaction-type-transfer\"]').classList.contains('active')");
    $page->assertNotPresent('.swal2-popup')
        ->assertNotPresent($button('to'))
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

it('finds an inactive payee by its alias, reactivates it for the transaction and deactivates it again after saving', function () {
    $payee = AccountEntity::factory()->asPayee($this->user)->create([
        'name' => 'Dormant Bakery',
        'alias' => 'zzcrumbs',
        'active' => false,
    ]);

    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    // Record PATCH calls, to wait for the deactivation that follows the save
    $page->script("() => { window.__patches = []; window.axios.interceptors.response.use((r) => { if (r.config.method === 'patch') window.__patches.push(r.config.data); return r; }); }");

    // Searching by alias lists the inactive payee, labeled as such
    $this->searchTomSelect($page, 'account_to', 'zzcrumbs');
    $page->assertSeeIn('#account_to-ts-dropdown', 'Dormant Bakery')
        ->assertSeeIn('#account_to-ts-dropdown', 'inactive');
    $page->click("#account_to-ts-dropdown [data-value=\"{$payee->id}\"]");

    // Picking it reactivates it, and offers to put it back
    $this->waitUntil($page, "document.querySelector('#checkbox-deactivate-reactivated')");
    expect($payee->fresh()->active)->toBeTrue();
    $page->assertChecked('#checkbox-deactivate-reactivated');

    $page->type('#transaction_amount_from', '10')
        ->click('#transactionFormStandard-Save');
    $this->waitUntil($page, standardModalIsClosed());
    $this->waitForTextIn($page, '.toast-container .toast.bg-success.show', 'Transaction added');

    // Back to how it was
    $this->waitUntil($page, "window.__patches.includes('{\"active\":false}')");
    expect($payee->fresh()->active)->toBeFalse();
})->group('critical');

it('finds an inactive account by its alias, reactivates it for the transaction and deactivates it again after saving', function () {
    $account = AccountEntity::factory()->asAccount($this->user)->create([
        'name' => 'Dormant Vault',
        'alias' => 'zzvault',
        'active' => false,
    ]);

    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    $page->script("() => { window.__patches = []; window.axios.interceptors.response.use((r) => { if (r.config.method === 'patch') window.__patches.push(r.config.data); return r; }); }");

    $this->searchTomSelect($page, 'account_from', 'zzvault');
    $page->assertSeeIn('#account_from-ts-dropdown', 'Dormant Vault')
        ->assertSeeIn('#account_from-ts-dropdown', 'inactive');
    $page->click("#account_from-ts-dropdown [data-value=\"{$account->id}\"]");

    $this->waitUntil($page, "document.querySelector('#checkbox-deactivate-reactivated')");
    expect($account->fresh()->active)->toBeTrue();

    $this->chooseTomSelectOption($page, 'account_to', 'Auchan', 'Auchan');
    $page->type('#transaction_amount_from', '10')
        ->click('#transactionFormStandard-Save');
    $this->waitUntil($page, standardModalIsClosed());
    $this->waitForTextIn($page, '.toast-container .toast.bg-success.show', 'Transaction added');

    $this->waitUntil($page, "window.__patches.includes('{\"active\":false}')");
    expect($account->fresh()->active)->toBeFalse();
})->group('extended');

it('offers a retry when the account and payee lists fail to load, and loads them on retry', function () {
    // Fail the first usage request only, as a flaky network would
    $failed = false;
    Event::listen(RouteMatched::class, function (RouteMatched $event) use (&$failed) {
        if (! $failed && $event->route->getName() === 'api.v1.reference-data.usage') {
            $failed = true;
            abort(500);
        }
    });

    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    $this->waitUntil($page, "document.querySelector('#account-date-range-filter').__vue_app__");
    $page->click('#create-standard-transaction-button');
    $this->waitUntil($page, "document.querySelector('" . STANDARD_MODAL . "').classList.contains('show')");

    // The alert appears only once the failed request comes back
    $this->waitUntil($page, "document.querySelector('#transactionFormStandard .alert button')");
    $page->assertSee('Could not load the account, payee, category and tag lists.')
        ->click('#transactionFormStandard .alert button');

    // Once the lists are in, the form settles and the selects work
    $this->waitUntil($page, "document.querySelector('#transactionFormStandard').dataset.loaded === 'true'");
    $page->assertDontSee('Could not load the account, payee, category and tag lists.');
    $this->chooseTomSelectOption($page, 'account_to', 'Auchan', 'Auchan');
})->group('extended');

it('ranks a payee used in an earlier transaction of the same modal session', function () {
    $payee = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'Zeta Fresh Market']);

    // Which group of the account_to dropdown lists the new payee, if any
    $groupOfPayee = "(() => { const o = document.querySelector('#account_to-ts-dropdown [data-value=\"{$payee->id}\"]'); return o ? o.closest('.optgroup').querySelector('.optgroup-header').textContent.trim() : null; })()";
    $openDropdown = function ($page) {
        $page->click('#account_to + .ts-wrapper .ts-control');
        $this->waitUntil($page, "document.querySelector('#account_to').tomselect.isOpen");
    };

    $page = visit(route('account-entity.show', ['account_entity' => $this->account->id]));
    standardModalOpen($page);

    // No history yet: not among the suggestions
    $openDropdown($page);
    expect(['Most used', 'Recent'])->not->toContain($page->script("() => {$groupOfPayee}"));
    $page->script("() => document.querySelector('#account_to').tomselect.close()");

    $this->chooseTomSelectOption($page, 'account_to', 'Zeta Fresh', 'Zeta Fresh Market');
    $page->type('#transaction_amount_from', '10')
        ->click('#transactionFormStandard-Save');
    $this->waitUntil($page, standardModalIsClosed());
    $this->waitForTextIn($page, '.toast-container .toast.bg-success.show', 'Transaction added');

    // Same page, no reload: the saved transaction already counts
    standardModalOpen($page);
    $openDropdown($page);
    expect(['Most used', 'Recent'])->toContain($page->script("() => {$groupOfPayee}"));
})->group('extended');

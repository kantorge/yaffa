<?php

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionDetailStandard;
use App\Models\User;

/*
 * Converted from the Dusk test of the same name (tests/Browser/Pages/Transactions), one it() per method.
 * Random dropdown picks became fixed demo-seed accounts, payees and categories, so failures are reproducible.
 */

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

// Tests call `$page = visit(...)` themselves: the browser plugin only treats a test as a browser test if its own
// closure does (see tests/CLAUDE.md)
function standardStandaloneReady(object $page): object
{
    test()->waitUntil($page, "document.querySelector('#account_from')?.tomselect && document.querySelector('#account_to')?.tomselect");

    return $page;
}

function standardStandaloneFillWithdrawal(object $page): object
{
    test()->chooseTomSelectOption($page, 'account_from', 'Cash account EUR', 'Cash account EUR');
    test()->chooseTomSelectOption($page, 'account_to', 'Auchan', 'Auchan');
    $page->type('#transaction_amount_from', '100');

    $categorySelectId = test()->addTransactionItem($page);
    test()->chooseTomSelectOption($page, $categorySelectId, 'Groceries', Category::firstWhere('name', 'Groceries')->full_name);

    return $page->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100');
}

function standardStandaloneSave(object $page, string $callback): void
{
    $page->click("[dusk=\"action-after-save-desktop-button-group\"] button[value=\"{$callback}\"]")
        ->click('#transactionFormStandard-Save');
}

function standardStandaloneSwitchType(object $page, string $type): void
{
    $page->click("[dusk=\"transaction-type-{$type}\"]");
    test()->confirmSwal($page);
}

const STANDARD_FORM_SAVED = "document.querySelector('#BootstrapNotificationContainer')?.textContent.includes('Transaction added')";

it('loads the standard transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    $buttons = '[dusk="action-after-save-desktop-button-group"]';

    $page->assertVisible($buttons)
        ->assertVisible('#transactionFormStandard-Save')
        ->assertPresent("{$buttons} button[value=\"returnToPrimaryAccount\"]")
        ->assertNotPresent("{$buttons} button[value=\"returnToSecondaryAccount\"]");

    standardStandaloneSwitchType($page, 'transfer');

    $page->assertPresent("{$buttons} button[value=\"returnToPrimaryAccount\"]")
        ->assertPresent("{$buttons} button[value=\"returnToSecondaryAccount\"]");
})->group('critical');

it('does not submit the standard transaction form with errors', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);

    $page->click('#transactionFormStandard-Save');
    $this->waitUntil($page, "document.querySelector('#transactionFormStandard .alert.alert-danger')");

    $page->assertRoute('transaction.create', ['type' => 'standard']);
})->group('critical');

it('displays the currency correctly for various settings', function () {
    $amountFromCurrency = '[dusk="label-amountFrom-currency"]';
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);

    $page->assertNotPresent($amountFromCurrency);

    // Withdrawal: the currency follows account from
    $this->chooseTomSelectOption($page, 'account_from', 'Investment account EUR', 'Investment account EUR');
    $page->assertSeeIn('#account_from + .ts-wrapper .ts-control', 'Investment account EUR');
    $this->waitForTextIn($page, $amountFromCurrency, '€');

    $this->clearTomSelect($page, 'account_from');
    $this->waitUntil($page, "!document.querySelector('{$amountFromCurrency}')");

    // Deposit: the type change resets both sides, then the currency follows account to
    $this->chooseTomSelectOption($page, 'account_from', 'Investment account EUR', 'Investment account EUR');
    standardStandaloneSwitchType($page, 'deposit');
    $page->assertNotPresent($amountFromCurrency);

    $this->chooseTomSelectOption($page, 'account_to', 'Investment account EUR', 'Investment account EUR');
    $this->waitForTextIn($page, $amountFromCurrency, '€');

    $this->clearTomSelect($page, 'account_to');
    $this->waitUntil($page, "!document.querySelector('{$amountFromCurrency}')");

    // Transfer: account to stays selected (it is an account on both types), but its currency is reset
    $this->chooseTomSelectOption($page, 'account_to', 'Investment account EUR', 'Investment account EUR');
    standardStandaloneSwitchType($page, 'transfer');
    $page->assertSeeIn('#account_to + .ts-wrapper .ts-control', 'Investment account EUR')
        ->assertNotPresent($amountFromCurrency);

    $this->chooseTomSelectOption($page, 'account_from', 'Cash account EUR', 'Cash account EUR');
    $this->waitForTextIn($page, $amountFromCurrency, '€');

    // A different currency on account from shows both amounts with their own currencies
    $this->chooseTomSelectOption($page, 'account_from', 'Cash account USD', 'Cash account USD');
    $this->waitForTextIn($page, $amountFromCurrency, '$');
    $this->waitForTextIn($page, '[dusk="label-amountTo-currency"]', '€');
})->group('critical');

it('submits a withdrawal transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    $page->click('#transactionFormStandard-Save');

    $this->waitUntil($page, STANDARD_FORM_SAVED);
})->group('critical');

it('submits a deposit transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    standardStandaloneSwitchType($page, 'deposit');

    $this->chooseTomSelectOption($page, 'account_to', 'Cash account EUR', 'Cash account EUR');
    $this->chooseTomSelectOption($page, 'account_from', 'Auchan', 'Auchan');
    $page->type('#transaction_amount_from', '100');

    $categorySelectId = $this->addTransactionItem($page);
    $this->chooseTomSelectOption($page, $categorySelectId, 'Groceries', Category::firstWhere('name', 'Groceries')->full_name);
    $page->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100')
        ->click('#transactionFormStandard-Save');

    $this->waitUntil($page, STANDARD_FORM_SAVED);
})->group('critical');

it('submits a transfer transaction form with the same currencies', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    standardStandaloneSwitchType($page, 'transfer');

    $this->chooseTomSelectOption($page, 'account_from', 'Cash account USD', 'Cash account USD');
    $this->chooseTomSelectOption($page, 'account_to', 'Investment account USD', 'Investment account USD');
    $page->type('#transaction_amount_from', '100')
        ->assertMissing('#transaction_amount_to')
        ->click('#transactionFormStandard-Save');

    $this->waitUntil($page, STANDARD_FORM_SAVED);
})->group('critical');

it('submits a transaction form with different currencies', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    standardStandaloneSwitchType($page, 'transfer');

    $this->chooseTomSelectOption($page, 'account_from', 'Cash account USD', 'Cash account USD');
    $this->chooseTomSelectOption($page, 'account_to', 'Investment account EUR', 'Investment account EUR');
    $page->type('#transaction_amount_from', '100');
    $this->waitUntil($page, "document.querySelector('#transaction_amount_to')");

    // Amount to is required when the currencies differ
    $page->click('#transactionFormStandard-Save');
    $this->waitForTextIn($page, '#transactionFormStandard .alert', 'The amount to field is required.');

    // The exchange rate is shown once amount to is set
    $page->type('#transaction_amount_to', '100')
        ->click('#transactionFormStandard');
    $this->waitForTextIn($page, '[dusk="label-transaction-exchange-rate"]', '1.0000');

    $page->click('#transactionFormStandard-Save');
    $this->waitUntil($page, STANDARD_FORM_SAVED);
})->group('critical');

it('submits a transaction form with schedule')->todo();

it('opens a new form after saving with the "add an other transaction" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    standardStandaloneSave($page, 'create');

    $this->waitUntil($page, STANDARD_FORM_SAVED);
    $page->assertRoute('transaction.create', ['type' => 'standard']);
})->group('critical');

it('opens the clone form after saving with the "clone this transaction" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    standardStandaloneSave($page, 'clone');

    $this->waitUntil($page, "location.pathname.includes('/clone')");
    $page->assertRoute('transaction.open', [
        'action' => 'clone',
        'transaction' => Transaction::orderByDesc('id')->first()->id,
    ]);
})->group('critical');

it('shows the transaction after saving with the "show transaction" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    standardStandaloneSave($page, 'show');

    $this->waitUntil($page, "location.pathname.includes('/show')");
    $page->assertRoute('transaction.open', [
        'action' => 'show',
        'transaction' => Transaction::orderByDesc('id')->first()->id,
    ]);
})->group('critical');

it('returns to the selected account after saving with the "return to selected account" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    standardStandaloneSave($page, 'returnToPrimaryAccount');

    $account = AccountEntity::firstWhere('name', 'Cash account EUR');
    $this->waitUntil($page, "location.pathname === '" . route('account-entity.show', ['account_entity' => $account->id], false) . "'");
    expect(Transaction::orderByDesc('id')->with('config')->first()->config->account_from_id)->toBe($account->id);
})->group('critical');

it('returns to the target account of a transfer after saving with the "return to target account" callback', function () {
    $account = AccountEntity::firstWhere('name', 'Cash account USD');

    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    standardStandaloneSwitchType($page, 'transfer');

    $this->chooseTomSelectOption($page, 'account_to', $account->name, $account->name);
    $this->chooseTomSelectOption($page, 'account_from', 'Investment account EUR', 'Investment account EUR');
    $this->waitUntil($page, "document.querySelector('#transaction_amount_to')");
    $page->type('#transaction_amount_from', '100')
        ->type('#transaction_amount_to', '100');
    standardStandaloneSave($page, 'returnToSecondaryAccount');

    $path = route('account-entity.show', ['account_entity' => $account->id], false);
    $this->waitUntil($page, "location.pathname === '{$path}'");
})->group('critical');

it('returns to the dashboard after saving with the "return to dashboard" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    standardStandaloneSave($page, 'returnToDashboard');

    $this->waitUntil($page, "location.pathname === '" . route('home', [], false) . "'");
})->group('critical');

it('returns to the previous page after saving with the "return to previous page" callback', function () {
    $page = visit(route('tags.index'));
    $page->navigate(route('transaction.create', ['type' => 'standard']));

    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    standardStandaloneSave($page, 'back');

    $this->waitUntil($page, "location.pathname === '" . route('tags.index', [], false) . "'");
})->group('critical');

it('adds multiple transaction items')->todo();

it('adds a new payee through the modal and uses it', function () {
    $category = Category::factory()->create([
        'user_id' => $this->user->id,
        'parent_id' => Category::inRandomOrder()->parentCategory()->first()->id,
        'active' => true,
    ]);

    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    $this->chooseTomSelectOption($page, 'account_from', 'Cash account EUR', 'Cash account EUR');

    $page->click('#account_to_container > button');
    $this->waitUntil($page, "document.querySelector('#newPayeeModal').classList.contains('show')");
    $page->type('#newPayeeModal-name', 'New Payee From Modal');
    $this->chooseTomSelectOption($page, 'newPayeeModal-category_id', $category->name, $category->full_name);
    $page->click('#newPayeeModal button[type="submit"]');
    $this->waitUntil($page, "!document.querySelector('#newPayeeModal').classList.contains('show')");

    // The new payee is preset on the payee side
    $payee = $this->user->payees()->firstWhere('name', 'New Payee From Modal');
    $this->assertTomSelectValues($page, 'account_to', [$payee->id]);

    $page->type('#transaction_amount_from', '100');
    $categorySelectId = $this->addTransactionItem($page);
    $this->chooseTomSelectOption($page, $categorySelectId, $category->name, $category->full_name);
    $page->type('#transaction_item_container .transaction_item_row input.transaction_item_amount', '100');
    standardStandaloneSave($page, 'show');

    $this->waitUntil($page, "location.pathname.includes('/show')");
    $transaction = Transaction::orderByDesc('id')->with('config.accountTo')->first();
    expect($transaction->config->account_to_id)->toBe($payee->id);
    $this->waitForTextIn($page, '[dusk="label-account-to-name"]', $transaction->config->accountTo->name);
})->group('critical');

it('reactivates a payee through the new payee modal', function () {
    $payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => false]);
    $similarPayee = "#newPayeeModal #similar-payee-list li[data-id=\"{$payee->id}\"]";

    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    // Real key presses: the similar payee search runs on keyup
    $page->click('#account_to_container > button')
        ->typeSlowly('#newPayeeModal-name', $payee->name, 0);

    $this->waitForTextIn($page, $similarPayee, $payee->name);
    $page->assertSeeIn($similarPayee, '(inactive)')
        ->click("{$similarPayee} a");

    $this->waitUntil($page, "!document.querySelector('#newPayeeModal').classList.contains('show')");
    $this->assertTomSelectValues($page, 'account_to', [$payee->id]);
    $page->assertSeeIn('#account_to + .ts-wrapper .ts-control', $payee->name);

    expect($payee->fresh()->active)->toBeTrue();
})->group('critical');

it('shows the add new payee button next to the payee side of the transaction type', function () {
    $button = fn (string $side) => "#account_{$side}_container > button[data-coreui-target=\"#newPayeeModal\"]";

    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    $page->assertVisible($button('to'));

    standardStandaloneSwitchType($page, 'deposit');
    $page->assertVisible($button('from'));

    standardStandaloneSwitchType($page, 'transfer');
    $page->assertMissing($button('to'))
        ->assertMissing($button('from'));
})->group('critical');

it('does not allow transaction items for the transfer transaction type', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);
    $page->type('#transaction_amount_from', '100');
    $this->addTransactionItem($page);

    standardStandaloneSwitchType($page, 'transfer');
    $page->assertDisabled('[dusk="button-add-transaction-item"]')
        ->assertMissing('#transaction_item_container .transaction_item_row');

    standardStandaloneSwitchType($page, 'withdrawal');
    $page->assertEnabled('[dusk="button-add-transaction-item"]')
        ->assertMissing('#transaction_item_container .transaction_item_row');
})->group('critical');

it('loads the edit form of a transfer with different currencies correctly', function () {
    $transaction = Transaction::factory()
        ->for($this->user)
        ->for(
            TransactionDetailStandard::factory()->create([
                'amount_from' => 10,
                'amount_to' => 20,
                'account_from_id' => AccountEntity::firstWhere('name', 'Cash account USD')->id,
                'account_to_id' => AccountEntity::firstWhere('name', 'Cash account EUR')->id,
            ]),
            'config'
        )
        ->create([
            'transaction_type' => TransactionTypeEnum::TRANSFER->value,
            'config_type' => 'standard',
        ]);

    $page = visit(route('transaction.open', ['action' => 'edit', 'transaction' => $transaction->id]));
    $this->assertTomSelectValues($page, 'account_from', [$transaction->config->account_from_id]);
    $this->assertTomSelectValues($page, 'account_to', [$transaction->config->account_to_id]);
    $this->waitUntil($page, "document.querySelector('#transaction_amount_to')");

    $page->assertValue('#transaction_amount_from', '10')
        ->assertValue('#transaction_amount_to', '20');
    $this->waitForTextIn($page, '[dusk="label-transaction-exchange-rate"]', '2.0000');
})->group('critical');

it('changes the date on the standard form', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));

    $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
    $page->script("() => {
        const el = document.querySelector('#standard-date');
        el.value = '{$previousMonthStart}';
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }");
    standardStandaloneSave($page, 'show');

    $this->waitUntil($page, "location.pathname.includes('/show')");
    expect(Transaction::orderByDesc('id')->first()->date->format('Y-m-d'))->toBe($previousMonthStart);
})->group('critical');

it('loads all details of the source transaction into the clone form', function () {
    // make() + save() instead of create(), to skip the afterCreating callback that adds random items
    Transaction::factory()
        ->for($this->user)
        ->for(
            TransactionDetailStandard::factory()->create([
                'amount_from' => 100,
                'amount_to' => 100,
                'account_from_id' => $this->user->accounts->first()->id,
                'account_to_id' => $this->user->payees->first()->id,
            ]),
            'config'
        )
        ->make([
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'config_type' => 'standard',
        ])
        ->save();

    $transaction = Transaction::orderByDesc('id')->first();
    $transaction->transactionItems()->create([
        'category_id' => $this->user->categories->first()->id,
        'amount' => 50,
        'comment' => 'Test comment',
    ]);
    $transaction->transactionItems()
        ->create([
            'category_id' => $this->user->categories->last()->id,
            'amount' => 50,
            'comment' => null,
        ])
        ->tags()
        ->attach($this->user->tags->first()->id);
    $items = $transaction->transactionItems()->with('tags')->orderBy('id')->get();

    $page = visit(route('transaction.open', ['action' => 'clone', 'transaction' => $transaction->id]));

    // The Dusk test had these account assertions commented out (the old select widget made them unreliable)
    $this->assertTomSelectValues($page, 'account_from', [$transaction->config->account_from_id]);
    $this->assertTomSelectValues($page, 'account_to', [$transaction->config->account_to_id]);

    // The items keep their creation order
    $this->assertTomSelectValues($page, $this->tomSelectIdByTestId($page, 'transaction-item-category-0'), [$items[0]->category_id]);
    $this->assertTomSelectValues($page, $this->tomSelectIdByTestId($page, 'transaction-item-tags-0'), []);
    $this->assertTomSelectValues($page, $this->tomSelectIdByTestId($page, 'transaction-item-category-1'), [$items[1]->category_id]);
    $this->assertTomSelectValues($page, $this->tomSelectIdByTestId($page, 'transaction-item-tags-1'), [$items[1]->tags->first()->id]);

    $page->assertValue('#transaction_amount_from', '100')
        ->assertValue('#transaction_amount_to', '100')
        ->assertValue('#transaction_item_0 input.transaction_item_amount', '50')
        ->assertValue('#transaction_item_0 input.transaction_item_comment', 'Test comment')
        ->assertValue('#transaction_item_1 input.transaction_item_amount', '50')
        ->assertValue('#transaction_item_1 input.transaction_item_comment', '');
})->group('critical');

it('resets the next date of the original transaction by default when replacing a scheduled item', function () {
    // The transaction factory also creates the schedule
    $transaction = Transaction::factory()
        ->for($this->user)
        ->for(TransactionDetailStandard::factory()->withdrawal($this->user)->create(), 'config')
        ->create([
            'transaction_type' => TransactionTypeEnum::WITHDRAWAL->value,
            'config_type' => 'standard',
            'schedule' => true,
            'reconciled' => false,
        ]);

    $page = visit(route('transaction.open', ['action' => 'replace', 'transaction' => $transaction->id]));
    $this->waitUntil($page, "document.querySelector('#transaction_schedule_current') && document.querySelector('#transaction_schedule_original')");
    $page->assertVisible('#transaction_schedule_current')
        ->assertVisible('#transaction_schedule_original')
        ->clear('#schedule_end_current');
    standardStandaloneSave($page, 'show');

    $this->waitUntil($page, "location.pathname.includes('/show')");
    $newTransaction = Transaction::orderByDesc('id')->with('transactionSchedule')->first();
    expect($newTransaction->transactionSchedule->start_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'))
        ->and($newTransaction->transactionSchedule->next_date->format('Y-m-d'))->toBe(now()->format('Y-m-d'))
        ->and($transaction->fresh('transactionSchedule')->transactionSchedule->next_date)->toBeNull();
})->group('critical');

/*
 * Not in the Dusk file: tag creation (spec FR-6) and the category's select-on-close (spec FR-1 item 8, owner review R2).
 * The position of the create option (owner review R1) is deliberately not asserted.
 */

it('creates a new tag and keeps existing ones in a transaction item', function () {
    $existingTag = $this->user->tags()->firstWhere('name', 'Kids');

    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneFillWithdrawal(standardStandaloneReady($page));
    $tagSelectId = $this->tomSelectIdByTestId($page, 'transaction-item-tags-0');

    // An existing tag's name, in any case, is not offered for creation
    $this->searchTomSelect($page, $tagSelectId, 'kids');
    $page->assertNotPresent("#{$tagSelectId}-ts-dropdown .create");
    $this->chooseTomSelectOption($page, $tagSelectId, 'Kids', 'Kids');

    // A new name is offered as "<name> (new)" and selected as its text
    $this->searchTomSelect($page, $tagSelectId, 'Brand new tag');
    $page->assertSeeIn("#{$tagSelectId}-ts-dropdown .create", 'Brand new tag (new)')
        ->click("#{$tagSelectId}-ts-dropdown .create");
    $this->assertTomSelectValues($page, $tagSelectId, [$existingTag->id, 'Brand new tag']);

    standardStandaloneSave($page, 'show');
    $this->waitUntil($page, "location.pathname.includes('/show')");

    $tags = Transaction::orderByDesc('id')->first()->transactionItems()->first()->tags;
    expect($tags->pluck('name')->sort()->values()->all())->toBe(['Brand new tag', 'Kids'])
        ->and($tags->firstWhere('name', 'Kids')->id)->toBe($existingTag->id);
})->group('critical');

it('does not select the highlighted item category when the dropdown closes by Tab or click-away', function () {
    $page = visit(route('transaction.create', ['type' => 'standard']));
    standardStandaloneReady($page);

    // Tab away from a search
    $firstSelectId = $this->addTransactionItem($page);
    $this->searchTomSelect($page, $firstSelectId, 'Groceries');
    $page->keys("#{$firstSelectId} + .ts-wrapper .dropdown-input", 'Tab');
    $this->waitUntil($page, "!document.querySelector('#{$firstSelectId}').tomselect.isOpen");
    $this->assertTomSelectValues($page, $firstSelectId, []);

    // Click away from a search
    $secondSelectId = $this->addTransactionItem($page);
    $this->searchTomSelect($page, $secondSelectId, 'Groceries');
    $page->click('#transaction_amount_from');
    $this->waitUntil($page, "!document.querySelector('#{$secondSelectId}').tomselect.isOpen");
    $this->assertTomSelectValues($page, $secondSelectId, []);
})->group('critical');

<?php

use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\User;

/*
 * Converted from the Dusk test of the same name (tests/Browser/Pages/Transactions), one it() per method, without the
 * Dusk file's retry() wrappers. Also covers the investment select's escaping (T-SEC-1) and the account/investment
 * cross-filtering without stale results (T-CACHE-1).
 */

const INVESTMENT_USD = 'Test investment USD';
const INVESTMENT_USD_OPTION = 'Test investment USD (TIUSD)';
const INVESTMENT_EUR = 'Test investment EUR';
const INVESTMENT_EUR_OPTION = 'Test investment EUR (TIEUR)';
const INVESTMENT_ACCOUNT_USD = 'Investment account USD';
const INVESTMENT_ACCOUNT_EUR = 'Investment account EUR';
const INVESTMENT_FORM_SAVED = "document.querySelector('#BootstrapNotificationContainer')?.textContent.includes('Transaction added')";

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

// Tests call `$page = visit(...)` themselves (see tests/CLAUDE.md)
function investmentStandaloneReady(object $page): object
{
    test()->waitUntil($page, "document.querySelector('#account')?.tomselect && document.querySelector('#investment')?.tomselect");

    return $page;
}

function investmentStandaloneFillBuy(object $page): object
{
    $page->select('#transaction_type', 'buy')
        ->type('#transaction_quantity', '10')
        ->type('#transaction_price', '20')
        ->type('#transaction_commission', '30')
        ->type('#transaction_tax', '40');
    test()->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    test()->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);

    return $page;
}

function investmentStandaloneSave(object $page, string $callback): void
{
    $page->click("[dusk=\"action-after-save-desktop-button-group\"] button[value=\"{$callback}\"]")
        ->click('#transactionFormInvestment-Save');
}

// The labels currently listed in the open dropdown of <select id="$selectId">
function investmentDropdownLabels(object $page, string $selectId): array
{
    return $page->script("() => [...document.querySelectorAll('#{$selectId}-ts-dropdown [data-selectable]')].map((option) => option.textContent.trim())");
}

it('loads the investment transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $page->assertPresent('#transactionFormInvestment');
})->group('critical');

it('does not submit the investment transaction form with errors', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $page->click('#transactionFormInvestment-Save');
    $this->waitUntil($page, "document.querySelector('#transactionFormInvestment .alert.alert-danger')");

    $page->assertRoute('transaction.create', ['type' => 'investment']);
})->group('critical');

it('limits the investments to the currency of the selected account', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $page->assertSeeIn('#account + .ts-wrapper .ts-control', INVESTMENT_ACCOUNT_USD);
    // The account's currency has loaded once the total shows it
    $this->waitForTextIn($page, '[dusk="transaction-total-value"]', '$');

    // Open the investment dropdown without a search term
    $this->searchTomSelect($page, 'investment', '');
    expect(investmentDropdownLabels($page, 'investment'))
        ->toContain(INVESTMENT_USD_OPTION)
        ->not->toContain(INVESTMENT_EUR_OPTION);
})->group('critical');

it('limits the accounts to the currency of the selected investment', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->assertSeeIn('#investment + .ts-wrapper .ts-control', INVESTMENT_USD);

    $this->searchTomSelect($page, 'account', 'Investment account');
    expect(investmentDropdownLabels($page, 'account'))
        ->toContain(INVESTMENT_ACCOUNT_USD)
        ->not->toContain(INVESTMENT_ACCOUNT_EUR);
})->group('critical');

it('displays the currency correctly for various settings (account first)', function () {
    $total = '[dusk="transaction-total-value"]';
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->waitForTextIn($page, $total, '$');

    $this->clearTomSelect($page, 'account');
    $this->waitUntil($page, "!document.querySelector('{$total}')");

    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_EUR, INVESTMENT_EUR_OPTION);
    $this->waitForTextIn($page, $total, '€');

    $this->clearTomSelect($page, 'investment');
    $this->waitUntil($page, "!document.querySelector('{$total}')");
})->group('critical');

it('displays the currency correctly for various settings (investment first)', function () {
    $total = '[dusk="transaction-total-value"]';
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_EUR, INVESTMENT_EUR_OPTION);
    $this->waitForTextIn($page, $total, '€');

    $this->clearTomSelect($page, 'investment');
    $this->waitUntil($page, "!document.querySelector('{$total}')");

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->waitForTextIn($page, $total, '$');

    $this->clearTomSelect($page, 'account');
    $this->waitUntil($page, "!document.querySelector('{$total}')");
})->group('critical');

it('submits a buy transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));

    $page->assertDisabled('#transaction_dividend')
        ->click('#transactionFormInvestment-Save');
    $this->waitUntil($page, INVESTMENT_FORM_SAVED);
})->group('critical');

it('submits a sell transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->select('#transaction_type', 'sell')
        ->assertDisabled('#transaction_dividend')
        ->type('#transaction_quantity', '10')
        ->type('#transaction_price', '20')
        ->type('#transaction_commission', '30')
        ->type('#transaction_tax', '40')
        ->click('#transactionFormInvestment-Save');

    $this->waitUntil($page, INVESTMENT_FORM_SAVED);
})->group('critical');

it('submits a dividend transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $page->select('#transaction_type', 'dividend');
    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->assertDisabled('#transaction_quantity')
        ->assertDisabled('#transaction_price')
        ->type('#transaction_dividend', '1000')
        ->type('#transaction_commission', '30')
        ->type('#transaction_tax', '40')
        // No price to store for a dividend
        ->assertMissing('#store_price_checkbox')
        ->click('#transactionFormInvestment-Save');

    $this->waitUntil($page, INVESTMENT_FORM_SAVED);
})->group('critical');

it('submits an add shares transaction form', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->select('#transaction_type', 'add_shares')
        ->assertDisabled('#transaction_price')
        ->type('#transaction_quantity', '10')
        ->type('#transaction_commission', '30')
        ->type('#transaction_tax', '40')
        ->assertDisabled('#transaction_dividend')
        ->click('#transactionFormInvestment-Save');

    $this->waitUntil($page, INVESTMENT_FORM_SAVED);
})->group('critical');

it('submits a transaction with schedule')->todo();

it('opens a new form after saving with the "add an other transaction" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'create');

    $this->waitUntil($page, INVESTMENT_FORM_SAVED);
    $page->assertRoute('transaction.create', ['type' => 'investment']);
})->group('critical');

it('opens the clone form after saving with the "clone transaction" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'clone');

    $this->waitUntil($page, "location.pathname.includes('/clone')");
    $page->assertRoute('transaction.open', [
        'action' => 'clone',
        'transaction' => Transaction::orderByDesc('id')->first()->id,
    ]);
})->group('critical');

it('shows the transaction after saving with the "show transaction" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'show');

    $this->waitUntil($page, "location.pathname.includes('/show')");
    $page->assertRoute('transaction.open', [
        'action' => 'show',
        'transaction' => Transaction::orderByDesc('id')->first()->id,
    ]);
})->group('critical');

it('returns to the selected account after saving with the "return to selected account" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'returnToPrimaryAccount');

    $account = $this->user->accounts()->firstWhere('name', INVESTMENT_ACCOUNT_USD);
    $this->waitUntil($page, "location.pathname === '" . route('account-entity.show', ['account_entity' => $account->id], false) . "'");
    expect(Transaction::orderByDesc('id')->with('config')->first()->config->account_id)->toBe($account->id);
})->group('critical');

it('returns to the selected investment after saving with the "return to selected investment" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'returnToInvestment');

    $investment = $this->user->investments()->firstWhere('name', INVESTMENT_USD);
    $this->waitUntil($page, "location.pathname === '" . route('investments.show', ['investment' => $investment->id], false) . "'");
    expect(Transaction::orderByDesc('id')->with('config')->first()->config->investment_id)->toBe($investment->id);
})->group('critical');

it('returns to the dashboard after saving with the "return to dashboard" callback', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'returnToDashboard');

    $this->waitUntil($page, "location.pathname === '" . route('home', [], false) . "'");
})->group('critical');

it('returns to the previous page after saving with the "return to previous page" callback', function () {
    $page = visit(route('tags.index'));
    $page->navigate(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));
    investmentStandaloneSave($page, 'back');

    $this->waitUntil($page, "location.pathname === '" . route('tags.index', [], false) . "'");
})->group('critical');

it('changes the date on the investment form', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneFillBuy(investmentStandaloneReady($page));

    $previousMonthStart = now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
    $page->script("() => {
        const el = document.querySelector('#investment-date');
        el.value = '{$previousMonthStart}';
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }");
    investmentStandaloneSave($page, 'show');

    $this->waitUntil($page, "location.pathname.includes('/show')");
    expect(Transaction::orderByDesc('id')->first()->date->format('Y-m-d'))->toBe($previousMonthStart);
})->group('critical');

it('loads the default values from the URL', function () {
    $account = $this->user->accounts()->firstWhere('name', INVESTMENT_ACCOUNT_USD);
    $investment = $this->user->investments()->firstWhere('name', INVESTMENT_USD);

    $page = visit(route('transaction.create', [
        'type' => 'investment',
        'account' => $account->id,
        'investment' => $investment->id,
    ]));
    $this->assertTomSelectValues($page, 'account', [$account->id]);
    $this->assertTomSelectValues($page, 'investment', [$investment->id]);

    $page->assertSeeIn('#account + .ts-wrapper .ts-control', INVESTMENT_ACCOUNT_USD)
        ->assertSeeIn('#investment + .ts-wrapper .ts-control', INVESTMENT_USD);
})->group('critical');

it('shows the store price checkbox when there is no price for the date', function () {
    $investment = $this->user->investments()->firstWhere('name', INVESTMENT_USD);
    InvestmentPrice::where('investment_id', $investment->id)->where('date', now()->format('Y-m-d'))->delete();

    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->select('#transaction_type', 'buy')
        ->type('#transaction_quantity', '10')
        ->type('#transaction_price', '25.50');

    $this->waitUntil($page, "document.querySelector('#store_price_checkbox')");
    $page->assertVisible('#store_price_checkbox')
        ->assertVisible('label[for="store_price_checkbox"]');
})->group('critical');

it('hides the store price checkbox when a price exists for the date', function () {
    $investment = $this->user->investments()->firstWhere('name', INVESTMENT_USD);
    InvestmentPrice::updateOrCreate(
        ['investment_id' => $investment->id, 'date' => now()->format('Y-m-d')],
        ['price' => 100.00],
    );

    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $page->select('#transaction_type', 'buy');
    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->type('#transaction_quantity', '10')
        ->type('#transaction_price', '25.50');

    $this->waitUntil($page, "document.querySelector('span.existing-price-label')");
    $page->assertMissing('#store_price_checkbox');
})->group('critical');

it('saves a transaction with price storage enabled', function () {
    $investment = $this->user->investments()->firstWhere('name', INVESTMENT_USD);
    InvestmentPrice::where('investment_id', $investment->id)->where('date', now()->format('Y-m-d'))->delete();

    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_USD);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $page->select('#transaction_type', 'buy')
        ->type('#transaction_quantity', '10')
        ->type('#transaction_price', '35.75')
        ->type('#transaction_commission', '5')
        ->type('#transaction_tax', '2');

    $this->waitUntil($page, "document.querySelector('#store_price_checkbox')");
    $page->click('label[for="store_price_checkbox"]')
        ->assertChecked('#store_price_checkbox')
        ->click('#transactionFormInvestment-Save');

    $this->waitUntil($page, INVESTMENT_FORM_SAVED);
    $this->waitForTextIn($page, '#BootstrapNotificationContainer', 'Investment price stored');

    $transaction = Transaction::orderByDesc('id')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->config->quantity->toFloat())->toEqual(10)
        ->and($transaction->config->price->getAmount()->toFloat())->toEqual(35.75);

    $investmentPrice = InvestmentPrice::where('investment_id', $investment->id)->where('date', now()->format('Y-m-d'))->first();
    expect($investmentPrice)->not->toBeNull()
        ->and($investmentPrice->price->getAmount()->toFloat())->toEqual(35.75);
})->group('critical');

it('saves a transaction without price storage', function () {
    $investment = $this->user->investments()->firstWhere('name', INVESTMENT_EUR);
    InvestmentPrice::where('investment_id', $investment->id)->where('date', now()->format('Y-m-d'))->delete();

    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_EUR, INVESTMENT_ACCOUNT_EUR);
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_EUR, INVESTMENT_EUR_OPTION);
    $page->select('#transaction_type', 'buy')
        ->type('#transaction_quantity', '5')
        ->type('#transaction_price', '42.25')
        ->type('#transaction_commission', '3');

    $this->waitUntil($page, "document.querySelector('#store_price_checkbox')");
    $page->assertNotChecked('#store_price_checkbox')
        ->click('#transactionFormInvestment-Save');

    $this->waitUntil($page, INVESTMENT_FORM_SAVED);

    $transaction = Transaction::orderByDesc('id')->first();
    expect($transaction)->not->toBeNull()
        ->and($transaction->config->quantity->toFloat())->toEqual(5)
        ->and($transaction->config->price->getAmount()->toFloat())->toEqual(42.25);

    expect(InvestmentPrice::where('investment_id', $investment->id)->where('date', now()->format('Y-m-d'))->first())->toBeNull();
})->group('critical');

// T-SEC-1 (spec FR-7, AC-4)
it('shows an investment name containing HTML as literal text', function () {
    $name = '<img src=x onerror=window.__xss=1>';
    $investment = Investment::factory()->withUser($this->user)->create([
        'name' => $name,
        'symbol' => '<b>XSS</b>',
        'active' => true,
        'currency_id' => $this->user->currencies()->firstWhere('iso_code', 'USD')->id,
    ]);

    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    $this->chooseTomSelectOption($page, 'investment', 'onerror', "{$name} (<b>XSS</b>)");
    $this->assertTomSelectValues($page, 'investment', [$investment->id]);

    $page->assertSeeIn('#investment + .ts-wrapper .ts-control', $name)
        ->assertScript("document.querySelector('#investment + .ts-wrapper img, #investment + .ts-wrapper b')", null)
        ->assertScript('window.__xss', null);

    // Reopen: the listed option is escaped too
    $this->searchTomSelect($page, 'investment', 'onerror');
    $page->assertScript("document.querySelector('#investment-ts-dropdown img, #investment-ts-dropdown b')", null)
        ->assertScript('window.__xss', null);
})->group('critical');

// T-CACHE-1 (spec D4, AC-6): the currency filter is read on every request, also after a dropdown was already opened
it('filters the investments and accounts by the current selection without stale results', function () {
    $page = visit(route('transaction.create', ['type' => 'investment']));
    investmentStandaloneReady($page);

    // No account yet: investments of every currency are listed
    $this->searchTomSelect($page, 'investment', 'Test investment');
    expect(investmentDropdownLabels($page, 'investment'))->toContain(INVESTMENT_USD_OPTION, INVESTMENT_EUR_OPTION);
    $page->keys('#investment + .ts-wrapper .dropdown-input', 'Escape');

    // After picking an EUR account, the same search lists only EUR investments
    $this->chooseTomSelectOption($page, 'account', INVESTMENT_ACCOUNT_EUR, INVESTMENT_ACCOUNT_EUR);
    $this->waitForTextIn($page, '[dusk="transaction-total-value"]', '€');
    $this->searchTomSelect($page, 'investment', 'Test investment');
    expect(investmentDropdownLabels($page, 'investment'))->toContain(INVESTMENT_EUR_OPTION)->not->toContain(INVESTMENT_USD_OPTION);
    $page->keys('#investment + .ts-wrapper .dropdown-input', 'Escape');

    // The other direction: with the account cleared, accounts of every currency are listed...
    $this->clearTomSelect($page, 'account');
    $this->searchTomSelect($page, 'account', 'Investment account');
    expect(investmentDropdownLabels($page, 'account'))->toContain(INVESTMENT_ACCOUNT_USD, INVESTMENT_ACCOUNT_EUR);
    $page->keys('#account + .ts-wrapper .dropdown-input', 'Escape');

    // ...and after picking a USD investment, the same search lists only USD accounts
    $this->chooseTomSelectOption($page, 'investment', INVESTMENT_USD, INVESTMENT_USD_OPTION);
    $this->searchTomSelect($page, 'account', 'Investment account');
    expect(investmentDropdownLabels($page, 'account'))->toContain(INVESTMENT_ACCOUNT_USD)->not->toContain(INVESTMENT_ACCOUNT_EUR);
})->group('critical');

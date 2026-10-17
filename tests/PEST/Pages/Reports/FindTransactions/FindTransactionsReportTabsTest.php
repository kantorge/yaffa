<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

/*
 * Locks in the visible behaviour of the Find transactions report tabs, so the widgets can move to shared/
 * without changing it. Data lives in March/April 2019, where the demo data (last year only) never reaches.
 */

const REPORT_ROWS = "$('#tab-transaction-list table').DataTable().rows({ search: 'applied' }).count()";

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

/**
 * @param  array<int, array{Category, int}>  $items  [category, amount] pairs, replacing the factory's random items
 */
function reportWithdrawal(User $user, string $date, array $items): Transaction
{
    $transaction = Transaction::factory()->for($user)->withdrawal($user)->create(['date' => $date]);
    $transaction->transactionItems()->delete();
    foreach ($items as [$category, $amount]) {
        $transaction->transactionItems()->create(['category_id' => $category->id, 'amount' => $amount]);
    }

    return $transaction;
}

function reportCategories(User $user): array
{
    $suffix = uniqid();
    $parent = Category::factory()->for($user)->create(['name' => "Tabs parent {$suffix}", 'active' => true]);

    return [
        Category::factory()->for($user)->create(['name' => "Tabs child A {$suffix}", 'parent_id' => $parent->id, 'active' => true]),
        Category::factory()->for($user)->create(['name' => "Tabs child B {$suffix}", 'parent_id' => $parent->id, 'active' => true]),
    ];
}

function reportRange(array $extra = []): string
{
    return route('reports.transactions', ['date_from' => '2019-03-01', 'date_to' => '2019-04-30'] + $extra);
}

/** JS expression: names of the categories linked in the monthly breakdown. */
function breakdownCategoryLinks(): string
{
    return "[...document.querySelectorAll('#tab-monthly-breakdown a.category-link')].map((a) => a.textContent.trim())";
}

it('switches between the report tabs', function () {
    [$a] = reportCategories($this->user);
    reportWithdrawal($this->user, '2019-03-10', [[$a, 10]]);

    $page = visit(reportRange());
    $this->waitUntil($page, "document.querySelector('#tab-summary.active')");

    foreach (['transaction-list', 'timeline-charts', 'category-charts', 'monthly-breakdown', 'waterfall', 'summary'] as $tab) {
        $page->click("#nav-{$tab}");
        $this->waitUntil($page, "document.querySelector('#tab-{$tab}.active.show')");
    }
})->group('critical');

it('lists exactly the transactions in the selected period', function () {
    [$a] = reportCategories($this->user);
    $inRange = reportWithdrawal($this->user, '2019-03-10', [[$a, 10]]);
    $outOfRange = reportWithdrawal($this->user, '2019-06-10', [[$a, 20]]);

    $page = visit(reportRange());
    $this->waitUntil($page, "document.querySelector('#nav-transaction-list')");
    $page->click('#nav-transaction-list');
    $this->waitUntil($page, "document.querySelector('#tab-transaction-list table button[data-delete][data-id=\"{$inRange->id}\"]')", 30_000);

    $page->assertScript(REPORT_ROWS, 1)
        ->assertMissing("#tab-transaction-list table button[data-delete][data-id=\"{$outOfRange->id}\"]");
})->group('critical');

it('drills down from the monthly breakdown to that month and category, and returns or clears', function () {
    [$a, $b] = reportCategories($this->user);
    $march = reportWithdrawal($this->user, '2019-03-10', [[$a, 10]]);
    reportWithdrawal($this->user, '2019-04-10', [[$b, 20]]);

    $page = visit(reportRange());
    $this->waitUntil($page, "document.querySelector('#nav-monthly-breakdown')");
    $page->click('#nav-monthly-breakdown');
    $this->waitUntil($page, "document.querySelector('#tab-monthly-breakdown')");

    // The first linked cell of category A's row is its March amount
    $this->waitUntil($page, breakdownCategoryLinks() . ".includes('{$a->name}')", 30_000);
    $page->script(<<<JS
        () => [...document.querySelectorAll('#tab-monthly-breakdown tr')]
            .find((row) => row.querySelector('a.category-link')?.textContent.trim() === '{$a->name}')
            .querySelector('a.cell-link').click()
    JS);

    $this->waitUntil($page, "document.querySelector('#tab-transaction-list.active') && document.querySelector('#tab-transaction-list .alert-warning')");
    $this->waitUntil($page, REPORT_ROWS . ' === 1');
    $page->assertPresent("#tab-transaction-list table button[data-delete][data-id=\"{$march->id}\"]");

    // Returning goes back to the monthly breakdown tab
    $page->script("() => [...document.querySelectorAll('#tab-transaction-list .alert-warning button')].find((b) => b.className.includes('outline-secondary')).click()");
    $this->waitUntil($page, "document.querySelector('#tab-monthly-breakdown.active')");

    // Drill down again, then clear the additional filtering: both transactions are listed
    $page->script(<<<JS
        () => [...document.querySelectorAll('#tab-monthly-breakdown tr')]
            .find((row) => row.querySelector('a.category-link')?.textContent.trim() === '{$a->name}')
            .querySelector('a.cell-link').click()
    JS);
    $this->waitUntil($page, REPORT_ROWS . ' === 1');
    $page->script("() => document.querySelector('#tab-transaction-list .alert-warning .btn-warning').click()");
    $this->waitUntil($page, "!document.querySelector('#tab-transaction-list .alert-warning') && " . REPORT_ROWS . ' === 2');
})->group('critical');

it('counts only the matching items in the category-aware tabs when the option is on', function () {
    [$a, $b] = reportCategories($this->user);
    reportWithdrawal($this->user, '2019-03-10', [[$a, 10], [$b, 20]]);

    $page = visit(reportRange(['categories' => [$a->id]]));
    $this->waitUntil($page, "document.querySelector('#matchingItemsOnlySwitch')", 30_000);
    $page->click('#nav-monthly-breakdown');

    // Off: the whole matching transaction is broken down, so the other item's category shows up too
    $this->waitUntil($page, breakdownCategoryLinks() . ".includes('{$b->name}')", 30_000);
    expect($page->script('() => ' . breakdownCategoryLinks()))->toContain($a->name);

    // On: only the item in the filtered category remains
    $page->click('#matchingItemsOnlySwitch');
    $this->waitUntil($page, '! ' . breakdownCategoryLinks() . ".includes('{$b->name}')");
    expect($page->script('() => ' . breakdownCategoryLinks()))->toContain($a->name);
})->group('critical');

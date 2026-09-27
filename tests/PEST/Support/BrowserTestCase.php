<?php

namespace Tests\PEST\Support;

use Illuminate\Foundation\Http\Events\RequestHandled;
use Tests\TestCase;

abstract class BrowserTestCase extends TestCase
{
    /**
     * Run DatabaseSeeder once per run, together with RefreshDatabase's initial migrate:fresh.
     * Each test then runs in its own rolled-back transaction on top of the seeded demo data.
     */
    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // The app is served in-process, so state that a real server resets per request leaks between
        // requests. `auth:sanctum` (every API call) switches the default guard to Sanctum's RequestGuard,
        // which then breaks the next web request (AuthenticateSession calls viaRemember() on it).
        // shouldUse() also rewrites config('auth.defaults.guard'), hence reading it once here.
        $defaultGuard = config('auth.defaults.guard');
        $this->app['events']->listen(
            RequestHandled::class,
            fn () => $this->app['auth']->shouldUse($defaultGuard),
        );
    }

    /**
     * Wait until the given JavaScript expression is truthy on the page (Pest browser assertions don't wait).
     */
    public function waitUntil(object $page, string $expression, int $timeoutMs = 5000): void
    {
        $page->script(<<<JS
            () => new Promise((resolve, reject) => {
                const deadline = Date.now() + {$timeoutMs};
                const check = () => {
                    let result = false;
                    try { result = ({$expression}); } catch (e) {}
                    if (result) return resolve(true);
                    if (Date.now() > deadline) return reject(new Error('Timed out waiting for: ' + {$this->jsString($expression)}));
                    setTimeout(check, 50);
                };
                check();
            })
        JS);

        // A wait that times out fails the test, so a satisfied one is an assertion
        $this->addToAssertionCount(1);
    }

    /**
     * Open the Tom Select built on <select id="$selectId">, type $search into its dropdown input, click the
     * option whose label is exactly $label, and wait until it is the value.
     */
    public function chooseTomSelectOption(object $page, string $selectId, string $search, string $label): void
    {
        $this->searchTomSelect($page, $selectId, $search);

        $findOption = "[...document.querySelectorAll('#{$selectId}-ts-dropdown [data-selectable]')]"
            . '.find((option) => option.textContent.trim() === ' . $this->jsString($label) . ')';
        $this->waitUntil($page, $findOption);
        $value = $page->script("() => {$findOption}.dataset.value");

        $page->click("#{$selectId}-ts-dropdown [data-value=\"{$value}\"]");
        $this->waitUntil($page, "[].concat(document.querySelector('#{$selectId}').tomselect.getValue()).includes('{$value}')");
    }

    /**
     * Open the dropdown, type $search into its search input, and wait until the results are shown.
     */
    public function searchTomSelect(object $page, string $selectId, string $search): void
    {
        // A multi select stays open after a pick; clicking its control again could hit a chip's remove button
        if (! $page->script("() => document.querySelector('#{$selectId}').tomselect.isOpen")) {
            $page->click("#{$selectId} + .ts-wrapper .ts-control");
        }
        // Type only once the dropdown is open: opening it resets the search input
        $this->waitUntil($page, "document.querySelector('#{$selectId}').tomselect.isOpen");
        $page->type("#{$selectId} + .ts-wrapper .dropdown-input", $search);
        $this->waitUntil($page, "(() => { const ts = document.querySelector('#{$selectId}').tomselect; return ts.isOpen && !ts.loading; })()");
    }

    /**
     * Wait until the Tom Select on <select id="$selectId"> holds count($expected) values, then assert they are
     * exactly $expected (order-insensitive). An empty $expected asserts no selection.
     *
     * @param  array<int, int|string>  $expected
     */
    public function assertTomSelectValues(object $page, string $selectId, array $expected): void
    {
        $values = "[].concat(document.querySelector('#{$selectId}').tomselect.getValue()).filter((v) => v !== '')";
        $this->waitUntil($page, "document.querySelector('#{$selectId}')?.tomselect && {$values}.length === " . count($expected));

        $expected = array_map('strval', $expected);
        sort($expected);
        $page->assertScript("{$values}.sort()", $expected);
    }

    /**
     * Click the clear button and wait until the value is empty.
     */
    public function clearTomSelect(object $page, string $selectId): void
    {
        $page->click("#{$selectId} + .ts-wrapper .clear-button");
        $this->waitUntil($page, "document.querySelector('#{$selectId}').tomselect.items.length === 0");
    }

    /**
     * Wait until the element matching $selector contains $text.
     */
    public function waitForTextIn(object $page, string $selector, string $text): void
    {
        $this->waitUntil($page, 'document.querySelector(' . $this->jsString($selector) . ')?.textContent.includes(' . $this->jsString($text) . ')');
    }

    /**
     * Return the id of the Tom Select-managed <select data-testid="$testId"> (Tom Select gives id-less selects one),
     * for use with the other Tom Select helpers.
     */
    public function tomSelectIdByTestId(object $page, string $testId): string
    {
        $select = "document.querySelector('[data-testid=\"{$testId}\"]')";
        $this->waitUntil($page, "{$select}?.tomselect");

        return $page->script("() => {$select}.id");
    }

    /**
     * Click "add transaction item" and wait until the new item's category select is ready. Returns that select's id.
     */
    public function addTransactionItem(object $page): string
    {
        $rows = "document.querySelectorAll('#transaction_item_container .transaction_item_row')";
        $count = $page->script("() => {$rows}.length");

        $page->click('[dusk="button-add-transaction-item"]');
        $this->waitUntil($page, "{$rows}.length === " . ($count + 1) . " && {$rows}[{$count}].querySelector('select.category').tomselect");

        return $page->script("() => {$rows}[{$count}].querySelector('select.category').id");
    }

    /**
     * Wait for the SweetAlert2 dialog, confirm it and wait until it is gone.
     */
    public function confirmSwal(object $page): void
    {
        $this->waitUntil($page, "document.querySelector('.swal2-confirm')");
        $page->click('.swal2-confirm');
        $this->waitUntil($page, "!document.querySelector('.swal2-container')");
    }

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}

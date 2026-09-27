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
    protected function waitUntil(object $page, string $expression, int $timeoutMs = 5000): void
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
    }

    /**
     * Open the Tom Select built on <select id="$selectId">, type $search into its dropdown input, click the
     * option whose label is exactly $label, and wait until it is the value.
     */
    protected function chooseTomSelectOption(object $page, string $selectId, string $search, string $label): void
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
    protected function searchTomSelect(object $page, string $selectId, string $search): void
    {
        $page->click("#{$selectId} + .ts-wrapper .ts-control")
            ->type("#{$selectId} + .ts-wrapper .dropdown-input", $search);
        $this->waitUntil($page, "(() => { const ts = document.querySelector('#{$selectId}').tomselect; return ts.isOpen && !ts.loading; })()");
    }

    /**
     * Wait until the Tom Select on <select id="$selectId"> holds count($expected) values, then assert they are
     * exactly $expected (order-insensitive). An empty $expected asserts no selection.
     *
     * @param  array<int, int|string>  $expected
     */
    protected function assertTomSelectValues(object $page, string $selectId, array $expected): void
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
    protected function clearTomSelect(object $page, string $selectId): void
    {
        $page->click("#{$selectId} + .ts-wrapper .clear-button");
        $this->waitUntil($page, "document.querySelector('#{$selectId}').tomselect.items.length === 0");
    }

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}

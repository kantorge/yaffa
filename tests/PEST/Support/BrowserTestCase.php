<?php

namespace Tests\PEST\Support;

use Tests\TestCase;

abstract class BrowserTestCase extends TestCase
{
    /**
     * Run DatabaseSeeder once per run, together with RefreshDatabase's initial migrate:fresh.
     * Each test then runs in its own rolled-back transaction on top of the seeded demo data.
     */
    protected $seed = true;

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

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}

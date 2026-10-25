<?php

use App\Models\Investment;
use App\Models\User;

test('investment list loads active holdings first and fetches other filters on demand', function () {
    $user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $active = Investment::factory()->withUser($user)->create(['active' => true]);
    $inactive = Investment::factory()->withUser($user)->create(['active' => false]);
    $this->actingAs($user);

    $page = visit(route('investments.index'));
    $this->waitUntil($page, "window.investments?.some(row => row.id === {$active->id})", 20000);
    $page->assertScript('window.investments.every(row => row.active)', true)
        ->assertScript('document.querySelector("#table_filter_active_yes").checked', true);

    $page->click('label[for="table_filter_active_no"]');
    $this->waitUntil($page, "window.investments.some(row => row.id === {$inactive->id})", 20000);
    $page->assertScript('window.investments.every(row => !row.active)', true);

    $page->click('label[for="table_filter_active_any"]');
    $this->waitUntil($page, "window.investments.some(row => row.id === {$active->id}) && window.investments.some(row => row.id === {$inactive->id})", 20000);

    $page->script(<<<'JS'
        () => {
            const originalAjax = window.$.ajax;
            window.$.ajax = function (options) {
                if (!options.url.includes('/investments/summary')) {
                    return originalAjax.apply(this, arguments);
                }
                window.$.ajax = originalAjax;
                const deferred = window.$.Deferred();
                const request = deferred.promise();
                request.abort = () => deferred.reject({}, 'abort');
                setTimeout(() => deferred.reject({}, 'error'), 0);
                return request;
            };
        }
        JS);
    $page->click('label[for="table_filter_active_no"]');
    $this->waitUntil($page, '!document.querySelector("#investment-summary-error").hidden');
    $page->click('#investment-summary-retry');
    $this->waitUntil($page, "window.investments.some(row => row.id === {$inactive->id})", 20000);
    $page->assertScript('document.querySelector("#investment-summary-error").hidden', true)
        ->assertNoJavaScriptErrors();
});

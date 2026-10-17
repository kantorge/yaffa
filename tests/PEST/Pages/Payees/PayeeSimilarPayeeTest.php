<?php

use App\Models\AccountEntity;
use App\Models\User;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('selects a similar payee from the new-payee modal and scrolls its row into view', function (bool $active) {
    // Enough rows to make the table scroll; the target sorts to the top, the table starts scrolled to the bottom
    AccountEntity::factory()->asPayee($this->user)->count(40)->sequence(fn ($s) => ['name' => "Filler {$s->index}"])->create();
    $payee = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'Aaa Similar Fuel', 'active' => $active]);
    $link = "#similar-payee-list li[data-id='{$payee->id}'] a";

    $page = visit(route('account-entity.index', ['type' => 'payee', 'create' => 1]));
    $this->waitUntil($page, "document.querySelector('#newPayeeModal').classList.contains('show')");
    $page->script("() => { document.querySelector('.dt-scroll-body').scrollTop = 99999; }");

    $page->type('#newPayeeModal-name', 'Aaa Similar Fuel')
        ->script("() => document.querySelector('#newPayeeModal-name').dispatchEvent(new KeyboardEvent('keyup'))");
    $this->waitUntil($page, "document.querySelector(\"{$link}\")");

    // Only the name is the link; the (inactive) label is outside it
    $page->assertScript("document.querySelector(\"{$link}\").textContent.trim()", 'Aaa Similar Fuel')
        ->assertScript("document.querySelector(\"{$link}\").parentElement.textContent.includes('(inactive)')", ! $active);

    $page->click($link);
    $this->waitUntil($page, "(() => {
        const row = [...document.querySelectorAll('#table tbody tr')].find((tr) => tr.cells[0]?.textContent === 'Aaa Similar Fuel');
        if (!row) return false;
        const r = row.getBoundingClientRect();
        const body = row.closest('.dt-scroll-body').getBoundingClientRect();
        return r.top >= body.top && r.bottom <= body.bottom && r.top >= 0 && r.bottom <= window.innerHeight;
    })()");

    expect($payee->fresh()->active)->toBeTrue();
})->with(['active payee' => true, 'inactive payee' => false]);

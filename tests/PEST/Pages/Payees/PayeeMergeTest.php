<?php

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('merges two payees picked in the selects, never offering the same payee on both sides', function () {
    $source = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'Merge source payee', 'active' => true]);
    $target = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'Merge target payee', 'active' => true]);

    $page = visit(route('payees.merge.form'));
    $this->waitUntil($page, "document.querySelector('#payee_source').tomselect");

    $this->chooseTomSelectOption($page, 'payee_source', 'Merge source', 'Merge source payee');

    // The target select doesn't offer the payee already chosen as source
    $this->searchTomSelect($page, 'payee_target', 'Merge source');
    $page->assertMissing("#payee_target-ts-dropdown [data-value=\"{$source->id}\"]");

    $this->chooseTomSelectOption($page, 'payee_target', 'Merge target', 'Merge target payee');

    $page->radio('action', 'close')
        ->click('input[type=submit]')
        ->click('.swal2-confirm');
    $this->waitUntil($page, "!location.pathname.includes('/merge')");

    expect($source->fresh()->active)->toBeFalse()
        ->and($target->fresh()->active)->toBeTrue()
        ->and(DB::table('transaction_details_standard')->where('account_from_id', $source->id)->exists())->toBeFalse();
});

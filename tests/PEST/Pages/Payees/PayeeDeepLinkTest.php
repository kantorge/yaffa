<?php

use App\Models\AccountEntity;
use App\Models\User;

/*
 * The old payee create/edit pages redirect to the payee list with ?create=1 / ?edit={id},
 * which opens PayeeForm.vue in its modal (spec FR-8).
 */

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

const PAYEE_TABLE_READY = 'window.table !== undefined';

function modalIsOpen(string $id): string
{
    return "document.querySelector('#{$id}').classList.contains('show')";
}

it('opens the new-payee modal from ?create=1 and adds the saved payee without a reload', function () {
    $page = visit(route('account-entity.create', ['type' => 'payee']));
    $this->waitUntil($page, modalIsOpen('newPayeeModal'));

    $page->assertQueryStringMissing('create')
        ->script('() => { window.__noReload = true; }');

    $page->type('#newPayeeModal-name', 'Deep link payee')
        ->click('#newPayeeModal button[type=submit]');

    $this->waitUntil($page, "window.payees.some((payee) => payee.name === 'Deep link payee')");
    $page->assertScript('window.__noReload', true)
        ->assertNoJavaScriptErrors();

    expect($this->user->payees()->where('name', 'Deep link payee')->exists())->toBeTrue();
});

it('opens the edit modal from ?edit={id} with the payee data loaded and saves changes', function () {
    [$default, $preferred, $excluded] = $this->user->categories()->take(3)->get();
    $payee = AccountEntity::factory()
        ->asPayee($this->user, ['category_id' => $default->id])
        ->create(['name' => 'Deep link edit payee', 'alias' => 'deep alias']);
    $payee->categoryPreference()->attach([
        $preferred->id => ['preferred' => true],
        $excluded->id => ['preferred' => false],
    ]);

    $page = visit(route('account-entity.edit', ['account_entity' => $payee->id]));
    $this->waitUntil($page, modalIsOpen('editPayeeModal'));

    $selected = fn (string $id) => "Array.from(document.querySelector('#{$id}').selectedOptions).map((o) => o.value).join(',')";

    $page->assertQueryStringMissing('edit')
        ->assertValue('#editPayeeModal-name', 'Deep link edit payee')
        ->assertValue('#editPayeeModal-alias', 'deep alias')
        ->assertScript($selected('editPayeeModal-category_id'), (string) $default->id)
        ->assertScript($selected('editPayeeModal-preferred_categories'), (string) $preferred->id)
        ->assertScript($selected('editPayeeModal-not_preferred_categories'), (string) $excluded->id);

    $page->type('#editPayeeModal-name', 'Deep link edited payee')
        ->click('#editPayeeModal button[type=submit]');

    $this->waitUntil($page, "window.payees.some((payee) => payee.name === 'Deep link edited payee')");
    $page->assertNoJavaScriptErrors();

    expect($payee->fresh()->name)->toBe('Deep link edited payee');
});

it('does not reopen a deep-linked modal after it is closed and the page is reloaded', function () {
    $page = visit(route('account-entity.index', ['type' => 'payee', 'create' => 1]));
    $this->waitUntil($page, modalIsOpen('newPayeeModal'));

    $page->click('#newPayeeModal .btn-close');
    $this->waitUntil($page, '!' . modalIsOpen('newPayeeModal'));

    $page->refresh();
    $this->waitUntil($page, PAYEE_TABLE_READY);

    $page->assertQueryStringMissing('create')
        ->assertScript(modalIsOpen('newPayeeModal'), false);
});

it('shows an error and no half-filled modal for an unknown ?edit id', function () {
    $page = visit(route('account-entity.index', ['type' => 'payee', 'edit' => 999999]));
    $this->waitUntil($page, "document.body.innerText.includes('Failed to load payee data')");

    $page->assertQueryStringMissing('edit')
        ->assertScript(modalIsOpen('editPayeeModal'), false);
});

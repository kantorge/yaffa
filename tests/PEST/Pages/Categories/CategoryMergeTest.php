<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::where('email', 'demo@yaffa.cc')->firstOrFail();
    $this->actingAs($this->user);
});

it('merges a category preset from the URL into a target picked in the select', function () {
    // Two child categories, so the parent-into-child rule doesn't apply; the source has transaction items to move
    $source = Category::where('user_id', $this->user->id)
        ->whereNotNull('parent_id')
        ->whereIn('id', DB::table('transaction_items')->select('category_id'))
        ->firstOrFail();
    $target = Category::where('user_id', $this->user->id)
        ->whereNotNull('parent_id')
        ->whereKeyNot($source->id)
        ->firstOrFail();

    $page = visit(route('categories.merge.form', ['categorySource' => $source->id]));
    $this->waitUntil($page, "document.querySelector('#category_source').tomselect?.getValue() === '{$source->id}'");

    // The target select doesn't offer the source category
    $this->searchTomSelect($page, 'category_target', $source->name);
    $page->assertMissing("#category_target-ts-dropdown [data-value=\"{$source->id}\"]");

    $this->chooseTomSelectOption($page, 'category_target', $target->name, $target->full_name);

    $page->radio('action', 'close')
        ->click('input[type=submit]')
        ->click('.swal2-confirm');
    $this->waitUntil($page, "!location.pathname.includes('/merge')");

    expect($source->fresh()->active)->toBeFalse()
        ->and(DB::table('transaction_items')->where('category_id', $source->id)->exists())->toBeFalse();
});

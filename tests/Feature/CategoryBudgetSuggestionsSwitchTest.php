<?php

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults to calculating budget suggestions', function () {
    $user = User::factory()->create();

    expect(Category::factory()->for($user)->create()->fresh()->calculate_budget_suggestions)->toBeTrue();
});

it('switches the flag through the dedicated endpoint and leaves active alone', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['active' => true]);

    $this->actingAs($user)
        ->patchJson(route('api.v1.categories.patch-budget-suggestions', $category), ['calculate_budget_suggestions' => false])
        ->assertOk();

    $category->refresh();
    expect($category->calculate_budget_suggestions)->toBeFalse()->and($category->active)->toBeTrue();
});

it('validates the flag and rejects another user\'s category', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();
    $foreign = Category::factory()->for(User::factory()->create())->create();

    $this->actingAs($user)
        ->patchJson(route('api.v1.categories.patch-budget-suggestions', $category), [])
        ->assertUnprocessable();
    $this->actingAs($user)
        ->patchJson(route('api.v1.categories.patch-budget-suggestions', $foreign), ['calculate_budget_suggestions' => false])
        ->assertForbidden();
});

it('saves the form value; an unchecked box sends only the hidden 0', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create(['name' => 'Food', 'default_aggregation' => 'month']);
    $base = ['name' => 'Food', 'default_aggregation' => 'month', 'active' => 1];

    $this->actingAs($user)->put(route('categories.update', $category), $base + ['calculate_budget_suggestions' => 0])->assertRedirect();
    expect($category->fresh()->calculate_budget_suggestions)->toBeFalse();

    $this->actingAs($user)->put(route('categories.update', $category), $base + ['calculate_budget_suggestions' => 1])->assertRedirect();
    expect($category->fresh()->calculate_budget_suggestions)->toBeTrue();
});

it('renders the toggle, disabled with a note when the parent has it off', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create(['calculate_budget_suggestions' => false]);
    $child = Category::factory()->for($user)->create(['parent_id' => $parent->id]);

    $this->actingAs($user)->get(route('categories.edit', $child))
        ->assertOk()
        ->assertSee('Excluded via parent category');
});

it('keeps the default (on) when the API creates a category without the flag', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.categories.store'), ['name' => 'Fuel', 'default_aggregation' => 'month', 'active' => true])
        ->assertCreated();

    expect($user->categories()->where('name', 'Fuel')->firstOrFail()->calculate_budget_suggestions)->toBeTrue();
});

it('disables the checkbox under an off parent and keeps the child\'s own value in the hidden input', function () {
    $user = User::factory()->create();
    $parent = Category::factory()->for($user)->create(['calculate_budget_suggestions' => false]);
    $child = Category::factory()->for($user)->create(['parent_id' => $parent->id, 'calculate_budget_suggestions' => true]);

    $html = $this->actingAs($user)->get(route('categories.edit', $child))->getContent();

    expect($html)
        ->toMatch('/<input type="hidden" id="calculate_budget_suggestions_hidden" name="calculate_budget_suggestions" value="1"/')
        ->toMatch('/id="calculate_budget_suggestions"[^>]*disabled/s');
});

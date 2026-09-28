<?php

use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects creating a payee without a name', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.payees.store'), [
            'name' => '',
            'active' => 1,
            'config_type' => 'payee',
            'config' => ['category_id' => $category->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($user->payees()->count())->toBe(0);
});

it('rejects updating a payee with an empty name', function () {
    $user = User::factory()->create();
    $payee = AccountEntity::factory()->asPayee($user)->create();

    $this->actingAs($user)
        ->patchJson(route('api.v1.payees.update', ['accountEntity' => $payee->id]), [
            'name' => '',
            'config_type' => 'payee',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    expect($payee->fresh()->name)->toBe($payee->name);
});

it('rejects creating a payee with a category both preferred and not preferred', function () {
    $user = User::factory()->create();
    $category = Category::factory()->for($user)->create();

    $this->actingAs($user)
        ->postJson(route('api.v1.payees.store'), [
            'name' => 'Conflicting Payee',
            'active' => true,
            'config_type' => 'payee',
            'config' => [
                'category_id' => null,
                'preferred' => [$category->id],
                'not_preferred' => [$category->id],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['config.preferred.0', 'config.not_preferred.0']);

    $this->assertDatabaseMissing('account_entities', ['name' => 'Conflicting Payee']);
    $this->assertDatabaseEmpty('account_entity_category_preference');
});

it('rejects updating a payee with a category both preferred and not preferred', function () {
    $user = User::factory()->create();
    $existingCategory = Category::factory()->for($user)->create();
    $conflictingCategory = Category::factory()->for($user)->create();

    $payee = AccountEntity::factory()->asPayee($user)->create(['name' => 'Existing Payee']);
    $payee->categoryPreference()->sync([
        $existingCategory->id => ['preferred' => true],
    ]);

    $this->actingAs($user)
        ->patchJson(route('api.v1.payees.update', ['accountEntity' => $payee->id]), [
            'name' => 'Existing Payee',
            'config_type' => 'payee',
            'config' => [
                'category_id' => null,
                'preferred' => [$conflictingCategory->id],
                'not_preferred' => [(string) $conflictingCategory->id],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['config.preferred.0', 'config.not_preferred.0']);

    $this->assertDatabaseCount('account_entity_category_preference', 1);
    $this->assertDatabaseHas('account_entity_category_preference', [
        'account_entity_id' => $payee->id,
        'category_id' => $existingCategory->id,
        'preferred' => true,
    ]);
});

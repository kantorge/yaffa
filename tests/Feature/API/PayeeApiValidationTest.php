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

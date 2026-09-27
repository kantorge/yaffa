<?php

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

uses(RefreshDatabase::class);

it('redirects the payee create page to the payee list modal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('account-entity.create', ['type' => 'payee']))
        ->assertRedirectToRoute('account-entity.index', ['type' => 'payee', 'create' => 1]);
});

it('redirects the payee edit page to the payee list modal', function () {
    $user = User::factory()->create();
    $payee = AccountEntity::factory()->asPayee($user)->create();

    $this->actingAs($user)
        ->get(route('account-entity.edit', ['account_entity' => $payee->id]))
        ->assertRedirectToRoute('account-entity.index', ['type' => 'payee', 'edit' => $payee->id]);
});

it('forbids editing or updating another user\'s payee before redirecting or rejecting', function () {
    $payee = AccountEntity::factory()->asPayee(User::factory()->create())->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('account-entity.edit', ['account_entity' => $payee->id]))
        ->assertForbidden();
    $this->patch(route('account-entity.update', ['account_entity' => $payee->id]), [
        'name' => 'Hijacked',
        'config_type' => 'payee',
    ])->assertForbidden();

    expect($payee->fresh()->name)->not->toBe('Hijacked');
});

it('rejects storing a payee through the web route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('account-entity.store', ['type' => 'payee']), [
            'name' => 'Web payee',
            'active' => 1,
            'config_type' => 'payee',
            'config' => ['category_id' => null],
        ])
        ->assertStatus(Response::HTTP_NOT_FOUND);

    expect($user->payees()->count())->toBe(0);
});

it('rejects updating a payee through the web route', function () {
    $user = User::factory()->create();
    $payee = AccountEntity::factory()->asPayee($user)->create();

    $this->actingAs($user)
        ->patch(route('account-entity.update', ['account_entity' => $payee->id]), [
            'name' => 'Renamed payee',
            'active' => 1,
            'config_type' => 'payee',
            'config' => ['category_id' => null],
        ])
        ->assertStatus(Response::HTTP_NOT_FOUND);

    expect($payee->fresh()->name)->not->toBe('Renamed payee');
});

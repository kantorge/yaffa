<?php

use App\Models\AccountEntity;
use App\Models\User;
use App\Services\PayeeProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->store = fn (array $attributes) => $this->postJson(route('api.v1.payees.store'), $attributes + [
        'config_type' => 'payee',
        'active' => true,
    ]);
});

it('persists the auto-recording policy and the itemization setting', function () {
    $id = ($this->store)([
        'name' => 'Corner Grocer',
        'config' => ['auto_record_policy' => 'never', 'itemization_expected' => true],
    ])->assertCreated()->json('id');

    $payee = AccountEntity::find($id);
    expect($payee->config->auto_record_policy)->toBe('never')
        ->and($payee->config->itemization_expected)->toBeTrue();

    $this->patchJson(route('api.v1.payees.update', $id), [
        'name' => 'Corner Grocer',
        'config_type' => 'payee',
        'config' => ['auto_record_policy' => 'always', 'itemization_expected' => false],
    ])->assertOk();

    expect($payee->config->fresh()->auto_record_policy)->toBe('always')
        ->and($payee->config->fresh()->itemization_expected)->toBeFalse();
});

it('defaults a new payee to follow the global setting', function () {
    $id = ($this->store)(['name' => 'Plain Payee'])->assertCreated()->json('id');

    expect(AccountEntity::find($id)->config->auto_record_policy)->toBe('follow_global');
});

it('rejects an unknown policy', function () {
    ($this->store)(['name' => 'Odd Payee', 'config' => ['auto_record_policy' => 'sometimes']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['config.auto_record_policy']);
});

it('rejects an alias that another payee already uses once normalized', function () {
    ($this->store)(['name' => 'Fuel Station', 'alias' => 'OMV'])->assertCreated();

    ($this->store)(['name' => 'Another Station', 'alias' => "Shell\nomv"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['alias']);
});

it('rejects an alias that equals the normalized name of another payee', function () {
    ($this->store)(['name' => 'OMV'])->assertCreated();

    ($this->store)(['name' => 'Fuel Station', 'alias' => 'OMV 4471'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['alias']);
});

it('rejects a name that collides with another payee once normalized', function () {
    ($this->store)(['name' => 'Példa Kft.'])->assertCreated();

    ($this->store)(['name' => 'pelda'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('allows the same alias for payees of different users', function () {
    $other = AccountEntity::factory()->asPayee(User::factory()->create())->create(['alias' => 'OMV']);

    ($this->store)(['name' => 'Fuel Station', 'alias' => 'OMV'])->assertCreated();

    expect($other->fresh()->alias)->toBe('OMV');
});

it('does not block saving a payee on an unchanged alias it shares with legacy data', function () {
    $first = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'First', 'alias' => 'OMV']);
    $second = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'Second', 'alias' => 'OMV']);

    $this->patchJson(route('api.v1.payees.update', $second->id), [
        'name' => 'Second renamed',
        'config_type' => 'payee',
        'alias' => 'OMV',
    ])->assertOk();

    expect($first->fresh()->alias)->toBe('OMV');
});

it('rejects a payee alias that repeats itself', function () {
    ($this->store)(['name' => 'Fuel Station', 'alias' => "OMV\nomv 12"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['alias']);
});

it('renders the candidates page and the payee page with the profile', function () {
    $payee = AccountEntity::factory()->asPayee($this->user)->create(['active' => true]);
    app(PayeeProfileService::class)->calculate($payee);

    $this->get(route('payees.auto-record-candidates'))
        ->assertOk()
        ->assertSee('autoRecordCandidates', false);

    $this->get(route('account-entity.show', $payee->id))->assertOk();
});

it('requires a login for the candidates page', function () {
    auth()->logout();

    $this->get(route('payees.auto-record-candidates'))->assertRedirect();
});

it('resolves and saves the auto-recording thresholds', function () {
    $this->patchJson(route('api.v1.ai.settings.update'), ['auto_record_min_history' => 7, 'payee_similarity_min' => 0.95])
        ->assertOk()
        ->assertJsonPath('auto_record_min_history', 7)
        ->assertJsonPath('payee_similarity_min', 0.95)
        ->assertJsonPath('auto_record_wilson_min', 0.8)
        ->assertJsonPath('payee_similarity_margin', 0.1);

    $this->patchJson(route('api.v1.ai.settings.update'), ['auto_record_wilson_min' => 1.5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['auto_record_wilson_min']);
});

it('does not treat a case-only edit of a name or alias as a change', function () {
    AccountEntity::factory()->asPayee($this->user)->create(['name' => 'omv', 'alias' => 'shell']);
    $second = AccountEntity::factory()->asPayee($this->user)->create(['name' => 'Fuel', 'alias' => 'OMV']);

    // "Fuel" -> "FUEL" and "OMV" -> "Omv": same normalized values as before, so legacy collisions do not block
    $this->patchJson(route('api.v1.payees.update', $second->id), [
        'name' => 'FUEL',
        'config_type' => 'payee',
        'alias' => 'Omv',
    ])->assertOk();
});

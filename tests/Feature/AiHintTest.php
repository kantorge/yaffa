<?php

use App\Models\AccountEntity;
use App\Models\AiHint;
use App\Models\User;
use App\Services\AiHintService;
use App\Services\AiPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('only lists, shows and changes the hints of the authenticated user', function () {
    $user = User::factory()->create();
    $own = AiHint::factory()->for($user)->create();
    $foreign = AiHint::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson(route('api.v1.ai-hints.index'))->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $own->id);
    $this->getJson(route('api.v1.ai-hints.show', $own))->assertOk();
    $this->getJson(route('api.v1.ai-hints.show', $foreign))->assertForbidden();
    $this->patchJson(route('api.v1.ai-hints.update', $foreign), ['name' => 'x', 'hint' => 'y', 'active' => true])->assertForbidden();
    $this->deleteJson(route('api.v1.ai-hints.destroy', $foreign))->assertForbidden();
});

it('creates, updates and deletes a hint', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $id = $this->postJson(route('api.v1.ai-hints.store'), [
        'name' => 'Raiffeisen',
        'trigger_text' => 'myRaiffeisen',
        'hint' => 'Transfer to {account:1}',
        'active' => true,
    ])->assertCreated()->json('id');

    expect(AiHint::find($id)->user_id)->toBe($user->id);

    $this->patchJson(route('api.v1.ai-hints.update', $id), ['name' => 'R2', 'hint' => 'h', 'active' => false])
        ->assertOk()
        ->assertJsonPath('active', false);

    $this->deleteJson(route('api.v1.ai-hints.destroy', $id))->assertOk();
    expect(AiHint::find($id))->toBeNull();
});

it('requires name, hint and active', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->postJson(route('api.v1.ai-hints.store'), [])->assertJsonValidationErrors(['name', 'hint', 'active']);
});

it('selects active hints by case-insensitive trigger text, or without a trigger', function () {
    $user = User::factory()->create();
    AiHint::factory()->for($user)->create(['trigger_text' => 'myRaiffeisen', 'hint' => 'matching']);
    AiHint::factory()->for($user)->create(['trigger_text' => 'other bank', 'hint' => 'not matching']);
    AiHint::factory()->for($user)->create(['trigger_text' => null, 'hint' => 'always']);
    AiHint::factory()->for($user)->create(['trigger_text' => 'myraiffeisen', 'hint' => 'inactive', 'active' => false]);
    AiHint::factory()->create(['trigger_text' => 'myRaiffeisen', 'hint' => 'foreign']);

    expect(app(AiHintService::class)->hintsForText($user, 'From MYRAIFFEISEN - Terhelés'))
        ->toBe(['matching', 'always']);
});

it('renders account and payee tokens and leaves missing or foreign ones untouched', function () {
    $user = User::factory()->create();
    $account = AccountEntity::factory()->asAccount($user)->create(['name' => 'Raiffeisen']);
    $payee = AccountEntity::factory()->asPayee($user)->create(['name' => 'Patria']);
    $foreign = AccountEntity::factory()->asAccount(User::factory()->create())->create(['name' => 'Secret']);

    $rendered = app(AiHintService::class)->render(
        $user,
        "From {account:{$account->id}} paid {payee:{$payee->id}} {account:999999} {account:{$foreign->id}}"
    );

    expect($rendered)->toBe("From Raiffeisen paid Patria {account:999999} {account:{$foreign->id}}");
});

it('adds the hints to the extraction prompt only when there are some', function () {
    $builder = new AiPromptBuilder();

    expect($builder->buildMainExtractionPrompt('doc text'))->not->toContain('USER-DEFINED PATTERNS')
        ->and($builder->buildMainExtractionPrompt('doc text', null, null, ['Use account Raiffeisen']))
        ->toContain('USER-DEFINED PATTERNS')
        ->toContain('* Use account Raiffeisen');
});

it('has an onboarding topic for the AI hints page', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->getJson(route('api.v1.onboarding.show', ['topic' => 'aiHints']))
        ->assertOk()
        ->assertJsonCount(1, 'steps');
});

it('limits a hint to 500 characters', function () {
    Sanctum::actingAs(User::factory()->create(), ['*']);
    $payload = ['name' => 'n', 'active' => true];

    $this->postJson(route('api.v1.ai-hints.store'), $payload + ['hint' => str_repeat('a', 501)])->assertJsonValidationErrors('hint');
    $this->postJson(route('api.v1.ai-hints.store'), $payload + ['hint' => str_repeat('a', 500)])->assertCreated();
});

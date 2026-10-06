<?php

use App\Models\AccountEntity;
use App\Models\User;
use App\Services\PayeeMatch;
use App\Services\PayeeMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function matcherPayee(User $user, string $name, ?string $alias = null, bool $active = true): AccountEntity
{
    return AccountEntity::factory()->asPayee($user)->create(['name' => $name, 'alias' => $alias, 'active' => $active]);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->matcher = app(PayeeMatcher::class);
});

it('matches an exact name before anything else', function () {
    $omv = matcherPayee($this->user, 'OMV', 'OMV Budapest');
    matcherPayee($this->user, 'Shell');

    $match = $this->matcher->match('omv', $this->user);

    expect($match->payee->id)->toBe($omv->id)
        ->and($match->tier)->toBe(PayeeMatch::TIER_EXACT)
        ->and($match->autoEligible)->toBeTrue();
});

it('matches an exact alias line', function () {
    $payee = matcherPayee($this->user, 'Fuel station', "OMV\nMOL");

    expect($this->matcher->match('mol', $this->user)->payee->id)->toBe($payee->id);
});

it('matches an alias as leading whole tokens and ignores store numbers', function () {
    $omv = matcherPayee($this->user, 'OMV fuel', 'OMV');

    $match = $this->matcher->match('OMV 4472 DEBRECEN', $this->user);

    expect($match->payee->id)->toBe($omv->id)
        ->and($match->tier)->toBe(PayeeMatch::TIER_LEADING_TOKEN)
        ->and($match->autoEligible)->toBeTrue();
});

it('does not treat a partial token as a leading token', function () {
    matcherPayee($this->user, 'Fuel station', 'OMV');

    $match = $this->matcher->match('OMVX DEBRECEN', $this->user);

    expect($match?->tier)->not->toBe(PayeeMatch::TIER_LEADING_TOKEN);
});

it('lets the longest alias win', function () {
    matcherPayee($this->user, 'Generic grocer', 'Spar');
    $express = matcherPayee($this->user, 'Spar Express shop', 'Spar Express');

    $match = $this->matcher->match('SPAR EXPRESS 0012 BUDAPEST', $this->user);

    expect($match->payee->id)->toBe($express->id);
});

it('flags a clear one-typo similarity match as auto-eligible', function () {
    $payee = matcherPayee($this->user, 'Budapest Parking');

    $match = $this->matcher->match('Budapest Parkng', $this->user);

    expect($match->payee->id)->toBe($payee->id)
        ->and($match->tier)->toBe(PayeeMatch::TIER_SIMILARITY)
        ->and($match->score)->toBeGreaterThanOrEqual(0.92)
        ->and($match->autoEligible)->toBeTrue();
});

it('does not auto-accept a similarity match below the minimum', function () {
    matcherPayee($this->user, 'Budapest Parking');

    $match = $this->matcher->match('Budapest Parkinson Clinic', $this->user);

    expect($match)->not->toBeNull()
        ->and($match->score)->toBeLessThan(0.92)
        ->and($match->autoEligible)->toBeFalse();
});

it('does not auto-accept a similarity match with a low margin', function () {
    matcherPayee($this->user, 'Budapest Parking North');
    matcherPayee($this->user, 'Budapest Parking South');

    $match = $this->matcher->match('Budapest Parking Nort', $this->user);

    expect($match->margin)->toBeLessThan(0.1)
        ->and($match->autoEligible)->toBeFalse();
});

it('does not auto-accept a similarity match on a very short name', function () {
    matcherPayee($this->user, 'Tes');

    $match = $this->matcher->match('Tesco', $this->user);

    expect($match)->not->toBeNull()
        ->and($match->tier)->toBe(PayeeMatch::TIER_SIMILARITY)
        ->and($match->autoEligible)->toBeFalse();
});

it('skips inactive payees and other users payees', function () {
    matcherPayee($this->user, 'Dormant Shop', null, false);
    matcherPayee(User::factory()->create(), 'Foreign Shop');

    expect($this->matcher->match('Dormant Shop', $this->user))->toBeNull()
        ->and($this->matcher->match('Foreign Shop', $this->user))->toBeNull();
});

<?php

use App\Models\AccountEntity;
use App\Models\AiUserSettings;
use App\Models\User;
use App\Services\AiUserSettingsResolver;
use App\Services\AssetMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function certainPayee(User $user, string $name, ?string $alias = null, bool $active = true): AccountEntity
{
    return AccountEntity::factory()->asPayee($user)->create(['name' => $name, 'alias' => $alias, 'active' => $active]);
}

function certainAccount(User $user, string $name, ?string $alias = null, bool $active = true): AccountEntity
{
    return AccountEntity::factory()->asAccount($user)->create(['name' => $name, 'alias' => $alias, 'active' => $active]);
}

/** The payee a person would not need to double check, if any. */
function certainPayeeIdFor(AssetMatchingService $matching, string $text): ?int
{
    return $matching->certainMatch($matching->matchPayees($text))['id'] ?? null;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->matching = new AssetMatchingService($this->user);
});

it('is certain about an exact name before anything else', function () {
    $omv = certainPayee($this->user, 'OMV', 'OMV Budapest');
    certainPayee($this->user, 'Shell');

    expect(certainPayeeIdFor($this->matching, 'omv'))->toBe($omv->id)
        ->and($this->matching->matchPayees('omv')[0]['similarity'])->toBe(1.0);
});

it('is certain about an exact alias line', function () {
    $payee = certainPayee($this->user, 'Fuel station', "OMV\nMOL");

    expect(certainPayeeIdFor($this->matching, 'mol'))->toBe($payee->id);
});

it('is certain about an alias that opens the text as whole tokens, ignoring store numbers', function () {
    $omv = certainPayee($this->user, 'OMV fuel', 'OMV');

    expect(certainPayeeIdFor($this->matching, 'OMV 4472 DEBRECEN'))->toBe($omv->id);
});

it('does not treat a partial token as a leading token', function () {
    certainPayee($this->user, 'Fuel station', 'OMV');

    expect(certainPayeeIdFor($this->matching, 'OMVX DEBRECEN'))->toBeNull();
});

it('lets the longest alias win', function () {
    certainPayee($this->user, 'Generic grocer', 'Spar');
    $express = certainPayee($this->user, 'Spar Express shop', 'Spar Express');

    expect(certainPayeeIdFor($this->matching, 'SPAR EXPRESS 0012 BUDAPEST'))->toBe($express->id);
});

it('is not certain when two payees match equally', function () {
    certainPayee($this->user, 'Grocer One', 'Spar');
    certainPayee($this->user, 'Grocer Two', 'Spar Market');
    certainPayee($this->user, 'Grocer Three', 'Spar Market');

    expect(certainPayeeIdFor($this->matching, 'Spar Market 12'))->toBeNull();
});

it('is certain about a clear one-typo similarity match', function () {
    $payee = certainPayee($this->user, 'Budapest Parking');

    $matches = $this->matching->matchPayees('Budapest Parkng');

    expect($matches[0]['similarity'])->toBeGreaterThanOrEqual(AiUserSettingsResolver::DEFAULT_MATCH_AUTO_ACCEPT_THRESHOLD)
        ->and(certainPayeeIdFor($this->matching, 'Budapest Parkng'))->toBe($payee->id);
});

it('is not certain below the auto-accept threshold, and is once the user lowers it', function () {
    $payee = certainPayee($this->user, 'Budapest Parking');

    expect($this->matching->matchPayees('Budapest Parkinson Clinic'))->not->toBeEmpty()
        ->and(certainPayeeIdFor($this->matching, 'Budapest Parkinson Clinic'))->toBeNull();

    AiUserSettings::query()->where('user_id', $this->user->id)->update(['match_auto_accept_threshold' => 0.5]);

    expect(certainPayeeIdFor(new AssetMatchingService($this->user), 'Budapest Parkinson Clinic'))->toBe($payee->id);
});

it('is not certain when the best match does not lead by the margin', function () {
    certainPayee($this->user, 'Budapest Parking North');
    certainPayee($this->user, 'Budapest Parking South');

    $matches = $this->matching->matchPayees('Budapest Parking Nort');

    expect($matches[0]['similarity'] - $matches[1]['similarity'])->toBeLessThan(AiUserSettingsResolver::DEFAULT_MATCH_MIN_MARGIN)
        ->and($this->matching->certainMatch($matches))->toBeNull();
});

it('skips inactive payees and other users payees', function () {
    certainPayee($this->user, 'Dormant Shop', null, false);
    certainPayee(User::factory()->create(), 'Foreign Shop');

    expect($this->matching->matchPayees('Dormant Shop'))->toBe([])
        ->and($this->matching->matchPayees('Foreign Shop'))->toBe([]);
});

it('derives the card ending from the extracted digits or from a masked number', function (?string $digits, ?string $account, ?string $expected) {
    expect(AssetMatchingService::cardDigits($digits, $account))->toBe($expected);
})->with([
    'the extracted digits' => ['7393', null, '7393'],
    'a longer extracted number' => ['4003 2200 0000 7393', null, '7393'],
    'a masked account text' => [null, '400322******7393', '7393'],
    'a short masked account text' => [null, '****7393', '7393'],
    'the extracted digits win' => ['1111', '400322******7393', '1111'],
    'a plain number in an account name is no card' => [null, 'Savings 2024', null],
    'too few digits' => ['73', null, null],
    'nothing' => [null, null, null],
]);

it('matches an account by the closing digits of its alias lines', function () {
    $account = certainAccount($this->user, 'Raiffeisen', "400322******7393\nVisa Debit 7393\n****7393\n****3412");
    certainAccount($this->user, 'Credit card', "Mastercard-3457\n****3457");

    $matches = $this->matching->matchAccounts('', '7393');

    expect($matches)->toHaveCount(1)
        ->and($this->matching->certainMatch($matches)['id'])->toBe($account->id)
        ->and($this->matching->certainMatch($this->matching->matchAccounts('', '3412'))['id'])->toBe($account->id)
        ->and($this->matching->matchAccounts('', '0000'))->toBe([]);
});

it('is not certain about a card ending shared by two accounts, or one of an inactive account', function () {
    certainAccount($this->user, 'Card A', '1234');
    certainAccount($this->user, 'Card B', '****1234');
    $closed = certainAccount($this->user, 'Closed card', '5678', false);

    expect($this->matching->certainMatch($this->matching->matchAccounts('', '1234')))->toBeNull()
        ->and($this->matching->matchAccounts('', '5678'))->not->toContain($closed->id);
});

it('has a balanced preset equal to the application defaults, and presets that differ from each other', function () {
    $defaults = app(AiUserSettingsResolver::class)->resolveForUser($this->user);

    foreach (AiUserSettingsResolver::presets() as $group => $presets) {
        expect(array_keys($presets))->toBe(['loose', 'balanced', 'strict']);

        foreach ($presets['balanced'] as $key => $value) {
            expect($defaults[$key])->toEqual($value);
        }

        expect($presets['loose'])->not->toEqual($presets['balanced'])
            ->and($presets['strict'])->not->toEqual($presets['balanced'])
            ->and(array_keys($presets['loose']))->toBe(array_keys($presets['balanced']))
            ->and(array_keys($presets['strict']))->toBe(array_keys($presets['balanced']));
    }
});

it('is certain about an account whose name or alias line equals the text, and ignores inactive accounts', function () {
    $account = certainAccount($this->user, 'Raiffeisen', "myRaiffeisen Terhelés\nmyRaiffeisen\n****7393");
    certainAccount($this->user, 'Raiffeisen - EUR');
    certainAccount($this->user, 'myRaiffeisen Old', 'myRaiffeisen', false);

    $matches = $this->matching->matchAccounts('MyRaiffeisen');

    expect($matches[0])->toMatchArray(['id' => $account->id, 'similarity' => 1.0])
        ->and($this->matching->certainMatch($matches)['id'])->toBe($account->id)
        ->and(collect($matches)->pluck('name')->implode('|'))->not->toContain('myRaiffeisen Old');
});

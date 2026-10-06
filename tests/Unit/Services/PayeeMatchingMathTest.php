<?php

use App\Services\PayeeMatcher;
use App\Services\PayeeProfileService;

it('reproduces the specified Wilson lower bounds', function (int $successes, int $n, float $expected) {
    expect(round(PayeeProfileService::wilsonLowerBound($successes, $n), 3))->toBe($expected);
})->with([
    '10 of 10 misses' => [10, 10, 0.787],
    '11 of 11 passes' => [11, 11, 0.803],
    '20 of 21 passes' => [20, 21, 0.812],
    '18 of 20 misses' => [18, 20, 0.738],
]);

it('has no lower bound without a history', function () {
    expect(PayeeProfileService::wilsonLowerBound(0, 0))->toBe(0.0);
});

it('normalizes payee text for matching', function (string $input, string $expected) {
    expect(PayeeMatcher::normalize($input))->toBe($expected);
})->with([
    'store number' => ['OMV 4471 BUDAPEST', 'omv budapest'],
    'legal suffix and accents' => ['Példa Kft.', 'pelda'],
    'only a suffix is kept as is' => ['Kft', 'kft'],
    'punctuation' => ['McDonald\'s - Árpád híd', 'mcdonald s arpad hid'],
]);

it('splits an alias field into lines', function () {
    expect(PayeeMatcher::aliasLines("OMV\r\n  Shell \n\nMOL"))->toBe(['OMV', 'Shell', 'MOL'])
        ->and(PayeeMatcher::aliasLines(null))->toBe([]);
});

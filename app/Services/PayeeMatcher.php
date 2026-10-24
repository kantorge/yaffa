<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Normalization and alias helpers shared by everything that compares payee names. Matching free text to a
 * payee (a name as printed on a notification or receipt) is done by AssetMatchingService::matchPayees().
 */
class PayeeMatcher
{
    private const array LEGAL_SUFFIXES = ['kft', 'zrt', 'bt', 'nyrt', 'kkt', 'ltd', 'gmbh'];

    /**
     * Base normalization, plus legal suffixes and digit groups (store numbers, postal codes) removed:
     * `OMV 4471 BUDAPEST` => `omv budapest`, `Példa Kft.` => `pelda`.
     */
    public static function normalize(string $value): string
    {
        $base = AssetMatchingService::normalizeForMatching($value);
        $stripped = preg_replace('/\b(?:\d+|' . implode('|', self::LEGAL_SUFFIXES) . ')\b/u', ' ', $base) ?? $base;
        $stripped = Str::squish($stripped);

        return $stripped === '' ? $base : $stripped;
    }

    /**
     * One entry per non-empty line of an alias field.
     *
     * @return array<int, string>
     */
    public static function aliasLines(?string $alias): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $alias) ?: [])));
    }

    /**
     * Another payee of the user whose normalized name or alias line equals the given normalized value.
     */
    public static function findConflict(User $user, string $value, ?int $exceptPayeeId = null): ?AccountEntity
    {
        $normalized = self::normalize($value);

        if ($normalized === '') {
            return null;
        }

        return $user->payees()
            ->when($exceptPayeeId !== null, fn ($query) => $query->whereKeyNot($exceptPayeeId))
            ->get(['id', 'name', 'alias'])
            ->first(fn (AccountEntity $payee) => collect([$payee->name, ...self::aliasLines($payee->alias)])
                ->contains(fn (string $candidate) => self::normalize($candidate) === $normalized));
    }
}

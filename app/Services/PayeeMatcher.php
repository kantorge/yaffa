<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\User;
use Edgaras\StrSim\JaroWinkler;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Resolves free text (a payee name as printed on a notification or receipt) to one of the user's payees.
 * Tiers: exact name or alias, alias as leading whole tokens (longest wins), then Jaro-Winkler similarity.
 * Only active payees take part.
 */
class PayeeMatcher
{
    private const array LEGAL_SUFFIXES = ['kft', 'zrt', 'bt', 'nyrt', 'kkt', 'ltd', 'gmbh'];

    /** Shortest name or alias a similarity match may be auto-eligible on; short strings match too easily. */
    private const int MIN_AUTO_ELIGIBLE_LENGTH = 4;

    public function __construct(private readonly AiUserSettingsResolver $settingsResolver)
    {
    }

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

    public function match(string $text, User $user): ?PayeeMatch
    {
        $normalizedText = self::normalize($text);

        if ($normalizedText === '') {
            return null;
        }

        $payees = $user->payees()->active()->get(['id', 'name', 'alias', 'config_type', 'config_id', 'user_id', 'active']);

        // Candidate strings per payee: [payee, original string, normalized string, is alias]
        $candidates = $payees->flatMap(fn (AccountEntity $payee) => collect([[$payee->name, false]])
            ->concat(collect(self::aliasLines($payee->alias))->map(fn (string $line) => [$line, true]))
            ->map(fn (array $entry) => [$payee, $entry[0], self::normalize($entry[0]), $entry[1]])
            ->filter(fn (array $entry) => $entry[2] !== ''));

        $exact = $candidates->first(fn (array $entry) => $entry[2] === $normalizedText);
        if ($exact !== null) {
            return new PayeeMatch($exact[0], PayeeMatch::TIER_EXACT, 1.0, 1.0, true);
        }

        $leading = $candidates
            ->filter(fn (array $entry) => $entry[3] && str_starts_with($normalizedText . ' ', $entry[2] . ' '))
            ->sortByDesc(fn (array $entry) => mb_strlen($entry[2]))
            ->first();
        if ($leading !== null) {
            return new PayeeMatch($leading[0], PayeeMatch::TIER_LEADING_TOKEN, 1.0, 1.0, true);
        }

        return $this->bestBySimilarity($normalizedText, $candidates, $user);
    }

    /**
     * @param  Collection<int, array{0: AccountEntity, 1: string, 2: string, 3: bool}>  $candidates
     */
    private function bestBySimilarity(string $normalizedText, Collection $candidates, User $user): ?PayeeMatch
    {
        $settings = $this->settingsResolver->resolveForUser($user);

        // Best score and matched string per payee
        $perPayee = $candidates
            ->groupBy(fn (array $entry) => $entry[0]->id)
            ->map(fn (Collection $entries) => $entries
                ->map(fn (array $entry) => ['payee' => $entry[0], 'string' => $entry[1], 'score' => JaroWinkler::similarity($normalizedText, $entry[2])])
                ->sortByDesc('score')
                ->first())
            ->sortByDesc('score')
            ->values();

        $best = $perPayee->first();

        if ($best === null || $best['score'] < (float) $settings['asset_similarity_threshold']) {
            return null;
        }

        $margin = $best['score'] - (float) ($perPayee->get(1)['score'] ?? 0.0);

        return new PayeeMatch(
            $best['payee'],
            PayeeMatch::TIER_SIMILARITY,
            round($best['score'], 4),
            round($margin, 4),
            $best['score'] >= (float) $settings['payee_similarity_min']
                && $margin >= (float) $settings['payee_similarity_margin']
                && mb_strlen($best['string']) >= self::MIN_AUTO_ELIGIBLE_LENGTH,
        );
    }
}

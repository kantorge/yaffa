<?php

namespace App\Services;

use App\Models\AccountEntity;
use App\Models\AiHint;
use App\Models\User;

class AiHintService
{
    /**
     * Rendered texts of the user's active hints that apply to the given document text.
     *
     * A hint applies when its trigger text (plain, case-insensitive substring) occurs in the document,
     * or when it has no trigger text.
     *
     * @return array<int, string>
     */
    public function hintsForText(User $user, string $text): array
    {
        $hints = $user->aiHints()
            ->active()
            ->orderBy('id')
            ->get()
            ->filter(fn (AiHint $hint) => $hint->trigger_text === null
                || $hint->trigger_text === ''
                || mb_stripos($text, $hint->trigger_text) !== false);

        return $hints
            ->map(fn (AiHint $hint) => $this->render($user, $hint->hint))
            ->values()
            ->all();
    }

    /**
     * Replace {account:ID} and {payee:ID} tokens with the current entity names.
     * Tokens pointing to a missing (or foreign) entity are left untouched.
     */
    public function render(User $user, string $hint): string
    {
        if (! preg_match_all('/\{(account|payee):(\d+)\}/', $hint, $matches, PREG_SET_ORDER)) {
            return $hint;
        }

        $names = AccountEntity::query()
            ->where('user_id', $user->id)
            ->whereIn('id', array_unique(array_map(fn (array $match) => (int) $match[2], $matches)))
            ->pluck('name', 'id');

        return preg_replace_callback(
            '/\{(account|payee):(\d+)\}/',
            fn (array $match) => $names[(int) $match[2]] ?? $match[0],
            $hint
        );
    }
}

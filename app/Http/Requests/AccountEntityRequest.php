<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\FileImportProfile;
use App\Models\Payee;
use App\Services\PayeeMatcher;
use App\Models\TransactionDetailInvestment;
use Closure;
use Illuminate\Validation\Rule;

/**
 * @property AccountEntity $account_entity
 * @property string $config_type
 */
class AccountEntityRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var AccountEntity|null $accountEntity */
        $accountEntity = $this->route('account_entity') ?? $this->route('accountEntity');

        $rules = [
            'name' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                // The unique rule is scoped to the user and the config type of either the current entity or the request
                Rule::unique('account_entities')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('config_type', $this->config_type)
                    ->when($accountEntity, fn ($query) => $query->where('id', '!=', $accountEntity->id))),
            ],
            'config_type' => 'required|in:account,payee',
            'active' => 'boolean',
            'alias' => [
                'nullable',
                'string',
            ],
        ];

        if ($this->config_type === 'account') {
            $rules = array_merge($rules, [
                'preferred_file_import_profile_id' => [
                    'nullable',
                    'integer',
                    function (string $attribute, mixed $value, Closure $fail): void {
                        if ($value === null) {
                            return;
                        }

                        $exists = FileImportProfile::query()
                            ->selectableForUser($this->user())
                            ->where('id', (int) $value)
                            ->exists();

                        if (! $exists) {
                            $fail(__('The selected file import profile is not accessible.'));
                        }
                    },
                ],
                'config.opening_balance' => [
                    'required',
                    'numeric',
                    // Fit in signed DECIMAL(30,10) range
                    'min:-999999999999999999999.9999999999',
                    'max:999999999999999999999.9999999999',
                ],
                'config.account_group_id' => [
                    'required',
                    Rule::exists('account_groups', 'id')
                        ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                ],
                'config.currency_id' => [
                    'required',
                    Rule::exists('currencies', 'id')
                        ->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                    // An investment transaction's commission/tax/dividend are cast to the
                    // account's currency (MoneyCast) while price is cast to the investment's
                    // - changing the account's currency after it's been used would silently
                    // mismatch every existing transaction's stored values against a currency
                    // they were never recorded in.
                    function (string $attribute, mixed $value, Closure $fail) use ($accountEntity): void {
                        if (!$accountEntity instanceof AccountEntity || !$accountEntity->config instanceof Account) {
                            return;
                        }

                        if ((int) $value === (int) $accountEntity->config->currency_id) {
                            return;
                        }

                        $used = TransactionDetailInvestment::where('account_id', $accountEntity->id)->exists();

                        if ($used) {
                            $fail(__('The currency cannot be changed because this account is already used in an investment transaction.'));
                        }
                    },
                ],
                'config.default_date_range' => [
                    'nullable',
                    'string',
                    Rule::in(
                        collect(config('yaffa.account_date_presets'))
                            ->pluck('options')
                            ->flatten(1)
                            ->pluck('value')
                            ->prepend('none')
                            ->all()
                    )
                ],
            ]);
        }

        if ($this->config_type === 'payee') {
            // Payees are matched by normalized name and alias, so those have to stay unique per user.
            // Only a changed value is checked, so existing data never blocks an unrelated edit.
            $rules['name'][] = fn (string $attribute, mixed $value, Closure $fail) => $this->failOnPayeeConflict(
                [(string) $value],
                $accountEntity !== null && PayeeMatcher::normalize($accountEntity->name) === PayeeMatcher::normalize((string) $value) ? [] : [(string) $value],
                $accountEntity,
                $attribute,
                $fail,
            );
            $rules['alias'][] = fn (string $attribute, mixed $value, Closure $fail) => $this->failOnPayeeConflict(
                PayeeMatcher::aliasLines($value),
                $this->changedAliasLines($value, $accountEntity),
                $accountEntity,
                $attribute,
                $fail,
            );

            $rules = array_merge($rules, [
                'config.auto_record_policy' => ['sometimes', Rule::in(Payee::AUTO_RECORD_POLICIES)],
                'config.itemization_expected' => ['sometimes', 'boolean'],
                'config.category_id' => [
                    'nullable',
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                ],
                'config.preferred' => [
                    'nullable',
                    'array',
                ],
                'config.preferred.*' => [
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                    Rule::notIn($this->scalarList('config.not_preferred')),
                ],
                'config.not_preferred' => [
                    'nullable',
                    'array',
                ],
                'config.not_preferred.*' => [
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                    Rule::notIn($this->scalarList('config.preferred')),
                    'different:config.category_id',
                ],
            ]);
        }

        return $rules;
    }

    /**
     * @param  array<int, string>  $own  All values of the field, to catch duplicates within the field itself
     * @param  array<int, string>  $changed  The values that are new or changed and are checked against other payees
     */
    private function failOnPayeeConflict(array $own, array $changed, ?AccountEntity $accountEntity, string $attribute, Closure $fail): void
    {
        $normalized = array_map(PayeeMatcher::normalize(...), $own);

        foreach ($changed as $value) {
            $conflict = PayeeMatcher::findConflict($this->user(), $value, $accountEntity?->id);

            if ($conflict !== null) {
                $fail(__('":value" is already used by the payee ":payee" (names and aliases are compared without numbers and company suffixes).', [
                    'value' => $value,
                    'payee' => $conflict->name,
                ]));

                return;
            }
        }

        if ($attribute === 'alias' && count($normalized) !== count(array_unique($normalized))) {
            $fail(__('The alias lines must be different from each other.'));
        }
    }

    /**
     * The alias lines that are new, comparing by normalized value so a case-only edit is not a change.
     *
     * @return array<int, string>
     */
    private function changedAliasLines(?string $alias, ?AccountEntity $accountEntity): array
    {
        $existing = array_map(PayeeMatcher::normalize(...), PayeeMatcher::aliasLines($accountEntity?->alias));

        return array_values(array_filter(
            PayeeMatcher::aliasLines($alias),
            fn (string $line) => ! in_array(PayeeMatcher::normalize($line), $existing, true),
        ));
    }

    /**
     * Scalar values of an input array, for use as literal Rule::notIn() values.
     *
     * @return array<int, int|string>
     */
    private function scalarList(string $key): array
    {
        return array_values(array_filter((array) $this->input($key), 'is_scalar'));
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
            'preferred_file_import_profile_id' => $this->preferred_file_import_profile_id ?? null,
        ]);

        // Handle category_id - use input() method to handle both array and object notation
        if ($this->has('config')) {
            $this->merge([
                'config.category_id' => $this->input('config.category_id'),
            ]);
        }
    }
}

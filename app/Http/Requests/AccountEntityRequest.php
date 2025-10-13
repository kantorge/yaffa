<?php

namespace App\Http\Requests;

use App\Models\AccountEntity;
use Illuminate\Validation\Rule;

/**
 * @property AccountEntity $account_entity
 * @property string $config_type
 */
class AccountEntityRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                // The unique rule is scoped to the user and the config type of either the current entity or the request
                Rule::unique('account_entities')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->where('config_type', $this->config_type)
                    ->when($this->account_entity, fn ($query) => $query->where('id', '!=', $this->account_entity->id))),
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
            $rules = array_merge($rules, [
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
                    Rule::notIn('config.not_preferred'),
                ],
                'config.not_preferred' => [
                    'nullable',
                    'array',
                ],
                'config.not_preferred.*' => [
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                    Rule::notIn('config.preferred'),
                    'different:config.category_id',
                ],
            ]);
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
            'config.category_id' => $this->config->category_id ?? null,
        ]);
    }
}

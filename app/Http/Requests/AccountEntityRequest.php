<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AccountEntityRequest extends FormRequest
{
    public function rules()
    {
        $rules = [
            'name' => [
                'required',
                'min:2',
                'max:191',
                Rule::unique('account_entities')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->account_entity, function ($query) {
                            return $query
                                ->where('config_type', $this->account_entity->config_type)
                                ->where('id', '!=', $this->account_entity->id);
                        });
                }),
            ],
            'config_type' => 'required|in:account,payee',
            'active' => 'boolean',
        ];

        if ($this->config_type === 'account') {
            $rules = array_merge($rules, [
                'config.opening_balance' => [
                    'required',
                    'numeric',
                ],
                'config.account_group_id' => [
                    'required',
                    Rule::exists('account_groups', 'id')->where(function ($query) {
                        return $query->where('user_id', Auth::user()->id);
                    }),
                ],
                'config.currency_id' => [
                    'required',
                    Rule::exists('currencies', 'id')->where(function ($query) {
                        return $query->where('user_id', Auth::user()->id);
                    }),
                ],
            ]);
        }

        if ($this->config_type === 'payee') {
            $rules = array_merge($rules, [
                'config.category_id' => [
                    'nullable',
                    Rule::exists('categories', 'id')->where(function ($query) {
                        return $query->where('user_id', Auth::user()->id);
                    }),
                ],
            ]);
        }

        return $rules;
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
            'config.category_id' => $this->config->category_id ?? null,
        ]);
    }
}

<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BudgetSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
            ],
            'all' => ['nullable', 'boolean'],
            'data_through' => ['nullable', 'date_format:Y-m'],
            'sensitivity' => ['nullable', Rule::in(array_keys(config('yaffa.budget_suggestions.sensitivity')))],
            'bias' => ['nullable', Rule::in(['neutral', 'conservative', 'conservative_rounded'])],
        ];
    }

    /**
     * Exactly one of category_ids (non-empty) or all=1 is required.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasIds = ! empty($this->input('category_ids'));
                $all = $this->boolean('all');

                if ($hasIds === $all) {
                    $validator->errors()->add('category_ids', __('Provide either category_ids or all=1.'));
                }
            },
        ];
    }
}

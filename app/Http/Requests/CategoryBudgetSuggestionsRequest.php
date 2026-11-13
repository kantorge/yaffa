<?php

namespace App\Http\Requests;

class CategoryBudgetSuggestionsRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'calculate_budget_suggestions' => ['required', 'boolean'],
        ];
    }
}

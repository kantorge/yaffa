<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetChartRequest extends FormRequest
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
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer'],
            'accountSelection' => ['nullable', 'string', Rule::in(['any', 'selected', 'none'])],
            'accountEntity' => ['nullable', 'integer'],
        ];
    }
}

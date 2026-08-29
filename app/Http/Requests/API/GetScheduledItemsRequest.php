<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class GetScheduledItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'              => ['nullable', 'string'],
            'category_required' => ['nullable', 'boolean'],
            'categories'        => ['nullable', 'array'],
            'categories.*'      => ['integer'],
            'accountSelection'  => ['nullable', 'string'],
            'accountEntity'     => ['required_if:accountSelection,selected', 'integer'],
            'includeBudgets'    => ['nullable', 'boolean'],
            'includeItemDetails' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class FindTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from'    => ['nullable', 'date'],
            'date_to'      => ['nullable', 'date', 'after_or_equal:date_from'],
            'accounts'     => ['nullable', 'array'],
            'accounts.*'   => ['integer'],
            'payees'       => ['nullable', 'array'],
            'payees.*'     => ['integer'],
            'categories'   => ['nullable', 'array'],
            'categories.*' => ['integer'],
            'tags'         => ['nullable', 'array'],
            'tags.*'       => ['integer'],
            'only_count'   => ['nullable', 'boolean'],
        ];
    }
}

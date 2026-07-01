<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class FindTransactionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['accounts', 'payees', 'categories', 'tags'] as $field) {
            if ($this->has($field) && is_array($this->input($field)) && empty($this->input($field))) {
                $this->request->remove($field);
                $this->query->remove($field);
            }
        }

        if ($this->has('only_count') && ! $this->boolean('only_count')) {
            $this->request->remove('only_count');
            $this->query->remove('only_count');
        }
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

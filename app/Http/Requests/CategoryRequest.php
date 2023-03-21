<?php

namespace App\Http\Requests;

class CategoryRequest extends FormRequest
{
    public function rules()
    {
        return [
            'name' => [
                'required',
                'min:2',
                'max:191',
            ],
            'active' => [
                'boolean',
            ],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
            'parent_id' => $this->parent_id ?? null,
        ]);
    }
}

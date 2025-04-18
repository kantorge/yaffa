<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('categories')->where(function ($query) {
                    // If it's a parent category
                    if (empty($this->parent_id)) {
                        return $query->whereNull('parent_id');
                    }

                    // If it's a child category
                    return $query->where('parent_id', $this->parent_id);
                })->ignore($this->category),
            ],
            'active' => [
                'boolean',
            ],
            'parent_id' => [
                'nullable',
                'exists:categories,id',
            ],
            'default_aggregation' => [
                'required',
                Rule::in(['month', 'quarter', 'year']),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
            'parent_id' => $this->parent_id ?? null,
        ]);
    }
}

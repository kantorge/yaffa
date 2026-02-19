<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Contracts\Validation\Validator;
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
                    $query->where('user_id', $this->user()->id);

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
                Rule::exists('categories', 'id')->where('user_id', $this->user()->id),
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $parentId = $this->input('parent_id');
            if (empty($parentId)) {
                return;
            }

            $routeCategory = $this->route('category');
            $currentCategoryId = $routeCategory instanceof Category ? $routeCategory->getKey() : null;

            if ($currentCategoryId && (int) $parentId === (int) $currentCategoryId) {
                $validator->errors()->add('parent_id', __('A category cannot be its own parent.'));

                return;
            }

            $allCategories = Category::query()
                ->where('user_id', $this->user()->id)
                ->get(['id', 'parent_id'])
                ->keyBy('id');

            $visited = [];
            if ($currentCategoryId) {
                $visited[(int) $currentCategoryId] = true;
            }

            $nextParentId = (int) $parentId;
            while ($nextParentId > 0) {
                if (isset($visited[$nextParentId])) {
                    $validator->errors()->add('parent_id', __('Invalid category hierarchy: parent loop detected.'));

                    return;
                }
                $visited[$nextParentId] = true;

                $nextParent = $allCategories->get($nextParentId);
                $nextParentId = (int) ($nextParent->parent_id ?? 0);
            }
        });
    }
}

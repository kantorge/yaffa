<?php

namespace App\Http\Requests;

use App\Services\CategoryLearningService;
use Illuminate\Validation\Rule;

class CategoryLearningRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->routeIs('api.v1.category-learning.index')) {
            return;
        }

        $description = $this->input('item_description');

        if (is_string($description)) {
            $description = app(CategoryLearningService::class)->normalize($description);
        }

        $this->merge([
            'item_description' => $description,
        ]);
    }

    public function rules(): array
    {
        if ($this->routeIs('api.v1.category-learning.index')) {
            return $this->indexRules();
        }

        if ($this->routeIs('api.v1.category-learning.store') || $this->routeIs('api.v1.category-learning.update')) {
            return $this->upsertRules();
        }

        return [];
    }

    private function indexRules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive,all'],
        ];
    }

    private function upsertRules(): array
    {
        return [
            'item_description' => $this->itemDescriptionRules(),
            'category_id' => $this->categoryIdRules(),
            'active' => ['required', 'boolean'],
        ];
    }

    private function itemDescriptionRules(): array
    {
        $rules = [
            'required',
            'string',
            'max:255',
        ];

        if ($this->routeIs('api.v1.category-learning.update')) {
            $categoryLearningId = (int) ($this->route('categoryLearning')?->getKey() ?? 0);

            $rules[] = Rule::unique('category_learning', 'item_description')
                ->where(fn ($query) => $query->where('user_id', $this->user()->id))
                ->ignore($categoryLearningId);
        }

        return $rules;
    }

    private function categoryIdRules(): array
    {
        return [
            'required',
            'integer',
            Rule::exists('categories', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
        ];
    }
}

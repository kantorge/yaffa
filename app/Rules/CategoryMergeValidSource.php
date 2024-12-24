<?php

namespace App\Rules;

use App\Models\Category;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class CategoryMergeValidSource implements Rule, DataAwareRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array  $data
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     *
     * @return bool
     */
    public function passes($attribute, $value): bool
    {
        // Fail if source category or target category is not set
        if (! isset($this->data['category_source']) || ! isset($this->data['category_target'])) {
            return false;
        }

        // Hydrate source model from value
        $categorySource = Category::find($value);

        // Hydrate target model from retrieved data
        $categoryTarget = Category::find($this->data['category_target']);

        // Check invalid combination, where source is a parent (it's parent is null) and target is a child (it's parent is not null)
        return ! ($categorySource->parent_id === null && $categoryTarget->parent_id !== null);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): string
    {
        return __('Cannot merge a parent category into a child category.');
    }
}

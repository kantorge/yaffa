<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class CategoryMergeValidSource implements DataAwareRule, ValidationRule
{
    /**
     * All of the data under validation.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Set the data under validation.
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  mixed  $value
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function passes(string $attribute, $value): bool
    {
        // Fail if source category or target category is not set
        if (!isset($this->data['category_source']) || !isset($this->data['category_target'])) {
            return false;
        }

        // Hydrate source model from value
        $categorySource = Category::find($value);

        // Hydrate target model from retrieved data
        $categoryTarget = Category::find($this->data['category_target']);

        // Check invalid combination, where source is a parent (it's parent is null) and target is a child (it's parent is not null)
        return !($categorySource->parent_id === null && $categoryTarget->parent_id !== null);
    }

    /**
     * Run the validation rule for the ValidationRule contract (Laravel 11).
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->passes($attribute, $value)) {
            $fail($this->message());
        }
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('Cannot merge a parent category into a child category.');
    }
}

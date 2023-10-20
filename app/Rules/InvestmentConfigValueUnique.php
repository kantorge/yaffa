<?php

namespace App\Rules;

use App\Models\AccountEntity;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\Rule;

class InvestmentConfigValueUnique implements DataAwareRule, Rule
{
    private string $attribute;

    private ?AccountEntity $existingAccountEntity;

    public function __construct(string $attribute, ?AccountEntity $existingAccountEntity)
    {
        $this->attribute = $attribute;
        $this->existingAccountEntity = $existingAccountEntity;
    }

    /**
     * All of the data under validation.
     */
    protected array $data = [];

    /**
     * Set the data under validation.
     *
     * @param  array  $data
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
     */
    public function passes($attribute, $value): bool
    {
        // Get the current user
        $user = auth()->user();

        // Get all investments for the user, except the one being edited.
        $investments = $user->investments()
            ->when($this->existingAccountEntity, fn ($query) => $query->where('id', '!=', $this->existingAccountEntity->id))
            ->get();

        // Check if the requested value is unique
        return $investments->contains(fn ($investment) => $investment->config[$this->attribute] === $value) === false;
    }

    /**
     * Get the validation error message.
     */
    public function message(): string
    {
        return __('The :attribute has already been taken.');
    }
}

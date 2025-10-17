<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Validation\Rule;

/**
 * @property Tag $tag
 */
class TagRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Pass ID to unique check, if it exists in request
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('tags')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->when($this->tag, fn ($query) => $query->where('id', '!=', $this->tag->id))),
            ],
            'active' => [
                'boolean',
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
        ]);
    }
}

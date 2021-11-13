<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Pass ID to unique check, if it exists in request
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => [
                'required',
                'min:2',
                'max:191',
                Rule::unique('tags')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->tag, function ($query) {
                            return $query->where('id', '!=', $this->tag->id);
                        });
                }),
            ],
            'active' => [
                'boolean',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Ensure that checkbox values are available
        $this->merge([
            'active' => $this->active ?? 0,
        ]);
    }
}

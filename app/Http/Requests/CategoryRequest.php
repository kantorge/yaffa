<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

class CategoryRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|min:2|max:191',
            'active' => 'boolean',
            'parent_id' => 'nullable|exists:categories,id',
        ];

        return $rules;
    }

    /**
     * Load validator error messages to standard notifications array
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {

        $validator->after(function (Validator $validator) {
            foreach ($validator->errors()->all() as $message) {
                add_notification($message, 'danger');
            }
        });
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        //check for checkbox-es
        $this->merge([
            'active' => $this->active ?? 0,
            'parent_id' => $this->parent_id ?? null,
        ]);
    }
}

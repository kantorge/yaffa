<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CurrencyRequest extends FormRequest
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
                Rule::unique('currencies')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->currency, function ($query) {
                            return $query->where('id', '!=', $this->currency->id);
                        });
                }),
            ],
            'iso_code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->currency, function ($query) {
                            return $query->where('id', '!=', $this->currency->id);
                        });
                }),
            ],
            'num_digits' => [
                'required',
                'numeric',
                'between:0,4',
            ],
            'suffix' => [
                'string',
                'nullable',
                'max:5',
            ],
            'base' => [
                'boolean',
                'nullable',
                Rule::unique('currencies')->where(function ($query) {
                    return $query->where('user_id', $this->user()->id);
                }),
            ],
            'auto_update' => [
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
            'base' => $this->base ?? null,
            'auto_update' => $this->auto_update ?? 0,
        ]);

        // Make ISO code uppercase
        $this->merge([
            'iso_code' => Str::upper($this->iso_code),
        ]);
    }
}

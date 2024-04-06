<?php

namespace App\Http\Requests;

use App\Models\Currency;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @property Currency $currency
 * @property string $iso_code
 */
class CurrencyRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     * Pass ID to unique check, if it exists in request
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('currencies')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->currency, fn ($query) => $query->where('id', '!=', $this->currency->id));
                }),
            ],
            'iso_code' => [
                'required',
                'string',
                'size:3',
                Rule::unique('currencies')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->currency, fn ($query) => $query->where('id', '!=', $this->currency->id));
                }),
            ],
            'auto_update' => [
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
            'base' => $this->base ?? null,
            'auto_update' => $this->auto_update ?? 0,
        ]);

        // Make ISO code uppercase
        $this->merge([
            'iso_code' => Str::upper($this->iso_code),
        ]);
    }
}

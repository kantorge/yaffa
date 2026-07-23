<?php

namespace App\Http\Requests;

use App\Enums\ApiTokenAbility;
use Illuminate\Validation\Rule;

class ApiTokenRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $maxExpiresAt = now()->addDays((int) config('yaffa.api_token_max_lifetime_days'));

        return [
            'name' => [
                'required',
                'string',
                'min:' . self::DEFAULT_STRING_MIN_LENGTH,
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],
            'abilities' => [
                'required',
                'array',
                'min:1',
            ],
            'abilities.*' => [
                Rule::in(ApiTokenAbility::values()),
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:now',
                'before_or_equal:' . $maxExpiresAt->toDateTimeString(),
            ],
        ];
    }
}

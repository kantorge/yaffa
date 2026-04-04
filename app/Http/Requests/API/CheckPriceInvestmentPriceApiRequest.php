<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckPriceInvestmentPriceApiRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ['date' => [
                    'required',
                    Rule::date()->format('Y-m-d'),
                ],];
    }
}

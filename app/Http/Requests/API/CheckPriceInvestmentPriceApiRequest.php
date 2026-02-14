<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class CheckPriceInvestmentPriceApiRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ['date' => [
            'required',
            'date_format:Y-m-d',
        ],];
    }
}

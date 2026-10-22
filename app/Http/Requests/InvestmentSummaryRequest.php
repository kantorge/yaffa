<?php

namespace App\Http\Requests;

class InvestmentSummaryRequest extends FormRequest
{
    public function rules(): array
    {
        return ['active' => ['sometimes', 'required', 'in:0,1,all']];
    }
}

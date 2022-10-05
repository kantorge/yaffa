<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class InvestmentGroupRequest extends FormRequest
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
                Rule::unique('investment_groups')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->investment_group, function ($query) {
                            return $query->where('id', '!=', $this->investment_group->id);
                        });
                }),
            ],
        ];
    }
}

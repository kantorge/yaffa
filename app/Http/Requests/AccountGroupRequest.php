<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AccountGroupRequest extends FormRequest
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
                Rule::unique('account_groups')->where(function ($query) {
                    return $query
                        ->where('user_id', $this->user()->id)
                        ->when($this->account_group, fn ($query) => $query->where('id', '!=', $this->account_group->id));
                }),
            ],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\AccountGroup;
use Illuminate\Validation\Rule;

/**
 * @property AccountGroup $account_group
 */
class AccountGroupRequest extends FormRequest
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
                Rule::unique('account_groups')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->when($this->account_group, fn ($query) => $query->where('id', '!=', $this->account_group->id))),
            ],
        ];
    }
}

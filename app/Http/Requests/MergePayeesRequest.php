<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MergePayeesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payee_source' => [
                'required',
                Rule::exists('account_entities', 'id')->where(function ($query) {
                    $query->where('config_type', 'payee')
                        ->where('user_id', $this->user()->id);
                }),
            ],
            'payee_target' => [
                'required',
                Rule::exists('account_entities', 'id')->where(function ($query) {
                    $query->where('config_type', 'payee')
                        ->where('user_id', $this->user()->id);
                }),
                'different:payee_source',
            ],
            'action' => [
                'required',
                'in:delete,close',
            ],
        ];
    }
}

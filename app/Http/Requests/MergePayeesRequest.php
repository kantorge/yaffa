<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
                'exists:account_entities,id,config_type,payee',
            ],
            'payee_target' => [
                'required',
                'exists:account_entities,id,config_type,payee',
                'different:payee_source',
            ],
            'action' => [
                'required',
                'in:delete,close',
            ],
        ];
    }
}

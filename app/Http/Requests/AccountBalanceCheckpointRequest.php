<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AccountBalanceCheckpointRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'checkpoint_date' => ['required', 'date'],
            'checkpoint_type' => ['required', Rule::in(['cash', 'investment', 'total'])],
            'balance' => ['required', 'numeric', 'min:-9999999999999.99', 'max:9999999999999.99'],
            'note' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:191'],
            'source_document_id' => ['nullable', 'string', 'max:191'],
        ];
    }
}

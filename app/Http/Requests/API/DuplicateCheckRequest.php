<?php

namespace App\Http\Requests\API;

use App\Http\Requests\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The fields of a transaction being entered that identify the purchase, see SameEventClassifier::keyFromEntry().
 */
class DuplicateCheckRequest extends FormRequest
{
    public function rules(): array
    {
        $ownedEntity = fn (string $configType) => Rule::exists('account_entities', 'id')->where(
            fn ($query) => $query->where('user_id', $this->user()->id)->where('config_type', $configType)
        );

        return [
            'config_type' => ['required', 'in:standard,investment'],
            'transaction_type' => ['required', 'string'],
            'account_id' => ['nullable', 'integer', $ownedEntity('account')],
            'payee_id' => ['nullable', 'integer', $ownedEntity('payee')],
            'date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'exclude_transaction_id' => [
                'nullable',
                'integer',
                Rule::exists('transactions', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}

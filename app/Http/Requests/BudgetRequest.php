<?php

namespace App\Http\Requests;

use App\Enums\TransactionType as TransactionTypeEnum;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            'category_id' => __('category'),
            'account_id' => __('account'),
            'transaction_type' => __('transaction type'),
            'amount' => __('amount'),
            'start_date' => __('schedule start date'),
            'end_date' => __('schedule end date'),
            'frequency' => __('schedule frequency'),
            'interval' => __('schedule interval'),
            'count' => __('schedule count'),
            'inflation' => __('schedule inflation'),
        ];
    }

    public function rules(): array
    {
        $ownedCategoryRule = Rule::exists('categories', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id);
        });

        $ownedAccountRule = Rule::exists('account_entities', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id)
                ->where('config_type', 'account');
        });

        return [
            'category_id' => ['required', $ownedCategoryRule],
            'account_id' => ['nullable', $ownedAccountRule],
            // A Budget is a category-level target, mirroring only the standard (non-transfer,
            // non-investment) transaction types - see FR-4/Non-Goals.
            'transaction_type' => [
                'required',
                Rule::in([TransactionTypeEnum::WITHDRAWAL->value, TransactionTypeEnum::DEPOSIT->value]),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'comment' => [
                'nullable',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],

            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                // Must be empty, if count is provided
                'prohibits:count',
            ],
            'frequency' => [
                'required',
                Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
            ],
            'interval' => 'nullable|integer|gte:1',
            'count' => [
                'nullable',
                'integer',
                'gte:1',
                // Must be empty, if end_date is provided
                'prohibits:end_date',
            ],
            'inflation' => 'nullable|numeric',
        ];
    }
}

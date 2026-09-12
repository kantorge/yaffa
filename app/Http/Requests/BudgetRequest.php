<?php

namespace App\Http\Requests;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\ValidatesRecurrenceRule;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    use ValidatesRecurrenceRule;

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
            'by_day' => __('schedule day of week'),
            'by_month' => __('schedule month'),
            'count' => __('schedule count'),
            'inflation' => __('schedule inflation'),
            'original_schedule_config.start_date' => __('original schedule start date'),
            'original_schedule_config.end_date' => __('original schedule end date'),
            'original_schedule_config.frequency' => __('original schedule frequency'),
            'original_schedule_config.interval' => __('original schedule interval'),
            'original_schedule_config.by_day' => __('original schedule day of week'),
            'original_schedule_config.by_month' => __('original schedule month'),
            'original_schedule_config.count' => __('original schedule count'),
            'original_schedule_config.inflation' => __('original schedule inflation'),
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

        $ownedBudgetRule = Rule::exists('budgets', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id);
        });

        $rules = [
            // 'replace' (mirroring TransactionRequest) closes out the source budget (via
            // 'id' + 'original_schedule_config') and creates this one as its replacement -
            // absent entirely, store()/update() behave exactly as before.
            'action' => 'nullable|in:new,edit,replace',
            'id' => [
                'nullable',
                $ownedBudgetRule,
                Rule::requiredIf(fn () => $this->input('action') === 'replace'),
            ],

            'category_id' => ['required', $ownedCategoryRule],
            'account_id' => ['nullable', $ownedAccountRule],
            // A Budget is a category-level target, mirroring only the standard (non-transfer,
            // non-investment) transaction types - see FR-4/Non-Goals.
            'transaction_type' => [
                'required',
                Rule::in([TransactionTypeEnum::WITHDRAWAL->value, TransactionTypeEnum::DEPOSIT->value]),
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                // Fit in unsigned DECIMAL(12,4) range
                'max:99999999.9999',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],

            'start_date' => [
                'required',
                'date',
                $this->maxRecurrencePeriodsRule('frequency', 'interval'),
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
            'by_day' => $this->byDayRule('frequency'),
            'by_month' => $this->byMonthRule('frequency', 'by_day'),
            'count' => [
                'nullable',
                'integer',
                'gte:1',
                // Must be empty, if end_date is provided
                'prohibits:end_date',
            ],
            'inflation' => 'nullable|numeric|min:-100',
        ];

        // Add optional rules for closing out the source budget being replaced
        if ($this->input('action') === 'replace') {
            $rules = array_merge($rules, [
                'original_schedule_config.start_date' => [
                    'required',
                    'date',
                    $this->maxRecurrencePeriodsRule('original_schedule_config.frequency', 'original_schedule_config.interval'),
                ],
                'original_schedule_config.end_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:original_schedule_config.start_date',
                    'prohibits:original_schedule_config.count',
                ],
                'original_schedule_config.frequency' => [
                    'required',
                    Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
                ],
                'original_schedule_config.interval' => 'nullable|integer|gte:1',
                'original_schedule_config.by_day' => $this->byDayRule('original_schedule_config.frequency'),
                'original_schedule_config.by_month' => $this->byMonthRule('original_schedule_config.frequency', 'original_schedule_config.by_day'),
                'original_schedule_config.count' => [
                    'nullable',
                    'integer',
                    'gte:1',
                    'prohibits:original_schedule_config.end_date',
                ],
                'original_schedule_config.inflation' => 'nullable|numeric|min:-100',
            ]);
        }

        return $rules;
    }
}

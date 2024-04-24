<?php

namespace App\Http\Requests;

use App\Rules\IsFalsy;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            // Investment specific fields
            'config.account_id' => __('account'),
            'config.investment_id' => __('investment'),
            'config.dividend' => __('dividend'),
            'config.quantity' => __('quantity'),
            'config.price' => __('price'),
            'config.commission' => __('commission'),
            'config.tax' => __('tax'),
            // Standard fields
            'config.amount_to' => __('amount to'),
            // Schedule fields
            'schedule_config.start_date' => __('schedule start date'),
            'schedule_config.next_date' => __('schedule next date'),
            'schedule_config.end_date' => __('schedule end date'),
            'schedule_config.frequency' => __('schedule frequency'),
            'schedule_config.interval' => __('schedule interval'),
            'schedule_config.count' => __('schedule count'),
            'schedule_config.inflation' => __('schedule inflation'),
        ];
    }

    public function rules(): array
    {
        $rules = [
            'action' => 'required|in:create,edit,clone,enter,replace,finalize',
            'fromModal' => 'nullable|boolean',

            'id' => 'nullable|exists:transactions,id',
            'transaction_type_id' => 'required|exists:transaction_types,id',
            'comment' => [
                'nullable',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],
            'reconciled' => 'boolean',
            'schedule' => 'boolean',
            'budget' => 'boolean',
            'config_type' => 'required|in:standard,investment',

            'source_id' => 'nullable|exists:App\Models\ReceivedMail,id',
        ];

        // Basic transaction has no schedule at all, or has only schedule enabled
        $isBasic = (!$this->get('schedule') && !$this->get('budget')) || $this->get('schedule');

        // Set date and schedule related rules
        if ($this->get('schedule') || $this->get('budget')) {
            $rules = array_merge($rules, [
                'reconciled' => [
                    'boolean',
                    new IsFalsy(), // Scheduled or budgeted items cannot be reconciled
                ],

                'schedule_config.start_date' => [
                    'required',
                    'date',
                ],
                'schedule_config.next_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:schedule_config.start_date',
                ],
                'schedule_config.automatic_recording' => [
                    'boolean'
                ],
                'schedule_config.end_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:schedule_config.start_date',
                    'after_or_equal:schedule_config.next_date',
                    // Must be empty, if count is provided
                    'prohibits:schedule_config.count',
                ],
                'schedule_config.frequency' => [
                    'required',
                    Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
                ],
                'schedule_config.interval' => 'nullable|integer|gte:1',
                'schedule_config.count' => [
                    'nullable',
                    'integer',
                    'gte:1',
                    // Must be empty, if end_date is provided
                    'prohibits:schedule_config.end_date',
                ],
                'schedule_config.inflation' => 'nullable|numeric',
            ]);
        } else {
            $rules = array_merge($rules, [
                'date' => 'required|date',
            ]);
        }

        // Add optional rules for replacing a schedule
        if ($this->get('action') === 'replace') {
            $rules = array_merge($rules, [
                'original_schedule_config.start_date' => 'required|date',
                'original_schedule_config.next_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:original_schedule_config.start_date',
                ],
                'original_schedule_config.end_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:original_schedule_config.start_date',
                ],
                'original_schedule_config.frequency' => [
                    'required',
                    Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
                ],
                'original_schedule_config.interval' => 'nullable|integer|gte:1',
                'original_schedule_config.count' => 'nullable|integer|gte:1',
                'original_schedule_config.inflation' => 'nullable|numeric',
            ]);
        }

        // Adjustments based on transaction type
        if ($this->get('config_type') === 'standard') {
            //any standard transactions have common rules for items
            $rules = array_merge($rules, [
                'items' => 'array',
                'items.*' => 'array',
                'items.*.amount' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],
                'items.*.category_id' => [
                    'required',
                    'exists:categories,id',
                ],
                'items.*.comment' => 'nullable|max:191',
                'items.*.tags' => 'array',
                //TODO: rule validation with option to create new tag
                //'transactionItems.*.tags.*' => 'nullable|exists:tags,id',
            ]);

            //adjust detail related rules, based on transaction type
            //accounts are only needed for basic setup (not budget only)
            if ($this->get('transaction_type') === 'withdrawal') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,account',
                    ],
                    'config.account_to_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,payee',
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',

                    //technical field, but required for standard transaction
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => 'nullable|exists:categories,id',

                ]);
            } elseif ($this->get('transaction_type') === 'deposit') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,payee',
                    ],
                    'config.account_to_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,account',
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',

                    // Technical fields, but required for standard transaction
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => 'nullable|exists:categories,id',

                ]);
            } elseif ($this->get('transaction_type') === 'transfer') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        'exists:account_entities,id,config_type,account',
                    ],
                    'config.account_to_id' => [
                        'required',
                        'exists:account_entities,id,config_type,account',
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0',
                ]);
            }
        } elseif ($this->get('config_type') === 'investment') {
            // Adjust detail related rules, based on transaction type
            $rules = array_merge($rules, [
                'config.account_id' => [
                    'required',
                    'exists:account_entities,id,config_type,account',
                ],
                'config.investment_id' => [
                    'required',
                    'exists:investments,id',
                ],
                'config.commission' => 'nullable|numeric|gte:0',
                'config.tax' => 'nullable|numeric|gte:0',
            ]);

            //TODO: validate currency of account and investment

            $rules = array_merge($rules, $this->getInvestmentAmountRules($this->transaction_type_id));
        }

        return $rules;
    }

    private function getInvestmentAmountRules($transactionTypeId): array
    {
        // Buy OR Sell
        if ($transactionTypeId === 4 || $transactionTypeId === 5) {
            return [
                'config.price' => 'required|numeric|gt:0',
                'config.quantity' => 'required|numeric|gt:0',
            ];
        }

        // Add shares OR Remove shares
        if ($transactionTypeId === 6 || $transactionTypeId === 7) {
            return [
                'config.quantity' => 'required|numeric|gt:0',
            ];
        }

        // Dividend OR Interest yield
        if ($transactionTypeId === 8 || $transactionTypeId === 11) {
            return [
                'config.dividend' => 'required|numeric|gt:0',
            ];
        }

        // Earlier cap gains (9 and 10) are not used currently

        // Fallback
        return [];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Get transaction type ID by name
        if ($this->transaction_type) {
            $this->merge([
                'transaction_type_id' => config('transaction_types')[$this->transaction_type]['id']
            ]);
        }

        // Ensure that flags are set to false if not provided
        $this->merge([
            'reconciled' => $this->reconciled ?? 0,
            'schedule' => $this->schedule ?? 0,
            'budget' => $this->budget ?? 0,
        ]);
    }
}

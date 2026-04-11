<?php

namespace App\Http\Requests;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Rules\IsFalsy;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function attributes(): array
    {
        return [
            'transaction_type' => __('transaction type'),
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
            'ai_document_id' => __('AI document'),
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
        $ownedTransactionRule = Rule::exists('transactions', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id);
        });

        $ownedCategoryRule = Rule::exists('categories', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id);
        });

        $ownedAccountRule = Rule::exists('account_entities', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id)
                ->where('config_type', 'account');
        });

        $ownedPayeeRule = Rule::exists('account_entities', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id)
                ->where('config_type', 'payee');
        });

        $ownedInvestmentRule = Rule::exists('investments', 'id')->where(function ($query) {
            $query->where('user_id', $this->user()->id);
        });

        $rules = [
            'action' => 'required|in:create,edit,clone,enter,replace,finalize',

            'id' => ['nullable', $ownedTransactionRule],
            'transaction_type' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (TransactionTypeEnum::tryFrom($value) === null) {
                        $fail('The ' . $attribute . ' is invalid.');
                    }
                },
            ],
            'comment' => [
                'nullable',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
            ],
            'reconciled' => 'boolean',
            'schedule' => 'boolean',
            'budget' => 'boolean',
            'config_type' => 'required|in:standard,investment',

            // Optional AI document association - exists, owned by the user, and not already finalized
            'ai_document_id' => [
                'nullable',
                Rule::exists('ai_documents', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id)
                        ->where('status', '!=', 'finalized');
                }),
            ],
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
                    $ownedCategoryRule,
                ],
                'items.*.comment' => 'nullable|max:' . self::DEFAULT_STRING_MAX_LENGTH,
                'items.*.tags' => 'array',
                //TODO: rule validation with option to create new tag
                //'transactionItems.*.tags.*' => 'nullable|exists:tags,id',

                // Fields related to AI-based matching and learning
                'items.*.description' => 'nullable|max:' . self::DEFAULT_STRING_MAX_LENGTH,
                'items.*.learnRecommendation' => 'nullable|boolean',
            ]);

            // Adjust detail related rules, based on transaction type
            // Accounts are only needed for basic setup (not budget only)
            if ($this->get('transaction_type') === 'withdrawal') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        $ownedAccountRule,
                    ],
                    'config.account_to_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        $ownedPayeeRule,
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',

                    // Technical field, but required for standard transaction
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => ['nullable', $ownedCategoryRule],

                ]);
            } elseif ($this->get('transaction_type') === 'deposit') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        $ownedPayeeRule,
                    ],
                    'config.account_to_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        $ownedAccountRule,
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',

                    // Technical fields, but required for standard transaction
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => ['nullable', $ownedCategoryRule],

                ]);
            } elseif ($this->get('transaction_type') === 'transfer') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        $ownedAccountRule,
                    ],
                    'config.account_to_id' => [
                        'required',
                        $ownedAccountRule,
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
                    $ownedAccountRule,
                ],
                'config.investment_id' => [
                    'required',
                    $ownedInvestmentRule,
                ],
                'config.commission' => 'nullable|numeric|gte:0',
                'config.tax' => 'nullable|numeric|gte:0',
            ]);

            //TODO: validate currency of account and investment

            $rules = array_merge($rules, $this->getInvestmentAmountRules($this->transaction_type));
        }

        return $rules;
    }

    private function getInvestmentAmountRules($transactionType): array
    {
        $transactionTypeEnum = TransactionTypeEnum::tryFrom($transactionType);

        if ($transactionTypeEnum === null) {
            return [];
        }

        // Buy OR Sell
        if ($transactionTypeEnum === TransactionTypeEnum::BUY || $transactionTypeEnum === TransactionTypeEnum::SELL) {
            return [
                'config.price' => 'required|numeric|gt:0',
                'config.quantity' => 'required|numeric|gt:0',
            ];
        }

        // Add shares OR Remove shares
        if ($transactionTypeEnum === TransactionTypeEnum::ADD_SHARES || $transactionTypeEnum === TransactionTypeEnum::REMOVE_SHARES) {
            return [
                'config.quantity' => 'required|numeric|gt:0',
            ];
        }

        // Dividend OR Interest yield
        if ($transactionTypeEnum === TransactionTypeEnum::DIVIDEND || $transactionTypeEnum === TransactionTypeEnum::INTEREST_YIELD) {
            return [
                'config.dividend' => 'required|numeric|gt:0',
            ];
        }

        // Fallback
        return [];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure that flags are set to false if not provided
        $this->merge([
            'reconciled' => $this->reconciled ?? 0,
            'schedule' => $this->schedule ?? 0,
            'budget' => $this->budget ?? 0,
        ]);
    }
}

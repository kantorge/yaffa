<?php

namespace App\Http\Requests;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\TransactionSchedule;
use App\Rules\IsFalsy;
use Closure;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Recurr\Exception\InvalidArgument;
use Recurr\Exception\InvalidWeekday;

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
            'schedule_config.by_day' => __('schedule day of week'),
            'schedule_config.by_month' => __('schedule month'),
            'schedule_config.inflation' => __('schedule inflation'),
            'original_schedule_config.start_date' => __('original schedule start date'),
            'original_schedule_config.next_date' => __('original schedule next date'),
            'original_schedule_config.end_date' => __('original schedule end date'),
            'original_schedule_config.frequency' => __('original schedule frequency'),
            'original_schedule_config.interval' => __('original schedule interval'),
            'original_schedule_config.count' => __('original schedule count'),
            'original_schedule_config.by_day' => __('original schedule day of week'),
            'original_schedule_config.by_month' => __('original schedule month'),
            'original_schedule_config.inflation' => __('original schedule inflation'),
        ];
    }

    /**
     * Ordinal-weekday BYDAY rule (e.g. "1WE", "-1FR"), only meaningful for
     * MONTHLY/YEARLY frequencies.
     */
    private function byDayRule(string $frequencyField): array
    {
        return [
            'nullable',
            'string',
            'regex:/^(-?[1-4])(MO|TU|WE|TH|FR|SA|SU)$/',
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && !in_array($this->input($frequencyField), ['MONTHLY', 'YEARLY'], true)) {
                    $fail(__('Day-of-week recurrence requires a monthly or yearly frequency.'));
                }
            },
        ];
    }

    /**
     * Month (1-12) pinning a YEARLY ordinal-weekday rule to a specific month,
     * e.g. "last Friday of November". Required whenever a YEARLY by_day is
     * set, since recurr resolves an unscoped YEARLY BYDAY across the whole
     * year rather than per month.
     */
    private function byMonthRule(string $frequencyField, string $byDayField): array
    {
        return [
            'nullable',
            'integer',
            'between:1,12',
            // A plain closure is skipped by the validator when the field is null and
            // 'nullable' is present, so the "required" direction needs an implicit
            // rule (Rule::requiredIf isn't skipped) rather than a closure fail().
            Rule::requiredIf(fn () => $this->input($frequencyField) === 'YEARLY' && (bool) $this->input($byDayField)),
            // Reject the inverse too: TransactionSchedule::buildRule() only applies
            // by_month when by_day is also set, so a YEARLY schedule without a
            // by_day would silently ignore by_month rather than use it.
            Rule::prohibitedIf(fn () => $this->input($frequencyField) === 'YEARLY' && !$this->input($byDayField)),
            function ($attribute, $value, $fail) use ($frequencyField) {
                if ($value && $this->input($frequencyField) !== 'YEARLY') {
                    $fail(__('Month only applies to yearly day-of-week recurrence.'));
                }
            },
        ];
    }

    /**
     * next_date is trusted verbatim wherever a real transaction gets recorded -
     * see TransactionSchedule::occursOn() for why - so a value that isn't an
     * actual occurrence of the configured rule (e.g. left over from before a
     * frequency/pattern change, or just hand-typed) needs to be caught here
     * rather than only degrading gracefully at read time.
     */
    private function nextDateOccursOnRule(string $prefix): Closure
    {
        return function ($attribute, $value, $fail) use ($prefix) {
            if (!$value) {
                return;
            }

            $frequency = $this->input("{$prefix}.frequency");
            $startDate = $this->input("{$prefix}.start_date");

            // The bare minimum for a schedule to be valid is a start date and a frequency,
            // so if either is missing, the other rules will already fail and this one can skip.
            if (!$frequency || !$startDate) {
                return;
            }

            $schedule = new TransactionSchedule([
                'start_date' => $startDate,
                'end_date' => $this->input("{$prefix}.end_date"),
                'frequency' => $frequency,
                'interval' => $this->input("{$prefix}.interval") ?: 1,
                'count' => $this->input("{$prefix}.count"),
                'by_day' => $this->input("{$prefix}.by_day"),
                'by_month' => $this->input("{$prefix}.by_month"),
            ]);

            try {
                if (!$schedule->occursOn(Carbon::parse($value))) {
                    $fail(__('The :attribute must be a date the schedule actually recurs on.'));
                }
            } catch (InvalidArgument|InvalidWeekday|Exception) {
                // A malformed rule (e.g. an invalid frequency/by_day combination) is
                // already surfaced by the other rules on those fields - don't pile on.
            }
        };
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
            'catch_up_schedule' => 'boolean',
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
                    $this->nextDateOccursOnRule('schedule_config'),
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
                'schedule_config.by_day' => $this->byDayRule('schedule_config.frequency'),
                'schedule_config.by_month' => $this->byMonthRule('schedule_config.frequency', 'schedule_config.by_day'),
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
        if ($this->input('action') === 'replace') {
            $rules = array_merge($rules, [
                'original_schedule_config.start_date' => 'required|date',
                'original_schedule_config.next_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:original_schedule_config.start_date',
                    $this->nextDateOccursOnRule('original_schedule_config'),
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
                'original_schedule_config.by_day' => $this->byDayRule('original_schedule_config.frequency'),
                'original_schedule_config.by_month' => $this->byMonthRule('original_schedule_config.frequency', 'original_schedule_config.by_day'),
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
                'config.price' => [
                    'required',
                    'numeric',
                    'gt:0',
                    // Fit in signed DECIMAL(20,10) range
                    'max:9999999999.9999999999',
                ],
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
            'catch_up_schedule' => $this->catch_up_schedule ?? 0,
        ]);
    }
}

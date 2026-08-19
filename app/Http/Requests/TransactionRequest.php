<?php

namespace App\Http\Requests;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Http\Traits\ValidatesRecurrenceRule;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Investment;
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
    use ValidatesRecurrenceRule;

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

    /**
     * An investment transaction's cash side (account) and priced side (investment) must
     * share the same currency - commission/tax/dividend are cast to the account's currency
     * (MoneyCast) while price is cast to the investment's currency (TransactionDetailInvestment
     * resolveAccountCurrency()/resolveInvestmentCurrency()), and arithmetic combining the two
     * throws Brick\Money\Exception\MoneyMismatchException on a mismatch. That guard is
     * fail-closed but only fires once the transaction is already committed (from the
     * post-commit TransactionCreated listener) - this rejects the mismatch up front instead.
     */
    private function accountInvestmentCurrencyMatchRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $investmentId = $this->input('config.investment_id');

            if (!$value || !$investmentId) {
                return;
            }

            $account = AccountEntity::with('config')->find($value);
            $investment = Investment::find($investmentId);

            if (!$account?->config instanceof Account || !$investment) {
                return;
            }

            if ($account->config->currency_id !== $investment->currency_id) {
                $fail(__('The selected account and investment must use the same currency.'));
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

        // Set date and schedule related rules
        if ($this->get('schedule')) {
            $rules = array_merge($rules, [
                'reconciled' => [
                    'boolean',
                    new IsFalsy(), // Scheduled items cannot be reconciled
                ],

                'schedule_config.start_date' => [
                    'required',
                    'date',
                    $this->maxRecurrencePeriodsRule('schedule_config.frequency', 'schedule_config.interval'),
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
                'original_schedule_config.start_date' => [
                    'required',
                    'date',
                    $this->maxRecurrencePeriodsRule('original_schedule_config.frequency', 'original_schedule_config.interval'),
                ],
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
                    // Fit in signed DECIMAL(12,4) range
                    'max:99999999.9999',
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
            if ($this->get('transaction_type') === 'withdrawal') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        $ownedAccountRule,
                    ],
                    'config.account_to_id' => [
                        'required',
                        $ownedPayeeRule,
                    ],
                    'config.amount_from' => [
                        'required',
                        'numeric',
                        'gt:0',
                        // Fit in signed DECIMAL(12,4) range
                        'max:99999999.9999',
                    ],
                    'config.amount_to' => [
                        'required',
                        'numeric',
                        'gt:0',
                        'same:config.amount_from',
                        // Fit in signed DECIMAL(12,4) range
                        'max:99999999.9999',
                    ],

                    // Technical field, but required for standard transaction
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => ['nullable', $ownedCategoryRule],

                ]);
            } elseif ($this->get('transaction_type') === 'deposit') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        $ownedPayeeRule,
                    ],
                    'config.account_to_id' => [
                        'required',
                        $ownedAccountRule,
                    ],
                    'config.amount_from' => [
                        'required',
                        'numeric',
                        'gt:0',
                        // Fit in signed DECIMAL(12,4) range
                        'max:99999999.9999',
                    ],
                    'config.amount_to' => [
                        'required',
                        'numeric',
                        'gt:0',
                        'same:config.amount_from',
                        // Fit in signed DECIMAL(12,4) range
                        'max:99999999.9999',
                    ],

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
                    'config.amount_from' => [
                        'required',
                        'numeric',
                        'gt:0',
                        // Fit in signed DECIMAL(12,4) range
                        'max:99999999.9999',
                    ],
                    'config.amount_to' => [
                        'required',
                        'numeric',
                        'gt:0',
                        // Fit in signed DECIMAL(12,4) range
                        'max:99999999.9999',
                    ],
                ]);
            }
        } elseif ($this->get('config_type') === 'investment') {
            // Adjust detail related rules, based on transaction type
            $rules = array_merge($rules, [
                'config.account_id' => [
                    'required',
                    $ownedAccountRule,
                    $this->accountInvestmentCurrencyMatchRule(),
                ],
                'config.investment_id' => [
                    'required',
                    $ownedInvestmentRule,
                ],
                'config.commission' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    // Fit in signed DECIMAL(14,4) range
                    'max:9999999999.9999',
                ],
                'config.tax' => [
                    'nullable',
                    'numeric',
                    'gte:0',
                    // Fit in signed DECIMAL(14,4) range
                    'max:9999999999.9999',
                ],
            ]);

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
                'config.quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                    // Fit in signed DECIMAL(14,4) range
                    'max:9999999999.9999',
                ],
            ];
        }

        // Add shares OR Remove shares
        if ($transactionTypeEnum === TransactionTypeEnum::ADD_SHARES || $transactionTypeEnum === TransactionTypeEnum::REMOVE_SHARES) {
            return [
                'config.quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                    // Fit in signed DECIMAL(14,4) range
                    'max:9999999999.9999',
                ],
            ];
        }

        // Dividend OR Interest yield
        if ($transactionTypeEnum === TransactionTypeEnum::DIVIDEND || $transactionTypeEnum === TransactionTypeEnum::INTEREST_YIELD) {
            return [
                'config.dividend' => [
                    'required',
                    'numeric',
                    'gt:0',
                    // Fit in signed DECIMAL(12,4) range
                    'max:99999999.9999',
                ],
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
            'catch_up_schedule' => $this->catch_up_schedule ?? 0,
        ]);
    }
}

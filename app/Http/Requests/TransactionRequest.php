<?php

namespace App\Http\Requests;

use App\Models\TransactionType;
use App\Rules\IsFalsy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    const REQUIRED_POSITIVE_NUMBER = 'required|numeric|gt:0';

    private function accountEntityRule(string $configType, bool $required = true): array
    {
        return [
            ($required ? 'required' : 'nullable'),
            Rule::exists('account_entities', 'id')
                ->where(function ($query) use ($configType) {
                    $query->where('config_type', $configType)
                        ->where('user_id', $this->user()->id);
                }),
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            // TODO: should these depend on the transaction type?
            'account_from_id' => __('source'),
            'account_to_id' => __('destination'),
            'amount_primary' => __('amount'),
            'amount_secondary' => __('destination amount'),

            'quantity' => __('quantity'),
            'price' => __('price'),
            'commission' => __('commission'),
            'tax' => __('tax'),
        ];
    }

    public function rules(): array
    {
        $rules = [
            'action' => 'required|in:create,edit,clone,enter,replace,finalize',
            'fromModal' => 'nullable|boolean',

            'id' => 'nullable|exists:transactions,id',
            'transaction_type_id' => 'required|exists:transaction_types,id',
            'comment' => 'nullable|max:191',
            'reconciled' => 'boolean',
            'schedule' => 'boolean',
            'budget' => 'boolean',

            'source_id' => 'nullable|exists:App\Models\ReceivedMail,id',
        ];

        // Basic transaction has no schedule at all, or has only schedule enabled
        $isBasic = (! $this->get('schedule') && ! $this->get('budget')) || $this->get('schedule');

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
                    'boolean',
                ],
                'schedule_config.end_date' => [
                    'nullable',
                    'date',
                    'after_or_equal:schedule_config.start_date',
                ],
                'schedule_config.frequency' => [
                    'required',
                    Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
                ],
                'schedule_config.interval' => 'nullable|integer|gte:1',
                'schedule_config.count' => 'nullable|integer|gte:1',
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
        $transactionType = TransactionType::where('name', $this->get('transaction_type'))->first();
        if ($transactionType->type === 'standard') {
            // Standard transactions have common rules for items
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
                //TODO: rule validation with the possibility to create a new tag
                //'transactionItems.*.tags.*' => 'nullable|exists:tags,id',
            ]);

            // Adjust detail related rules, based on transaction type
            // Accounts are only needed for basic setup (not budget only)
            if ($this->get('transaction_type') === 'withdrawal') {
                $rules = array_merge($rules, [
                    'account_from_id' => $this->accountEntityRule('account', $isBasic),
                    'account_to_id' => $this->accountEntityRule('payee', $isBasic),
                    'amount_primary' => self::REQUIRED_POSITIVE_NUMBER,
                    'amount_secondary' => 'prohibited',

                    // Technical field, but required for standard transaction
                    // TODO: remove this and handle it in the controller
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => 'nullable|exists:categories,id',

                ]);
            } elseif ($this->get('transaction_type') === 'deposit') {
                $rules = array_merge($rules, [
                    'account_from_id' => $this->accountEntityRule('payee', $isBasic),
                    'account_to_id' => $this->accountEntityRule('account', $isBasic),
                    'amount_primary' => self::REQUIRED_POSITIVE_NUMBER,
                    'amount_secondary' => 'prohibited',

                    // Technical fields, but required for standard transaction
                    // TODO: remove this and handle it in the controller
                    'remaining_payee_default_amount' => 'nullable|numeric|gte:0',
                    'remaining_payee_default_category_id' => 'nullable|exists:categories,id',

                ]);
            } elseif ($this->get('transaction_type') === 'transfer') {
                $rules = array_merge($rules, [
                    'account_from_id' => array_merge(
                        $this->accountEntityRule('account'),
                        ['different:account_to_id'],
                    ),
                    'account_to_id' => array_merge(
                        $this->accountEntityRule('account'),
                        ['different:account_from_id'],
                    ),
                    'amount_primary' => self::REQUIRED_POSITIVE_NUMBER,
                    'amount_secondary' => self::REQUIRED_POSITIVE_NUMBER,
                ]);
            }
        } elseif ($this->get('config_type') === 'transaction_detail_investment') {
            // Adjust detail related rules, based on transaction type
            //TODO: validate currency of account and investment

            $rules = array_merge(
                $rules,
                [
                    'commission' => 'nullable|numeric|gte:0',
                    'tax' => 'nullable|numeric|gte:0',
                ],
                $this->getInvestmentRuleDetails($this->transaction_type_id)
            );
        }

        return $rules;
    }

    private function getInvestmentRuleDetails($transactionTypeId): array
    {
        if ($transactionTypeId === 4) {
            // Buy
            $rules = [
                'account_from' => $this->accountEntityRule('account'),
                'account_to' => $this->accountEntityRule('investment'),
                'price' => self::REQUIRED_POSITIVE_NUMBER,
                'quantity' => self::REQUIRED_POSITIVE_NUMBER,
            ];
        } elseif ($transactionTypeId === 5) {
            // Sell
            $rules = [
                'account_from' => $this->accountEntityRule('investment'),
                'account_to' => $this->accountEntityRule('account'),
                'price' => self::REQUIRED_POSITIVE_NUMBER,
                'quantity' => self::REQUIRED_POSITIVE_NUMBER,
            ];
        } elseif ($transactionTypeId === 6) {
            // Add shares
            $rules = [
                'account_from' => $this->accountEntityRule('account'),
                'account_to' => $this->accountEntityRule('investment'),
                'quantity' => self::REQUIRED_POSITIVE_NUMBER,
            ];
        } elseif ($transactionTypeId === 7) {
            // Remove shares
            $rules = [
                'account_from' => $this->accountEntityRule('investment'),
                'account_to' => $this->accountEntityRule('account'),
                'quantity' => self::REQUIRED_POSITIVE_NUMBER,
            ];
        } elseif ($transactionTypeId === 8 || $transactionTypeId === 11) {
            // Dividend OR Cap gains
            $rules = [
                'account_from' => $this->accountEntityRule('investment'),
                'account_to' => $this->accountEntityRule('account'),
                'amount_primary' => self::REQUIRED_POSITIVE_NUMBER,
            ];
        } else {
            $rules = [];
        }

        return $rules;
    }

    /**
     * Configure conditional rules for some items
     */
    public function withValidator(Validator $validator): void
    {
        // Schedule start/next date rule is needed if schedule AND/OR budget is used, AND end_date is provided
        $validator->sometimes(
            ['schedule_config.next_date', 'schedule_config.start_date'],
            'before_or_equal:schedule_config.end_date',
            fn ($input) => ($input->schedule || $input->budget) && array_key_exists('end_date', $input->schedule_config) && $input->schedule_config['end_date']
        );
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Get transaction type ID by name
        if ($this->transaction_type) {
            $this->merge([
                'transaction_type_id' => TransactionType::where('name', $this->transaction_type)->first()->id,
            ]);
        }

        // Ensure that reconciled flag is set to false if not provided
        $this->merge([
            'reconciled' => $this->reconciled ?? 0,
        ]);
    }
}

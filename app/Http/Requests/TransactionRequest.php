<?php

namespace App\Http\Requests;

use \App\Models\TransactionType;
use App\Rules\IsFalsy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransactionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $rules = [
            'transaction_type_id' => "required|exists:transaction_types,id",
            'comment' => 'nullable|max:191',
            'reconciled' => 'boolean',
            'schedule' => 'boolean',
            'budget' => 'boolean',
            'config_type' => 'required|in:transaction_detail_standard,transaction_detail_investment',
        ];

        // Basic transaction has no schedule at all, or has only schedule enabled
        $isBasic = (!$this->get('schedule') && !$this->get('budget')) || $this->get('schedule');

        // Set date and schedule related rules
        if ($this->get('schedule') || $this->get('budget')) {
            $rules = array_merge($rules, [
                'reconciled' => [
                    'boolean',
                    new IsFalsy, // Scheduled or budgeted items cannot be reconciled
                ],

                'schedule_config.start_date' => 'required|date',
                'schedule_config.next_date' => [
                    'required',
                    'date',
                    'after_or_equal:schedule_config.start_date',
                ],
                'schedule_config.end_date' => 'nullable|date|after_or_equal:schedule_start',
                'schedule_config.frequency' => [
                    'required',
                    Rule::in(['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY']),
                ],
                'schedule_config.interval' => 'nullable|numeric|gte:1',
                'schedule_config.count' => 'nullable|numeric|gte:1',
            ]);
        } else {
            $rules = array_merge($rules, [
                'date' => 'required|date'
            ]);
        }

        // Adjustments based on transaction type
        if ($this->get('config_type') === 'transaction_detail_standard') {
            //any standard transactions have common rules for items
            $rules = array_merge($rules, [
                'items' => 'array',
                'items.*' => 'array',
                'items.*.amount' => [
                    'nullable',
                    'required_with:items.*.category,items.*.comment,items.*.tags',
                    'numeric',
                    'gt:0',
                ],
                'items.*.category_id' => [
                    'nullable',
                    'required_with:items.*.amount',
                    'exists:categories,id',
                ],
                'items.*.comment' => 'nullable|max:191',
                'items.*.tags' => 'array',
                //TODO: rule validation with option to create new tag
                //'transactionItems.*.tags.*' => 'nullable|exists:tags,id',
            ]);

            //adjust detail related rules, based on transaction type
            //accounts are only needed for basic setup (not budget only)
            //TODO: make it more dynamic instead of fixed IDs
            if ($this->get('transaction_type') === 'withdrawal') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,account'
                    ],
                    'config.account_to_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,payee'
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',

                    //technical field, but required for standard transaction
                    'remaining_payee_default_amount' => "nullable|numeric|gte:0",
                    'remaining_payee_default_category_id' => "nullable|exists:categories,id",

                ]);
            } elseif ($this->get('transaction_type') === 'deposit') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,payeee'
                    ],
                    'config.account_to_id' => [
                        ($isBasic ? 'required' : 'nullable'),
                        'exists:account_entities,id,config_type,account'
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',

                    //technical field, but required for standard transaction
                    'remaining_payee_default_amount' => "nullable|numeric|gte:0",
                    'remaining_payee_default_category_id' => "nullable|exists:categories,id",

                ]);
            } elseif ($this->get('transaction_type') === 'transfer') {
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        'exists:account_entities,id,config_type,account'
                    ],
                    'config.account_to_id' => [
                        'required',
                        'exists:account_entities,id,config_type,account'
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0',
                ]);
            }
        } elseif ($this->get('config_type') === 'transaction_detail_investment') {
            //adjust detail related rules, based on transaction type
            $rules = array_merge($rules, [
                'config.account_id' => [
                    'required',
                    'exists:account_entities,id,config_type,account'
                ],
                'config.investment_id' => [
                    'required',
                    'exists:investments,id'
                ],
            ]);


            //TODO: make it more dynamic instead of fixed IDs
            if ($this->get('transaction_type_id') === 4 || $this->get('transaction_type_id') === 5) {
                //buy OR sell
                $rules = array_merge($rules, [
                    'config.price' => 'required|numeric|gt:0',
                    'config.quantity' => 'required|numeric|gt:0',
                    'config.commission' => 'nullable|numeric|gte:0',
                    'config.tax' => 'nullable|numeric|gte:0',
                ]);
            } elseif ($this->get('transaction_type_id') === 6 || $this->get('transaction_type_id') === 7) {
                //add OR remove shares
                $rules = array_merge($rules, [
                    'config.quantity' => 'required|numeric|gt:0',
                    'config.commission' => 'nullable|numeric|gte:0',
                    'config.tax' => 'nullable|numeric|gte:0',
                ]);
            } elseif ($this->get('transaction_type_id') === 8 || $this->get('transaction_type_id') === 9 || $this->get('transaction_type_id') === 10) {
                //dividend OR cap gainst
                $rules = array_merge($rules, [
                    'config.dividend' => 'required|numeric|gt:0',
                    'config.commission' => 'nullable|numeric|gte:0',
                    'config.tax' => 'nullable|numeric|gte:0',
                ]);
            }
        }

        return $rules;
    }

    /**
     * Configure conditional rules for some items
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        // Schedule start/next date rule is needed if schedule AND/OR budget is used, AND end_date is provided
        $validator->sometimes(
            ['schedule_config.next_date', 'schedule_config.start_date'],
            'before_or_equal:schedule_config.end_date',
            function ($input) {
                return ($input->schedule || $input->budget) && array_key_exists('end_date', $input->schedule_config) && $input->schedule_config['end_date'];
            }
        );
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Get transaction type ID by name
        if ($this->transaction_type) {
            $this->merge([
                'transaction_type_id' => TransactionType::where('name', $this->transaction_type)->first()->id
            ]);
        }
    }
}

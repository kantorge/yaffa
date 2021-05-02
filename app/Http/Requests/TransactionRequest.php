<?php

namespace App\Http\Requests;

use App\Components\FlashMessages;
use App\Rules\IsFalsy;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{
    use FlashMessages;

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

        //basic transaction has no schedule at all, or has only schedule enabled
        $isBasic = (!$this->get('schedule') && !$this->get('budget')) || $this->get('schedule');

        //adjust date and schedule related rules
        if (   $this->get('schedule')
            || $this->get('budget')) {

            $rules = array_merge($rules, [
                'reconciled' => [
                    'boolean',
                    //scheduled or budgeted items cannot be reconciled
                    new IsFalsy,
                ],

                'schedule_start' => 'required|date',
                'schedule_next' => 'required|date|after_or_equal:schedule_start|before_or_equal:shedule_end',
                'schedule_end' => 'nullable|date|after_or_equal:schedule_start',
                'schedule_frequency' => 'required', //TODO: validate values
                'schedule_interval' => 'nullable|numeric|gte:1',
                'schedule_count' => 'nullable|numeric|gte:1',
            ]);
        } else {
            $rules = array_merge($rules, [
                'date' => 'required|date'
            ]);
        }

        //adjustment based on transaction type
        if ($this->get('config_type') === 'transaction_detail_standard') {
            //any standard transactions have common rules for items
            $rules = array_merge($rules, [
                'transactionItems' => 'array',
                'transactionItems.*' => 'array',
                'transactionItems.*.amount' => [
                    'nullable',
                    'required_with:transactionItems.*.category,transactionItems.*.comment,transactionItems.*.tags',
                    'numeric',
                    'gt:0',
                ],
                'transactionItems.*.category_id' => [
                    'nullable',
                    'required_with:transactionItems.*.amount',
                    'exists:categories,id',
                ],
                'transactionItems.*.comment' => 'nullable|max:191',
                'transactionItems.*.tags' => 'array',
                //TODO: rule validation with option to create new tag
                //'transactionItems.*.tags.*' => 'nullable|exists:tags,id',
            ]);

            //adjust detail related rules, based on transaction type
            //accounts are only needed for basic setup (not budget only)
            //TODO: make it more dynamic instead of fixed IDs
            if ($this->get('transaction_type_id') === 1) {
                //withdrawal
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
            } else if ($this->get('transaction_type_id') === 2) {
                //deposit
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
                    'config.amount_to' => 'required|numeric|gt:0',

                    //technical field, but required for standard transaction
                    'remaining_payee_default_amount' => "nullable|numeric|gte:0",
                    'remaining_payee_default_category_id' => "nullable|exists:categories,id",

                ]);
            } else if ($this->get('transaction_type_id') === 3) {
                //transfer
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
            if (   $this->get('transaction_type_id') === 4
                || $this->get('transaction_type_id') === 5) {
                //buy OR sell
                $rules = array_merge($rules, [
                    'config.price' => 'required|numeric|gt:0',
                    'config.quantity' => 'required|numeric|gt:0',
                    'config.commission' => 'nullable|numeric|gte:0',
                    'config.tax' => 'nullable|numeric|gte:0',
                ]);

            } else if (   $this->get('transaction_type_id') === 6
                       || $this->get('transaction_type_id') === 7) {
                //add OR remove shares
                $rules = array_merge($rules, [
                    'config.quantity' => 'required|numeric|gt:0',
                    'config.commission' => 'nullable|numeric|gte:0',
                    'config.tax' => 'nullable|numeric|gte:0',
                ]);

            }
            else if (   $this->get('transaction_type_id') === 8
                     || $this->get('transaction_type_id') === 9
                     || $this->get('transaction_type_id') === 10) {
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
     * Load validator error messages to standard notifications array
     *
     * @return void
     */
    public function withValidator(Validator $validator): void
    {

        $validator->after(function (Validator $validator) {
            foreach ($validator->errors()->all() as $message) {
                self::addSimpleDangerMessage($message);
            }
        });
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        //get transaction type ID by name
        if ($this->transaction_type) {
            $this->merge([
                'transaction_type_id' => \App\Models\TransactionType::where('name', $this->transaction_type)->first()->id
            ]);
        }

        //check for checkbox-es
        $this->merge([
            'reconciled' => $this->reconciled ?? 0,
            'schedule' => $this->schedule ?? 0,
            'budget' => $this->budget ?? 0,
        ]);
    }
}

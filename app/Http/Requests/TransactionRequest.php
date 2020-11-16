<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
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
            'reconciled' => 'boolean',  //TODO: apply schedule and budget related rules
            'schedule' => 'boolean',
            'budget' => 'boolean',
            'config_type' => 'required|in:transaction_detail_standard,transaction_detail_investment',
        ];

        //adjust date and schedule related rules
        if (   $this->get('schedule')
            || $this->get('budget')) {

            $rules = array_merge($rules, [
                'schedule_start' => 'required|date',
                'schedule_next' => 'required|date',  //TODO: not earlier than schedule_start
                'schedule_end' => 'nullable|date', //TODO: not earlier than schedule_start
                'schedule_frequency' => 'required',
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
            $rules = array_merge($rules, [
                'transactionItems' => 'array',
                'transactionItems.*' => 'array',
                'transactionItems.*.amount' => 'nullable|numeric|gt:0',
                'transactionItems.*.category' => 'nullable|exists:categories,id',
                'transactionItems.*.comment' => 'nullable|max:191',
                'transactionItems.*.tags' => 'array',
                //TODO: rule validation with option to create new
                //'transactionItems.*.tags.*' => 'nullable|exists:tags,id',
            ]);

            //adjust detail related rules, based on transaction type
            //TODO: make it more dynamic instead of fixed IDs
            if ($this->get('transaction_type_id') === 1) {
                //withdrawal
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        'exists:account_entities,id,config_type,account'
                    ],
                    'config.account_to_id' => [
                        'required',
                        'exists:account_entities,id,config_type,payee'
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0|same:config.amount_from',
                ]);
            } else if ($this->get('transaction_type_id') === 2) {
                //deposit
                $rules = array_merge($rules, [
                    'config.account_from_id' => [
                        'required',
                        'exists:account_entities,id,config_type,payeee'
                    ],
                    'config.account_to_id' => [
                        'required',
                        'exists:account_entities,id,config_type,account'
                    ],
                    'config.amount_from' => 'required|numeric|gt:0',
                    'config.amount_to' => 'required|numeric|gt:0',
                ]);
            } else if ($this->get('transaction_type_id') === 3) {
                //deposit
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

            ]);


            //TODO: make it more dynamic instead of fixed IDs
            if (   $this->get('transaction_type_id') === 4
                || $this->get('transaction_type_id') === 5) {
                //buy OR sell
                $rules = array_merge($rules, [
                    'price' => 'required|numeric|gt_0',
                    'quantity' => 'required|numeric|gt_0',
                    'commission' => 'required|numeric|gt_0',
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
                add_notification($message, 'danger');
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
                'transaction_type_id' => \App\TransactionType::where('name', $this->transaction_type)->first()->id
            ]);
        }

        //check for checkbox-es
        $this->merge([
            'reconciled' => $this->reconciled ?? 0,
            'schedule' => $this->schedule ?? 0,
            'budget' => $this->budget ?? 0,
        ]);

        //dd($this);
    }
}

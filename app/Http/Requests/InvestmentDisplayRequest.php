<?php

namespace App\Http\Requests;

use App\Models\AccountEntity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvestmentDisplayRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'account' => [
                'nullable',
                'integer',
                Rule::exists('account_entities', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('config_type', 'account'),
            ],
        ];
    }

    /**
     * Foreign, non-account or unknown ids are a 404 (does not reveal existence).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new NotFoundHttpException();
    }

    public function account(): ?AccountEntity
    {
        $id = $this->validated('account');

        return $id ? AccountEntity::find($id) : null;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\TransactionTemplate;
use Illuminate\Validation\Rule;

/**
 * The draft's structure and references are validated by TransactionDraftService::normalize(). Here only
 * what is specific to templates: no date, no AI-only `raw` data, and a unique name per user.
 *
 * @property TransactionTemplate|null $template
 */
class TransactionTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [
                $required,
                'string',
                'max:' . self::DEFAULT_STRING_MAX_LENGTH,
                Rule::unique('transaction_templates')->where(fn ($query) => $query
                    ->where('user_id', $this->user()->id)
                    ->when($this->template, fn ($query) => $query->where('id', '!=', $this->template->id))),
            ],
            'is_featured' => ['sometimes', 'boolean'],
            'draft' => [$required, 'array'],
            'draft.date' => ['prohibited'],
            'draft.raw' => ['prohibited'],
        ];
    }
}

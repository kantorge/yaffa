<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CategoryLearningMergeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'source_id' => [
                'required',
                'integer',
                Rule::exists('category_learning', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                'different:target_id',
            ],
            'target_id' => [
                'required',
                'integer',
                Rule::exists('category_learning', 'id')->where(fn ($query) => $query->where('user_id', $this->user()->id)),
                'different:source_id',
            ],
        ];
    }
}

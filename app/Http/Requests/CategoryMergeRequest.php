<?php

namespace App\Http\Requests;

use App\Rules\CategoryMergeValidSource;
use Illuminate\Validation\Rule;

class CategoryMergeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_target' => [
                'required',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
                'different:category_source',
            ],
            'category_source' => [
                'required',
                Rule::exists('categories', 'id')->where(function ($query) {
                    $query->where('user_id', $this->user()->id);
                }),
                new CategoryMergeValidSource(),
            ],
            'action' => [
                'required',
                'in:delete,close',
            ],
        ];
    }
}

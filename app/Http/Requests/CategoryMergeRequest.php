<?php

namespace App\Http\Requests;

use App\Rules\CategoryMergeValidSource;

class CategoryMergeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_target' => [
                'required',
                'exists:categories,id',
                'different:category_source',
            ],
            'category_source' => [
                'required',
                'exists:categories,id',
                new CategoryMergeValidSource(),
            ],
            'action' => [
                'required',
                'in:delete,close',
            ],
        ];
    }
}

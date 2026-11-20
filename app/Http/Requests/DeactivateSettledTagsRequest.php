<?php

namespace App\Http\Requests;

/**
 * Deactivate settled recharge tags. Without `tag_ids`, every settled active tag is deactivated;
 * the service ignores ids that are not the user's, not settled or already inactive.
 */
class DeactivateSettledTagsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
        ];
    }
}

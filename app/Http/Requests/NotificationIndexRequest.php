<?php

namespace App\Http\Requests;

class NotificationIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'since' => ['nullable', 'date'],
            'unread' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}

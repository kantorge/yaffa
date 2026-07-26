<?php

namespace App\Http\Requests;

use App\Enums\CheckpointType;
use Illuminate\Validation\Rule;

class AdvancedReconcileDashboardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'checkpoint_type' => ['nullable', Rule::in(CheckpointType::values())],
            'display' => ['nullable', Rule::in(['status', 'balance'])],
        ];
    }
}

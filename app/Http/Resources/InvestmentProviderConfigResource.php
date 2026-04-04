<?php

namespace App\Http\Resources;

use App\Models\InvestmentProviderConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvestmentProviderConfig */
class InvestmentProviderConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider_key' => $this->provider_key,
            'options' => $this->options,
            'last_error' => $this->last_error,
            'rate_limit_overrides' => $this->rate_limit_overrides,
            'has_credentials' => ! empty($this->credentials),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

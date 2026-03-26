<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
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
            'user_id' => $this->user_id,
            'name' => $this->name,
            'full_name' => $this->full_name,
            'active' => $this->active,
            'parent_id' => $this->parent_id,
            'default_aggregation' => $this->default_aggregation,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

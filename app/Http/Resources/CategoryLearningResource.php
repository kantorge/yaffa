<?php

namespace App\Http\Resources;

use App\Models\CategoryLearning;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CategoryLearning */
class CategoryLearningResource extends JsonResource
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
            'item_description' => $this->item_description,
            'category' => $this->relationLoaded('category')
                ? new CategoryResource($this->category)
                : null,
            'usage_count' => $this->usage_count,
            'active' => $this->active,
            'status' => $this->active ? 'active' : 'inactive',
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

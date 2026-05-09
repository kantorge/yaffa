<?php

namespace App\Http\Resources;

use App\Models\GoogleDriveConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GoogleDriveConfig */
class GoogleDriveConfigResource extends JsonResource
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
            'service_account_email' => $this->service_account_email,
            'folder_id' => $this->folder_id,
            'folder_name' => $this->folder_name,
            'post_import_actions' => $this->post_import_actions,
            'processed_folder_id' => $this->processed_folder_id,
            'processed_folder_name' => $this->processed_folder_name,
            'enabled' => $this->enabled,
            'sync_interval_minutes' => $this->sync_interval_minutes,
            'last_sync_at' => $this->last_sync_at,
            'last_error' => $this->last_error,
            'error_count' => $this->error_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

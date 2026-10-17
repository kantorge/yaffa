<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiDocument>
 */
class AiDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'ready_for_processing',
            'source_type' => 'manual_upload',
            'processed_transaction_data' => null,
            'ai_chat_history' => null,
            'google_drive_file_id' => null,
            'received_mail_id' => null,
            'custom_prompt' => null,
            'processed_at' => null,
        ];
    }
}

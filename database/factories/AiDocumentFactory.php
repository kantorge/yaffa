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
            'status' => $this->faker->randomElement(['ready_for_processing', 'processing', 'processing_failed', 'ready_for_review', 'finalized']),
            'source_type' => $this->faker->randomElement(['manual_upload', 'received_email', 'google_drive']),
            'processed_transaction_data' => null,
            'google_drive_file_id' => null,
            'received_mail_id' => null,
            'custom_prompt' => null,
            'processed_at' => null,
        ];
    }
}

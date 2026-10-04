<?php

namespace Database\Factories;

use App\Enums\AiDocumentStatus;
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
            'status_changed_at' => now(),
            'source_type' => $this->faker->randomElement(['manual_upload', 'received_email', 'google_drive']),
            'processed_transaction_data' => null,
            'ai_chat_history' => null,
            'google_drive_file_id' => null,
            'received_mail_id' => null,
            'custom_prompt' => null,
            'processed_at' => null,
        ];
    }

    public function readyForReview(): static
    {
        return $this->state(fn () => [
            'status' => AiDocumentStatus::ReadyForReview->value,
            'processed_at' => now(),
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn () => [
            'status' => AiDocumentStatus::Finalized->value,
            'processed_at' => now(),
        ]);
    }

    public function autoRecorded(): static
    {
        return $this->state(fn () => [
            'status' => AiDocumentStatus::AutoRecorded->value,
            'processed_at' => now(),
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn () => [
            'status' => AiDocumentStatus::Duplicate->value,
            'processed_at' => now(),
        ]);
    }

    public function awaitingItemization(): static
    {
        return $this->state(fn () => [
            'status' => AiDocumentStatus::AwaitingItemization->value,
            'processed_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn () => [
            'status' => AiDocumentStatus::Dismissed->value,
            'processed_at' => now(),
        ]);
    }

    public function withDraft(array $draft): static
    {
        return $this->state(fn () => [
            'processed_transaction_data' => $draft,
        ]);
    }
}

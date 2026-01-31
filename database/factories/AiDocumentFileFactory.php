<?php

namespace Database\Factories;

use App\Models\AiDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiDocumentFile>
 */
class AiDocumentFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ai_document_id' => AiDocument::factory(),
            'file_path' => 'ai_documents/' . fake()->uuid() . '/' . fake()->slug() . '.pdf',
            'file_name' => fake()->word() . '.pdf',
            'file_type' => $this->faker->randomElement(['pdf', 'jpg', 'png', 'txt']),
        ];
    }
}

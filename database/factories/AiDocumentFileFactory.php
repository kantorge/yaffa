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
        $fileType = $this->faker->randomElement(['pdf', 'jpg', 'png', 'txt']);
        $fileName = fake()->word().'.'.$fileType;
        $filePath = 'ai_documents/'.fake()->uuid().'/'.$fileName;

        return [
            'ai_document_id' => AiDocument::factory(),
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_type' => $fileType,
        ];
    }
}


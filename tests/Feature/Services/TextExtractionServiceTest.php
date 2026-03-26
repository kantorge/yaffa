<?php

namespace Tests\Feature\Services;

use App\Exceptions\OcrUnavailableException;
use App\Models\AiDocument;
use App\Models\AiDocumentFile;
use App\Models\User;
use App\Services\TextExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;

class TextExtractionServiceTest extends TestCase
{
    use RefreshDatabase;

    private TextExtractionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TextExtractionService();
    }

    public function test_extract_from_document_with_text_file(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        // Create a temporary text file
        $textContent = 'This is test transaction data';
        $tempFile = tempnam(storage_path('app'), 'test_txt_');
        file_put_contents($tempFile, $textContent);

        $relativePath = str_replace(storage_path('app') . '/', '', $tempFile);

        // Create document file reference
        AiDocumentFile::factory()
            ->for($document)
            ->create([
                'file_path' => $relativePath,
                'file_type' => 'txt',
            ]);

        try {
            $extracted = $this->service->extractFromDocument($document);

            $this->assertStringContainsString($textContent, $extracted);
        } finally {
            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_extract_from_document_throws_exception_for_images_without_ocr(): void
    {
        // Disable both OCR methods
        config(['ai-documents.ocr.tesseract_enabled' => false]);

        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        // Create a fake image file
        $tempFile = tempnam(storage_path('app'), 'test_img_');
        file_put_contents($tempFile, 'fake image data');

        $relativePath = str_replace(storage_path('app') . '/', '', $tempFile);

        AiDocumentFile::factory()
            ->for($document)
            ->create([
                'file_path' => $relativePath,
                'file_type' => 'jpg',
            ]);

        try {
            $this->expectException(OcrUnavailableException::class);
            $this->expectExceptionMessage('OCR');

            // Note: vision config is null, and tesseract is disabled
            $this->service->extractFromDocument($document, null);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_can_process_document_without_images(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        // Add text file (no OCR needed)
        $tempFile = tempnam(storage_path('app'), 'test_txt_');
        file_put_contents($tempFile, 'Test content');
        $relativePath = str_replace(storage_path('app') . '/', '', $tempFile);

        AiDocumentFile::factory()
            ->for($document)
            ->create([
                'file_path' => $relativePath,
                'file_type' => 'txt',
            ]);

        try {
            // Should return true even if OCR is disabled (no images present)
            $result = $this->service->canProcess($document);

            $this->assertTrue($result);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_can_process_document_with_images_requires_ocr(): void
    {
        // Disable both OCR methods
        config(['ai-documents.ocr.tesseract_enabled' => false]);

        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        // Add image file
        $tempFile = tempnam(storage_path('app'), 'test_img_');
        file_put_contents($tempFile, 'fake image');
        $relativePath = str_replace(storage_path('app') . '/', '', $tempFile);

        AiDocumentFile::factory()
            ->for($document)
            ->create([
                'file_path' => $relativePath,
                'file_type' => 'png',
            ]);

        try {
            // Should return false - has images but no OCR available
            $result = $this->service->canProcess($document);

            $this->assertFalse($result);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_extract_from_file_with_unsupported_type_throws_exception(): void
    {
        $tempFile = tempnam(storage_path('app'), 'test_pdf_');
        $relativePath = str_replace(storage_path('app') . '/', '', $tempFile);

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Unsupported file type');

            $this->service->extractFromFile($relativePath, 'exe');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_extract_continues_after_file_extraction_failure(): void
    {
        $user = User::factory()->create();
        $document = AiDocument::factory()->for($user)->create();

        // Create two files: one valid, one invalid
        $validFile = tempnam(storage_path('app'), 'valid_');
        file_put_contents($validFile, 'Valid content');
        $validRelativePath = str_replace(storage_path('app') . '/', '', $validFile);

        $invalidFile = tempnam(storage_path('app'), 'invalid_');
        file_put_contents($invalidFile, 'This should fail');
        $invalidRelativePath = str_replace(storage_path('app') . '/', '', $invalidFile);

        AiDocumentFile::factory()
            ->for($document)
            ->create([
                'file_path' => $validRelativePath,
                'file_type' => 'txt',
            ]);

        AiDocumentFile::factory()
            ->for($document)
            ->create([
                'file_path' => $invalidRelativePath,
                'file_type' => 'exe', // Invalid type - will be skipped
            ]);

        try {
            $extracted = $this->service->extractFromDocument($document);

            // Should contain at least the valid file content
            $this->assertStringContainsString('Valid content', $extracted);
        } finally {
            if (file_exists($validFile)) {
                unlink($validFile);
            }
            if (file_exists($invalidFile)) {
                unlink($invalidFile);
            }
        }
    }
}

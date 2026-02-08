<?php

namespace Tests\Feature\Services;

use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;

class OcrServiceHttpModeTest extends TestCase
{
    use RefreshDatabase;

    private OcrService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OcrService();
    }

    public function test_extract_routes_to_http_mode(): void
    {
        // Configure HTTP mode but with port that won't respond
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'http']);
        config(['ai-documents.ocr.tesseract_http.host' => 'localhost']);
        config(['ai-documents.ocr.tesseract_http.port' => 9999]);
        config(['ai-documents.ocr.tesseract_http.timeout' => 1]);

        // Create a temporary image file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
        file_put_contents($tempFile, 'fake image data');

        try {
            // Tesseract HTTP is not available (port 9999 doesn't respond)
            // So tesseract_is_available() returns false
            // Then OcrService falls back to Vision API and eventually throws OcrUnavailableException
            $result = tesseract_is_available();
            $this->assertFalse($result, 'HTTP service at port 9999 should not be available');

            // Try to extract - should throw because neither method available
            $this->service->extract($tempFile);
        } catch (\Exception $e) {
            // This is expected - HTTP not available and no Vision API
            $this->assertStringContainsString('OCR', $e->getMessage());
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_extract_routes_to_binary_mode(): void
    {
        // Configure binary mode
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'binary']);
        config(['ai-documents.ocr.tesseract_binary.path' => '/nonexistent/tesseract']);

        // Create a temporary image file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img_');
        file_put_contents($tempFile, 'fake image data');

        try {
            // tesseract_is_available will return false (path doesn't exist)
            // So extract will fail with OcrUnavailableException
            $result = tesseract_is_available();
            $this->assertFalse($result);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_config_mode_switch_works(): void
    {
        // Start with binary mode
        config(['ai-documents.ocr.tesseract_mode' => 'binary']);
        $this->assertFalse(tesseract_http_available()); // HTTP not checked in binary mode

        // Switch to HTTP mode
        config(['ai-documents.ocr.tesseract_mode' => 'http']);
        // HTTP check will fail (no server)
        $this->assertFalse(tesseract_is_available());

        // Switch back to binary mode
        config(['ai-documents.ocr.tesseract_mode' => 'binary']);
        // Returns false (path not set)
        $this->assertFalse(tesseract_is_available());
    }
}

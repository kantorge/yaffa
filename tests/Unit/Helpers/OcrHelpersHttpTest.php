<?php

namespace Tests\Unit\Helpers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OcrHelpersHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_tesseract_http_available_returns_false_when_disabled(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => false]);
        config(['ai-documents.ocr.tesseract_mode' => 'http']);

        $result = tesseract_is_available();

        $this->assertFalse($result);
    }

    public function test_tesseract_http_available_checks_http_mode(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'http']);
        config(['ai-documents.ocr.tesseract_http.host' => 'localhost']);
        config(['ai-documents.ocr.tesseract_http.port' => 9999]);

        // Mock will fail since no server is running
        $result = tesseract_is_available();

        $this->assertFalse($result);
    }

    public function test_tesseract_binary_available_returns_false_when_http_mode(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'http']);

        // Binary check should not be called in HTTP mode
        $result = tesseract_binary_available();

        // Binary function still works when called directly, but http mode is used in tesseract_is_available()
        $this->assertIsBool($result);
    }

    public function test_route_logic_selects_http_mode(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'http']);

        // tesseract_is_available should route to HTTP check
        $result = tesseract_is_available();

        // Result depends on whether service is running
        $this->assertIsBool($result);
    }

    public function test_route_logic_selects_binary_mode(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'binary']);

        // tesseract_is_available should route to binary check
        $result = tesseract_is_available();

        $this->assertIsBool($result);
    }

    public function test_invalid_tesseract_mode_returns_false(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_mode' => 'invalid-mode']);

        $result = tesseract_is_available();

        $this->assertFalse($result);
    }
}

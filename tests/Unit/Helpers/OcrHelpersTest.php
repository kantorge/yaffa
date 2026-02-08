<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

class OcrHelpersTest extends TestCase
{
    public function test_tesseract_is_available_returns_false_when_disabled(): void
    {
        // Mock config to disable Tesseract
        config(['ai-documents.ocr.tesseract_enabled' => false]);

        $result = tesseract_is_available();

        $this->assertFalse($result);
    }

    public function test_tesseract_is_available_returns_false_when_path_not_set(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_binary.path' => null]);

        $result = tesseract_is_available();

        $this->assertFalse($result);
    }

    public function test_tesseract_is_available_returns_false_when_binary_not_found(): void
    {
        config(['ai-documents.ocr.tesseract_enabled' => true]);
        config(['ai-documents.ocr.tesseract_binary.path' => '/nonexistent/path/to/tesseract']);

        $result = tesseract_is_available();

        $this->assertFalse($result);
    }

    public function test_tesseract_is_available_returns_false_when_file_not_executable(): void
    {
        // Create a temporary non-executable file
        $tempFile = tempnam(sys_get_temp_dir(), 'not_executable_');

        try {
            config(['ai-documents.ocr.tesseract_enabled' => true]);
            config(['ai-documents.ocr.tesseract_binary.path' => $tempFile]);

            // Ensure file is not executable
            chmod($tempFile, 0600);

            $result = tesseract_is_available();

            $this->assertFalse($result);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_tesseract_is_available_checks_executable(): void
    {
        // This test just checks that the function doesn't crash
        // In CI environment, tesseract usually isn't installed
        $result = tesseract_is_available();

        $this->assertIsBool($result);
    }
}

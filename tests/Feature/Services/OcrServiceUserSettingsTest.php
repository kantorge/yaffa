<?php

namespace Tests\Feature\Services;

use App\Services\OcrService;
use Tests\TestCase;

class OcrServiceUserSettingsTest extends TestCase
{
    private string $fakeTesseractBinaryPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeTesseractBinaryPath = tempnam(sys_get_temp_dir(), 'fake_tesseract_') ?: '';
        file_put_contents(
            $this->fakeTesseractBinaryPath,
            <<<'BASH'
#!/usr/bin/env bash
if [[ "$1" == "--version" ]]; then
  echo "tesseract 5.0.0"
  exit 0
fi

# Echo arguments so tests can assert OCR language and processed image path
printf '%s' "$*"
BASH
        );
        chmod($this->fakeTesseractBinaryPath, 0755);

        config([
            'ai-documents.ocr.tesseract_enabled' => true,
            'ai-documents.ocr.tesseract_mode' => 'binary',
            'ai-documents.ocr.tesseract_binary.path' => $this->fakeTesseractBinaryPath,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->fakeTesseractBinaryPath) && is_file($this->fakeTesseractBinaryPath)) {
            unlink($this->fakeTesseractBinaryPath);
        }

        parent::tearDown();
    }

    public function test_extract_uses_user_ocr_language_for_tesseract_binary_mode(): void
    {
        $inputFilePath = tempnam(sys_get_temp_dir(), 'ocr_input_') ?: '';
        file_put_contents($inputFilePath, 'dummy image bytes');

        try {
            $service = new OcrService();
            $result = $service->extract($inputFilePath, null, [
                'ocr_language' => 'fra',
            ]);

            $this->assertStringContainsString('-l fra', $result);
        } finally {
            if (is_file($inputFilePath)) {
                unlink($inputFilePath);
            }
        }
    }

    public function test_extract_downscales_image_when_user_tesseract_limits_are_set(): void
    {
        $largeImagePath = $this->createTemporaryJpeg(width: 400, height: 260);

        try {
            $service = new OcrService();
            $result = $service->extract($largeImagePath, null, [
                'ocr_language' => 'eng',
                'image_max_width_tesseract' => 100,
                'image_max_height_tesseract' => 100,
            ]);

            $processedImagePath = explode(' ', $result)[0] ?? '';

            $this->assertNotSame($largeImagePath, $processedImagePath);
            $this->assertStringContainsString('.temp/resized_', $processedImagePath);
        } finally {
            if (is_file($largeImagePath)) {
                unlink($largeImagePath);
            }
        }
    }

    private function createTemporaryJpeg(int $width, int $height): string
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), 'ocr_image_') ?: '';
        $jpegPath = $tempFilePath . '.jpg';

        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, 255, 255, 255));
        imagejpeg($image, $jpegPath);
        imagedestroy($image);

        if (is_file($tempFilePath)) {
            unlink($tempFilePath);
        }

        return $jpegPath;
    }
}

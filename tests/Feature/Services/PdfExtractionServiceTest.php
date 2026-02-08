<?php

namespace Tests\Feature\Services;

use App\Services\PdfExtractionService;
use Exception;
use Tests\TestCase;

class PdfExtractionServiceTest extends TestCase
{
    private PdfExtractionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PdfExtractionService();
    }

    public function test_extract_throws_exception_for_invalid_pdf(): void
    {
        $this->expectException(Exception::class);

        // Create a temporary invalid PDF
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_pdf_');
        file_put_contents($tempFile, 'This is not a valid PDF');

        try {
            $this->service->extract($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_has_extractable_text_returns_false_for_invalid_pdf(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'invalid_pdf_');
        file_put_contents($tempFile, 'Not a PDF');

        try {
            $result = $this->service->hasExtractableText($tempFile);
            $this->assertFalse($result);
        } finally {
            unlink($tempFile);
        }
    }

    public function test_has_extractable_text_returns_false_for_empty_pdf(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_pdf_');

        try {
            // Create minimal empty PDF
            file_put_contents($tempFile, '%PDF-1.4
1 0 obj
<< >>
endobj
xref
0 1
0000000000 65535 f
trailer
<< /Size 1 >>
startxref
0
%%EOF');

            $result = $this->service->hasExtractableText($tempFile);
            // Empty PDF should return false
            $this->assertFalse($result);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}

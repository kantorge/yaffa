<?php

namespace Tests\Unit\Services;

use App\Services\GoogleDriveService;
use PHPUnit\Framework\TestCase;

class GoogleDriveServiceTest extends TestCase
{
    private GoogleDriveService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GoogleDriveService();
    }

    // ===== isExcludedFromImport() - file exclusion logic =====

    public function test_yaffa_txt_is_excluded_from_import(): void
    {
        $this->assertTrue($this->service->isExcludedFromImport('yaffa.txt'));
    }

    public function test_file_with_processed_prefix_is_excluded_from_import(): void
    {
        $this->assertTrue($this->service->isExcludedFromImport('processed_receipt.pdf'));
    }

    public function test_processed_prefix_exclusion_is_case_sensitive(): void
    {
        // Exclusion is case-sensitive; uppercase prefix is not excluded
        $this->assertFalse($this->service->isExcludedFromImport('Processed_receipt.pdf'));
        $this->assertFalse($this->service->isExcludedFromImport('PROCESSED_receipt.pdf'));
    }

    public function test_yaffa_txt_exclusion_is_case_sensitive(): void
    {
        // Only exact lowercase match is excluded
        $this->assertFalse($this->service->isExcludedFromImport('Yaffa.txt'));
        $this->assertFalse($this->service->isExcludedFromImport('YAFFA.TXT'));
    }

    public function test_normal_pdf_is_not_excluded_from_import(): void
    {
        $this->assertFalse($this->service->isExcludedFromImport('receipt.pdf'));
    }

    public function test_normal_image_is_not_excluded_from_import(): void
    {
        $this->assertFalse($this->service->isExcludedFromImport('photo.jpg'));
    }

    public function test_file_containing_processed_but_not_prefixed_is_not_excluded(): void
    {
        // Must start with processed_, not just contain it
        $this->assertFalse($this->service->isExcludedFromImport('my_processed_receipt.pdf'));
        $this->assertFalse($this->service->isExcludedFromImport('receipt_processed.pdf'));
    }

    public function test_processed_prefix_applies_to_various_extensions(): void
    {
        $this->assertTrue($this->service->isExcludedFromImport('processed_receipt.pdf'));
        $this->assertTrue($this->service->isExcludedFromImport('processed_photo.jpg'));
        $this->assertTrue($this->service->isExcludedFromImport('processed_scan.png'));
        $this->assertTrue($this->service->isExcludedFromImport('processed_document.txt'));
    }
}

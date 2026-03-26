<?php

namespace App\Services;

use App\Exceptions\OcrUnavailableException;
use App\Models\AiDocument;
use App\Models\AiProviderConfig;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TextExtractionService
{
    private PdfExtractionService $pdfExtractionService;

    private OcrService $ocrService;

    public function __construct()
    {
        $this->pdfExtractionService = new PdfExtractionService();
        $this->ocrService = new OcrService();
    }

    /**
     * Extract text from all files in a document
     *
     * Handles multiple file types:
     * - PDF: Always attempts text extraction
     * - Images (JPG, PNG): Requires OCR (Tesseract or Vision API)
     * - Text (TXT): Direct file read
     *
     * @param AiDocument $document The document to extract text from
     * @param AiProviderConfig|null $visionConfig Optional config for Vision API fallback on images
     * @param  array<string, mixed>|null  $aiUserSettings  Optional resolved AI settings for per-user OCR/image behavior
     * @return string Content from all files concatenated with separators
     *
     * @throws OcrUnavailableException if document has images but no OCR is available
     * @throws Exception if text extraction fails
     */
    public function extractFromDocument(AiDocument $document, ?AiProviderConfig $visionConfig = null, ?array $aiUserSettings = null): string
    {
        $texts = [];
        $imageCount = 0;

        foreach ($document->aiDocumentFiles as $file) {
            try {
                $text = $this->extractFromFile(
                    $file->file_path,
                    $file->file_type,
                    $visionConfig,
                    $aiUserSettings,
                );

                if ($text) {
                    $texts[] = $text;
                }

                // Count images for validation
                if (in_array($file->file_type, ['jpg', 'jpeg', 'png'])) {
                    $imageCount++;
                }
            } catch (OcrUnavailableException $e) {
                Log::error("OCR unavailable for document {$document->id}: {$e->getMessage()}");
                throw $e;
            } catch (Exception $e) {
                // Log error but continue with other files
                Log::warning("Failed to extract text from file {$file->file_path}: {$e->getMessage()}");
            }
        }

        // Check if document had images but no text was extracted (OCR would have been needed)
        if ($imageCount > 0 && empty($texts)) {
            throw new OcrUnavailableException(
                'Document contains ' . $imageCount . ' image(s) but no text could be extracted. '
                . 'Please enable OCR (Tesseract or Vision AI) to process this document.'
            );
        }

        return implode("\n\n---\n\n", $texts);
    }

    /**
     * Extract text from a single file
     *
     * Routes to appropriate extraction method based on file type.
     *
     * @param string $filePath Relative path within storage
     * @param string $fileType File extension (pdf, txt, jpg, jpeg, png)
     * @param AiProviderConfig|null $visionConfig Optional config for Vision API fallback
     * @param  array<string, mixed>|null  $aiUserSettings  Optional resolved AI settings for per-user OCR/image behavior
     * @return string Extracted text
     *
     * @throws OcrUnavailableException if images present but no OCR available
     * @throws Exception if extraction fails
     */
    public function extractFromFile(
        string $filePath,
        string $fileType,
        ?AiProviderConfig $visionConfig = null,
        ?array $aiUserSettings = null,
    ): string {
        $fileType = mb_strtolower($fileType);
        $fullPath = Storage::disk('local')->path($filePath);

        return match ($fileType) {
            'pdf' => $this->extractFromPdf($fullPath),
            'txt' => $this->extractFromText($fullPath),
            'jpg', 'jpeg', 'png' => $this->extractFromImage($fullPath, $visionConfig, $aiUserSettings),
            default => throw new Exception("Unsupported file type: {$fileType}"),
        };
    }

    /**
     * Extract text from a PDF file
     *
     * Always attempts extraction regardless of config.
     * If PDF has no extractable text (scanned image), it's treated as a failed extraction.
     *
     * @param string $fullPath Full file system path to PDF
     * @return string Extracted text (may be empty for scanned PDFs)
     *
     * @throws Exception if PDF cannot be read
     */
    private function extractFromPdf(string $fullPath): string
    {
        return $this->pdfExtractionService->extract($fullPath);
    }

    /**
     * Extract text from a plain text file
     *
     * @param string $fullPath Full file system path to text file
     * @return string File contents
     *
     * @throws Exception if file cannot be read
     */
    private function extractFromText(string $fullPath): string
    {
        try {
            $content = file_get_contents($fullPath);
            if ($content === false) {
                throw new Exception('Could not read file contents');
            }

            return $content;
        } catch (Exception $e) {
            Log::error("Text file extraction failed: {$e->getMessage()}");
            throw new Exception("Failed to extract text from file: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Extract text from an image using OCR
     *
     * Delegates to OcrService which handles Tesseract + Vision API fallback.
     *
     * @param string $fullPath Full file system path to image
     * @param AiProviderConfig|null $visionConfig Config for Vision API fallback
     * @param  array<string, mixed>|null  $aiUserSettings  Optional resolved AI settings for per-user OCR/image behavior
     * @return string Extracted text
     *
     * @throws OcrUnavailableException if no OCR method available
     * @throws Exception if extraction fails
     */
    private function extractFromImage(string $fullPath, ?AiProviderConfig $visionConfig = null, ?array $aiUserSettings = null): string
    {
        return $this->ocrService->extract($fullPath, $visionConfig, $aiUserSettings);
    }

    /**
     * Check if document can be processed (has required OCR for images)
     *
     * @param AiDocument $document
     * @param AiProviderConfig|null $visionConfig
     * @return bool
     */
    public function canProcess(AiDocument $document, ?AiProviderConfig $visionConfig = null): bool
    {
        $hasImages = $document->aiDocumentFiles()
            ->whereIn('file_type', ['jpg', 'jpeg', 'png'])
            ->exists();

        if (!$hasImages) {
            // No images, can always process
            return true;
        }

        // Has images - check if OCR is available
        return $this->ocrService->isAvailable($visionConfig);
    }
}

<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;

class PdfExtractionService
{
    /**
     * Extract text from a PDF file
     *
     * @param string $filePath The full file path to the PDF
     * @return string Extracted text from all pages, concatenated with newlines
     *
     * @throws Exception if PDF cannot be parsed
     */
    public function extract(string $filePath): string
    {
        try {
            $parser = new PdfParser();
            $document = $parser->parseFile($filePath);
            $pages = $document->getPages();

            Log::debug("PDF info", [
                'pages' => $document->getPages(),
                'details' => $document->getDetails(),
            ]);

            $texts = [];
            foreach ($pages as $page) {
                try {
                    $text = $page->getText();
                    if ($text) {
                        $texts[] = $text;
                    }
                } catch (Exception $e) {
                    // Log page-level errors but continue with other pages
                    Log::warning("Failed to extract text from PDF page: {$e->getMessage()}");
                }
            }

            Log::debug("Extracted text from PDF: " . implode("\n", $texts));

            return implode("\n", $texts);
        } catch (Exception $e) {
            Log::error("PDF extraction failed: {$e->getMessage()}");
            throw new Exception("Failed to extract text from PDF: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Check if a PDF file has extractable text
     *
     * A PDF is considered to have extractable text if:
     * - Parsing succeeds
     * - At least one page contains non-empty text
     *
     * @param string $filePath The full file path to the PDF
     * @return bool
     */
    public function hasExtractableText(string $filePath): bool
    {
        try {
            $text = $this->extract($filePath);
            return !empty(trim($text));
        } catch (Exception) {
            return false;
        }
    }
}

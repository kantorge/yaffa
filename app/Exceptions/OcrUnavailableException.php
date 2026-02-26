<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a document contains images that require OCR processing,
 * but neither Tesseract nor Vision AI is available/enabled.
 */
class OcrUnavailableException extends Exception
{
    public function __construct(
        string $message = 'OCR is required to process images, but no OCR method is available or enabled.',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;

/**
 * Check if Tesseract OCR is available on the system
 *
 * Supports both local binary execution and remote HTTP service.
 * Configuration determines which mode is used.
 */
function tesseract_is_available(): bool
{
    if (!config('ai-documents.ocr.tesseract_enabled')) {
        return false;
    }

    $mode = config('ai-documents.ocr.tesseract_mode', 'binary');

    return match ($mode) {
        'http' => tesseract_http_available(),
        'binary' => tesseract_binary_available(),
        default => false,
    };
}

/**
 * Check if local Tesseract binary is available and executable
 */
function tesseract_binary_available(): bool
{
    $tesseractPath = config('ai-documents.ocr.tesseract_binary.path');

    // Check if path is configured and file exists
    if (!$tesseractPath || !file_exists($tesseractPath)) {
        return false;
    }

    // Check if file is executable
    if (!is_executable($tesseractPath)) {
        return false;
    }

    // Try to execute tesseract to verify it works (check --version)
    try {
        $process = new Symfony\Component\Process\Process([$tesseractPath, '--version']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    } catch (Exception) {
        return false;
    }
}

/**
 * Check if remote Tesseract HTTP service is available and responding
 *
 * Makes a health check request to the Tesseract HTTP endpoint.
 */
function tesseract_http_available(): bool
{
    try {
        $host = config('ai-documents.ocr.tesseract_http.host', 'localhost');
        $port = config('ai-documents.ocr.tesseract_http.port', 8888);
        $timeout = (int) config('ai-documents.ocr.tesseract_http.timeout', 5);

        $client = new GuzzleHttp\Client(['timeout' => $timeout]);

        $response = $client->get("http://{$host}:{$port}/health", [
            'http_errors' => false,
        ]);

        return $response->getStatusCode() === 200;
    } catch (Exception $e) {
        Log::warning("Tesseract HTTP health check failed: {$e->getMessage()}");

        return false;
    }
}

<?php

namespace App\Services;

use App\Exceptions\OcrUnavailableException;
use App\Models\AiProviderConfig;
use Exception;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Prism\Prism\ValueObjects\Media\Image;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OcrService
{
    private ImagePreprocessingService $imagePreprocessingService;

    private const DEFAULT_OCR_LANGUAGE = 'eng';

    public function __construct()
    {
        $this->imagePreprocessingService = new ImagePreprocessingService();
    }

    /**
     * Extract text from an image using OCR
     *
     * Attempts extraction in order of preference:
     * 1. Tesseract OCR (if enabled and available) - uses original resolution
     * 2. Vision AI (if enabled and model supports vision) - uses resized image
     * 3. Throws OcrUnavailableException if neither is available
     *
     * @param string $filePath Full file path to the image
     * @param AiProviderConfig|null $visionConfig User's AI config for Vision API fallback
     * @param array<string, mixed>|null $aiUserSettings Resolved per-user AI settings
     * @return string Extracted text from the image
     *
     * @throws OcrUnavailableException if no OCR method is available
     * @throws Exception if OCR processing fails
     */
    public function extract(string $filePath, ?AiProviderConfig $visionConfig = null, ?array $aiUserSettings = null): string
    {
        // Try Tesseract first (local, cheapest)
        if (tesseract_is_available()) {
            try {
                $text = $this->extractWithTesseract($filePath, $aiUserSettings);
                if ($text) {
                    return $text;
                }
            } catch (Exception $e) {
                Log::warning("Tesseract extraction failed: {$e->getMessage()}, falling back to Vision API if available");
            }
        }

        // Fall back to Vision AI
        if ($visionConfig?->vision_enabled && $this->modelSupportsVision($visionConfig)) {
            try {
                $text = $this->extractWithVisionApi($filePath, $visionConfig, $aiUserSettings);

                return $text;
            } catch (Exception $e) {
                Log::error("Vision API extraction failed: {$e->getMessage()}");
                throw $e;
            }
        }

        // No OCR method available
        throw new OcrUnavailableException(
            'Document contains images that require OCR processing, but no OCR method is available. '
            . 'Please enable Tesseract (via Docker) or Vision AI in your provider settings.'
        );
    }

    /**
     * Extract text from image using Tesseract OCR
     *
     * Routes to binary or HTTP implementation based on configuration.
     * Uses original image resolution for best accuracy.
     *
     * @param string $filePath Full file path to the image
     * @return string Extracted text
     *
     * @throws Exception if Tesseract fails
     */
    private function extractWithTesseract(string $filePath, ?array $aiUserSettings = null): string
    {
        $mode = config('ai-documents.ocr.tesseract_mode', 'binary');
        $language = $this->resolveOcrLanguage($aiUserSettings);
        $preparedFilePath = $this->prepareImageForTesseract($filePath, $aiUserSettings);

        try {
            return match ($mode) {
                'http' => $this->extractWithTesseractHttp($preparedFilePath, $language),
                'binary' => $this->extractWithTesseractBinary($preparedFilePath, $language),
                default => throw new Exception("Unknown Tesseract mode: {$mode}"),
            };
        } finally {
            if ($preparedFilePath !== $filePath) {
                $this->imagePreprocessingService->cleanup($preparedFilePath);
            }
        }
    }

    /**
     * Extract text from image using local Tesseract binary
     *
     * @param string $filePath Full file path to the image
     * @return string Extracted text
     *
     * @throws Exception if Tesseract fails
     */
    private function extractWithTesseractBinary(string $filePath, string $language): string
    {
        try {
            $tesseractPath = config('ai-documents.ocr.tesseract_binary.path');

            $process = new Process([$tesseractPath, $filePath, 'stdout', '-l', $language]);
            $process->setTimeout(30); // Tesseract can take time on large images
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();

            Log::info("Tesseract binary extracted " . mb_strlen($output) . " characters from image");
            Log::debug("Tesseract binary raw output: " . $output);

            return $output;
        } catch (ProcessFailedException $e) {
            Log::error("Tesseract process failed: {$e->getMessage()}");
            throw new Exception("Tesseract OCR extraction failed: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            Log::error("Tesseract binary extraction error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Extract text from image using remote Tesseract HTTP service
     *
     * Communicates with Tesseract running in a separate Docker container
     * via HTTP API.
     *
     * @param string $filePath Full file path to the image
     * @return string Extracted text
     *
     * @throws Exception if Tesseract HTTP service fails
     */
    private function extractWithTesseractHttp(string $filePath, string $language): string
    {
        try {
            $host = config('ai-documents.ocr.tesseract_http.host');
            $port = config('ai-documents.ocr.tesseract_http.port');
            $timeout = (int) config('ai-documents.ocr.tesseract_http.timeout', 30);
            $endpoint = config('ai-documents.ocr.tesseract_http.endpoint', '/api/v1/ocr');

            $url = "http://{$host}:{$port}{$endpoint}";

            // Read image and convert to base64
            $imageData = file_get_contents($filePath);
            if ($imageData === false) {
                throw new Exception("Failed to read image file: {$filePath}");
            }

            $imageBase64 = base64_encode($imageData);

            // Make HTTP request to Tesseract server
            $client = new HttpClient(['timeout' => $timeout]);

            $response = $client->post($url, [
                'json' => [
                    'image_base64' => $imageBase64,
                    'language' => $language,
                ],
            ]);

            $result = json_decode((string) $response->getBody(), true);

            if (! is_array($result)) {
                throw new Exception('Invalid response from Tesseract server');
            }

            if (($result['status'] ?? null) === 'success') {
                $text = $result['text'] ?? '';
                Log::info("Tesseract HTTP extracted " . mb_strlen($text) . " characters from image");
                Log::debug("Tesseract HTTP raw response: " . json_encode($result));
                Log::debug("Extracted text: " . $text);

                return $text;
            }

            $errorMsg = $result['message'] ?? 'Unknown OCR error';
            throw new Exception("Tesseract OCR error: {$errorMsg}");
        } catch (ConnectException $e) {
            Log::error("Tesseract HTTP connection failed: {$e->getMessage()}");
            throw new Exception("Tesseract server unreachable at http://{$host}:{$port}", 0, $e);
        } catch (RequestException $e) {
            Log::error("Tesseract HTTP request failed: {$e->getMessage()}");
            throw new Exception("Tesseract HTTP request failed: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            Log::error("Tesseract HTTP extraction error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Extract text from image using Vision AI (OpenAI/Gemini)
     *
     * Resizes image to maximum 2048px dimension to reduce API token costs.
     *
     * @param string $filePath Full file path to the image
     * @param AiProviderConfig $config User's AI provider configuration
     * @return string Extracted text from image
     *
     * @throws Exception if Vision API call fails
     * @throws ClientExceptionInterface if HTTP request fails
     */
    private function extractWithVisionApi(string $filePath, AiProviderConfig $config, ?array $aiUserSettings = null): string
    {
        $resizedPath = $filePath;

        try {
            // Resize image for Vision API (reduces token costs)
            $resizedPath = $this->imagePreprocessingService->resizeForVisionApi(
                filePath: $filePath,
                maxWidth: $this->resolveIntSetting($aiUserSettings, 'image_max_width_vision'),
                maxHeight: $this->resolveIntSetting($aiUserSettings, 'image_max_height_vision'),
                quality: $this->resolveIntSetting($aiUserSettings, 'image_quality_vision'),
            );

            // Build vision prompt
            $prompt = 'Please extract all text from this image. Return ONLY the extracted text, nothing else. Ignore any non-text content or hand-writing.';

            // Call Vision AI provider using Prism with Image value object
            $response = \Prism\Prism\Facades\Prism::text()
                ->using($config->provider, $config->model)
                ->usingProviderConfig([
                    'api_key' => $config->api_key,
                ])
                ->withPrompt($prompt, [Image::fromLocalPath($resizedPath)])
                ->asText();

            $text = $response->text ?? '';

            Log::info("Vision API extracted " . mb_strlen($text) . " characters from image");
            Log::debug("Vision API raw response: " . json_encode($response));
            Log::debug("Extracted text: " . $text);

            return $text;
        } catch (ClientExceptionInterface $e) {
            Log::error("Vision API HTTP request failed: {$e->getMessage()}");
            throw new Exception("Vision API request failed: {$e->getMessage()}", 0, $e);
        } catch (Exception $e) {
            Log::error("Vision API extraction failed: {$e->getMessage()}");
            throw $e;
        } finally {
            // Clean up temporary resized image
            if ($resizedPath !== $filePath) {
                $this->imagePreprocessingService->cleanup($resizedPath);
            }
        }
    }

    /**
     * Check if a model supports vision capabilities
     *
     * Vision capability is defined in the config for each model.
     *
     * @param AiProviderConfig $config
     * @return bool
     */
    private function modelSupportsVision(AiProviderConfig $config): bool
    {
        $modelsConfig = config('ai-documents.providers.' . $config->provider . '.models', []);

        // Handle both list and associative array formats
        if (is_array($modelsConfig) && !array_is_list($modelsConfig)) {
            // Associative format with metadata
            return (bool) ($modelsConfig[$config->model]['vision'] ?? false);
        }

        // List format - assume no vision support
        return false;
    }

    /**
     * Check if OCR is available for this document
     *
     * @param AiProviderConfig|null $visionConfig
     * @return bool
     */
    public function isAvailable(?AiProviderConfig $visionConfig = null): bool
    {
        $tesseractAvailable = tesseract_is_available();
        $visionAvailable = $visionConfig?->vision_enabled && $this->modelSupportsVision($visionConfig);

        return $tesseractAvailable || $visionAvailable;
    }

    /**
     * @param  array<string, mixed>|null  $aiUserSettings
     */
    private function prepareImageForTesseract(string $filePath, ?array $aiUserSettings = null): string
    {
        $maxWidth = $this->resolveIntSetting($aiUserSettings, 'image_max_width_tesseract');
        $maxHeight = $this->resolveIntSetting($aiUserSettings, 'image_max_height_tesseract');

        if ($maxWidth === null && $maxHeight === null) {
            return $filePath;
        }

        $preparedPath = $this->imagePreprocessingService->resizeForTesseract($filePath, $maxWidth, $maxHeight);

        if ($preparedPath !== $filePath) {
            Log::warning('Image downscaled before Tesseract OCR based on user settings', [
                'max_width' => $maxWidth,
                'max_height' => $maxHeight,
                'file_path' => $filePath,
            ]);
        }

        return $preparedPath;
    }

    /**
     * @param  array<string, mixed>|null  $aiUserSettings
     */
    private function resolveOcrLanguage(?array $aiUserSettings = null): string
    {
        $language = $aiUserSettings['ocr_language'] ?? self::DEFAULT_OCR_LANGUAGE;

        if (! is_string($language) || $language === '') {
            return self::DEFAULT_OCR_LANGUAGE;
        }

        return $language;
    }

    /**
     * @param  array<string, mixed>|null  $aiUserSettings
     */
    private function resolveIntSetting(?array $aiUserSettings, string $key): ?int
    {
        if (! is_array($aiUserSettings)) {
            return null;
        }

        if (! array_key_exists($key, $aiUserSettings) || $aiUserSettings[$key] === null) {
            return null;
        }

        return (int) $aiUserSettings[$key];
    }
}

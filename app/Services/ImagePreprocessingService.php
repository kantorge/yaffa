<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class ImagePreprocessingService
{
    private const DEFAULT_VISION_MAX_WIDTH = 2048;

    private const DEFAULT_VISION_MAX_HEIGHT = 2048;

    private const DEFAULT_IMAGE_QUALITY = 85;

    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Resize image to maximum dimension for Vision API processing.
     *
     * @param  int|null  $maxWidth  Optional max width override
     * @param  int|null  $maxHeight  Optional max height override
     * @param  int|null  $quality  Optional quality override
     */
    public function resizeForVisionApi(string $filePath, ?int $maxWidth = null, ?int $maxHeight = null, ?int $quality = null): string
    {
        $resolvedMaxWidth = $maxWidth ?? self::DEFAULT_VISION_MAX_WIDTH;
        $resolvedMaxHeight = $maxHeight ?? self::DEFAULT_VISION_MAX_HEIGHT;
        $resolvedQuality = $quality ?? self::DEFAULT_IMAGE_QUALITY;

        return $this->resizeWithConstraints(
            filePath: $filePath,
            maxWidth: $resolvedMaxWidth,
            maxHeight: $resolvedMaxHeight,
            quality: $resolvedQuality,
            target: 'Vision API',
        );
    }

    public function resizeForTesseract(string $filePath, ?int $maxWidth = null, ?int $maxHeight = null): string
    {
        if ($maxWidth === null && $maxHeight === null) {
            return $filePath;
        }

        return $this->resizeWithConstraints(
            filePath: $filePath,
            maxWidth: $maxWidth,
            maxHeight: $maxHeight,
            quality: self::DEFAULT_IMAGE_QUALITY,
            target: 'Tesseract',
        );
    }

    /**
     * Get dimensions of an image without loading full content
     *
     * @param string $filePath Full file path to the image
     * @return array{width: int, height: int} Image dimensions
     *
     * @throws Exception if image dimensions cannot be determined
     */
    public function getDimensions(string $filePath): array
    {
        try {
            $image = $this->imageManager->read($filePath);

            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (Exception $e) {
            Log::error("Failed to get image dimensions: {$e->getMessage()}");
            throw new Exception("Failed to get image dimensions: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Clean up temporary resized image
     *
     * @param string $filePath Path to temporary file
     */
    public function cleanup(string $filePath): void
    {
        try {
            if (file_exists($filePath) && str_contains($filePath, '.temp')) {
                unlink($filePath);
            }
        } catch (Exception $e) {
            Log::warning("Failed to cleanup temporary image: {$e->getMessage()}");
        }
    }

    private function resizeWithConstraints(
        string $filePath,
        ?int $maxWidth,
        ?int $maxHeight,
        int $quality,
        string $target,
    ): string {
        try {
            if (! is_file($filePath) || ! is_readable($filePath)) {
                throw new Exception("Image file is not readable: {$filePath}");
            }

            $fileSize = filesize($filePath);
            if ($fileSize === false || $fileSize === 0) {
                throw new Exception("Image file is empty or size unavailable: {$filePath}");
            }

            $image = $this->imageManager->read($filePath);

            $width = $image->width();
            $height = $image->height();

            Log::info("Original image dimensions: {$width}x{$height}, size: {$fileSize} bytes");

            $effectiveMaxWidth = $maxWidth !== null ? max(1, $maxWidth) : $width;
            $effectiveMaxHeight = $maxHeight !== null ? max(1, $maxHeight) : $height;

            if ($width <= $effectiveMaxWidth && $height <= $effectiveMaxHeight) {
                return $filePath;
            }

            $widthRatio = $effectiveMaxWidth / $width;
            $heightRatio = $effectiveMaxHeight / $height;
            $scale = min($widthRatio, $heightRatio);

            $newWidth = (int) ($width * $scale);
            $newHeight = (int) ($height * $scale);

            $image->scale($newWidth, $newHeight);

            $tempPath = Storage::disk('local')->path('.temp/' . uniqid('resized_') . '.' . pathinfo($filePath, PATHINFO_EXTENSION));

            if (! is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $resolvedQuality = min(100, max(1, $quality));
            $image->save($tempPath, quality: $resolvedQuality);

            Log::info("Image resized for {$target}: {$width}x{$height} -> {$newWidth}x{$newHeight}");

            return $tempPath;
        } catch (Exception $e) {
            Log::error("Image preprocessing failed for {$target}: {$e->getMessage()}");
            throw new Exception("Failed to resize image for {$target}: {$e->getMessage()}", 0, $e);
        }
    }
}

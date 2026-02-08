<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class ImagePreprocessingService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Resize image to maximum dimension for Vision API processing
     *
     * Vision AI models typically limit image dimensions to reduce token costs.
     * This method maintains aspect ratio while constraining the maximum dimension.
     *
     * @param string $filePath Full file path to the image
     * @param int $maxDimension Maximum width or height (default 2048px for Vision API)
     * @return string Path to resized image (temporary file)
     *
     * @throws Exception if image cannot be read or resized
     */
    public function resizeForVisionApi(string $filePath, int $maxDimension = 2048): string
    {
        try {
            // Read original image
            $image = $this->imageManager->read($filePath);

            // Get current dimensions
            $width = $image->width();
            $height = $image->height();

            // Check if resizing is needed
            if ($width <= $maxDimension && $height <= $maxDimension) {
                // Image already within bounds, return original
                return $filePath;
            }

            // Calculate new dimensions maintaining aspect ratio
            if ($width > $height) {
                // Landscape orientation
                $newWidth = $maxDimension;
                $newHeight = (int) ($height * ($maxDimension / $width));
            } else {
                // Portrait or square orientation
                $newHeight = $maxDimension;
                $newWidth = (int) ($width * ($maxDimension / $height));
            }

            // Resize image
            $image->scale($newWidth, $newHeight);

            // Save to temporary location
            $tempPath = Storage::disk('local')->path('.temp/' . uniqid('resized_') . '.' . pathinfo($filePath, PATHINFO_EXTENSION));

            // Create temp directory if it doesn't exist
            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            $image->save($tempPath);

            Log::info("Image resized for Vision API: {$width}x{$height} → {$newWidth}x{$newHeight}");

            return $tempPath;
        } catch (Exception $e) {
            Log::error("Image preprocessing failed: {$e->getMessage()}");
            throw new Exception("Failed to resize image for Vision API: {$e->getMessage()}", 0, $e);
        }
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
}

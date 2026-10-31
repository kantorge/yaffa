<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadLimitService
{
    /**
     * Effective per-file upload limit in whole MB: the lower of the app setting and what PHP accepts.
     */
    public static function maxFileMb(): int
    {
        $appLimit = (int) config('ai-documents.file_upload.max_file_size_mb');
        $phpLimit = (int) floor(UploadedFile::getMaxFilesize() / 1048576);

        return max(1, min($appLimit, $phpLimit));
    }
}

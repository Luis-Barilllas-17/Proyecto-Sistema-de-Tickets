<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class UploadSecurityPolicy
{
    /** Five files at five MiB each keeps a request below the application upload budget. */
    public const MAX_FILES_PER_REQUEST = 5;

    public const MAX_FILE_SIZE_KILOBYTES = 5120;

    public const MAX_FILE_SIZE_BYTES = 5_242_880;

    /**
     * @param  array<int, string>  $allowedExtensions
     * @return array<int, string>
     */
    public static function fileRules(array $allowedExtensions): array
    {
        return [
            'file',
            'mimes:'.implode(',', $allowedExtensions),
            'max:'.self::MAX_FILE_SIZE_KILOBYTES,
        ];
    }

    public static function assertSafeSize(UploadedFile $file): void
    {
        $size = $file->getSize();

        if (! is_int($size) || $size < 0 || $size > self::MAX_FILE_SIZE_BYTES) {
            throw new RuntimeException('El archivo supera el límite seguro de 5 MB.');
        }
    }
}

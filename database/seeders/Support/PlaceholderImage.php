<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

class PlaceholderImage
{
    public static function store(string $relativePath): string
    {
        $normalizedPath = ltrim($relativePath, '/');
        $disk = Storage::disk('public');

        if (! $disk->exists($normalizedPath)) {
            $disk->put($normalizedPath, self::generateJpeg(basename($normalizedPath)));
        }

        return $normalizedPath;
    }

    private static function generateJpeg(string $label): string
    {
        $image = imagecreatetruecolor(640, 480);
        $background = imagecolorallocate($image, 210, 218, 226);
        $textColor = imagecolorallocate($image, 45, 55, 72);

        imagefill($image, 0, 0, $background);
        imagestring($image, 5, 24, 24, $label, $textColor);

        ob_start();
        imagejpeg($image, null, 85);
        $contents = ob_get_clean() ?: '';
        imagedestroy($image);

        return $contents;
    }
}

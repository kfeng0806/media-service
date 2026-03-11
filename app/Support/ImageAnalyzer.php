<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Interfaces\ImageInterface;

final class ImageAnalyzer
{
    private const float TRANSPARENCY_THRESHOLD = 0.10;

    private const int MAX_SAMPLE_PIXELS = 10000;

    /**
     * Determine if the image has significant transparency (>= 10% of sampled pixels).
     * Uses grid sampling to avoid scanning every pixel on large images.
     */
    public static function hasSignificantTransparency(ImageInterface $image): bool
    {
        $width = $image->width();
        $height = $image->height();
        $totalPixels = $width * $height;

        $sampleSize = min($totalPixels, self::MAX_SAMPLE_PIXELS);
        $step = max(1, (int) sqrt($totalPixels / $sampleSize));

        $transparentCount = 0;
        $sampledCount = 0;

        for ($y = 0; $y < $height; $y += $step) {
            for ($x = 0; $x < $width; $x += $step) {
                if ($image->pickColor($x, $y)->alpha()->toInt() < 255) {
                    $transparentCount++;
                }
                $sampledCount++;
            }
        }

        if ($sampledCount === 0) {
            return false;
        }

        return ($transparentCount / $sampledCount) >= self::TRANSPARENCY_THRESHOLD;
    }

    /**
     * Determine output format: PNG if significant transparency, otherwise JPG.
     */
    public static function determineOutputFormat(ImageInterface $image): string
    {
        return self::hasSignificantTransparency($image) ? 'png' : 'jpg';
    }

    /**
     * Detect if a GIF file contains animation (multiple frames).
     */
    public static function isAnimatedGif(UploadedFile $file): bool
    {
        $handle = fopen($file->path(), 'rb');

        if ($handle === false) {
            return false;
        }

        $chunk = fread($handle, 1024 * 1024);
        fclose($handle);

        if ($chunk === false) {
            return false;
        }

        return substr_count($chunk, "\x00\x21\xF9\x04") > 1;
    }
}

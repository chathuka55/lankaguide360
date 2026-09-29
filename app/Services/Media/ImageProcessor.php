<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Turns an uploaded or downloaded image into WebP variants (400/800/1600 px wide) on the
 * public disk. Re-encoding also strips EXIF data such as GPS positions.
 */
class ImageProcessor
{
    public const QUALITY = 80;

    /**
     * @param  string  $binary  raw image bytes (JPEG, PNG, WebP, …)
     * @param  string  $directory  e.g. "media/place/12"
     * @param  string  $basename  file name without width suffix or extension
     * @return array{path: string, variants: array<int, string>, width: int, height: int}
     */
    public function storeVariants(string $binary, string $directory, string $basename): array
    {
        $original = ImageManager::gd()->read($binary);
        $disk = Storage::disk('public');
        $variants = [];
        $largest = null;

        foreach (Media::WIDTHS as $width) {
            $image = clone $original;
            $image->scaleDown(width: $width);

            $path = trim($directory, '/').'/'.$basename.'-'.$width.'.webp';
            $disk->put($path, (string) $image->toWebp(quality: self::QUALITY));
            $variants[$width] = $path;
            $largest = $image;

            // A small source gives identical variants above its own width; stop there.
            if ($original->width() <= $width) {
                break;
            }
        }

        /** @var ImageInterface $largest */
        return [
            'path' => end($variants),
            'variants' => $variants,
            'width' => $largest->width(),
            'height' => $largest->height(),
        ];
    }
}

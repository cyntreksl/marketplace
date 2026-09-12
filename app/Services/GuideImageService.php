<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class GuideImageService
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(private readonly ImageManagerInterface $images) {}

    /** @return array{disk: string, path: string} */
    public function store(UploadedFile $upload): array
    {
        $disk = (string) config('filesystems.media', 'r2');
        $path = 'guides/'.Str::uuid().'.webp';
        $image = $this->images->decodeSplFileInfo($upload)->scaleDown(width: 1600, height: 1200);
        $stored = Storage::disk($disk)->put($path, (string) $image->encode(new WebpEncoder(quality: 86, strip: true)), [
            'CacheControl' => self::CACHE_CONTROL,
            'ContentType' => 'image/webp',
        ]);

        if ($stored === false) {
            throw new RuntimeException('The guide hero image could not be stored.');
        }

        return ['disk' => $disk, 'path' => $path];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if (filled($path)) {
            Storage::disk($disk ?: (string) config('filesystems.media', 'r2'))->delete((string) $path);
        }
    }
}

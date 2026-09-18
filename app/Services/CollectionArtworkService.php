<?php

namespace App\Services;

use App\Models\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class CollectionArtworkService
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(private readonly ImageManagerInterface $images) {}

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     * @return array{disk: string, path: string}
     */
    public function store(Collection $collection, UploadedFile $upload, array $crop, string $type): array
    {
        $definition = $this->definition($type);
        $disk = $this->mediaDisk();
        $path = "collections/{$collection->getKey()}/{$definition['directory']}/".Str::uuid().'.webp';
        $source = $this->images->decodeSplFileInfo($upload);

        $this->validateCrop($source, $crop, $definition, $type);

        $artwork = (clone $source)
            ->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
            ->resize($definition['width'], $definition['height']);

        $stored = Storage::disk($disk)->put($path, (string) $artwork->encode(new WebpEncoder(quality: 86, strip: true)), [
            'CacheControl' => self::CACHE_CONTROL,
            'ContentType' => 'image/webp',
        ]);

        if ($stored === false) {
            throw new RuntimeException('The collection artwork could not be stored.');
        }

        return ['disk' => $disk, 'path' => $path];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if ($path === null) {
            return;
        }

        Storage::disk($disk ?: $this->mediaDisk())->delete($path);
    }

    /**
     * @return array{directory: string, width: int, height: int, minimum_width: int, minimum_height: int}
     */
    private function definition(string $type): array
    {
        return match ($type) {
            'tile' => [
                'directory' => 'tile',
                'width' => 800,
                'height' => 800,
                'minimum_width' => 800,
                'minimum_height' => 800,
            ],
            'banner' => [
                'directory' => 'banner',
                'width' => 1600,
                'height' => 500,
                'minimum_width' => 1600,
                'minimum_height' => 500,
            ],
            default => throw new RuntimeException('Unsupported collection artwork type.'),
        };
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     * @param  array{directory: string, width: int, height: int, minimum_width: int, minimum_height: int}  $definition
     */
    private function validateCrop(ImageInterface $image, array $crop, array $definition, string $type): void
    {
        $ratioDifference = abs(($crop['width'] * $definition['height']) - ($crop['height'] * $definition['width']));
        $ratioTolerance = max($definition['width'], $definition['height']) * 2;
        $isInsideImage = $crop['x'] >= 0
            && $crop['y'] >= 0
            && $crop['width'] >= $definition['minimum_width']
            && $crop['height'] >= $definition['minimum_height']
            && $image->width() >= $crop['x'] + $crop['width']
            && $image->height() >= $crop['y'] + $crop['height'];

        if ($ratioDifference > $ratioTolerance || ! $isInsideImage) {
            $label = $type === 'banner' ? 'collection banner' : 'collection tile image';
            $ratio = $type === 'banner' ? '16:5' : '1:1';

            throw ValidationException::withMessages([
                'crop' => "The {$label} must use a valid {$ratio} crop inside the uploaded image.",
            ]);
        }
    }

    private function mediaDisk(): string
    {
        $disk = config('filesystems.media');

        if (! is_string($disk) || $disk === '') {
            throw new RuntimeException('The media filesystem disk is not configured.');
        }

        return $disk;
    }
}

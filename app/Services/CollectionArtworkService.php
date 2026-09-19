<?php

namespace App\Services;

use App\Models\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Alignment;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;

class CollectionArtworkService
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public const OPEN_GRAPH_WIDTH = 1200;

    public const OPEN_GRAPH_HEIGHT = 630;

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

    /**
     * Builds a 1200x630 share image that shows the whole artwork uncropped over a blurred
     * copy of itself, so link previews (which crop to 1.91:1) never cut the artwork off.
     *
     * @return array{disk: string, path: string}|null
     */
    public function storeOpenGraph(Collection $collection): ?array
    {
        $source = collect([
            [$collection->banner_image_path, $collection->banner_image_disk],
            [$collection->image_path, $collection->image_disk],
            [$collection->vertical_image_path, $collection->vertical_image_disk],
        ])->first(fn (array $artwork): bool => is_string($artwork[0]) && $artwork[0] !== '');

        if ($source === null) {
            return null;
        }

        $binary = Storage::disk($source[1] ?: $this->mediaDisk())->get($source[0]);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('The collection artwork could not be read.');
        }

        $canvas = $this->images->decodeBinary($binary)
            ->cover(self::OPEN_GRAPH_WIDTH, self::OPEN_GRAPH_HEIGHT)
            ->blur(35)
            ->brightness(-20);
        $canvas->insert(
            $this->images->decodeBinary($binary)->scale(self::OPEN_GRAPH_WIDTH, self::OPEN_GRAPH_HEIGHT),
            alignment: Alignment::CENTER,
        );

        $disk = $this->mediaDisk();
        $path = "collections/{$collection->getKey()}/open-graph/".Str::uuid().'.jpg';
        $stored = Storage::disk($disk)->put($path, (string) $canvas->encode(new JpegEncoder(quality: 88, progressive: true, strip: true)), [
            'CacheControl' => self::CACHE_CONTROL,
            'ContentType' => 'image/jpeg',
        ]);

        if ($stored === false) {
            throw new RuntimeException('The collection open graph image could not be stored.');
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
            'vertical' => [
                'directory' => 'vertical',
                'width' => 900,
                'height' => 1600,
                'minimum_width' => 900,
                'minimum_height' => 1600,
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
            $label = match ($type) {
                'banner' => 'collection banner',
                'vertical' => 'collection vertical image',
                default => 'collection tile image',
            };
            $ratio = match ($type) {
                'banner' => '16:5',
                'vertical' => '9:16',
                default => '1:1',
            };

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

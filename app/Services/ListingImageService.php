<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Jobs\GenerateListingImageVariants;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Alignment;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;
use Throwable;

class ListingImageService
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function __construct(
        private readonly ImageManagerInterface $images,
        private readonly ListingRepository $listings,
    ) {}

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     */
    public function store(Listing $listing, UploadedFile $upload, array $crop, int $sortOrder, bool $isCover): ListingMedia
    {
        return $this->storeImage($listing, $upload, $crop, $sortOrder, $isCover);
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     */
    public function storeVariant(Listing $listing, ListingVariant $variant, UploadedFile $upload, array $crop): ListingMedia
    {
        return $this->storeImage($listing, $upload, $crop, 0, false, $variant);
    }

    /** @param Collection<int, ListingMedia> $mediaItems */
    public function removeVariantImages(Collection $mediaItems): void
    {
        foreach ($mediaItems as $media) {
            $this->removeMedia($media);
        }
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     */
    private function storeImage(
        Listing $listing,
        UploadedFile $upload,
        array $crop,
        int $sortOrder,
        bool $isCover,
        ?ListingVariant $variant = null,
    ): ListingMedia {
        $disk = $this->mediaDisk();
        $version = (string) Str::uuid();
        $directory = $variant === null
            ? "listings/{$listing->id}/{$version}"
            : "listings/{$listing->id}/variants/{$variant->id}/{$version}";
        $sourcePath = $directory.'/source.webp';
        $canonicalPath = $directory.'/main.webp';
        $storedPaths = [];

        try {
            $sourceImage = $this->images->decodeSplFileInfo($upload);
            $this->validateCrop($sourceImage, $crop);

            if (! $this->putImage($disk, $sourcePath, $sourceImage, new WebpEncoder(quality: 90, strip: true))) {
                throw new RuntimeException('The listing image source could not be stored.');
            }

            $storedPaths[] = $sourcePath;
            $canonical = (clone $sourceImage)
                ->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])
                ->resize(1600, 1600);

            if (! $this->putImage($disk, $canonicalPath, $canonical, new WebpEncoder(quality: 85, strip: true))) {
                throw new RuntimeException('The listing image could not be stored.');
            }

            $storedPaths[] = $canonicalPath;
            $attributes = [
                'disk' => $disk,
                'path' => $canonicalPath,
                'source_path' => $sourcePath,
                'crop_x' => $crop['x'],
                'crop_y' => $crop['y'],
                'crop_width' => $crop['width'],
                'crop_height' => $crop['height'],
                'variant_version' => $version,
                'variants' => null,
                'processing_status' => 'pending',
                'processing_error' => null,
                'type' => $variant === null ? 'image' : 'variant_image',
                'sort_order' => $sortOrder,
            ];
            $media = $variant === null
                ? $this->listings->createMedia($listing, $attributes)
                : $this->listings->createVariantMedia($variant, $attributes);

            DB::connection()->afterRollBack(fn () => Storage::disk($disk)->delete($storedPaths));
            GenerateListingImageVariants::dispatch($media->id, $version, $isCover)->afterCommit();

            return $media;
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($storedPaths);

            throw $exception;
        }
    }

    public function generateVariants(int $mediaId, string $version, bool $includeOpenGraph): void
    {
        $media = $this->listings->findMedia($mediaId);

        if ($media === null || $media->variant_version !== $version) {
            return;
        }

        $disk = $media->disk;
        $directory = dirname($media->path);
        $variants = [
            'thumbnail' => $directory.'/thumbnail-320x320.webp',
            'card' => $directory.'/card-640x640.webp',
            'card_2x' => $directory.'/card-1280x1280.webp',
        ];

        if ($includeOpenGraph) {
            $variants['open_graph'] = $directory.'/open-graph-1200x630.jpg';
        }

        if ($this->variantsAreReady($media, $variants)) {
            return;
        }

        try {
            $canonical = Storage::disk($disk)->get($media->path);
            $this->putWebpVariant($disk, $variants['thumbnail'], $canonical, 320, 320);
            $this->putWebpVariant($disk, $variants['card'], $canonical, 640, 640);
            $this->putWebpVariant($disk, $variants['card_2x'], $canonical, 1280, 1280);

            if ($includeOpenGraph) {
                $this->putOpenGraphVariant($disk, $variants['open_graph'], $canonical);
            }

            $currentMedia = $this->listings->findMedia($mediaId);

            if ($currentMedia === null || $currentMedia->variant_version !== $version) {
                Storage::disk($disk)->delete(array_values($variants));

                return;
            }

            $oldVariantPaths = is_array($currentMedia->variants) ? array_values($currentMedia->variants) : [];
            $currentMedia->forceFill([
                'variants' => $variants,
                'processing_status' => 'ready',
                'processing_error' => null,
            ]);
            $this->listings->saveMedia($currentMedia);

            Storage::disk($disk)->delete(array_diff($oldVariantPaths, array_values($variants)));
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete(array_values($variants));

            throw $exception;
        }
    }

    /** @param array<int, int> $mediaIds */
    public function remove(Listing $listing, array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        $coverId = $listing->media()->value('id');
        $mediaItems = $this->listings->mediaForListing($listing, $mediaIds);

        foreach ($mediaItems as $media) {
            $this->removeMedia($media);
        }

        if ($coverId !== null && in_array((int) $coverId, $mediaIds, true)) {
            $newCover = $listing->media()->first();

            if ($newCover !== null && $newCover->variant_version !== null) {
                GenerateListingImageVariants::dispatch($newCover->id, $newCover->variant_version, true)->afterCommit();
            }
        }
    }

    public function markFailed(int $mediaId, string $version, ?Throwable $exception): void
    {
        $media = $this->listings->findMedia($mediaId);

        if ($media === null || $media->variant_version !== $version) {
            return;
        }

        $media->forceFill([
            'processing_status' => 'failed',
            'processing_error' => Str::limit($exception?->getMessage() ?? 'Image processing failed.', 1000),
        ]);
        $this->listings->saveMedia($media);
    }

    /**
     * @param  array{x: int, y: int, width: int, height: int}  $crop
     */
    private function validateCrop(ImageInterface $image, array $crop): void
    {
        $isSquare = abs($crop['width'] - $crop['height']) <= 2;
        $isInsideImage = $crop['x'] >= 0
            && $crop['y'] >= 0
            && $crop['width'] > 0
            && $crop['height'] > 0
            && $image->width() >= $crop['x'] + $crop['width']
            && $image->height() >= $crop['y'] + $crop['height'];

        if (! $isSquare || ! $isInsideImage) {
            throw ValidationException::withMessages([
                'image_crops' => 'Each photo must have a valid square crop inside the uploaded image.',
            ]);
        }
    }

    private function removeMedia(ListingMedia $media): void
    {
        $paths = array_values(array_filter([
            $media->path,
            $media->source_path,
            ...array_values(is_array($media->variants) ? $media->variants : []),
        ]));
        $disk = $media->disk;
        $this->listings->deleteMedia($media);
        DB::connection()->afterCommit(fn () => Storage::disk($disk)->delete($paths));
    }

    private function putWebpVariant(string $disk, string $path, string $canonical, int $width, int $height): void
    {
        $image = $this->images->decodeBinary($canonical)->resize($width, $height);

        if (! $this->putImage($disk, $path, $image, new WebpEncoder(quality: 82, strip: true))) {
            throw new RuntimeException("The listing image variant {$path} could not be stored.");
        }
    }

    /** @param array<string, string> $expectedVariants */
    private function variantsAreReady(ListingMedia $media, array $expectedVariants): bool
    {
        if ($media->processing_status !== 'ready' || $media->variants !== $expectedVariants) {
            return false;
        }

        return collect($expectedVariants)
            ->every(fn (string $path): bool => Storage::disk($media->disk)->exists($path));
    }

    private function putOpenGraphVariant(string $disk, string $path, string $canonical): void
    {
        $background = $this->images->decodeBinary($canonical)
            ->cover(1200, 630)
            ->blur(35)
            ->brightness(-30);
        $foreground = $this->images->decodeBinary($canonical)
            ->contain(760, 570, 'ffffff');

        $background->insert($foreground, alignment: Alignment::CENTER);

        $logoPath = public_path('prodeals-email-logo.png');
        if (is_file($logoPath)) {
            $logo = $this->images->decodePath($logoPath)->scaleDown(width: 195);
            $background->insert($logo, x: 28, y: 28);
        }

        if (! $this->putImage($disk, $path, $background, new JpegEncoder(quality: 88, progressive: true, strip: true))) {
            throw new RuntimeException('The listing open graph image could not be stored.');
        }
    }

    private function putImage(string $disk, string $path, ImageInterface $image, WebpEncoder|JpegEncoder $encoder): bool
    {
        return Storage::disk($disk)->put($path, (string) $image->encode($encoder), [
            'CacheControl' => self::CACHE_CONTROL,
            'ContentType' => $encoder instanceof WebpEncoder ? 'image/webp' : 'image/jpeg',
        ]) !== false;
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

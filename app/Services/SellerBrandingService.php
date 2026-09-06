<?php

namespace App\Services;

use App\Contracts\Repositories\SellerStoreRepository;
use App\Models\SellerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Interfaces\ImageManagerInterface;
use RuntimeException;
use Throwable;

class SellerBrandingService
{
    public function __construct(private readonly SellerStoreRepository $sellers, private readonly ImageManagerInterface $images) {}

    /** @param array<string, mixed> $data */
    public function update(SellerProfile $seller, array $data): void
    {
        $disk = Storage::disk(config('filesystems.media'));
        $createdPaths = [];
        $attributes = ['about' => filled($data['about'] ?? null) ? trim($data['about']) : null];
        try {
            foreach (['logo' => [512, 512], 'cover' => [1600, 400]] as $field => [$width, $height]) {
                $upload = $data[$field] ?? null;
                if ($upload instanceof UploadedFile) {
                    $image = $this->images->decodeSplFileInfo($upload);
                    $crop = $data[$field.'_crop'];
                    if ($image->width() < $crop['x'] + $crop['width'] || $image->height() < $crop['y'] + $crop['height']
                        || abs($crop['width'] / $crop['height'] - $width / $height) > 0.03) {
                        throw ValidationException::withMessages([$field => 'Choose a crop within the image with the correct proportions.']);
                    }
                    $image->crop($crop['width'], $crop['height'], $crop['x'], $crop['y'])->resize($width, $height);
                    $path = 'sellers/'.$seller->id.'/'.Str::uuid().'/'.$field.'.webp';
                    $createdPaths[] = $path;
                    if (! $disk->put($path, (string) $image->encode(new WebpEncoder(quality: 85, strip: true)), ['visibility' => 'public', 'ContentType' => 'image/webp'])) {
                        throw new RuntimeException('Store artwork could not be uploaded.');
                    }
                    $attributes[$field.'_path'] = $path;
                } elseif ($data['remove_'.$field] ?? false) {
                    $attributes[$field.'_path'] = null;
                }
            }
            $oldPaths = $this->sellers->updateBranding($seller->id, $attributes);
        } catch (Throwable $exception) {
            $this->cleanUp($createdPaths);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }
            report($exception);
            throw ValidationException::withMessages(['branding' => 'Your store could not be saved. Your existing branding is unchanged. Please try again.']);
        }
        $this->cleanUp($oldPaths);
    }

    /** @param array<int, string> $paths */
    private function cleanUp(array $paths): void
    {
        if ($paths === []) {
            return;
        }
        try {
            if (! Storage::disk(config('filesystems.media'))->delete($paths)) {
                report(new RuntimeException('Unused seller artwork could not be removed.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class RemoteListingImageService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_PIXELS = 40_000_000;

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @return array{upload: UploadedFile, crop: array{x: int, y: int, width: int, height: int}}
     */
    public function download(string $url, string $field): array
    {
        $this->validatePublicHttpsUrl($url, $field);
        $temporaryPath = tempnam(sys_get_temp_dir(), 'mcp-listing-');

        if ($temporaryPath === false) {
            throw ValidationException::withMessages([$field => 'The image could not be prepared for download.']);
        }

        try {
            $response = $this->http
                ->connectTimeout(3)
                ->timeout(15)
                ->withOptions([
                    'allow_redirects' => false,
                    'on_headers' => function (ResponseInterface $response) use ($field): void {
                        $contentLength = $response->getHeaderLine('Content-Length');

                        if ($contentLength !== '' && (int) $contentLength > self::MAX_BYTES) {
                            throw ValidationException::withMessages([$field => 'Each image must be 5 MB or smaller.']);
                        }
                    },
                    'progress' => function (float $downloadTotal, float $downloadedBytes) use ($field): void {
                        if ($downloadTotal > self::MAX_BYTES || $downloadedBytes > self::MAX_BYTES) {
                            throw ValidationException::withMessages([$field => 'Each image must be 5 MB or smaller.']);
                        }
                    },
                ])
                ->sink($temporaryPath)
                ->get($url);

            if (! $response->successful()) {
                throw ValidationException::withMessages([$field => 'The image URL must return a successful response without redirects.']);
            }

            $fileSize = filesize($temporaryPath);

            if ($fileSize === false || $fileSize === 0 || $fileSize > self::MAX_BYTES) {
                throw ValidationException::withMessages([$field => 'Each image must be a non-empty file no larger than 5 MB.']);
            }

            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

            if (! is_string($mimeType) || ! isset(self::EXTENSIONS[$mimeType])) {
                throw ValidationException::withMessages([$field => 'Images must be JPEG, PNG, or WebP files.']);
            }

            $dimensions = @getimagesize($temporaryPath);

            if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
                throw ValidationException::withMessages([$field => 'The downloaded file is not a valid image.']);
            }

            if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
                throw ValidationException::withMessages([$field => 'The image dimensions are too large.']);
            }

            $squareSize = min($dimensions[0], $dimensions[1]);
            $extension = self::EXTENSIONS[$mimeType];

            return [
                'upload' => new UploadedFile(
                    $temporaryPath,
                    "remote-image.{$extension}",
                    $mimeType,
                    UPLOAD_ERR_OK,
                    true,
                ),
                'crop' => [
                    'x' => (int) floor(($dimensions[0] - $squareSize) / 2),
                    'y' => (int) floor(($dimensions[1] - $squareSize) / 2),
                    'width' => $squareSize,
                    'height' => $squareSize,
                ],
            ];
        } catch (ValidationException $exception) {
            @unlink($temporaryPath);

            throw $exception;
        } catch (Throwable $exception) {
            @unlink($temporaryPath);

            throw ValidationException::withMessages([
                $field => 'The image could not be downloaded. Confirm the URL is public, uses HTTPS, and points directly to an image.',
            ]);
        }
    }

    public function removeTemporaryUpload(UploadedFile $upload): void
    {
        @unlink($upload->getPathname());
    }

    /**
     * @return array{upload: UploadedFile, crop: array{x: int, y: int, width: int, height: int}}
     */
    public function decodeBase64(string $payload, string $filename, string $field): array
    {
        if (preg_match('/\Adata:image\/(?:jpeg|png|webp);base64,(.+)\z/s', $payload, $matches) === 1) {
            $payload = $matches[1];
        }

        $binary = base64_decode($payload, true);

        if ($binary === false) {
            throw ValidationException::withMessages([$field => 'The image content must be valid base64.']);
        }

        if ($binary === '' || strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages([$field => 'Each image must be a non-empty file no larger than 5 MB.']);
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'mcp-listing-');

        if ($temporaryPath === false || file_put_contents($temporaryPath, $binary) === false) {
            throw ValidationException::withMessages([$field => 'The image could not be prepared for upload.']);
        }

        try {
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);

            if (! is_string($mimeType) || ! isset(self::EXTENSIONS[$mimeType])) {
                throw ValidationException::withMessages([$field => 'Images must be JPEG, PNG, or WebP files.']);
            }

            $dimensions = @getimagesize($temporaryPath);

            if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
                throw ValidationException::withMessages([$field => 'The uploaded content is not a valid image.']);
            }

            if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
                throw ValidationException::withMessages([$field => 'The image dimensions are too large.']);
            }

            $squareSize = min($dimensions[0], $dimensions[1]);
            $safeFilename = pathinfo($filename, PATHINFO_FILENAME).'.'.self::EXTENSIONS[$mimeType];

            return [
                'upload' => new UploadedFile($temporaryPath, $safeFilename, $mimeType, UPLOAD_ERR_OK, true),
                'crop' => [
                    'x' => (int) floor(($dimensions[0] - $squareSize) / 2),
                    'y' => (int) floor(($dimensions[1] - $squareSize) / 2),
                    'width' => $squareSize,
                    'height' => $squareSize,
                ],
            ];
        } catch (ValidationException $exception) {
            @unlink($temporaryPath);

            throw $exception;
        }
    }

    private function validatePublicHttpsUrl(string $url, string $field): void
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;

        if ($scheme !== 'https' || $host === '' || $port !== 443 || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages([$field => 'Image URLs must be public HTTPS URLs on port 443.']);
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->resolveHostAddresses($host);

        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => ! $this->isPublicAddress($address))) {
            throw ValidationException::withMessages([$field => 'Image URLs must resolve only to public internet addresses.']);
        }
    }

    /** @return array<int, string> */
    private function resolveHostAddresses(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        return collect($records)
            ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter(fn (?string $address): bool => is_string($address))
            ->unique()
            ->values()
            ->all();
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}

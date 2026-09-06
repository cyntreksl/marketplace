<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ProductImageGenerationService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly RemoteListingImageService $remoteImages,
    ) {}

    /**
     * @return array{upload: UploadedFile, crop: array{x: int, y: int, width: int, height: int}}
     */
    public function generate(string $prompt, string $field): array
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw ValidationException::withMessages([
                $field => 'Product image generation is not configured.',
            ]);
        }

        $request = $this->http
            ->baseUrl((string) config('services.openai.base_url', 'https://api.openai.com/v1'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout(max(10, (int) config('services.openai.product_images.timeout', 90)));

        $organization = config('services.openai.organization');

        if (is_string($organization) && $organization !== '') {
            $request = $request->withHeaders(['OpenAI-Organization' => $organization]);
        }

        try {
            $response = $request->post('/images/generations', [
                'model' => (string) config('services.openai.product_images.model', 'gpt-image-2'),
                'prompt' => $prompt,
                'size' => '1024x1024',
                'quality' => 'low',
                'output_format' => 'jpeg',
                'output_compression' => 80,
                'n' => 1,
            ]);
        } catch (ConnectionException) {
            throw ValidationException::withMessages([
                $field => 'Product image generation timed out. Try a shorter, simpler prompt.',
            ]);
        }

        $responseBody = $response->json();

        if (! $response->successful()) {
            $errorCode = is_array($responseBody) ? Arr::get($responseBody, 'error.code') : null;
            $message = $errorCode === 'moderation_blocked'
                ? 'The image prompt was blocked by the image safety filter. Revise the prompt and try again.'
                : 'Product image generation failed. Try again or provide an image URL.';

            throw ValidationException::withMessages([$field => $message]);
        }

        $base64Image = is_array($responseBody) ? Arr::get($responseBody, 'data.0.b64_json') : null;

        if (! is_string($base64Image) || $base64Image === '') {
            throw ValidationException::withMessages([
                $field => 'Product image generation returned no image.',
            ]);
        }

        return $this->remoteImages->decodeBase64($base64Image, 'generated-product.jpg', $field);
    }
}

<?php

namespace App\Mcp\Tools;

use App\Models\Listing;
use App\Models\User;
use App\Services\ListingService;
use App\Services\RemoteListingImageService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[Name('update-draft-product')]
#[Title('Update Draft Product')]
#[Description('Updates the title, short description, description, specifications, or warranty of an existing draft product and optionally adds gallery images. Supply only fields to change; omitted fields are preserved. SEO meta_title and meta_description are generated when missing and refreshed when their source product content changes; explicit SEO overrides take precedence. Use specifications_text for specifications. Images accept public HTTPS URLs or base64-encoded JPEG, PNG, and WebP file contents, including data URLs. For a new product and a requested generated photo, use create-draft-product with image_generation_prompt instead. This never creates a duplicate, submits, or publishes the product.')]
#[IsReadOnly(false)]
#[IsDestructive(true)]
#[IsOpenWorld]
class UpdateDraftProductTool extends Tool
{
    public function __construct(
        private readonly ListingService $listings,
        private readonly RemoteListingImageService $remoteImages,
    ) {}

    public function handle(Request $request): Response
    {
        $seller = $this->resolveSeller($request);

        if (! $seller instanceof User) {
            return Response::error('Unable to identify seller. Please provide a valid "seller_email" or "seller_id", or authenticate the session.');
        }

        /** @var array<int, UploadedFile> $temporaryUploads */
        $temporaryUploads = [];

        try {
            $attributes = $this->validateRequest($request);
            [$images, $crops] = $this->prepareImages($request, $temporaryUploads);
            $listing = $this->listings->updateDraftContent($seller, (int) $request->get('listing_id'), $attributes, $images, $crops);
        } catch (ValidationException $exception) {
            return Response::error('Validation failed: '.$exception->validator->errors()->first());
        } catch (Throwable $exception) {
            return Response::error('Failed to update draft product: '.$exception->getMessage());
        } finally {
            foreach ($temporaryUploads as $upload) {
                $this->remoteImages->removeTemporaryUpload($upload);
            }
        }

        return Response::json($this->formatOutput($listing));
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'seller_email' => $schema->string()->description('Email address of the product seller. Required if the MCP session is not authenticated.'),
            'seller_id' => $schema->integer()->description('User ID of the product seller, as an alternative to seller_email.'),
            'listing_id' => $schema->integer()->description('ID returned by create-draft-product.')->required(),
            'title' => $schema->string()->description('Replacement product title. Omit to preserve the current title.')->min(1)->max(160),
            'short_description' => $schema->string()->nullable()->description('Replacement short description. Pass null to clear; omit to preserve.')->max(160),
            'description' => $schema->string()->nullable()->description('Replacement product description. Pass null to clear; omit to preserve.')->max(10000),
            'specifications_text' => $schema->string()->nullable()->description('Replacement specifications as text, such as "Material: Cotton\nWeight: 200g". Replaces all existing specifications. Pass null to clear; omit to preserve.')->max(10000),
            'warranty' => $schema->string()->nullable()->description('Replacement warranty period and coverage. Pass null to clear; omit to preserve.')->max(500),
            'meta_title' => $schema->string()->nullable()->description('Optional SEO title override. Automatically generated when missing or when the product title changes. Pass null to regenerate.')->max(60),
            'meta_description' => $schema->string()->nullable()->description('Optional SEO description override. Automatically generated when missing or when the product title or descriptions change. Pass null to regenerate.')->max(160),
            'image_urls' => $schema->array()
                ->description('Direct public HTTPS JPEG, PNG, or WebP image URLs. URLs and uploaded files combined may not take the product above five gallery images.')
                ->min(1)
                ->max(5)
                ->items($schema->string()->format('uri')->max(2048)),
            'image_files' => $schema->array()
                ->description('JPEG, PNG, or WebP files supplied as base64 content. Use this for generated images or files that do not have a public URL.')
                ->min(1)
                ->max(5)
                ->items($schema->object([
                    'filename' => $schema->string()->description('Original filename, including extension.')->max(255)->required(),
                    'content_base64' => $schema->string()->description('Raw base64 file content or a data:image/...;base64 data URL. Maximum decoded size is 5 MB.')->max(7100000)->required(),
                ])),
        ];
    }

    private function resolveSeller(Request $request): ?User
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user;
        }

        if ($email = $request->get('seller_email')) {
            return User::query()->where('email', (string) $email)->first();
        }

        if ($id = $request->get('seller_id')) {
            return User::query()->find((int) $id);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'seller_email' => ['nullable', 'email:rfc', 'max:255'],
            'seller_id' => ['nullable', 'integer'],
            'listing_id' => ['required', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'specifications_text' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'warranty' => ['sometimes', 'nullable', 'string', 'max:500'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:60'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:160'],
            'image_urls' => ['nullable', 'array', 'between:1,5'],
            'image_urls.*' => ['required', 'string', 'url:https', 'max:2048', 'distinct'],
            'image_files' => ['nullable', 'array', 'between:1,5'],
            'image_files.*.filename' => ['required', 'string', 'max:255'],
            'image_files.*.content_base64' => ['required', 'string', 'max:7100000'],
        ]);

        $imageCount = count((array) $request->get('image_urls', []))
            + count((array) $request->get('image_files', []));

        if ($imageCount > 5) {
            throw ValidationException::withMessages(['images' => 'No more than five images may be added at once.']);
        }

        $attributes = Arr::only($validated, ['title', 'short_description', 'description', 'specifications_text', 'warranty', 'meta_title', 'meta_description']);

        if ($attributes === [] && $imageCount === 0) {
            throw ValidationException::withMessages(['listing_id' => 'Provide at least one field to update or an image to add.']);
        }

        return $attributes;
    }

    /**
     * @param  array<int, UploadedFile>  $temporaryUploads
     * @return array{0: array<int, UploadedFile>, 1: array<int, array{x: int, y: int, width: int, height: int}>}
     */
    private function prepareImages(Request $request, array &$temporaryUploads): array
    {
        $images = [];
        $crops = [];

        foreach ((array) $request->get('image_urls', []) as $index => $url) {
            $prepared = $this->remoteImages->download((string) $url, "image_urls.{$index}");
            $images[] = $prepared['upload'];
            $crops[] = $prepared['crop'];
            $temporaryUploads[] = $prepared['upload'];
        }

        foreach ((array) $request->get('image_files', []) as $index => $file) {
            $prepared = $this->remoteImages->decodeBase64(
                (string) ($file['content_base64'] ?? ''),
                (string) ($file['filename'] ?? 'product-image'),
                "image_files.{$index}.content_base64",
            );
            $images[] = $prepared['upload'];
            $crops[] = $prepared['crop'];
            $temporaryUploads[] = $prepared['upload'];
        }

        return [$images, $crops];
    }

    /** @return array<string, mixed> */
    private function formatOutput(Listing $listing): array
    {
        $listing->load('media');
        $requirements = [
            'category' => $listing->category_id !== null,
            'brand' => filled($listing->brand_id) || filled($listing->brand_name),
            'sku' => filled($listing->sku),
            'description' => filled($listing->description),
            'condition' => filled($listing->condition),
            'price' => $listing->price !== null && (float) $listing->price >= 1,
            'product_image' => $listing->media->isNotEmpty(),
        ];

        if ($listing->product_type === 'variant') {
            $requirements['variants'] = $listing->variants()->exists();
            $requirements['variant_skus'] = $listing->variants()->exists()
                && ! $listing->variants()->whereNull('sku')->exists();
        }

        $missingRequirements = collect($requirements)
            ->reject(fn (bool $isComplete): bool => $isComplete)
            ->keys()
            ->values()
            ->all();

        return [
            'id' => $listing->id,
            'status' => $listing->status,
            'meta_title' => $listing->meta_title,
            'meta_description' => $listing->meta_description,
            'images_count' => $listing->media->count(),
            'images' => $listing->media->values()->map(fn ($media, int $index): array => [
                'id' => $media->id,
                'url' => $media->url,
                'is_cover' => $index === 0,
            ])->all(),
            'ready_for_review' => $missingRequirements === [],
            'missing_review_requirements' => $missingRequirements,
            'message' => "Draft product {$listing->id} updated successfully. It has not been submitted or published.",
        ];
    }
}

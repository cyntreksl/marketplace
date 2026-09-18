<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\CollectionRepository;
use App\Http\Requests\ArchiveResourceRequest;
use App\Http\Requests\RemoveCollectionImageRequest;
use App\Http\Requests\StoreCollectionBannerImageRequest;
use App\Http\Requests\StoreCollectionImageRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionActivationRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Models\Collection;
use App\Models\Listing;
use App\Services\CollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AdminCollectionController extends Controller
{
    public function index(Request $request, CollectionRepository $collections): Response
    {
        Gate::authorize('viewAny', Collection::class);

        return Inertia::render('admin/catalog/collections/index', [
            'collections' => $collections->paginateForAdmin()->through(fn (Collection $collection): array => [
                'id' => $collection->id,
                'name' => $collection->name,
                'slug' => $collection->slug,
                'type' => $collection->type->value,
                'rule_key' => $collection->rule_key,
                'is_active' => $collection->is_active,
                'show_on_homepage_tile' => $collection->show_on_homepage_tile,
                'show_on_homepage_grid' => $collection->show_on_homepage_grid,
                'show_in_navigation' => $collection->show_in_navigation,
                'sort_order' => $collection->sort_order,
                'listings_count' => $collection->getAttribute('listings_count'),
                'deleted_at' => $collection->deleted_at,
            ]),
        ]);
    }

    public function show(Request $request, int $collection, CollectionRepository $collections): Response
    {
        $model = $collections->withTrashed($collection);
        Gate::authorize('view', $model);

        return Inertia::render('admin/catalog/collections/show', [
            'collection' => $this->presentDetail($model),
        ]);
    }

    public function listings(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Collection::class);

        $search = trim((string) $request->query('search', ''));
        $listings = Listing::query()
            ->select(['id', 'title', 'sku'])
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->limit(20)
            ->get();

        return response()->json(['data' => $listings]);
    }

    public function store(StoreCollectionRequest $request, CollectionService $collections): RedirectResponse
    {
        $data = $request->validated();
        $collections->create($request->user(), Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.collections.index')->with('status', 'Collection created.');
    }

    public function update(UpdateCollectionRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        $data = $request->validated();
        $collections->update($request->user(), $collection, Arr::except($data, 'reason'), $data['reason']);

        return to_route('admin.collections.show', $collection)->with('status', 'Collection updated.');
    }

    public function storeImage(StoreCollectionImageRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        /** @var array{x: int, y: int, width: int, height: int} $crop */
        $crop = $request->validated('crop');
        $collections->replaceImage($request->user(), $collection, $image, $crop, $request->validated('reason'));

        return to_route('admin.collections.show', $collection)->with('status', 'Collection image updated.');
    }

    public function destroyImage(RemoveCollectionImageRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        $collections->removeImage($request->user(), $collection, $request->validated('reason'));

        return to_route('admin.collections.show', $collection)->with('status', 'Collection image removed.');
    }

    public function storeBannerImage(StoreCollectionBannerImageRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        /** @var UploadedFile $image */
        $image = $request->file('image');
        /** @var array{x: int, y: int, width: int, height: int} $crop */
        $crop = $request->validated('crop');
        $collections->replaceBannerImage($request->user(), $collection, $image, $crop, $request->validated('reason'));

        return to_route('admin.collections.show', $collection)->with('status', 'Collection banner updated.');
    }

    public function destroyBannerImage(RemoveCollectionImageRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        $collections->removeBannerImage($request->user(), $collection, $request->validated('reason'));

        return to_route('admin.collections.show', $collection)->with('status', 'Collection banner removed.');
    }

    public function updateActivation(UpdateCollectionActivationRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        $collections->updateActivation($request->user(), $collection, $request->boolean('is_active'), $request->validated('reason'));

        return to_route('admin.collections.show', $collection)->with('status', 'Collection availability updated.');
    }

    public function destroy(ArchiveResourceRequest $request, Collection $collection, CollectionService $collections): RedirectResponse
    {
        Gate::authorize('delete', $collection);
        $collections->archive($request->user(), $collection, $request->validated('reason'));

        return to_route('admin.collections.show', $collection)->with('status', 'Collection archived.');
    }

    public function restore(ArchiveResourceRequest $request, int $collection, CollectionRepository $repository, CollectionService $collections): RedirectResponse
    {
        $model = $repository->withTrashed($collection);
        Gate::authorize('restore', $model);
        $collections->restore($request->user(), $model, $request->validated('reason'));

        return to_route('admin.collections.show', $model)->with('status', 'Collection restored.');
    }

    /** @return array<string, mixed> */
    private function presentDetail(Collection $collection): array
    {
        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'description' => $collection->description,
            'type' => $collection->type->value,
            'rule_key' => $collection->rule_key,
            'image_url' => $collection->imageUrl(),
            'banner_image_url' => $collection->bannerImageUrl(),
            'is_active' => $collection->is_active,
            'show_on_homepage_tile' => $collection->show_on_homepage_tile,
            'show_on_homepage_grid' => $collection->show_on_homepage_grid,
            'show_in_navigation' => $collection->show_in_navigation,
            'sort_order' => $collection->sort_order,
            'seo_title' => $collection->seo_title,
            'seo_description' => $collection->seo_description,
            'seo_intro' => $collection->seo_intro,
            'listings' => $collection->isManual()
                ? $collection->listings->map(fn (Listing $listing): array => ['id' => $listing->id, 'title' => $listing->title])->values()->all()
                : [],
            'deleted_at' => $collection->deleted_at,
        ];
    }
}

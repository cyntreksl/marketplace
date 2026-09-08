<?php

use App\Contracts\Repositories\SellerStoreRepository;
use App\Models\Category;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\SeoText;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('public stores expose only public seller fields and eligible products', function () {
    $seller = SellerProfile::factory()->create(['about' => 'Everyday kitchen essentials.', 'phone' => 'PRIVATE-PHONE', 'pickup_address' => 'PRIVATE-ADDRESS', 'review_reason' => 'PRIVATE-REASON']);
    $product = Listing::factory()->for($seller)->create();
    Listing::factory()->create();
    Listing::factory()->for($seller)->create(['status' => 'draft']);
    $soldOutProduct = Listing::factory()->for($seller)->create(['stock_quantity' => 0, 'reserved_quantity' => 0]);
    $this->get(route('stores.show', $seller->slug))->assertOk()->assertHeaderMissing('X-Robots-Tag')->assertInertia(fn (Assert $page) => $page
        ->component('storefront/stores/show')
        ->where('seller.store_name', $seller->store_name)
        ->where('seller.productCount', 2)
        ->where('seller.about', 'Everyday kitchen essentials.')
        ->where('seller.sellingSince', $seller->approved_at->format('F Y'))
        ->missing('seller.phone')->missing('seller.pickup_address')->missing('seller.return_address')
        ->missing('seller.bank_account_details')->missing('seller.documents')->missing('seller.review_reason')
        ->where('listings.total', 2)
        ->where('listings.data.0.id', $product->id)
        ->where('listings.data.1.id', $soldOutProduct->id)
        ->where('listings.data.1.stockStatus', 'out_of_stock')
        ->where('seo.robots', 'index,follow,max-image-preview:large'));
    $this->get(route('listings.show', $product->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sellerSummary.slug', $seller->slug)->where('sellerSummary.productCount', 2)->missing('sellerSummary.phone'));
});

test('ineligible and missing stores are not public', function (string $status) {
    $seller = SellerProfile::factory()->create(['status' => $status]);
    $this->get(route('stores.show', $seller->slug))->assertNotFound();
})->with(['pending', 'rejected', 'suspended']);

test('deleted stores and missing slugs return 404', function () {
    $seller = SellerProfile::factory()->create();
    $seller->delete();
    $this->get(route('stores.show', $seller->slug))->assertNotFound();
    $this->get(route('stores.show', 'missing-store'))->assertNotFound();
});

test('empty active stores are accessible but noindex and absent from sitemap', function () {
    $seller = SellerProfile::factory()->create(['status' => 'active', 'approved_at' => null]);
    $this->get(route('stores.show', $seller->slug))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow')->assertInertia(fn (Assert $page) => $page
        ->where('seller.productCount', 0)->where('seller.sellingSince', null)
        ->where('seo.robots', 'noindex,follow,max-image-preview:large'));
    $this->get(route('sitemap.stores'))->assertOk()->assertDontSee(route('stores.show', $seller->slug), false);
});

test('store filters and pagination never escape their seller', function () {
    $seller = SellerProfile::factory()->create();
    $category = Category::factory()->create();
    Listing::factory()->for($seller)->for($category)->count(19)->create(['title' => 'Kitchen tool', 'price' => 100]);
    Listing::factory()->create(['title' => 'Kitchen tool', 'price' => 1]);
    $this->get(route('stores.show', ['seller' => $seller->slug, 'search' => 'Kitchen', 'category' => $category->slug, 'sort' => 'price_asc', 'page' => 2, 'seller_id' => 999]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('listings.data', 1)->where('listings.total', 19)
        ->where('listings.per_page', 18)->where('listings.data.0.seller.slug', $seller->slug)
        ->where('seo.robots', 'noindex,follow,max-image-preview:large')
        ->where('seo.canonicalUrl', route('stores.show', $seller->slug)));
    $this->get(route('stores.show', ['seller' => $seller->slug, 'page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.canonicalUrl', route('stores.show', $seller->slug).'?page=2')
        ->where('seo.robots', 'index,follow,max-image-preview:large'));
});

test('store discovery includes only eligible stores with public stock', function () {
    $seller = SellerProfile::factory()->create();
    Listing::factory()->for($seller)->create();
    $hidden = SellerProfile::factory()->create(['status' => 'suspended']);
    Listing::factory()->for($hidden)->create();
    $this->get(route('sitemap.index'))->assertOk()->assertSee(route('sitemap.stores'), false);
    $this->get(route('sitemap.stores'))->assertOk()->assertSee(route('stores.show', $seller->slug), false)->assertDontSee(route('stores.show', $hidden->slug), false);
});

test('store branding belongs exclusively to the authenticated eligible seller', function () {
    $seller = SellerProfile::factory()->create();
    $other = SellerProfile::factory()->create(['about' => 'Other store']);
    $this->get(route('seller.store.edit'))->assertRedirect(route('login'));
    $this->actingAs($seller->user)->get(route('seller.store.edit'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('seller.slug', $seller->slug));
    $this->put(route('seller.store.update'), ['about' => 'Updated store', 'seller_id' => $other->id, 'slug' => 'changed'])->assertRedirect(route('seller.store.edit'));
    expect($seller->fresh()->about)->toBe('Updated store')->and($seller->fresh()->slug)->toBe($seller->slug)
        ->and($other->fresh()->about)->toBe('Other store');
    $seller->forceFill(['status' => 'suspended'])->save();
    $this->get(route('seller.store.edit'))->assertNotFound();
    $this->put(route('seller.store.update'), ['about' => 'No'])->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('seller.store.edit'))->assertNotFound();
});

test('seller artwork is cropped into R2 and replaced only after a successful save', function () {
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create(['logo_path' => 'sellers/old.webp']);
    Storage::disk('r2')->put('sellers/old.webp', 'old');
    $this->actingAs($seller->user)->put(route('seller.store.update'), [
        'about' => "Our store\nOur story", 'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
        'logo_crop' => ['x' => 0, 'y' => 0, 'width' => 256, 'height' => 256],
        'cover' => UploadedFile::fake()->image('cover.jpg', 800, 200),
        'cover_crop' => ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 200],
    ])->assertSessionHasNoErrors()->assertRedirect(route('seller.store.edit'));
    $seller->refresh();
    Storage::disk('r2')->assertExists([$seller->logo_path, $seller->cover_path]);
    Storage::disk('r2')->assertMissing('sellers/old.webp');
    expect(getimagesizefromstring(Storage::disk('r2')->get($seller->logo_path)))->toMatchArray([0 => 512, 1 => 512]);
    expect(getimagesizefromstring(Storage::disk('r2')->get($seller->cover_path)))->toMatchArray([0 => 1600, 1 => 400]);
    $this->get(route('stores.show', $seller->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seller.logoUrl', Storage::disk('r2')->url($seller->logo_path))
        ->where('seo.openGraph.image', Storage::disk('r2')->url($seller->cover_path)));
    $oldPaths = [$seller->logo_path, $seller->cover_path];
    $this->put(route('seller.store.update'), ['about' => '', 'remove_logo' => true, 'remove_cover' => true])->assertSessionHasNoErrors();
    expect($seller->fresh()->logo_path)->toBeNull()->and($seller->fresh()->cover_path)->toBeNull()->and($seller->fresh()->about)->toBeNull();
    Storage::disk('r2')->assertMissing($oldPaths);
});

test('branding validates text files and crop bounds', function () {
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create();
    $this->actingAs($seller->user)->put(route('seller.store.update'), ['about' => str_repeat('x', 2001), 'logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])->assertSessionHasErrors(['about', 'logo']);
    $this->put(route('seller.store.update'), ['logo' => UploadedFile::fake()->image('logo.png', 256, 256), 'logo_crop' => ['x' => 200, 'y' => 0, 'width' => 256, 'height' => 256]])->assertSessionHasErrors('logo');
    $this->put(route('seller.store.update'), ['cover' => UploadedFile::fake()->image('cover.png', 800, 200), 'cover_crop' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 200]])->assertSessionHasErrors('cover');
    expect(Storage::disk('r2')->allFiles())->toBeEmpty();
});

test('storage failure preserves existing seller branding', function () {
    $seller = SellerProfile::factory()->create(['logo_path' => 'original.webp', 'about' => 'Original']);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('url')->andReturn('https://media.example.test/image.webp');
    $disk->shouldReceive('put')->once()->andReturnFalse();
    $disk->shouldReceive('delete')->once()->andReturnTrue();
    Storage::shouldReceive('disk')->with('r2')->andReturn($disk);
    $this->actingAs($seller->user)->put(route('seller.store.update'), ['about' => 'Replacement', 'logo' => UploadedFile::fake()->image('logo.png', 256, 256), 'logo_crop' => ['x' => 0, 'y' => 0, 'width' => 256, 'height' => 256]])->assertSessionHasErrors('branding');
    expect($seller->fresh()->logo_path)->toBe('original.webp')->and($seller->fresh()->about)->toBe('Original');
});

test('persistence failure removes new uploads and preserves old artwork', function () {
    Storage::fake('r2');
    $seller = SellerProfile::factory()->create(['logo_path' => 'original.webp', 'about' => 'Original']);
    Storage::disk('r2')->put('original.webp', 'original');
    $repository = Mockery::mock(SellerStoreRepository::class);
    $repository->shouldReceive('findOwned')->andReturn($seller);
    $repository->shouldReceive('updateBranding')->once()->andThrow(new RuntimeException('Database unavailable'));
    $this->app->instance(SellerStoreRepository::class, $repository);
    $this->actingAs($seller->user)->put(route('seller.store.update'), ['about' => 'Replacement', 'logo' => UploadedFile::fake()->image('logo.png', 256, 256), 'logo_crop' => ['x' => 0, 'y' => 0, 'width' => 256, 'height' => 256]])->assertSessionHasErrors('branding');
    expect(Storage::disk('r2')->allFiles())->toBe(['original.webp']);
    expect($seller->fresh()->about)->toBe('Original');
});

test('product metadata preserves block boundaries and normalizes encoded whitespace', function () {
    expect(SeoText::plain('<p>Easy&nbsp;clean</p><p>Strong&#x20;handle</p><script>ignore</script>'))->toBe('Easy clean Strong handle');
    $listing = Listing::factory()->create(['meta_description' => null, 'short_description' => '<p>Easy&nbsp;clean</p><p>Strong handle</p>']);
    $this->get(route('listings.show', $listing->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('seo.description', 'Easy clean Strong handle')
        ->where('seo.jsonLd.0.description', 'Easy clean Strong handle'));
});

<?php

use App\Models\Category;
use App\Models\GoogleProductTaxonomyNode;
use App\Models\GoogleProductTaxonomyVersion;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Services\GoogleProductTaxonomyImportService;
use Database\Seeders\GoogleProductTaxonomySeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the bundled official taxonomy snapshot seeds the permitted marketplace hierarchy idempotently', function () {
    $path = config('catalog.taxonomy.source_path');

    expect(app(GoogleProductTaxonomyImportService::class)->versionFromPath($path))->toBe('2021-09-21')
        ->and(hash_file('sha256', $path))->toBe(config('catalog.taxonomy.checksum'));

    $this->seed(GoogleProductTaxonomySeeder::class);
    $this->seed(GoogleProductTaxonomySeeder::class);

    $taxonomy = GoogleProductTaxonomyVersion::query()->where('is_active', true)->sole();

    expect($taxonomy->version)->toBe('2021-09-21')
        ->and($taxonomy->node_count)->toBe(5595)
        ->and(GoogleProductTaxonomyVersion::query()->count())->toBe(1)
        ->and(GoogleProductTaxonomyNode::query()->count())->toBe(5595)
        ->and(GoogleProductTaxonomyNode::query()->whereNull('parent_id')->count())->toBe(21)
        ->and(Category::query()->whereNotNull('google_product_category_id')->count())->toBe(5385)
        ->and(Category::query()->where('is_active', true)->where('is_selectable', true)->count())->toBe(4548)
        ->and(5595 - Category::query()->whereNotNull('google_product_category_id')->count())->toBe(210)
        ->and(Category::query()->whereIn('google_product_category_id', config('catalog.taxonomy.excluded_google_ids'))->exists())->toBeFalse()
        ->and(Category::query()->whereNull('parent_id')->orderBy('sort_order')->limit(6)->pluck('name')->all())->toBe([
            'Electronics',
            'Fashion & Accessories',
            'Home & Garden',
            'Health & Beauty',
            'Toys & Games',
            'Sports & Outdoors',
        ]);
});

test('future taxonomy activation preserves category commerce data and safely retires removed mappings', function () {
    $service = app(GoogleProductTaxonomyImportService::class);
    $firstFile = UploadedFile::fake()->createWithContent('first.txt', "222 - Electronics\n100 - Electronics > Phones");
    $first = $service->importPath(null, $firstFile->getRealPath(), 'first.txt', 'first', 'en-US');
    $service->activate(null, $first, 'Initial test taxonomy.');

    $phones = Category::query()->where('google_product_category_id', 100)->sole();
    $phones->forceFill([
        'slug' => 'preserved-phones',
        'commission_percentage' => 12.5,
        'return_window_days' => 30,
        'cod_enabled' => false,
    ])->save();
    $manual = Category::factory()->create(['name' => 'Local Only', 'google_product_category_id' => null]);

    $secondFile = UploadedFile::fake()->createWithContent('second.txt', "222 - Electronics\n101 - Electronics > Tablets");
    $second = $service->importPath(null, $secondFile->getRealPath(), 'second.txt', 'second', 'en-US');
    $service->activate(null, $second, 'Replacement test taxonomy.');

    expect($phones->refresh())
        ->slug->toBe('preserved-phones')
        ->commission_percentage->toBe('12.50')
        ->return_window_days->toBe(30)
        ->cod_enabled->toBeFalse()
        ->is_active->toBeFalse()
        ->is_selectable->toBeFalse()
        ->and($manual->refresh()->is_active)->toBeTrue()
        ->and(Category::query()->where('google_product_category_id', 101)->sole()->is_selectable)->toBeTrue();
});

test('category lookup supports department browsing and full path search with a thirty result cap', function () {
    $taxonomy = GoogleProductTaxonomyVersion::factory()->create([
        'version' => 'lookup',
        'locale' => 'en-US',
        'source_filename' => 'lookup.txt',
        'checksum' => str_repeat('a', 64),
        'node_count' => 2,
        'is_active' => true,
    ]);
    $rootNode = GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 222,
        'name' => 'Electronics',
        'full_path' => 'Electronics',
        'depth' => 0,
    ]);
    GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 100,
        'parent_id' => $rootNode->id,
        'name' => 'Phones',
        'full_path' => 'Electronics > Phones',
        'depth' => 1,
    ]);
    $root = Category::factory()->create([
        'google_product_category_id' => 222,
        'name' => 'Electronics',
        'is_selectable' => false,
        'sort_order' => 1,
    ]);
    $leaf = Category::factory()->create([
        'parent_id' => $root->id,
        'google_product_category_id' => 100,
        'name' => 'Phones',
        'is_selectable' => true,
    ]);

    $this->getJson(route('categories.search'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $root->id)
        ->assertJsonPath('data.0.has_children', true)
        ->assertJsonPath('data.0.is_selectable', false);

    $this->getJson(route('categories.search', ['search' => 'Phones']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $leaf->id)
        ->assertJsonPath('data.0.path', 'Electronics > Phones')
        ->assertJsonStructure(['data' => [['id', 'name', 'path', 'slug', 'is_selectable', 'has_children', 'commission_percentage']]]);

    Category::factory()->count(31)->sequence(fn ($sequence) => [
        'name' => 'Searchable Phone '.$sequence->index,
        'slug' => 'searchable-phone-'.$sequence->index,
    ])->create();

    $this->getJson(route('categories.search', ['search' => 'Searchable Phone']))
        ->assertOk()
        ->assertJsonCount(30, 'data');
});

test('listings require selectable leaves and parent storefront filters include descendants', function () {
    Storage::fake('public');
    $taxonomy = GoogleProductTaxonomyVersion::factory()->create([
        'version' => 'listing-filter',
        'locale' => 'en-US',
        'source_filename' => 'listing-filter.txt',
        'checksum' => str_repeat('b', 64),
        'node_count' => 2,
        'is_active' => true,
    ]);
    $rootNode = GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 222,
        'name' => 'Electronics',
        'full_path' => 'Electronics',
        'depth' => 0,
    ]);
    GoogleProductTaxonomyNode::factory()->create([
        'google_product_taxonomy_version_id' => $taxonomy->id,
        'google_product_category_id' => 100,
        'parent_id' => $rootNode->id,
        'name' => 'Phones',
        'full_path' => 'Electronics > Phones',
        'depth' => 1,
    ]);
    $parent = Category::factory()->create([
        'google_product_category_id' => 222,
        'name' => 'Electronics',
        'slug' => 'electronics',
        'is_selectable' => false,
    ]);
    $leaf = Category::factory()->create([
        'parent_id' => $parent->id,
        'google_product_category_id' => 100,
        'name' => 'Phones',
        'slug' => 'electronics-phones',
        'is_selectable' => true,
    ]);
    $seller = SellerProfile::factory()->create();
    $listing = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'category_id' => $leaf->id,
        'title' => 'Descendant phone',
    ]);

    $this->actingAs($seller->user)->post(route('seller.listings.store'), [
        'category_id' => $parent->id,
        'title' => 'Invalid parent listing',
        'description' => 'A listing cannot be assigned to a branch.',
        'condition' => 'used',
        'listing_type' => 'buy_now',
        'location' => 'Colombo',
        'stock_quantity' => 1,
        'price' => '1000.00',
        'images' => [UploadedFile::fake()->image('invalid.jpg')],
    ])->assertSessionHasErrors('category_id');

    $invalidated = Listing::factory()->create([
        'seller_profile_id' => $seller->id,
        'category_id' => $parent->id,
        'status' => 'draft',
    ]);
    $this->actingAs($seller->user)
        ->get(route('seller.listings.edit', $invalidated))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selectedCategory.id', $parent->id)
            ->where('selectedCategory.is_selectable', false));

    $this->actingAs($seller->user)
        ->post(route('seller.listings.submit'), ['listing_id' => $invalidated->id])
        ->assertSessionHasErrors('category_id');

    $this->get(route('listings.index', ['category' => $parent->slug]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('listings.data', 1)
            ->where('listings.data.0.id', $listing->id));
});

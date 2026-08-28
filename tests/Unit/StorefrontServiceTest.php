<?php

use App\Contracts\Repositories\CatalogRepository;
use App\Contracts\Repositories\ListingRepository;
use App\Contracts\Repositories\PromotionRepository;
use App\Contracts\Repositories\ReviewRepository;
use App\Models\Category;
use App\Models\Listing;
use App\Services\SeoHeadService;
use App\Services\StaticMediaService;
use App\Services\StorefrontService;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery\MockInterface;

afterEach(function () {
    Mockery::close();
});

test('browse data combines listing results navigation context and filter options', function () {
    $listingRepository = Mockery::mock(ListingRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('paginatePublic')
            ->once()
            ->with(['category' => 'fashion', 'sort' => 'newest'])
            ->andReturn(new LengthAwarePaginator([], 0, 18, 1, ['path' => '/listings']));
    });
    $catalogRepository = Mockery::mock(CatalogRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('activeTopLevelCategories')->once()->andReturn(collect());
        $mock->shouldReceive('activeCategoryContextBySlug')->once()->with('fashion')->andReturn([
            'current' => ['id' => 1, 'name' => 'Fashion', 'slug' => 'fashion'],
            'ancestors' => [],
            'children' => [],
        ]);
        $mock->shouldReceive('availableBrands')->once()->andReturn(collect());
    });

    $data = (new StorefrontService($listingRepository, $catalogRepository, Mockery::mock(PromotionRepository::class), Mockery::mock(ReviewRepository::class), Mockery::mock(SeoHeadService::class), Mockery::mock(StaticMediaService::class)))->browseData([
        'category' => 'fashion',
        'sort' => 'newest',
    ]);

    expect($data['categoryContext']['current']['slug'])->toBe('fashion')
        ->and($data['filters']['sort'])->toBe('newest')
        ->and($data['listings'])->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($data['filterOptions']['brands'])->toBeEmpty();
});

test('listing details include an empty media collection and category trail', function () {
    $category = new Category(['name' => 'Laptops', 'slug' => 'laptops']);
    $listing = new Listing([
        'id' => 42,
        'title' => 'Modern laptop',
        'slug' => 'modern-laptop',
        'description' => 'A detailed listing.',
        'condition' => 'new',
        'listing_type' => 'buy_now',
        'price' => 120000,
        'location' => 'Colombo',
        'stock_quantity' => 2,
        'reserved_quantity' => 0,
    ]);
    $listing->setRelation('category', $category);
    $listing->setRelation('brand', null);
    $listing->setRelation('media', collect());
    $listing->setRelation('sellerProfile', null);
    $listing->setRelation('auction', null);

    $listingRepository = Mockery::mock(ListingRepository::class, function (MockInterface $mock) use ($listing): void {
        $mock->shouldReceive('findPublicBySlug')->once()->with('modern-laptop')->andReturn($listing);
    });
    $catalogRepository = Mockery::mock(CatalogRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('activeTopLevelCategories')->once()->andReturn(collect());
        $mock->shouldReceive('activeCategoryTrailBySlug')->once()->with('laptops')->andReturn([
            ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics'],
            ['id' => 2, 'name' => 'Laptops', 'slug' => 'laptops'],
        ]);
    });
    $reviewRepository = Mockery::mock(ReviewRepository::class, function (MockInterface $mock): void {
        $mock->shouldReceive('forListing')->once()->with(0, 20)->andReturn(collect());
    });
    $seo = Mockery::mock(SeoHeadService::class, function (MockInterface $mock) use ($listing): void {
        $mock->shouldReceive('listing')->once()->with($listing)->andReturn(['<title>Modern laptop</title>']);
    });

    $data = (new StorefrontService($listingRepository, $catalogRepository, Mockery::mock(PromotionRepository::class), $reviewRepository, $seo, Mockery::mock(StaticMediaService::class)))->listingDetailsData('modern-laptop');

    expect($data['listing']['media'])->toBeEmpty()
        ->and($data['head'])->toHaveCount(1)
        ->and($data['categoryTrail'])->toHaveCount(2)
        ->and($data['categoryTrail'][1]['slug'])->toBe('laptops');
});

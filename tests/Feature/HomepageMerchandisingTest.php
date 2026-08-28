<?php

use App\Models\Category;
use App\Models\Listing;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function homepageAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    return $admin;
}

test('an operations admin can assign popular and ordered homepage categories', function () {
    $admin = homepageAdmin();
    $categories = Category::factory()->count(6)->create();

    $this->actingAs($admin)->put(route('admin.homepage.categories.update'), [
        'popular_category_ids' => $categories->take(3)->pluck('id')->all(),
        'featured_category_ids' => $categories->take(5)->pluck('id')->all(),
        'reason' => 'Refresh seasonal homepage merchandising',
    ])->assertRedirect(route('admin.homepage.index'));

    expect(Category::query()->where('is_popular', true)->pluck('id')->all())
        ->toEqualCanonicalizing($categories->take(3)->pluck('id')->all())
        ->and(Category::query()->whereNotNull('homepage_order')->orderBy('homepage_order')->pluck('id')->all())
        ->toBe($categories->take(5)->pluck('id')->all());

    $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'action' => 'homepage.category_merchandising_updated']);
});

test('homepage category limits and authorization are enforced', function () {
    $categories = Category::factory()->count(11)->create();

    $this->actingAs(homepageAdmin())->put(route('admin.homepage.categories.update'), [
        'popular_category_ids' => $categories->pluck('id')->all(),
        'featured_category_ids' => $categories->take(6)->pluck('id')->all(),
        'reason' => 'This exceeds both supported limits',
    ])->assertSessionHasErrors(['popular_category_ids', 'featured_category_ids']);

    $this->actingAs(User::factory()->create())->get(route('admin.homepage.index'))->assertForbidden();
});

test('best offers require a discounted approved buy now listing', function () {
    $admin = homepageAdmin();
    $eligible = Listing::factory()->create(['price' => '10000.00', 'sale_price' => '7500.00']);
    $ineligible = Listing::factory()->create(['price' => '10000.00', 'sale_price' => null]);

    $this->actingAs($admin)->patch(route('admin.homepage.listings.update', $eligible), [
        'is_best_offer' => true,
        'is_new_arrival' => true,
        'reason' => 'Strong verified launch discount',
    ])->assertRedirect();

    expect($eligible->refresh()->is_best_offer)->toBeTrue()
        ->and($eligible->is_new_arrival)->toBeTrue();

    $this->actingAs($admin)->patch(route('admin.homepage.listings.update', $ineligible), [
        'is_best_offer' => true,
        'is_new_arrival' => false,
        'reason' => 'Attempt invalid offer placement',
    ])->assertSessionHasErrors('is_best_offer');
});

test('homepage output filters curated listings and defers lower sections', function () {
    Storage::fake('public');
    Storage::disk('public')->put('categories/featured/banner.webp', 'banner');
    $category = Category::factory()->create([
        'is_popular' => true,
        'homepage_order' => 1,
        'banner_image_path' => 'categories/featured/banner.webp',
        'banner_image_disk' => 'public',
    ]);
    $visible = Listing::factory()->create(['category_id' => $category->id, 'price' => '1000.00', 'sale_price' => '800.00', 'is_best_offer' => true, 'is_new_arrival' => true]);
    Listing::factory()->create(['category_id' => $category->id, 'status' => 'draft', 'price' => '1000.00', 'sale_price' => '700.00', 'is_best_offer' => true, 'is_new_arrival' => true]);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('popularCategories', 1)
        ->has('bestOffers', 1)
        ->where('bestOffers.0.id', $visible->id)
        ->has('newArrivals', 1)
        ->missing('categorySections')
        ->loadDeferredProps('homepage-below-fold', fn (Assert $reload) => $reload
            ->has('categorySections', 1)
            ->where('categorySections.0.category.banner_image_url', Storage::disk('public')->url($category->banner_image_path))
            ->where('categorySections.0.listings.0.id', $visible->id)
            ->has('socialProof')));

    $homeComponent = file_get_contents(resource_path('js/pages/storefront/home.tsx'));

    expect($homeComponent)
        ->toContain('section.category.banner_image_url')
        ->toContain('bg-gradient-to-br from-slate-950 via-teal-950 to-primary');
});

test('recently viewed listings preserve requested order and exclude unavailable products', function () {
    $first = Listing::factory()->create();
    $second = Listing::factory()->create();
    $draft = Listing::factory()->create(['status' => 'draft']);

    $this->getJson(route('listings.recent', ['ids' => [$second->id, $draft->id, $first->id]]))
        ->assertOk()
        ->assertJsonPath('listings.0.id', $second->id)
        ->assertJsonPath('listings.1.id', $first->id)
        ->assertJsonCount(2, 'listings');

    $this->getJson(route('listings.recent', ['ids' => range(1, 13)]))->assertUnprocessable()->assertJsonValidationErrors('ids');
});

<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('seller entered offer prices must be lower than the regular buy now price', function () {
    Storage::fake('public');
    config()->set('filesystems.media', 'public');
    $seller = User::factory()->create();
    SellerProfile::factory()->create(['user_id' => $seller->id]);
    $category = Category::factory()->create(['is_selectable' => true]);

    $attributes = [
        'category_id' => $category->id,
        'title' => 'Original home office desk',
        'description' => '<p>A carefully described marketplace product.</p>',
        'condition' => 'new',
        'listing_type' => 'buy_now',
        'location' => 'Colombo',
        'stock_quantity' => 3,
        'price' => '25000.00',
        'sale_price' => '25000.00',
        'images' => [UploadedFile::fake()->image('desk.jpg', 1600, 1200)],
        'image_crops' => [['x' => 0, 'y' => 0, 'width' => 1600, 'height' => 1200]],
    ];

    $this->actingAs($seller)->post(route('seller.listings.store'), $attributes)->assertSessionHasErrors('sale_price');

    $attributes['sale_price'] = '22500.00';
    $attributes['images'] = [UploadedFile::fake()->image('desk-valid.jpg', 1600, 1200)];
    $this->actingAs($seller)->post(route('seller.listings.store'), $attributes)->assertRedirect(route('seller.listings.index'));

    expect(Listing::query()->sole()->sale_price)->toBe('22500.00');
});

test('effective offer pricing is snapshotted through totals commissions and cod validation', function () {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create([
        'price' => '60000.00',
        'sale_price' => '40000.00',
        'commission_percentage' => '10.00',
    ]);
    $cart = Cart::factory()->create(['buyer_id' => $buyer->id]);
    $cart->items()->create(['listing_id' => $listing->id, 'quantity' => 1]);

    $this->actingAs($buyer)->post(route('checkout.store'), [
        'payment_method' => 'cod',
        'recipient_name' => 'Buyer Name',
        'address_line_one' => '10 Galle Road',
        'city' => 'Colombo',
        'phone' => '0771234567',
    ])->assertRedirect(route('buyer.orders.index', absolute: false));

    $order = CustomerOrder::query()->where('buyer_id', $buyer->id)->sole();
    $item = $order->sellerOrders()->sole()->items()->sole();

    expect($order->subtotal)->toBe('40000.00')
        ->and($item->unit_price)->toBe('40000.00')
        ->and($item->total)->toBe('40000.00')
        ->and($item->commission_amount)->toBe('4000.00');
});

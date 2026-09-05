<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\MarketplaceSetting;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

test('guests can manage a cart with authoritative totals and no account', function (): void {
    $listing = Listing::factory()->create(['price' => 1000, 'sale_price' => null, 'stock_quantity' => 10]);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 2])->assertSessionHasNoErrors()->assertSessionHas('cart_added', true);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])->assertSessionHasNoErrors();
    $id = $listing->id.'-base';
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.quantity', 3)->where('cart.total', '3600.00')->where('commerce.cart_quantity', 3));
    $this->patch(route('cart.items.update', $id), ['quantity' => 2])->assertSessionHasNoErrors();
    $this->get(route('checkout.show'))->assertRedirect(route('login'));
    $this->delete(route('cart.items.destroy', $id))->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.quantity', 0)->where('cart.total', '0.00')->where('cart.canCheckout', false));
    expect(Cart::count())->toBe(0);
});

test('guest cart merges once and preserves stock conflicts for correction', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['stock_quantity' => 5]);
    $cart = Cart::factory()->for($buyer, 'buyer')->create();
    CartItem::factory()->for($cart)->for($listing)->create(['quantity' => 4]);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 3])->assertSessionHasNoErrors();
    $this->actingAs($buyer)->get(route('cart.show'))->assertSessionMissing('guest_cart')->assertInertia(fn ($page) => $page->where('cart.quantity', 7)->where('cart.canCheckout', false));
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.quantity', 7));
    $this->patch(route('cart.items.update', $cart->items()->sole()->id), ['quantity' => 5])->assertSessionHasNoErrors();
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.canCheckout', true));
});

test('cart mutations are scoped to the buyer or guest session', function (): void {
    $otherItem = CartItem::factory()->create();
    $this->patch(route('cart.items.update', $otherItem->id), ['quantity' => 2])->assertNotFound();
    $this->actingAs(User::factory()->create())->delete(route('cart.items.destroy', $otherItem->id))->assertNotFound();
    expect($otherItem->fresh())->not->toBeNull();
});

test('unavailable listings and missing variant choices cannot be added', function (): void {
    $listing = Listing::factory()->create(['product_type' => 'variant']);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])->assertSessionHasErrors();
    $listing->update(['status' => 'archived']);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1])->assertNotFound();
});

test('stock changes are visible in both shared and full cart summaries', function (): void {
    $listing = Listing::factory()->create(['stock_quantity' => 3]);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 3]);
    $listing->update(['reserved_quantity' => 2]);
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.canCheckout', false)->where('commerce.cart.canCheckout', false)->where('cart.items.0.availableQuantity', 1));
    $this->patch(route('cart.items.update', $listing->id.'-base'), ['quantity' => 4])->assertSessionHasErrors('quantity');
});

test('shipping is configurable and COD eligibility includes delivery', function (): void {
    MarketplaceSetting::query()->create(['key' => 'checkout.shipping_fee', 'group' => 'checkout', 'value' => ['value' => 750]]);
    $listing = Listing::factory()->create(['price' => 49500, 'sale_price' => null]);
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1]);
    $this->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.shippingTotal', '750.00')->where('cart.total', '50250.00')->where('cart.paymentMethods', []));
});

test('unverified customers can manage carts but cannot checkout', function (): void {
    $this->actingAs(User::factory()->unverified()->create());
    $listing = Listing::factory()->create();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1, 'buy_now' => true])->assertRedirect(route('checkout.show'));
    $this->get(route('checkout.show'))->assertRedirect(route('verification.notice'));
});

test('guest checkout survives login and email verification', function (): void {
    Notification::fake();
    $buyer = User::factory()->unverified()->create(['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    $listing = Listing::factory()->create();
    $this->post(route('cart.items.store'), ['listing_id' => $listing->id, 'quantity' => 1, 'buy_now' => true])->assertRedirect(route('checkout.show'));
    $this->get(route('checkout.show'))->assertRedirect(route('login'));
    $this->post(route('login'), ['email' => $buyer->email, 'password' => 'password'])->assertRedirect(route('checkout.show'));
    $this->get(route('checkout.show'))->assertRedirect(route('verification.notice'));
    $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(10), ['id' => $buyer->id, 'hash' => sha1($buyer->email)]))->assertRedirect(route('checkout.show'));
    $this->get(route('checkout.show'))->assertInertia(fn ($page) => $page->has('cart.items', 1));
});

test('replayed guest merges cannot duplicate quantities', function (): void {
    $buyer = User::factory()->create();
    $listing = Listing::factory()->create(['stock_quantity' => 10]);
    $entry = ['id' => $listing->id.'-base', 'listing_id' => $listing->id, 'listing_variant_id' => null, 'selection_key' => 'base', 'quantity' => 2];
    $session = ['guest_cart' => [$entry['id'] => $entry], 'guest_cart_token' => (string) Str::uuid()];
    $this->actingAs($buyer)->withSession($session)->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.quantity', 2));
    $this->withSession($session)->get(route('cart.show'))->assertInertia(fn ($page) => $page->where('cart.quantity', 2));
});

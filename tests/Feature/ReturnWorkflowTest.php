<?php

use App\Models\CustomerOrder;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use App\ReturnReason;
use App\ReturnStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{seller: User, seller_profile: SellerProfile, seller_order: SellerOrder, item: OrderItem} */
function createReturnablePurchase(User $buyer, int $quantity = 2, ?DateTimeInterface $deliveredAt = null): array
{
    $seller = User::factory()->create();
    $sellerProfile = SellerProfile::factory()->for($seller)->create();
    $customerOrder = CustomerOrder::factory()->for($buyer, 'buyer')->create();
    $sellerOrder = SellerOrder::factory()
        ->for($customerOrder)
        ->for($sellerProfile)
        ->delivered()
        ->create([
            'delivered_at' => $deliveredAt ?? now(),
            'completed_at' => $deliveredAt ?? now(),
        ]);
    $item = OrderItem::factory()->for($sellerOrder)->create([
        'quantity' => $quantity,
        'unit_price' => '500.00',
        'total' => (string) (500 * $quantity),
    ]);

    return compact('seller', 'sellerProfile', 'sellerOrder', 'item');
}

test('a seller confirms delivery and opens the return window', function () {
    $buyer = User::factory()->create();
    $seller = User::factory()->create();
    $sellerProfile = SellerProfile::factory()->for($seller)->create();
    $customerOrder = CustomerOrder::factory()->for($buyer, 'buyer')->create();
    $sellerOrder = SellerOrder::factory()->for($customerOrder)->for($sellerProfile)->create([
        'status' => 'ready_to_ship',
        'ready_to_ship_at' => now()->subHour(),
    ]);
    $shipment = Shipment::factory()->for($sellerOrder)->create();

    $this->actingAs($seller)
        ->post(route('seller.orders.delivered', $sellerOrder))
        ->assertRedirect(route('seller.orders.index'));

    $sellerOrder->refresh();

    expect($sellerOrder->status)->toBe('completed')
        ->and($sellerOrder->delivered_at)->not->toBeNull()
        ->and($sellerOrder->completed_at?->equalTo($sellerOrder->delivered_at))->toBeTrue()
        ->and($shipment->refresh()->status)->toBe('delivered');
});

test('only the owning seller can confirm a ready to ship delivery', function () {
    $buyer = User::factory()->create();
    ['sellerOrder' => $sellerOrder] = createReturnablePurchase($buyer);
    $sellerOrder->forceFill(['status' => 'ready_to_ship', 'delivered_at' => null, 'completed_at' => null])->save();

    $this->actingAs(User::factory()->create())
        ->post(route('seller.orders.delivered', $sellerOrder))
        ->assertForbidden();

    expect($sellerOrder->refresh()->delivered_at)->toBeNull();
});

test('the exact seven day expiry is eligible and one second later is not', function () {
    Storage::fake('local');
    $buyer = User::factory()->create();
    $deliveredAt = now()->startOfSecond();
    ['item' => $item] = createReturnablePurchase($buyer, deliveredAt: $deliveredAt);

    $this->travelTo($deliveredAt->addDays(7));
    $this->actingAs($buyer)->post(route('buyer.returns.store'), [
        'order_item_id' => $item->id,
        'quantity' => 1,
        'reason' => ReturnReason::Damaged->value,
        'description' => 'The product arrived with visible damage.',
    ])->assertRedirect(route('buyer.returns.index'));

    ['item' => $expiredItem] = createReturnablePurchase($buyer, deliveredAt: $deliveredAt);
    $this->travelTo($deliveredAt->addDays(7)->addSecond());

    $this->actingAs($buyer)->post(route('buyer.returns.store'), [
        'order_item_id' => $expiredItem->id,
        'quantity' => 1,
        'reason' => ReturnReason::Damaged->value,
        'description' => 'The product arrived with visible damage.',
    ])->assertSessionHasErrors('order_item_id');
});

test('partial requests cannot exceed the purchased quantity', function () {
    $buyer = User::factory()->create();
    ['item' => $item] = createReturnablePurchase($buyer, quantity: 3);

    $payload = [
        'order_item_id' => $item->id,
        'quantity' => 2,
        'reason' => ReturnReason::NotAsDescribed->value,
        'description' => 'The specifications do not match the listing.',
    ];

    $this->actingAs($buyer)->post(route('buyer.returns.store'), $payload)->assertSessionHasNoErrors();
    $this->actingAs($buyer)->post(route('buyer.returns.store'), $payload)->assertSessionHasErrors('quantity');

    expect(ReturnRequest::query()->where('order_item_id', $item->id)->count())->toBe(1)
        ->and(ReturnRequest::query()->where('order_item_id', $item->id)->value('refund_amount'))->toBe('1000.00');
});

test('buyers cannot request returns for another buyers order line', function () {
    $buyer = User::factory()->create();
    ['item' => $item] = createReturnablePurchase($buyer);

    $this->actingAs(User::factory()->create())->post(route('buyer.returns.store'), [
        'order_item_id' => $item->id,
        'quantity' => 1,
        'reason' => ReturnReason::Other->value,
        'description' => 'This order does not belong to this account.',
    ])->assertSessionHasErrors('order_item_id');

    expect(ReturnRequest::query()->count())->toBe(0);
});

test('evidence is validated, stored privately, and isolated by authorization', function () {
    Storage::fake('local');
    $buyer = User::factory()->create();
    ['seller' => $seller, 'item' => $item] = createReturnablePurchase($buyer);

    $this->actingAs($buyer)->post(route('buyer.returns.store'), [
        'order_item_id' => $item->id,
        'quantity' => 1,
        'reason' => ReturnReason::Damaged->value,
        'description' => 'The product arrived with visible damage.',
        'evidence' => [UploadedFile::fake()->image('damage.jpg')->size(500)],
    ])->assertSessionHasNoErrors();

    $returnRequest = ReturnRequest::query()->firstOrFail();
    $path = $returnRequest->evidence[0]['path'];
    Storage::disk('local')->assertExists($path);

    $this->actingAs($buyer)->get(route('returns.evidence.show', [$returnRequest, 0]))->assertOk();
    $this->actingAs($seller)->get(route('returns.evidence.show', [$returnRequest, 0]))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('returns.evidence.show', [$returnRequest, 0]))->assertForbidden();

    $this->actingAs($buyer)->post(route('buyer.returns.store'), [
        'order_item_id' => $item->id,
        'quantity' => 1,
        'reason' => ReturnReason::Other->value,
        'description' => 'Unsupported evidence should be rejected.',
        'evidence' => [UploadedFile::fake()->create('note.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('evidence.0');
});

test('seller decisions are owner only and final in the portal', function () {
    $buyer = User::factory()->create();
    ['seller' => $seller, 'item' => $item] = createReturnablePurchase($buyer);
    $returnRequest = ReturnRequest::factory()->for($item, 'orderItem')->for($buyer, 'buyer')->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('seller.returns.update', $returnRequest), [
            'decision' => ReturnStatus::Approved->value,
            'response_reason' => 'The evidence supports the buyer request.',
        ])->assertForbidden();

    $this->actingAs($seller)
        ->patch(route('seller.returns.update', $returnRequest), [
            'decision' => ReturnStatus::Approved->value,
            'response_reason' => 'The evidence supports the buyer request.',
        ])->assertRedirect(route('seller.returns.index'));

    $this->actingAs($seller)
        ->patch(route('seller.returns.update', $returnRequest), [
            'decision' => ReturnStatus::Rejected->value,
            'response_reason' => 'Attempting to replace the final decision.',
        ])->assertSessionHasErrors('decision');

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::Approved)
        ->and($returnRequest->seller_responded_at)->not->toBeNull();
});

test('buyer and seller return queues are isolated', function () {
    $buyer = User::factory()->create();
    $otherBuyer = User::factory()->create();
    ['seller' => $seller, 'item' => $item] = createReturnablePurchase($buyer);
    ['item' => $otherItem] = createReturnablePurchase($otherBuyer);
    ReturnRequest::factory()->for($item, 'orderItem')->for($buyer, 'buyer')->create(['description' => 'Visible to the first buyer only.']);
    ReturnRequest::factory()->for($otherItem, 'orderItem')->for($otherBuyer, 'buyer')->create(['description' => 'Hidden request belonging elsewhere.']);

    $this->actingAs($buyer)
        ->get(route('buyer.returns.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('buyer/returns/index')
            ->has('returns.data', 1)
            ->where('returns.data.0.description', 'Visible to the first buyer only.'));

    $this->actingAs($seller)
        ->get(route('seller.returns.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('seller/returns/index')
            ->has('returns.data', 1)
            ->where('returns.data.0.description', 'Visible to the first buyer only.'));
});

test('operations users can view evidence without gaining a seller decision route', function () {
    Storage::fake('local');
    $buyer = User::factory()->create();
    ['item' => $item] = createReturnablePurchase($buyer);
    $returnRequest = ReturnRequest::factory()->for($item, 'orderItem')->for($buyer, 'buyer')->create([
        'evidence' => [['path' => 'return-evidence/example.jpg', 'name' => 'example.jpg', 'mime' => 'image/jpeg', 'size' => 100]],
    ]);
    Storage::disk('local')->put('return-evidence/example.jpg', 'evidence');
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    $this->actingAs($admin)->get(route('returns.evidence.show', [$returnRequest, 0]))->assertOk();
    $this->actingAs($admin)->patch(route('seller.returns.update', $returnRequest), [
        'decision' => ReturnStatus::Rejected->value,
        'response_reason' => 'Administrators must not override the seller.',
    ])->assertForbidden();

    expect($returnRequest->refresh()->status)->toBe(ReturnStatus::Requested);
});

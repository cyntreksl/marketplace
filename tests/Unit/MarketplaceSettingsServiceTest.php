<?php

use App\Contracts\Repositories\MarketplaceSettingRepository;
use App\Services\MarketplaceSettingsService;

test('marketplace integer settings accept seeded scalars and existing wrapped values', function (mixed $stored, int $expected): void {
    $repository = $this->createStub(MarketplaceSettingRepository::class);
    $repository->method('value')->willReturn($stored);

    expect((new MarketplaceSettingsService($repository))->integer('checkout.shipping_fee', 600))->toBe($expected);
})->with([[750, 750], [['value' => 850], 850], ['900', 900], [0, 0], [null, 600], ['invalid', 600], [[], 600]]);

test('review flags are read independently and default to disabled', function (): void {
    $repository = $this->createStub(MarketplaceSettingRepository::class);
    $repository->method('value')->willReturnCallback(fn (string $key): mixed => match ($key) {
        'reviews.product.enabled' => true,
        'reviews.seller.enabled' => null,
    });

    expect((new MarketplaceSettingsService($repository))->reviewFlags())->toBe([
        'product' => true,
        'seller' => false,
    ]);
});

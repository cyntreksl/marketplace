<?php

namespace App\Services;

use App\AuctionType;
use App\Contracts\Repositories\MarketplaceSettingRepository;

class MarketplaceSettingsService
{
    public function __construct(private readonly MarketplaceSettingRepository $settings) {}

    public function integer(string $key, int $default): int
    {
        $value = $this->settings->value($key);
        $value = is_array($value) ? ($value['value'] ?? null) : $value;

        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->settings->value($key);
        $value = is_array($value) ? ($value['value'] ?? null) : $value;

        return match (true) {
            is_bool($value) => $value,
            is_numeric($value) => (bool) $value,
            is_string($value) => filter_var($value, FILTER_VALIDATE_BOOL),
            default => $default,
        };
    }

    public function auctionsEnabled(): bool
    {
        return $this->boolean('auction.enabled');
    }

    public function auctionTypeEnabled(AuctionType $type): bool
    {
        return $this->auctionsEnabled() && $this->boolean($type->settingKey(), true);
    }

    public function productReviewsEnabled(): bool
    {
        return $this->boolean('reviews.product.enabled');
    }

    public function sellerReviewsEnabled(): bool
    {
        return $this->boolean('reviews.seller.enabled');
    }

    /** @return array{product: bool, seller: bool} */
    public function reviewFlags(): array
    {
        return [
            'product' => $this->productReviewsEnabled(),
            'seller' => $this->sellerReviewsEnabled(),
        ];
    }

    /** @return array{enabled: bool, types: array<string, bool>} */
    public function auctionFlags(): array
    {
        return [
            'enabled' => $this->auctionsEnabled(),
            'types' => collect(AuctionType::cases())->mapWithKeys(
                fn (AuctionType $type): array => [$type->value => $this->boolean($type->settingKey(), true)],
            )->all(),
        ];
    }
}

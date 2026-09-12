<?php

namespace App\Support;

use App\Models\User;

final readonly class CheckoutContext
{
    /** @param list<array<string, mixed>> $guestCartEntries */
    public function __construct(
        public ?User $buyer,
        public string $contactEmail,
        public bool $marketingOptIn,
        public array $guestCartEntries = [],
        public ?string $guestIdentityHash = null,
        public ?string $guestAccessToken = null,
    ) {}

    public function isGuest(): bool
    {
        return $this->buyer === null;
    }

    public function guestAccessTokenHash(): ?string
    {
        return $this->guestAccessToken === null
            ? null
            : hash('sha256', $this->guestAccessToken);
    }
}

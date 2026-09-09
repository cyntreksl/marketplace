<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Cookie;

final readonly class MetaParameterContext
{
    /** @param list<Cookie> $responseCookies */
    public function __construct(
        public ?string $fbc,
        public ?string $fbp,
        public ?string $clientIpAddress,
        public ?string $sourceUrl,
        public ?string $referrerUrl,
        public array $responseCookies,
    ) {}
}

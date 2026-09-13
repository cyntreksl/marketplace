<?php

namespace App\Support;

final readonly class MetaConversionReceipt
{
    /** @param list<string> $messages */
    public function __construct(
        public int $eventsReceived,
        public ?string $fbtraceId,
        public array $messages,
    ) {}
}

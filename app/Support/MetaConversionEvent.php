<?php

namespace App\Support;

final readonly class MetaConversionEvent
{
    /**
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    public function __construct(
        public string $name,
        public string $id,
        public int $occurredAt,
        public string $sourceUrl,
        public array $userData,
        public array $customData,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'event_name' => $this->name,
            'event_time' => $this->occurredAt,
            'event_id' => $this->id,
            'event_source_url' => $this->sourceUrl,
            'action_source' => 'website',
            'user_data' => $this->userData,
            'custom_data' => $this->customData,
        ];
    }
}

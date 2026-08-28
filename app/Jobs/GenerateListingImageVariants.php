<?php

namespace App\Jobs;

use App\Services\ListingImageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateListingImageVariants implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public int $uniqueFor = 3600;

    public function __construct(
        public int $mediaId,
        public string $version,
        public bool $includeOpenGraph,
    ) {}

    public function uniqueId(): string
    {
        return $this->mediaId.':'.$this->version;
    }

    public function handle(ListingImageService $images): void
    {
        try {
            $images->generateVariants($this->mediaId, $this->version, $this->includeOpenGraph);
        } catch (Throwable $exception) {
            $images->markFailed($this->mediaId, $this->version, $exception);

            throw $exception;
        }
    }
}

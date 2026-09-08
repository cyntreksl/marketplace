<?php

namespace App\Contracts;

interface GoogleMerchantGateway
{
    /** @return array<string, mixed> */
    public function source(): array;

    /** @return array<string, mixed> */
    public function latestUpload(): array;
}

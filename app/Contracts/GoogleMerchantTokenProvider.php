<?php

namespace App\Contracts;

interface GoogleMerchantTokenProvider
{
    public function token(bool $refresh = false): string;
}

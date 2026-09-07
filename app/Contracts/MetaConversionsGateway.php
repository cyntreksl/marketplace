<?php

namespace App\Contracts;

use App\Support\MetaConversionEvent;

interface MetaConversionsGateway
{
    public function send(MetaConversionEvent $event, ?string $testEventCode = null): void;
}

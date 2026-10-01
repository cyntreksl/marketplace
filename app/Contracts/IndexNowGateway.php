<?php

namespace App\Contracts;

interface IndexNowGateway
{
    /** @param list<string> $urls */
    public function submit(string $keyLocation, array $urls): void;
}

<?php

namespace App\Contracts;

interface GoogleSearchConsoleTokenProvider
{
    public function token(bool $refresh = false): string;
}

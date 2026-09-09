<?php

namespace App\Support;

class TrackingConsent
{
    public const COOKIE_NAME = 'prodeals_consent';

    public const VERSION = 1;

    /** @return array{version: int, analytics: bool, marketing: bool, decidedAt: string}|null */
    public function fromCookie(?string $cookie): ?array
    {
        if ($cookie === null || $cookie === '') {
            return null;
        }

        $decoded = json_decode(urldecode($cookie), true);

        if (! is_array($decoded)
            || ($decoded['version'] ?? null) !== self::VERSION
            || ! is_bool($decoded['analytics'] ?? null)
            || ! is_bool($decoded['marketing'] ?? null)
            || ! is_string($decoded['decidedAt'] ?? null)) {
            return null;
        }

        return [
            'version' => self::VERSION,
            'analytics' => $decoded['analytics'],
            'marketing' => $decoded['marketing'],
            'decidedAt' => $decoded['decidedAt'],
        ];
    }

    public function allowsGtmCookie(?string $cookie): bool
    {
        $consent = $this->fromCookie($cookie);

        return $consent !== null && ($consent['analytics'] || $consent['marketing']);
    }

    public function allowsMarketing(?string $cookie): bool
    {
        return $this->fromCookie($cookie)['marketing'] ?? false;
    }
}

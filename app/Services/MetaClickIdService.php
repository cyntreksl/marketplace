<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class MetaClickIdService
{
    public const string COOKIE_NAME = '_fbc';

    public const string BROWSER_COOKIE_NAME = '_fbp';

    private const string REQUEST_ATTRIBUTE = 'meta_fbc';

    private const int COOKIE_LIFETIME_DAYS = 90;

    private const int MAX_CLICK_ID_LENGTH = 500;

    private const string CLICK_ID_PATTERN = '/\A[A-Za-z0-9._-]+\z/D';

    private const string FBC_PATTERN = '/\Afb\.\d+\.\d{13}\.(?<click_id>[A-Za-z0-9._-]+)\z/D';

    public function capture(Request $request): ?Cookie
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $existingFbc = $this->validFbc($request->cookie(self::COOKIE_NAME));
        $clickId = $this->validClickId($request->query('fbclid'));

        if ($clickId === null) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $existingFbc);

            return null;
        }

        if ($existingFbc !== null && $this->clickIdFromFbc($existingFbc) === $clickId) {
            $request->attributes->set(self::REQUEST_ATTRIBUTE, $existingFbc);

            return null;
        }

        $fbc = 'fb.1.'.now()->getTimestampMs().'.'.$clickId;
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $fbc);
        $domain = config('session.domain');

        return new Cookie(
            name: self::COOKIE_NAME,
            value: $fbc,
            expire: now()->addDays(self::COOKIE_LIFETIME_DAYS),
            path: '/',
            domain: is_string($domain) && $domain !== '' ? $domain : null,
            secure: $request->isSecure(),
            httpOnly: false,
            sameSite: Cookie::SAMESITE_LAX,
        );
    }

    public function value(Request $request): ?string
    {
        if ($request->attributes->has(self::REQUEST_ATTRIBUTE)) {
            $value = $request->attributes->get(self::REQUEST_ATTRIBUTE);

            return is_string($value) ? $value : null;
        }

        $cookie = $this->capture($request);

        return $cookie?->getValue()
            ?? $this->validFbc($request->attributes->get(self::REQUEST_ATTRIBUTE));
    }

    private function isEnabled(): bool
    {
        return config('services.meta_conversions.enabled') === true
            && filled(config('services.meta_conversions.pixel_id'))
            && filled(config('services.meta_conversions.access_token'));
    }

    private function validClickId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return $value !== ''
            && mb_strlen($value) <= self::MAX_CLICK_ID_LENGTH
            && preg_match(self::CLICK_ID_PATTERN, $value) === 1
                ? $value
                : null;
    }

    private function validFbc(mixed $value): ?string
    {
        if (! is_string($value) || mb_strlen($value) > self::MAX_CLICK_ID_LENGTH + 32) {
            return null;
        }

        return preg_match(self::FBC_PATTERN, $value) === 1 ? $value : null;
    }

    private function clickIdFromFbc(string $fbc): ?string
    {
        preg_match(self::FBC_PATTERN, $fbc, $matches);

        return is_string($matches['click_id'] ?? null) ? $matches['click_id'] : null;
    }
}

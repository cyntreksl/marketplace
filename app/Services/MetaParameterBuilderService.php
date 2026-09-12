<?php

namespace App\Services;

use App\Support\MetaParameterContext;
use App\Support\TrackingConsent;
use FacebookAds\ParamBuilder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Throwable;

class MetaParameterBuilderService
{
    public const string CLICK_COOKIE_NAME = '_fbc';

    public const string BROWSER_COOKIE_NAME = '_fbp';

    public const string PII_EMAIL = 'email';

    public const string PII_PHONE = 'phone';

    public const string PII_FIRST_NAME = 'first_name';

    public const string PII_LAST_NAME = 'last_name';

    public const string PII_CITY = 'city';

    public const string PII_ZIP_CODE = 'zip_code';

    public const string PII_COUNTRY = 'country';

    public const string PII_EXTERNAL_ID = 'external_id';

    private const string REQUEST_ATTRIBUTE = 'meta_parameter_context';

    private const int COOKIE_LIFETIME_DAYS = 90;

    private const int MAX_CLICK_ID_LENGTH = 500;

    private const string CLICK_ID_PATTERN = '/\A[A-Za-z0-9._-]+\z/D';

    private const string FBC_PATTERN = '/\Afb\.\d+\.\d{13}\.[A-Za-z0-9._-]+(?:\.(?:[A-Za-z0-9_-]{8}|AQ|Ag|Aw|BA|BQ|Bg))?\z/D';

    private const string FBP_PATTERN = '/\Afb\.\d+\.\d{13}\.\d+(?:\.(?:[A-Za-z0-9_-]{8}|AQ|Ag|Aw|BA|BQ|Bg))?\z/D';

    public function __construct(private readonly TrackingConsent $trackingConsent) {}

    public function process(Request $request): MetaParameterContext
    {
        $existingContext = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if ($existingContext instanceof MetaParameterContext) {
            return $existingContext;
        }

        if (! $this->isEnabled()) {
            return $this->remember($request, new MetaParameterContext(null, null, null, null, null, []));
        }

        try {
            $allowsMarketing = $this->trackingConsent->allowsMarketing(
                $this->stringValue($request->cookie(TrackingConsent::COOKIE_NAME)),
            );
            $builder = $this->makeBuilder($request);
            $this->processBuilder($builder, $this->serverContext($request));

            return $this->remember($request, new MetaParameterContext(
                fbc: $this->validFbc($builder->getFbc())
                    ?? $this->validFbc($request->cookie(self::CLICK_COOKIE_NAME)),
                fbp: $allowsMarketing ? $this->validFbp($builder->getFbp()) : null,
                clientIpAddress: $this->bounded($builder->getClientIpAddress()),
                sourceUrl: $this->boundedUrl($builder->getEventSourceUrl()),
                referrerUrl: $this->boundedUrl($builder->getReferrerUrl()),
                responseCookies: $this->responseCookies($builder->getCookiesToSet(), $allowsMarketing),
            ));
        } catch (Throwable $exception) {
            report($exception);

            return $this->remember($request, $this->fallbackContext($request));
        }
    }

    public function normalizedAndHashedPii(?string $value, string $dataType): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return $this->bounded((new ParamBuilder)->getNormalizedAndHashedPII($value, $dataType));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function normalizedSriLankanPhone(?string $phone): ?string
    {
        if (! is_string($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '94'.substr($digits, 1);
        }

        return $digits;
    }

    private function makeBuilder(Request $request): ParamBuilder
    {
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $domain = is_string($configuredHost) && $configuredHost !== ''
            ? $configuredHost
            : $request->getHost();

        return new ParamBuilder([$domain]);
    }

    /** @param array<string, mixed> $server */
    protected function processBuilder(ParamBuilder $builder, array $server): void
    {
        $builder->processRequestFromContext($server);
    }

    /** @return array<string, mixed> */
    private function serverContext(Request $request): array
    {
        $server = $request->server->all();
        $clickId = $this->validClickId($request->query('fbclid'));
        $server['QUERY_STRING'] = $clickId === null
            ? '_meta_parameter_builder=1'
            : http_build_query(['fbclid' => $clickId]);
        $server['HTTP_COOKIE'] = $this->cookieHeader($request);

        if (isset($server['HTTP_REFERER'])) {
            $server['HTTP_REFERER'] = $this->sanitizedReferrer($server['HTTP_REFERER']);
        }

        return $server;
    }

    private function cookieHeader(Request $request): string
    {
        $cookies = $request->cookies->all();
        $existingFbc = $this->validFbc($cookies[self::CLICK_COOKIE_NAME] ?? null);

        if ($existingFbc === null) {
            unset($cookies[self::CLICK_COOKIE_NAME]);
        }

        if ($this->validFbp($cookies[self::BROWSER_COOKIE_NAME] ?? null) === null) {
            unset($cookies[self::BROWSER_COOKIE_NAME]);
        }

        $header = implode('; ', array_map(
            fn (string $name, mixed $value): string => $name.'='.rawurlencode(is_string($value) ? $value : ''),
            array_keys($cookies),
            array_values($cookies),
        ));

        return $header !== '' ? $header : '_meta_parameter_builder=1';
    }

    private function sanitizedReferrer(mixed $referrer): ?string
    {
        if (! is_string($referrer) || $referrer === '') {
            return null;
        }

        $queryString = parse_url($referrer, PHP_URL_QUERY);

        if (! is_string($queryString) || $queryString === '') {
            return $referrer;
        }

        parse_str($queryString, $query);

        if (! array_key_exists('fbclid', $query) || $this->validClickId($query['fbclid']) !== null) {
            return $referrer;
        }

        return $this->withoutReferrerClickId($referrer);
    }

    private function withoutReferrerClickId(mixed $referrer): ?string
    {
        if (! is_string($referrer) || $referrer === '') {
            return null;
        }

        $parts = parse_url($referrer);

        if (! is_array($parts)) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['fbclid']);
        $queryString = http_build_query($query);
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $authority = ($parts['user'] ?? '').(isset($parts['pass']) ? ':'.$parts['pass'] : '');
        $authority = $authority !== '' ? $authority.'@' : '';
        $authority .= $parts['host'] ?? '';
        $authority .= isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.$authority.($parts['path'] ?? '')
            .($queryString !== '' ? '?'.$queryString : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    /**
     * @param  array<int, mixed>  $settings
     * @return list<Cookie>
     */
    private function responseCookies(array $settings, bool $allowsMarketing): array
    {
        $cookies = [];

        foreach ($settings as $setting) {
            if (! is_object($setting)) {
                continue;
            }

            $cookieSettings = get_object_vars($setting);
            $name = $cookieSettings['name'] ?? null;
            $rawValue = $cookieSettings['value'] ?? null;

            if (! is_string($name)
                || ! in_array($name, [self::CLICK_COOKIE_NAME, self::BROWSER_COOKIE_NAME], true)
                || ($name === self::BROWSER_COOKIE_NAME && ! $allowsMarketing)) {
                continue;
            }

            $value = $name === self::CLICK_COOKIE_NAME
                ? $this->validFbc($rawValue)
                : $this->validFbp($rawValue);

            if ($value === null) {
                continue;
            }

            $domain = is_string($cookieSettings['domain'] ?? null) && $cookieSettings['domain'] !== ''
                ? $cookieSettings['domain']
                : config('session.domain');

            $cookies[] = new Cookie(
                name: $name,
                value: $value,
                expire: now()->addDays(self::COOKIE_LIFETIME_DAYS),
                path: '/',
                domain: is_string($domain) && $domain !== '' ? $domain : null,
                secure: true,
                httpOnly: false,
                sameSite: Cookie::SAMESITE_LAX,
            );
        }

        return $cookies;
    }

    private function fallbackContext(Request $request): MetaParameterContext
    {
        $allowsMarketing = $this->trackingConsent->allowsMarketing(
            $this->stringValue($request->cookie(TrackingConsent::COOKIE_NAME)),
        );

        return new MetaParameterContext(
            fbc: $this->validFbc($request->cookie(self::CLICK_COOKIE_NAME)),
            fbp: $allowsMarketing ? $this->validFbp($request->cookie(self::BROWSER_COOKIE_NAME)) : null,
            clientIpAddress: $this->bounded($request->ip()),
            sourceUrl: $this->boundedUrl($request->fullUrl()),
            referrerUrl: $this->boundedUrl($request->headers->get('referer')),
            responseCookies: [],
        );
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
        if (! is_string($value) || mb_strlen($value) > self::MAX_CLICK_ID_LENGTH + 40) {
            return null;
        }

        return preg_match(self::FBC_PATTERN, $value) === 1 ? $value : null;
    }

    private function validFbp(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return preg_match(self::FBP_PATTERN, $value) === 1 ? $value : null;
    }

    private function bounded(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 600 ? $value : null;
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function boundedUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' && mb_strlen($value) <= 4096 ? $value : null;
    }

    private function isEnabled(): bool
    {
        return config('services.meta_conversions.enabled') === true
            && filled(config('services.meta_conversions.pixel_id'))
            && filled(config('services.meta_conversions.access_token'));
    }

    private function remember(Request $request, MetaParameterContext $context): MetaParameterContext
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $context);

        return $context;
    }
}

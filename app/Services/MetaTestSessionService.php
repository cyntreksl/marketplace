<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

final class MetaTestSessionService
{
    private const string SESSION_KEY = 'meta_conversions.test_event_code';

    public function createCheckoutLink(string $testEventCode): string
    {
        $testEventCode = $this->validatedCode($testEventCode);

        return URL::temporarySignedRoute(
            'meta.conversions.test_session',
            now()->addMinutes(15),
            ['payload' => Crypt::encryptString($testEventCode)],
        );
    }

    public function activate(Request $request, string $payload): void
    {
        try {
            $testEventCode = $this->validatedCode(Crypt::decryptString($payload));
        } catch (DecryptException $exception) {
            throw new InvalidArgumentException('The Meta test checkout link is invalid.', previous: $exception);
        }

        $request->session()->put(self::SESSION_KEY, $testEventCode);
    }

    public function consume(Request $request): ?string
    {
        $testEventCode = $request->session()->pull(self::SESSION_KEY);

        if (! is_string($testEventCode)) {
            return null;
        }

        try {
            return $this->validatedCode($testEventCode);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function validatedCode(string $testEventCode): string
    {
        $testEventCode = trim($testEventCode);

        if (! preg_match('/\A[A-Za-z0-9_-]{4,100}\z/', $testEventCode)) {
            throw new InvalidArgumentException('The Meta Test Event Code is invalid.');
        }

        return $testEventCode;
    }
}

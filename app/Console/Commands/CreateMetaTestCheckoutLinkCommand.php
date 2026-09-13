<?php

namespace App\Console\Commands;

use App\Services\MetaTestSessionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('meta:conversions:test-checkout-link {--test-event-code= : Temporary code from Meta Events Manager, or - to read it from stdin}')]
#[Description('Create a short-lived checkout link that sends one Purchase through Meta Test Events')]
class CreateMetaTestCheckoutLinkCommand extends Command
{
    public function handle(MetaTestSessionService $testSession): int
    {
        $testEventCode = $this->option('test-event-code');

        if ($testEventCode === '-') {
            $testEventCode = trim((string) fgets(STDIN));
        }

        if (! is_string($testEventCode) || $testEventCode === '') {
            $this->components->error('Provide a temporary Meta Test Event Code.');

            return self::FAILURE;
        }

        try {
            $link = $testSession->createCheckoutLink($testEventCode);
        } catch (InvalidArgumentException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($link);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\MetaConversionsService;
use App\Services\MetaParameterBuilderService;
use App\Support\MetaConversionEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('meta:conversions:test {--test-event-code= : Temporary code from Meta Events Manager, or - to read it from stdin}')]
#[Description('Send a synthetic PageView event to Meta Events Manager')]
class TestMetaConversionsCommand extends Command
{
    public function handle(
        MetaConversionsService $conversions,
        MetaParameterBuilderService $parameterBuilder,
    ): int {
        $testEventCode = $this->option('test-event-code');

        if ($testEventCode === '-') {
            $testEventCode = trim((string) fgets(STDIN));
        }

        if (! is_string($testEventCode) || $testEventCode === '') {
            $this->components->error('Provide a temporary Meta Test Event Code.');

            return self::FAILURE;
        }

        if (! filled(config('services.meta_conversions.pixel_id')) || ! filled(config('services.meta_conversions.access_token'))) {
            $this->components->error('Meta Conversions API credentials are not configured.');

            return self::FAILURE;
        }

        $sourceUrl = rtrim((string) config('app.url'), '/').'/';
        $conversions->sendTest(new MetaConversionEvent(
            name: 'PageView',
            id: 'Test:'.Str::uuid(),
            occurredAt: now()->getTimestamp(),
            sourceUrl: $sourceUrl,
            userData: [
                'client_user_agent' => 'ProDeals Meta Conversions deployment verification',
                'em' => [$parameterBuilder->normalizedAndHashedPii('meta-test@prodeals.lk', MetaParameterBuilderService::PII_EMAIL)],
                'external_id' => [$parameterBuilder->normalizedAndHashedPii('prodeals-deployment-verification', MetaParameterBuilderService::PII_EXTERNAL_ID)],
            ],
            customData: ['content_name' => 'Deployment verification'],
        ), $testEventCode);

        $this->components->info('Meta accepted the synthetic test event.');

        return self::SUCCESS;
    }
}

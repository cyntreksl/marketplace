<?php

namespace App\Console\Commands;

use App\Services\MetaConversionsService;
use App\Support\MetaConversionEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('meta:conversions:test {--test-event-code= : Temporary code from Meta Events Manager, or - to read it from stdin}')]
#[Description('Send a synthetic ViewContent event to Meta Events Manager')]
class TestMetaConversionsCommand extends Command
{
    public function handle(MetaConversionsService $conversions): int
    {
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
            name: 'ViewContent',
            id: 'Test:'.Str::uuid(),
            occurredAt: now()->getTimestamp(),
            sourceUrl: $sourceUrl,
            userData: [
                'client_user_agent' => 'ProDeals Meta Conversions deployment verification',
                'em' => [hash('sha256', 'meta-test@prodeals.lk')],
                'external_id' => [hash('sha256', 'prodeals-deployment-verification')],
            ],
            customData: [
                'currency' => 'LKR',
                'value' => '1.00',
                'content_ids' => ['deployment-test'],
                'content_type' => 'product',
                'content_name' => 'Deployment verification',
                'contents' => [['id' => 'deployment-test', 'quantity' => 1, 'item_price' => '1.00']],
            ],
        ), $testEventCode);

        $this->components->info('Meta accepted the synthetic test event.');

        return self::SUCCESS;
    }
}

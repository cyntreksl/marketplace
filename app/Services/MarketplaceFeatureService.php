<?php

namespace App\Services;

use App\Contracts\Repositories\MarketplaceSettingRepository;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarketplaceFeatureService
{
    public function __construct(
        private readonly MarketplaceSettingRepository $settings,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @param array{product: bool, seller: bool} $flags */
    public function updateReviewFlags(User $admin, array $flags): void
    {
        DB::transaction(function () use ($admin, $flags): void {
            foreach ($flags as $name => $enabled) {
                $key = "reviews.{$name}.enabled";
                $setting = $this->settings->update($key, $enabled, 'reviews', $admin->id);

                $this->auditLogs->record(
                    $admin,
                    'marketplace.feature_flag_updated',
                    $setting,
                    after: ['key' => $key, 'value' => $enabled],
                );
            }
        }, attempts: 3);
    }
}

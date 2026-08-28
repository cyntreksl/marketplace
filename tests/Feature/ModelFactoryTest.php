<?php

use App\Models\AuditLog;
use App\Models\ListingMedia;
use App\Models\MarketplaceSetting;
use App\Models\PayoutRequest;
use App\Models\SellerLedgerEntry;

test('models with factory annotations have usable factories', function () {
    $models = [
        AuditLog::factory()->create(),
        ListingMedia::factory()->create(),
        MarketplaceSetting::factory()->create(),
        PayoutRequest::factory()->create(),
        SellerLedgerEntry::factory()->create(),
    ];

    foreach ($models as $model) {
        $this->assertModelExists($model);
    }
});

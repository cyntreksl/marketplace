<?php

namespace App\Services;

use App\Models\PayoutRequest;
use App\Models\SellerProfile;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SettlementService
{
    public function __construct(
        private readonly MarketplaceSettingsService $settings,
        private readonly SellerLedgerService $ledger,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function requestPayout(User $seller, SellerProfile $profile, string $amount): PayoutRequest
    {
        return DB::transaction(function () use ($seller, $profile, $amount): PayoutRequest {
            $available = BigDecimal::of($this->ledger->availableBalance($profile));
            $requested = BigDecimal::of($amount);
            $minimum = BigDecimal::of($this->settings->integer('settlement.minimum_payout_amount', 5000));

            if ($requested->isLessThan($minimum)) {
                throw ValidationException::withMessages(['amount' => 'The requested amount is below the payout minimum.']);
            }

            if ($requested->isGreaterThan($available)) {
                throw ValidationException::withMessages(['amount' => 'The requested amount exceeds your available balance.']);
            }

            $payout = PayoutRequest::query()->create(['seller_profile_id' => $profile->id, 'amount' => $amount, 'status' => 'pending']);
            $this->auditLogs->record($seller, 'payout.requested', $payout, after: $payout->getAttributes());

            return $payout;
        });
    }
}

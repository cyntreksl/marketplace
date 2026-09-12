<?php

namespace App\Services;

use App\Contracts\Repositories\OrderOperationsRepository;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\BuyerOrderCancelledNotification;
use App\Notifications\CancellationRefundCompletedNotification;
use App\RefundStatus;
use App\SellerOrderStatus;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerOrderCancellationService
{
    public function __construct(
        private readonly OrderOperationsRepository $orders,
        private readonly PaymentRefundStatusService $paymentStatuses,
        private readonly AuditLogService $auditLogs,
        private readonly OrderCustomerNotificationService $customerNotifications,
    ) {}

    public function cancel(User $actor, int $sellerOrderId, string $reason): SellerOrder
    {
        $notify = false;
        $refundPending = false;

        $sellerOrder = DB::transaction(function () use ($actor, $sellerOrderId, $reason, &$notify, &$refundPending): SellerOrder {
            $sellerOrder = $this->orders->lockSellerOrder($sellerOrderId) ?? abort(404);
            Gate::forUser($actor)->authorize('cancel', $sellerOrder);

            if ($sellerOrder->status === SellerOrderStatus::Cancelled->value) {
                return $sellerOrder;
            }

            if ($sellerOrder->customerOrder->auction_offer_id !== null) {
                throw ValidationException::withMessages(['order' => 'Auction orders cannot be cancelled through the seller order workflow.']);
            }

            if ($sellerOrder->status !== SellerOrderStatus::Paid->value) {
                throw ValidationException::withMessages(['order' => 'Only a new paid order can be cancelled.']);
            }

            $before = $sellerOrder->getAttributes();
            $this->orders->releaseInventory($sellerOrder);
            $sellerOrder->forceFill([
                'status' => SellerOrderStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
            ]);
            $sellerOrder = $this->orders->saveSellerOrder($sellerOrder);

            $customerOrder = $sellerOrder->customerOrder;
            $allCancelled = $customerOrder->sellerOrders->every(
                fn (SellerOrder $package): bool => $package->id === $sellerOrder->id
                    || $package->status === SellerOrderStatus::Cancelled->value,
            );
            if ($allCancelled) {
                $customerOrder->forceFill(['status' => 'cancelled']);
                $this->orders->saveCustomerOrder($customerOrder);
            }

            $payment = $customerOrder->payments->first();
            if ($payment !== null) {
                $payment = $this->orders->lockPayment($payment->id);
                if ($payment->method === 'cod' && $allCancelled) {
                    $payment->forceFill(['status' => 'cancelled']);
                    $this->orders->savePayment($payment);
                } elseif ($payment->method !== 'cod' && in_array($payment->status, ['paid', 'partially_refunded'], true)) {
                    $refundPending = true;
                    $this->orders->createRefund([
                        'seller_order_id' => $sellerOrder->id,
                        'payment_id' => $payment->id,
                        'method' => $payment->method,
                        'amount' => null,
                        'status' => RefundStatus::Pending,
                        'idempotency_key' => (string) Str::uuid(),
                    ]);
                }
            }

            $this->auditLogs->record($actor, 'seller_order.cancelled', $sellerOrder, $before, $sellerOrder->getAttributes(), $reason);
            $notify = true;

            return $sellerOrder->load(['customerOrder.buyer', 'sellerProfile', 'refund']);
        }, attempts: 3);

        if ($notify) {
            $this->customerNotifications->notify($sellerOrder->customerOrder, new BuyerOrderCancelledNotification(
                customerOrderNumber: $sellerOrder->customerOrder->number,
                sellerOrderNumber: $sellerOrder->number,
                sellerName: $sellerOrder->sellerProfile->store_name ?? 'Marketplace seller',
                reason: $reason,
                refundPending: $refundPending,
                recipientName: $this->customerNotifications->recipientName($sellerOrder->customerOrder),
                actionUrl: $this->customerActionUrl($sellerOrder),
            ));
        }

        return $sellerOrder;
    }

    public function completeRefund(User $operator, int $sellerOrderId, string $amount, string $reference): Refund
    {
        $refund = DB::transaction(function () use ($operator, $sellerOrderId, $amount, $reference): Refund {
            $sellerOrder = $this->orders->lockSellerOrder($sellerOrderId) ?? abort(404);
            $refund = $sellerOrder->refund;

            if ($sellerOrder->status !== SellerOrderStatus::Cancelled->value || $refund === null || $refund->status !== RefundStatus::Pending) {
                throw ValidationException::withMessages(['refund' => 'This package does not have a pending cancellation refund.']);
            }

            $payment = $refund->payment_id === null ? null : $this->orders->lockPayment($refund->payment_id);
            if ($payment === null) {
                throw ValidationException::withMessages(['refund' => 'The original payment could not be found.']);
            }

            $enteredAmount = BigDecimal::of($amount)->toScale(2, RoundingMode::Unnecessary);
            $refundableCeiling = $this->refundableCeiling($sellerOrder);
            $alreadyRefunded = $this->successfulRefundAmount($payment);
            $remainingPayment = BigDecimal::of($payment->amount)->minus($alreadyRefunded);

            if ($enteredAmount->isGreaterThan($refundableCeiling) || $enteredAmount->isGreaterThan($remainingPayment)) {
                throw ValidationException::withMessages(['amount' => 'The amount exceeds the package or payment refundable balance.']);
            }

            $before = $refund->getAttributes();
            $refund->forceFill([
                'amount' => (string) $enteredAmount,
                'status' => RefundStatus::Succeeded,
                'manual_reference' => $reference,
                'processed_by' => $operator->id,
                'completed_at' => now(),
            ]);
            $refund = $this->orders->saveRefund($refund);
            $this->paymentStatuses->recalculate($payment);
            $this->auditLogs->record($operator, 'refund.cancellation_completed_manually', $refund, $before, $refund->getAttributes(), $reference);

            return $refund->load(['sellerOrder.customerOrder.buyer']);
        }, attempts: 3);

        $sellerOrder = $refund->sellerOrder;
        if ($sellerOrder?->customerOrder !== null && $refund->amount !== null) {
            $this->customerNotifications->notify($sellerOrder->customerOrder, new CancellationRefundCompletedNotification(
                customerOrderNumber: $sellerOrder->customerOrder->number,
                sellerOrderNumber: $sellerOrder->number,
                amount: $refund->amount,
                reference: $reference,
                recipientName: $this->customerNotifications->recipientName($sellerOrder->customerOrder),
                actionUrl: $this->customerActionUrl($sellerOrder),
            ));
        }

        return $refund;
    }

    private function refundableCeiling(SellerOrder $sellerOrder): BigDecimal
    {
        $ceiling = BigDecimal::of($sellerOrder->subtotal)->plus($sellerOrder->shipping_charge);
        $allCancelled = $sellerOrder->customerOrder->sellerOrders->every(
            fn (SellerOrder $package): bool => $package->status === SellerOrderStatus::Cancelled->value,
        );

        if (! $allCancelled) {
            return $ceiling;
        }

        $allocatedShipping = $sellerOrder->customerOrder->sellerOrders->reduce(
            fn (BigDecimal $total, SellerOrder $package): BigDecimal => $total->plus($package->shipping_charge),
            BigDecimal::zero(),
        );
        $unallocatedShipping = BigDecimal::of($sellerOrder->customerOrder->shipping_total)->minus($allocatedShipping);

        return $unallocatedShipping->isPositive() ? $ceiling->plus($unallocatedShipping) : $ceiling;
    }

    private function customerActionUrl(SellerOrder $sellerOrder): string
    {
        return $sellerOrder->customerOrder->buyer_id === null
            ? route('order-tracking.index')
            : route('buyer.orders.show', $sellerOrder->customerOrder->number);
    }

    private function successfulRefundAmount(Payment $payment): BigDecimal
    {
        return $payment->refunds
            ->where('status', RefundStatus::Succeeded)
            ->reduce(
                fn (BigDecimal $total, Refund $refund): BigDecimal => $total->plus($refund->amount ?? '0'),
                BigDecimal::zero(),
            );
    }
}

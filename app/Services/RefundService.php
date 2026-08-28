<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\Repositories\RefundRepository;
use App\Models\Refund;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\RefundOutcomeNotification;
use App\RefundStatus;
use App\ReturnStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefundService
{
    public function __construct(
        private readonly RefundRepository $refunds,
        private readonly PaymentGateway $gateway,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function operationsQueue(): LengthAwarePaginator
    {
        return $this->refunds->operationsQueue()->through(
            fn (ReturnRequest $returnRequest): array => $this->serializeOperationsRequest($returnRequest)
        );
    }

    /** @return array<string, mixed> */
    private function serializeOperationsRequest(ReturnRequest $returnRequest): array
    {
        return [
            'id' => $returnRequest->id,
            'status' => $returnRequest->status->value,
            'quantity' => $returnRequest->quantity,
            'refund_amount' => $returnRequest->refund_amount,
            'refund_ready_at' => $returnRequest->refund_ready_at?->toIso8601String(),
            'seller_response' => $returnRequest->resolution_reason,
            'item' => [
                'title' => $returnRequest->orderItem->title,
                'seller_order_number' => $returnRequest->orderItem->sellerOrder->number,
                'seller_name' => $returnRequest->orderItem->sellerOrder->sellerProfile->store_name,
            ],
            'buyer' => ['name' => $returnRequest->buyer->name, 'email' => $returnRequest->buyer->email],
            'refund' => $returnRequest->refund === null ? null : [
                'id' => $returnRequest->refund->id,
                'method' => $returnRequest->refund->method,
                'status' => $returnRequest->refund->status->value,
                'amount' => $returnRequest->refund->amount,
                'provider_reference' => $returnRequest->refund->provider_reference,
                'manual_reference' => $returnRequest->refund->manual_reference,
                'failure_details' => $returnRequest->refund->failure_details,
                'completed_at' => $returnRequest->refund->completed_at?->toIso8601String(),
            ],
        ];
    }

    public function markReady(User $operator, int $returnRequestId): Refund
    {
        return DB::transaction(function () use ($operator, $returnRequestId): Refund {
            $returnRequest = $this->refunds->lockRequest($returnRequestId);
            $this->assertReturnStatus($returnRequest, [ReturnStatus::Approved]);

            if ($returnRequest->refund !== null) {
                throw ValidationException::withMessages(['refund' => 'A refund record already exists for this return.']);
            }

            $payment = $this->refunds->paymentFor($returnRequest);

            if ($payment === null || ! in_array($payment->method, ['stripe', 'bank_transfer', 'cod'], true)) {
                throw ValidationException::withMessages(['refund' => 'No supported order payment was found for this return.']);
            }

            $before = $returnRequest->getAttributes();
            $refund = $this->refunds->createRefund([
                'return_request_id' => $returnRequest->id,
                'payment_id' => $payment->id,
                'method' => $payment->method,
                'amount' => $returnRequest->refund_amount,
                'status' => RefundStatus::Pending,
                'idempotency_key' => (string) Str::uuid(),
                'processed_by' => $operator->id,
            ]);
            $returnRequest->forceFill(['status' => ReturnStatus::RefundPending, 'refund_ready_at' => now()]);
            $this->refunds->saveReturn($returnRequest);
            $this->auditLogs->record($operator, 'return_request.refund_ready', $returnRequest, $before, $returnRequest->getAttributes());
            $this->auditLogs->record($operator, 'refund.created', $refund, null, $refund->getAttributes());

            return $refund;
        });
    }

    public function processCard(User $operator, int $returnRequestId): Refund
    {
        [$refund, $payment] = DB::transaction(function () use ($operator, $returnRequestId): array {
            $returnRequest = $this->refunds->lockRequest($returnRequestId);
            $this->assertReturnStatus($returnRequest, [ReturnStatus::RefundPending, ReturnStatus::RefundFailed]);
            $refund = $returnRequest->refund;

            if ($refund === null || $refund->method !== 'stripe' || $refund->payment === null) {
                throw ValidationException::withMessages(['refund' => 'This return does not have a card refund ready to process.']);
            }

            $payment = $refund->payment;
            $refund->forceFill([
                'status' => RefundStatus::Processing,
                'failure_details' => null,
                'processed_by' => $operator->id,
            ]);
            $refund = $this->refunds->saveRefund($refund);
            $returnRequest->forceFill(['status' => ReturnStatus::RefundPending]);
            $this->refunds->saveReturn($returnRequest);

            return [$refund, $payment];
        });

        try {
            $result = $this->gateway->refund($payment, $refund->amount, $refund->idempotency_key);

            return $this->persistGatewayResult($operator, $returnRequestId, $result);
        } catch (Throwable $exception) {
            return $this->persistGatewayFailure($operator, $returnRequestId, $exception);
        }
    }

    public function completeManual(User $operator, int $returnRequestId, string $reference): Refund
    {
        $refund = DB::transaction(function () use ($operator, $returnRequestId, $reference): Refund {
            $returnRequest = $this->refunds->lockRequest($returnRequestId);
            $this->assertReturnStatus($returnRequest, [ReturnStatus::RefundPending, ReturnStatus::RefundFailed]);
            $refund = $returnRequest->refund;

            if ($refund === null || ! in_array($refund->method, ['bank_transfer', 'cod'], true)) {
                throw ValidationException::withMessages(['refund' => 'This return does not have a manual refund ready to complete.']);
            }

            $before = $refund->getAttributes();
            $refund->forceFill([
                'status' => RefundStatus::Succeeded,
                'manual_reference' => $reference,
                'failure_details' => null,
                'processed_by' => $operator->id,
                'completed_at' => now(),
            ]);
            $refund = $this->refunds->saveRefund($refund);
            $returnRequest->forceFill(['status' => ReturnStatus::Refunded, 'resolved_at' => now()]);
            $this->refunds->saveReturn($returnRequest);
            $this->auditLogs->record($operator, 'refund.completed_manually', $refund, $before, $refund->getAttributes(), $reference);

            return $refund;
        });

        $this->notifyOutcome($refund, 'succeeded');

        return $refund;
    }

    /** @param array{reference: string, status: string} $result */
    private function persistGatewayResult(User $operator, int $returnRequestId, array $result): Refund
    {
        $refund = DB::transaction(function () use ($operator, $returnRequestId, $result): Refund {
            $returnRequest = $this->refunds->lockRequest($returnRequestId);
            $refund = $returnRequest?->refund;
            abort_if($returnRequest === null || $refund === null, 404);
            $before = $refund->getAttributes();
            $status = RefundStatus::from($result['status']);

            $refund->forceFill([
                'status' => $status,
                'provider_reference' => $result['reference'],
                'failure_details' => $status === RefundStatus::Failed ? 'The payment provider reported that the refund failed.' : null,
                'processed_by' => $operator->id,
                'completed_at' => $status === RefundStatus::Succeeded ? now() : null,
            ]);
            $refund = $this->refunds->saveRefund($refund);
            $returnRequest->forceFill([
                'status' => match ($status) {
                    RefundStatus::Succeeded => ReturnStatus::Refunded,
                    RefundStatus::Failed => ReturnStatus::RefundFailed,
                    default => ReturnStatus::RefundPending,
                },
                'resolved_at' => $status === RefundStatus::Succeeded ? now() : $returnRequest->resolved_at,
            ]);
            $this->refunds->saveReturn($returnRequest);
            $this->auditLogs->record($operator, 'refund.gateway_result', $refund, $before, $refund->getAttributes());

            return $refund;
        });

        if (in_array($refund->status, [RefundStatus::Succeeded, RefundStatus::Failed], true)) {
            $this->notifyOutcome($refund, $refund->status->value);
        }

        return $refund;
    }

    private function persistGatewayFailure(User $operator, int $returnRequestId, Throwable $exception): Refund
    {
        $refund = DB::transaction(function () use ($operator, $returnRequestId, $exception): Refund {
            $returnRequest = $this->refunds->lockRequest($returnRequestId);
            $refund = $returnRequest?->refund;
            abort_if($returnRequest === null || $refund === null, 404);
            $before = $refund->getAttributes();
            $refund->forceFill([
                'status' => RefundStatus::Failed,
                'failure_details' => Str::limit($exception->getMessage(), 2000, ''),
                'processed_by' => $operator->id,
            ]);
            $refund = $this->refunds->saveRefund($refund);
            $returnRequest->forceFill(['status' => ReturnStatus::RefundFailed]);
            $this->refunds->saveReturn($returnRequest);
            $this->auditLogs->record($operator, 'refund.gateway_failed', $refund, $before, $refund->getAttributes(), $refund->failure_details);

            return $refund;
        });

        $this->notifyOutcome($refund, 'failed');

        return $refund;
    }

    /** @param array<int, ReturnStatus> $statuses */
    private function assertReturnStatus(?ReturnRequest $returnRequest, array $statuses): void
    {
        abort_if($returnRequest === null, 404);

        if (! in_array($returnRequest->status, $statuses, true)) {
            throw ValidationException::withMessages(['refund' => 'This return is not in a valid state for that refund action.']);
        }
    }

    private function notifyOutcome(Refund $refund, string $status): void
    {
        $refund = $this->refunds->withContext($refund);
        $refund->returnRequest->buyer->notify(new RefundOutcomeNotification(
            $refund->return_request_id,
            $refund->returnRequest->orderItem->title,
            $refund->amount,
            $status,
            $refund->failure_details,
        ));
        $this->refunds->operationsUsers()->each(fn (User $operator) => $operator->notify(new RefundOutcomeNotification(
            $refund->return_request_id,
            $refund->returnRequest->orderItem->title,
            $refund->amount,
            $status,
            $refund->failure_details,
            true,
        )));
    }
}

<?php

namespace App\Services;

use App\Contracts\CourierAdapter;
use App\Contracts\Repositories\ReturnRequestRepository;
use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Models\User;
use App\ReturnReason;
use App\ReturnStatus;
use Brick\Math\BigDecimal;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReturnWorkflowService
{
    public function __construct(
        private readonly ReturnRequestRepository $returns,
        private readonly CourierAdapter $couriers,
        private readonly AuditLogService $auditLogs,
    ) {}

    /** @return LengthAwarePaginator<int, non-empty-array<string, mixed>> */
    public function buyerItems(User $buyer): LengthAwarePaginator
    {
        return $this->returns->buyerItems($buyer)
            ->through(fn (OrderItem $item): array => $this->serializeBuyerItem($item));
    }

    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function buyerRequests(User $buyer): LengthAwarePaginator
    {
        return $this->returns->buyerRequests($buyer)->through(fn (ReturnRequest $returnRequest): array => $this->serializeRequest($returnRequest));
    }

    /** @return LengthAwarePaginator<int, non-empty-array<string, mixed>> */
    public function sellerRequests(User $seller): LengthAwarePaginator
    {
        return $this->returns->sellerRequests($seller)->through(fn (ReturnRequest $returnRequest): array => [
            ...$this->serializeRequest($returnRequest),
            'buyer' => [
                'name' => $returnRequest->buyer->name,
                'email' => $returnRequest->buyer->email,
            ],
        ]);
    }

    /**
     * @param  array{order_item_id: int, quantity: int, reason: string, description: string, evidence?: array<int, UploadedFile>}  $data
     */
    public function requestReturn(User $buyer, array $data): ReturnRequest
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($buyer, $data, &$storedPaths): ReturnRequest {
                $item = $this->returns->lockOrderItemForBuyer($data['order_item_id'], $buyer);

                if ($item === null) {
                    throw ValidationException::withMessages(['order_item_id' => 'This purchased item could not be found.']);
                }

                $sellerOrder = $item->sellerOrder;
                $expiresAt = $sellerOrder->delivered_at?->addDays(7);

                if ($sellerOrder->status !== 'completed' || $expiresAt === null) {
                    throw ValidationException::withMessages(['order_item_id' => 'Returns open only after the seller confirms delivery.']);
                }

                if (now()->greaterThan($expiresAt)) {
                    throw ValidationException::withMessages(['order_item_id' => 'The seven-day return window has closed.']);
                }

                $remainingQuantity = $item->quantity - $this->returns->claimedQuantity($item->id);

                if ($data['quantity'] > $remainingQuantity) {
                    throw ValidationException::withMessages(['quantity' => 'The requested quantity exceeds the quantity still available to return.']);
                }

                $returnRequest = $this->returns->create([
                    'order_item_id' => $item->id,
                    'buyer_id' => $buyer->id,
                    'quantity' => $data['quantity'],
                    'eligibility_expires_at' => $expiresAt,
                    'refund_amount' => (string) BigDecimal::of((string) $item->unit_price)->multipliedBy($data['quantity']),
                    'reason' => ReturnReason::from($data['reason']),
                    'status' => ReturnStatus::Requested,
                    'description' => $data['description'],
                    'evidence' => [],
                ]);

                $evidence = [];

                foreach ($data['evidence'] ?? [] as $file) {
                    $path = $file->store("return-evidence/{$returnRequest->id}", 'local');
                    $storedPaths[] = $path;
                    $evidence[] = [
                        'path' => $path,
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ];
                }

                if ($evidence !== []) {
                    $returnRequest->forceFill(['evidence' => $evidence]);
                    $returnRequest = $this->returns->save($returnRequest);
                }

                $this->auditLogs->record($buyer, 'return_request.created', $returnRequest, null, $returnRequest->getAttributes());

                return $returnRequest;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    public function decide(User $seller, int $returnRequestId, ReturnStatus $decision, string $reason): ReturnRequest
    {
        if (! in_array($decision, [ReturnStatus::Approved, ReturnStatus::Rejected], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approve or reject.']);
        }

        return DB::transaction(function () use ($seller, $returnRequestId, $decision, $reason): ReturnRequest {
            $returnRequest = $this->returns->lockRequestForSeller($returnRequestId, $seller);

            if ($returnRequest === null) {
                abort(403);
            }

            if ($returnRequest->status !== ReturnStatus::Requested) {
                throw ValidationException::withMessages(['decision' => 'This return request already has a final seller decision.']);
            }

            $before = $returnRequest->getAttributes();
            $returnRequest->forceFill([
                'status' => $decision,
                'resolution_reason' => $reason,
                'resolved_by' => $seller->id,
                'resolved_at' => now(),
                'seller_responded_at' => now(),
            ]);
            $returnRequest = $this->returns->save($returnRequest);
            $this->auditLogs->record($seller, "return_request.{$decision->value}", $returnRequest, $before, $returnRequest->getAttributes(), $reason);

            return $returnRequest;
        });
    }

    public function confirmDelivery(User $seller, int $sellerOrderId): SellerOrder
    {
        return DB::transaction(function () use ($seller, $sellerOrderId): SellerOrder {
            $sellerOrder = $this->returns->lockSellerOrderForSeller($sellerOrderId, $seller);

            if ($sellerOrder === null) {
                abort(403);
            }

            if ($sellerOrder->status !== 'ready_to_ship') {
                throw ValidationException::withMessages(['order' => 'Only orders that are ready to ship can be marked delivered.']);
            }

            $before = $sellerOrder->getAttributes();
            $deliveredAt = now();
            $sellerOrder->forceFill([
                'status' => 'completed',
                'completed_at' => $deliveredAt,
                'delivered_at' => $deliveredAt,
            ])->save();

            if ($sellerOrder->shipment !== null) {
                $this->couriers->updateStatus($sellerOrder->shipment, 'delivered');
            }

            $this->auditLogs->record($seller, 'seller_order.delivered', $sellerOrder, $before, $sellerOrder->getAttributes());

            return $sellerOrder->refresh();
        });
    }

    /** @return non-empty-array<string, mixed> */
    private function serializeBuyerItem(OrderItem $item): array
    {
        $deliveredAt = $item->sellerOrder->delivered_at;
        $expiresAt = $deliveredAt?->addDays(7);
        $remainingQuantity = max(0, $item->quantity - (int) $item->getAttribute('claimed_quantity'));

        return [
            'id' => $item->id,
            'title' => $item->title,
            'purchased_quantity' => $item->quantity,
            'remaining_quantity' => $remainingQuantity,
            'unit_price' => $item->unit_price,
            'seller_order_number' => $item->sellerOrder->number,
            'seller_name' => $item->sellerOrder->sellerProfile->store_name,
            'delivered_at' => $deliveredAt?->toIso8601String(),
            'eligibility_expires_at' => $expiresAt?->toIso8601String(),
            'is_eligible' => $item->sellerOrder->status === 'completed'
                && $expiresAt !== null
                && now()->lessThanOrEqualTo($expiresAt)
                && $remainingQuantity > 0,
        ];
    }

    /** @return array<string, mixed> */
    private function serializeRequest(ReturnRequest $returnRequest): array
    {
        return [
            'id' => $returnRequest->id,
            'status' => $returnRequest->status->value,
            'reason' => $returnRequest->reason->value,
            'reason_label' => $returnRequest->reason->label(),
            'description' => $returnRequest->description,
            'quantity' => $returnRequest->quantity,
            'refund_amount' => $returnRequest->refund_amount,
            'resolution_reason' => $returnRequest->resolution_reason,
            'eligibility_expires_at' => $returnRequest->eligibility_expires_at?->toIso8601String(),
            'seller_responded_at' => $returnRequest->seller_responded_at?->toIso8601String(),
            'created_at' => $returnRequest->created_at?->toIso8601String(),
            'evidence' => collect($returnRequest->evidence ?? [])->map(fn (array $file, int $index): array => ['index' => $index, 'name' => $file['name']])->all(),
            'item' => [
                'title' => $returnRequest->orderItem->title,
                'seller_order_number' => $returnRequest->orderItem->sellerOrder->number,
                'seller_name' => $returnRequest->orderItem->sellerOrder->sellerProfile->store_name ?? null,
            ],
            'refund' => $returnRequest->refund === null ? null : [
                'status' => $returnRequest->refund->status->value,
                'method' => $returnRequest->refund->method,
                'amount' => $returnRequest->refund->amount,
                'completed_at' => $returnRequest->refund->completed_at?->toIso8601String(),
            ],
        ];
    }
}

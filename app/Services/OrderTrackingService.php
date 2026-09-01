<?php

namespace App\Services;

use App\Contracts\Repositories\OrderTrackingRepository;
use Illuminate\Validation\ValidationException;

class OrderTrackingService
{
    public function __construct(private readonly OrderTrackingRepository $orders) {}

    /** @return array<string, mixed> */
    public function lookup(string $number, string $email): array
    {
        $order = $this->orders->findForPublicLookup(
            mb_strtoupper(trim($number)),
            mb_strtolower(trim($email)),
        );

        if ($order === null) {
            throw ValidationException::withMessages(['order' => 'We could not find an order matching those details.']);
        }

        return [
            'number' => $order->number,
            'status' => $order->status,
            'placedAt' => $order->created_at->toIso8601String(),
            'shipments' => $order->sellerOrders->map(function ($sellerOrder): array {
                $shipment = $sellerOrder->shipment;

                return [
                    'number' => $sellerOrder->number,
                    'seller' => $sellerOrder->sellerProfile->store_name,
                    'status' => $sellerOrder->status,
                    'readyAt' => $sellerOrder->ready_to_ship_at?->toIso8601String(),
                    'deliveredAt' => $sellerOrder->delivered_at?->toIso8601String(),
                    'courier' => $shipment?->courier_name,
                    'trackingNumber' => $shipment?->tracking_number,
                    'shipmentStatus' => $shipment?->status,
                    'history' => $shipment === null ? [] : ($shipment->status_history ?? []),
                ];
            })->values(),
        ];
    }
}

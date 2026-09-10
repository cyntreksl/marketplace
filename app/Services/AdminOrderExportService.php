<?php

namespace App\Services;

use App\Contracts\Repositories\OrderOperationsRepository;
use App\Models\CustomerOrder;
use App\Models\SellerOrder;
use Illuminate\Support\Collection;

class AdminOrderExportService
{
    /** @var array<string, string> */
    private const COLUMNS = [
        'order_number' => 'Order number',
        'order_status' => 'Order status',
        'buyer_name' => 'Buyer name',
        'buyer_email' => 'Buyer email',
        'subtotal' => 'Subtotal',
        'shipping_total' => 'Shipping total',
        'total' => 'Total',
        'payment_methods' => 'Payment methods',
        'payment_statuses' => 'Payment statuses',
        'paid_at' => 'Paid date',
        'package_numbers' => 'Package numbers',
        'seller_stores' => 'Seller stores',
        'package_statuses' => 'Package statuses',
        'product_titles' => 'Product titles',
        'line_item_count' => 'Line-item count',
        'total_quantity' => 'Total quantity',
        'shipping_recipient' => 'Shipping recipient',
        'shipping_phone' => 'Shipping phone',
        'shipping_city' => 'Shipping city',
        'created_at' => 'Created date',
        'updated_at' => 'Updated date',
    ];

    /** @var list<string> */
    private const DEFAULT_COLUMNS = [
        'order_number', 'order_status', 'buyer_name', 'buyer_email', 'total', 'payment_methods', 'payment_statuses', 'seller_stores', 'created_at',
    ];

    public function __construct(
        private readonly OrderOperationsRepository $orders,
        private readonly AdminXlsxWriter $writer,
    ) {}

    /** @return list<string> */
    public static function columnKeys(): array
    {
        return array_keys(self::COLUMNS);
    }

    /** @return list<array{key: string, label: string, defaultSelected: bool}> */
    public static function columnOptions(): array
    {
        $options = [];

        foreach (self::COLUMNS as $key => $label) {
            $options[] = ['key' => $key, 'label' => $label, 'defaultSelected' => in_array($key, self::DEFAULT_COLUMNS, true)];
        }

        return $options;
    }

    /** @param array<string, mixed> $filters
     * @param  list<string>  $columns
     */
    public function createTemporaryFile(array $filters, array $columns): string
    {
        $headers = array_map(fn (string $column): string => self::COLUMNS[$column], $columns);

        return $this->writer->createTemporaryFile('Orders', $headers, $this->rows($filters, $columns));
    }

    /** @param array<string, mixed> $filters
     * @param  list<string>  $columns
     * @return \Generator<int, list<mixed>>
     */
    private function rows(array $filters, array $columns): \Generator
    {
        foreach ($this->orders->lazyForAdminExport($filters) as $order) {
            yield array_map(fn (string $column): mixed => $this->value($order, $column), $columns);
        }
    }

    private function value(CustomerOrder $order, string $column): mixed
    {
        $payments = $order->payments->sortBy('id');
        $packages = $order->sellerOrders->sortBy('id');
        $items = $packages->flatMap(fn (SellerOrder $sellerOrder): Collection => $sellerOrder->items->sortBy('id'));

        return match ($column) {
            'order_number' => $order->number,
            'order_status' => $order->status,
            'buyer_name' => $order->buyer?->name,
            'buyer_email' => $order->buyer?->email,
            'subtotal' => (float) $order->subtotal,
            'shipping_total' => (float) $order->shipping_total,
            'total' => (float) $order->total,
            'payment_methods' => $payments->pluck('method')->implode('; '),
            'payment_statuses' => $payments->pluck('status')->implode('; '),
            'paid_at' => $payments->firstWhere('paid_at', '!=', null)?->paid_at,
            'package_numbers' => $packages->pluck('number')->implode('; '),
            'seller_stores' => $packages->map(fn (SellerOrder $sellerOrder): string => (string) $sellerOrder->sellerProfile?->store_name)->implode('; '),
            'package_statuses' => $packages->pluck('status')->implode('; '),
            'product_titles' => $items->pluck('title')->implode('; '),
            'line_item_count' => $items->count(),
            'total_quantity' => (int) $items->sum('quantity'),
            'shipping_recipient' => $order->shipping_address['recipient_name'] ?? null,
            'shipping_phone' => $order->shipping_address['phone'] ?? null,
            'shipping_city' => $order->shipping_address['city'] ?? null,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            default => null,
        };
    }
}

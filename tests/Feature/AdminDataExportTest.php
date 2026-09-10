<?php

use App\Models\CustomerOrder;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Models\ListingVariantOption;
use App\Models\ListingVariantOptionValue;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

function dataExportAdmin(): User
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));

    return $admin;
}

test('product and order indexes apply inclusive created date and payment filters', function () {
    $admin = dataExportAdmin();
    $matchingListing = Listing::factory()->create(['title' => 'Date Match', 'created_at' => '2026-09-10 23:59:59']);
    Listing::factory()->create(['title' => 'Date Miss', 'created_at' => '2026-09-11 00:00:00']);

    $this->actingAs($admin)
        ->get(route('admin.products.index', ['created_from' => '2026-09-10', 'created_to' => '2026-09-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('listings.total', 1)
            ->where('listings.data.0.id', $matchingListing->id)
            ->where('filters.created_to', '2026-09-10'));

    $matchingOrder = CustomerOrder::factory()->create(['number' => 'PRO-INDEX-MATCH', 'created_at' => '2026-09-10 00:00:00']);
    Payment::factory()->for($matchingOrder)->create(['method' => 'cod', 'status' => 'paid']);
    $splitOrder = CustomerOrder::factory()->create(['number' => 'PRO-INDEX-SPLIT', 'created_at' => '2026-09-10 12:00:00']);
    Payment::factory()->for($splitOrder)->create(['method' => 'cod', 'status' => 'pending_collection', 'paid_at' => null]);
    Payment::factory()->for($splitOrder)->create(['method' => 'stripe', 'status' => 'paid']);

    $this->actingAs($admin)
        ->get(route('admin.orders.index', [
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'created_from' => '2026-09-10',
            'created_to' => '2026-09-10',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.number', 'PRO-INDEX-MATCH')
            ->where('filters.payment_method', 'cod')
            ->where('filters.payment_status', 'paid')
            ->has('exportColumns', 21));
});

test('product export includes all filtered pages rather than only the first page', function () {
    Listing::factory()->count(21)->create(['title' => 'Bulk Export Product']);

    $response = $this->actingAs(dataExportAdmin())->get(route('admin.products.export', [
        'search' => 'Bulk Export Product',
        'columns' => ['product_id', 'title'],
    ]))->assertOk();
    $sheet = adminExportSheet($response->baseResponse->getFile()->getPathname());

    expect($sheet['rows'])->toHaveCount(21)
        ->and($sheet['headers'])->toBe(['Product ID', 'Title']);
});

test('product export applies every filter and includes variant internal data in selected columns', function () {
    $admin = dataExportAdmin();
    $seller = SellerProfile::factory()->create(['store_name' => 'Internal Supply']);
    $listing = Listing::factory()->for($seller)->create([
        'title' => 'Filtered Product',
        'product_type' => 'variant',
        'status' => 'approved',
        'supplier_name' => 'Warehouse A',
        'internal_notes' => '=private-note',
        'created_at' => '2026-09-10 15:00:00',
    ]);
    $option = ListingVariantOption::factory()->for($listing)->create(['name' => 'Color', 'position' => 0]);
    $blue = ListingVariantOptionValue::factory()->for($option, 'option')->create(['value' => 'Blue']);
    $activeVariant = ListingVariant::factory()->for($listing)->create([
        'sku' => 'BLUE-001',
        'cost_price' => '125.50',
        'selling_price' => '200.00',
        'stock_quantity' => 8,
        'reserved_quantity' => 3,
        'is_active' => true,
        'position' => 0,
    ]);
    $activeVariant->optionValues()->attach($blue);
    ListingVariant::factory()->for($listing)->create(['sku' => 'INACTIVE-001', 'is_active' => false, 'position' => 1]);
    Listing::factory()->for($seller)->create(['title' => 'Outside Date', 'created_at' => '2026-09-09 23:59:59']);

    $response = $this->actingAs($admin)->get(route('admin.products.export', [
        'search' => 'Filtered Product',
        'status' => 'approved',
        'listing_type' => $listing->listing_type,
        'product_type' => 'variant',
        'condition' => $listing->condition,
        'created_from' => '2026-09-10',
        'created_to' => '2026-09-10',
        'sort' => 'newest',
        'columns' => ['product_id', 'variant_id', 'variant', 'sku', 'cost_price', 'supplier_name', 'internal_notes', 'available_quantity', 'created_at'],
    ]));

    $response->assertOk()->assertDownload()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
    $sheet = adminExportSheet($response->baseResponse->getFile()->getPathname());

    expect($sheet['headers'])->toBe([
        'Product ID', 'Variant ID', 'Variant', 'SKU', 'Cost price', 'Supplier name', 'Internal notes', 'Available quantity', 'Created date',
    ])->and($sheet['rows'])->toHaveCount(1)
        ->and($sheet['rows'][0]['Product ID']['value'])->toBe((string) $listing->id)
        ->and($sheet['rows'][0]['Variant ID']['value'])->toBe((string) $activeVariant->id)
        ->and($sheet['rows'][0]['Variant']['value'])->toBe('Color: Blue')
        ->and($sheet['rows'][0]['SKU']['value'])->toBe('BLUE-001')
        ->and($sheet['rows'][0]['Cost price'])->toMatchArray(['type' => '', 'value' => '125.5'])
        ->and($sheet['rows'][0]['Supplier name']['value'])->toBe('Warehouse A')
        ->and($sheet['rows'][0]['Internal notes'])->toMatchArray(['type' => 'inlineStr', 'value' => '=private-note'])
        ->and($sheet['rows'][0]['Available quantity']['value'])->toBe('5')
        ->and($sheet['rows'][0]['Created date']['style'])->toBe('2')
        ->and(collect($sheet['rows'])->flatten()->contains('INACTIVE-001'))->toBeFalse();
});

test('variant products without active variants remain represented in product exports', function () {
    $listing = Listing::factory()->create([
        'title' => 'Variant Draft',
        'product_type' => 'variant',
        'sku' => 'LISTING-SKU',
        'cost_price' => '20.00',
        'stock_quantity' => 10,
    ]);
    ListingVariant::factory()->for($listing)->create(['is_active' => false]);

    $response = $this->actingAs(dataExportAdmin())->get(route('admin.products.export', [
        'search' => 'Variant Draft',
        'columns' => ['product_id', 'variant_id', 'title', 'sku', 'cost_price', 'stock_quantity'],
    ]))->assertOk();
    $sheet = adminExportSheet($response->baseResponse->getFile()->getPathname());

    expect($sheet['rows'])->toHaveCount(1)
        ->and($sheet['rows'][0]['Product ID']['value'])->toBe((string) $listing->id)
        ->and($sheet['rows'][0]['Variant ID']['value'])->toBe('')
        ->and($sheet['rows'][0]['Title']['value'])->toBe('Variant Draft')
        ->and($sheet['rows'][0]['SKU']['value'])->toBe('')
        ->and($sheet['rows'][0]['Cost price']['value'])->toBe('')
        ->and($sheet['rows'][0]['Stock quantity']['value'])->toBe('');
});

test('simple products use listing values while variants are ordered by position and id', function () {
    $admin = dataExportAdmin();
    $simple = Listing::factory()->create([
        'title' => 'Simple Export Product',
        'product_type' => 'simple',
        'sku' => 'SIMPLE-SKU',
        'cost_price' => '45.25',
        'stock_quantity' => 7,
        'reserved_quantity' => 2,
    ]);
    $variantListing = Listing::factory()->create(['title' => 'Ordered Variant Product', 'product_type' => 'variant']);
    $first = ListingVariant::factory()->for($variantListing)->create(['sku' => 'FIRST', 'position' => 1, 'is_active' => true]);
    $second = ListingVariant::factory()->for($variantListing)->create(['sku' => 'SECOND', 'position' => 2, 'is_active' => true]);

    $simpleResponse = $this->actingAs($admin)->get(route('admin.products.export', [
        'search' => 'Simple Export Product',
        'columns' => ['sku', 'cost_price', 'available_quantity'],
    ]))->assertOk();
    $simpleSheet = adminExportSheet($simpleResponse->baseResponse->getFile()->getPathname());

    expect($simpleSheet['rows'][0]['SKU']['value'])->toBe('SIMPLE-SKU')
        ->and($simpleSheet['rows'][0]['Cost price']['value'])->toBe('45.25')
        ->and($simpleSheet['rows'][0]['Available quantity']['value'])->toBe('5');

    $variantResponse = $this->actingAs($admin)->get(route('admin.products.export', [
        'search' => 'Ordered Variant Product',
        'columns' => ['variant_id', 'sku'],
    ]))->assertOk();
    $variantSheet = adminExportSheet($variantResponse->baseResponse->getFile()->getPathname());

    expect(array_map(
        fn (array $row): string => $row['Variant ID']['value'],
        $variantSheet['rows'],
    ))->toBe([(string) $first->id, (string) $second->id]);
});

test('user export supports grouped roles and keeps spreadsheet-like text inert', function () {
    $buyerRole = Role::factory()->create(['name' => Role::Buyer, 'label' => 'Buyer']);
    $sellerRole = Role::factory()->create(['name' => Role::BusinessSeller, 'label' => 'Business seller']);
    $user = User::factory()->create(['name' => '=SUM(1,1)', 'created_at' => '2026-09-10 01:00:00']);
    $user->roles()->attach([$buyerRole->id, $sellerRole->id]);
    SellerProfile::factory()->for($user)->create(['store_name' => 'Workbook Store']);

    $response = $this->actingAs(dataExportAdmin())->get(route('admin.users.export', [
        'account_type' => 'seller',
        'created_from' => '2026-09-10',
        'created_to' => '2026-09-10',
        'columns' => ['name', 'account_types', 'roles', 'store_name', 'created_at'],
    ]))->assertOk();
    $sheet = adminExportSheet($response->baseResponse->getFile()->getPathname());

    expect($sheet['headers'])->toBe(['Name', 'Account types', 'Exact roles', 'Store name', 'Registered date'])
        ->and($sheet['rows'])->toHaveCount(1)
        ->and($sheet['rows'][0]['Name'])->toMatchArray(['type' => 'inlineStr', 'value' => '=SUM(1,1)'])
        ->and($sheet['rows'][0]['Account types']['value'])->toBe('Seller; Buyer')
        ->and($sheet['rows'][0]['Exact roles']['value'])->toBe('business_seller; buyer')
        ->and($sheet['rows'][0]['Store name']['value'])->toBe('Workbook Store')
        ->and($sheet['rows'][0]['Registered date']['style'])->toBe('2')
        ->and($sheet['headers'])->not->toContain('Password', 'Two factor secret');
});

test('order export requires payment method and status to match the same payment', function () {
    $buyer = User::factory()->create(['name' => 'Matching Buyer', 'email' => 'matching@example.com']);
    $matching = CustomerOrder::factory()->for($buyer, 'buyer')->create([
        'number' => 'PRO-MATCH',
        'shipping_address' => ['recipient_name' => 'Recipient', 'phone' => '0712345678', 'city' => 'Colombo'],
        'created_at' => '2026-09-10 10:00:00',
    ]);
    Payment::factory()->for($matching)->create(['method' => 'cod', 'status' => 'paid', 'paid_at' => '2026-09-10 11:00:00']);
    $firstPackage = SellerOrder::factory()->for($matching)->create(['number' => 'PKG-1']);
    $secondPackage = SellerOrder::factory()->for($matching)->create(['number' => 'PKG-2']);
    OrderItem::factory()->for($firstPackage)->create(['title' => 'First item', 'quantity' => 2]);
    OrderItem::factory()->for($secondPackage)->create(['title' => 'Second item', 'quantity' => 3]);

    $splitMatch = CustomerOrder::factory()->create(['number' => 'PRO-SPLIT', 'created_at' => '2026-09-10 12:00:00']);
    Payment::factory()->for($splitMatch)->create(['method' => 'cod', 'status' => 'pending_collection', 'paid_at' => null]);
    Payment::factory()->for($splitMatch)->create(['method' => 'stripe', 'status' => 'paid']);

    $response = $this->actingAs(dataExportAdmin())->get(route('admin.orders.export', [
        'payment_method' => 'cod',
        'payment_status' => 'paid',
        'created_from' => '2026-09-10',
        'created_to' => '2026-09-10',
        'columns' => ['order_number', 'buyer_email', 'total', 'payment_methods', 'payment_statuses', 'package_numbers', 'product_titles', 'line_item_count', 'total_quantity', 'shipping_city', 'created_at'],
    ]))->assertOk();
    $sheet = adminExportSheet($response->baseResponse->getFile()->getPathname());

    expect($sheet['rows'])->toHaveCount(1)
        ->and($sheet['rows'][0]['Order number']['value'])->toBe('PRO-MATCH')
        ->and($sheet['rows'][0]['Buyer email']['value'])->toBe('matching@example.com')
        ->and($sheet['rows'][0]['Total']['type'])->toBe('')
        ->and($sheet['rows'][0]['Payment methods']['value'])->toBe('cod')
        ->and($sheet['rows'][0]['Payment statuses']['value'])->toBe('paid')
        ->and($sheet['rows'][0]['Package numbers']['value'])->toBe('PKG-1; PKG-2')
        ->and($sheet['rows'][0]['Product titles']['value'])->toBe('First item; Second item')
        ->and($sheet['rows'][0]['Line-item count']['value'])->toBe('2')
        ->and($sheet['rows'][0]['Total quantity']['value'])->toBe('5')
        ->and($sheet['rows'][0]['Shipping city']['value'])->toBe('Colombo')
        ->and($sheet['rows'][0]['Created date']['style'])->toBe('2');
});

test('export requests reject missing duplicate and unsupported columns', function (string $routeName, string $validColumn) {
    $admin = dataExportAdmin();

    $this->actingAs($admin)->get(route($routeName))->assertSessionHasErrors('columns');
    $this->actingAs($admin)->get(route($routeName, ['columns' => [$validColumn, $validColumn]]))->assertSessionHasErrors('columns.1');
    $this->actingAs($admin)->get(route($routeName, ['columns' => ['password']]))->assertSessionHasErrors('columns.0');
})->with([
    'products' => ['admin.products.export', 'title'],
    'users' => ['admin.users.export', 'name'],
    'orders' => ['admin.orders.export', 'order_number'],
]);

test('non administrators cannot download admin data exports', function (string $routeName) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName, ['columns' => ['created_at']]))
        ->assertForbidden();
})->with(['admin.products.export', 'admin.users.export', 'admin.orders.export']);

/**
 * @return array{
 *     headers: list<string>,
 *     rows: list<array<string, array{type: string, style: string, value: string}>>
 * }
 */
function adminExportSheet(string $workbookPath): array
{
    $archive = new ZipArchive;
    expect($archive->open($workbookPath))->toBeTrue();
    $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
    $archive->close();
    expect($worksheet)->toBeString();

    $document = new DOMDocument;
    expect($document->loadXML($worksheet))->toBeTrue();
    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $parsedRows = [];

    foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') ?: [] as $row) {
        $cells = [];

        foreach ($xpath->query('x:c', $row) ?: [] as $cell) {
            $reference = $cell->attributes?->getNamedItem('r')?->nodeValue ?? '';
            $column = preg_replace('/\d+/', '', $reference);
            if ($column === null) {
                continue;
            }
            $index = adminExportColumnIndex($column);
            $type = $cell->attributes?->getNamedItem('t')?->nodeValue ?? '';
            $cells[$index] = [
                'type' => $type,
                'style' => $cell->attributes?->getNamedItem('s')?->nodeValue ?? '',
                'value' => $type === 'inlineStr'
                    ? $xpath->evaluate('string(x:is/x:t)', $cell)
                    : $xpath->evaluate('string(x:v)', $cell),
            ];
        }

        $parsedRows[] = $cells;
    }

    $headers = array_map(fn (array $cell): string => $cell['value'], $parsedRows[0]);
    $rows = [];

    foreach (array_slice($parsedRows, 1) as $parsedRow) {
        $values = [];
        foreach ($headers as $index => $header) {
            $values[$header] = $parsedRow[$index] ?? ['type' => '', 'style' => '', 'value' => ''];
        }
        $rows[] = $values;
    }

    return ['headers' => array_values($headers), 'rows' => $rows];
}

function adminExportColumnIndex(string $column): int
{
    $index = 0;
    foreach (str_split($column) as $letter) {
        $index = ($index * 26) + ord($letter) - 64;
    }

    return $index - 1;
}

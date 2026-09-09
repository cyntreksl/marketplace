<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingMedia;
use App\Models\ListingVariant;
use App\Models\ListingVariantOption;
use App\Models\ListingVariantOptionValue;
use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

test('an operations admin can download the live product catalog in the Meta spreadsheet format', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $seller = SellerProfile::factory()->create(['store_name' => 'Ceylon Supply']);
    $category = Category::factory()->create(['name' => 'Coffee Grinders', 'google_product_category_id' => 499873]);
    $brand = Brand::factory()->create(['name' => 'Kopi']);
    $listing = Listing::factory()->for($seller)->for($category)->for($brand)->create([
        'title' => 'Precision Coffee Grinder',
        'slug' => 'precision-coffee-grinder',
        'short_description' => '<p>Freshly ground coffee at home.</p>',
        'condition' => 'refurbished',
        'gtin' => '4006381333931',
        'price' => '12500.00',
        'sale_price' => '10000.00',
        'stock_quantity' => 5,
        'reserved_quantity' => 2,
    ]);
    ListingMedia::factory()->for($listing)->create([
        'disk' => 'r2',
        'path' => 'listings/grinder.png',
        'type' => 'image',
    ]);
    Listing::factory()->create(['title' => 'Unpublished product', 'status' => 'draft', 'approved_at' => null]);

    $response = $this->actingAs($admin)->get(route('admin.products.meta-catalogue-export'));

    $response->assertOk()
        ->assertDownload()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    expect($response->baseResponse)->toBeInstanceOf(BinaryFileResponse::class);
    $rows = metaCatalogueRows($response->baseResponse->getFile()->getPathname());

    expect($rows)->toHaveCount(3)
        ->and(array_values($rows[1]))->toBe([
            'id', 'title', 'description', 'availability', 'condition', 'link', 'image_link', 'brand',
            'price', 'google_product_category', 'fb_product_category', 'quantity_to_sell_on_facebook',
            'sale_price', 'sale_price_effective_date', 'item_group_id', 'gender', 'color', 'size',
            'age_group', 'material', 'pattern', 'shipping', 'shipping_weight', 'offer_disclaimer',
            'offer_disclaimer_url', 'video[0].url', 'video[0].tag[0]', 'gtin', 'product_tags[0]',
            'product_tags[1]', 'style[0]',
        ])
        ->and($rows[2]['A'])->toBe((string) $listing->id)
        ->and($rows[2]['B'])->toBe('Precision Coffee Grinder')
        ->and($rows[2]['C'])->toBe('Freshly ground coffee at home.')
        ->and($rows[2]['D'])->toBe('in stock')
        ->and($rows[2]['E'])->toBe('used')
        ->and($rows[2]['F'])->toBe(route('listings.show', $listing->slug))
        ->and($rows[2]['G'])->toContain('listings/grinder.png')
        ->and($rows[2]['H'])->toBe('Kopi')
        ->and($rows[2]['I'])->toBe('12500.00 LKR')
        ->and($rows[2]['J'])->toBe('499873')
        ->and($rows[2]['L'])->toBe('3')
        ->and($rows[2]['M'])->toBe('10000.00 LKR')
        ->and($rows[2]['V'])->toBe('LK::Standard:600.00 LKR')
        ->and($rows[2]['AB'])->toBe('4006381333931')
        ->and($rows[2]['AC'])->toBe('Coffee Grinders')
        ->and($rows[2]['AD'])->toBe('Ceylon Supply')
        ->and(collect($rows)->flatten()->contains('Unpublished product'))->toBeFalse();
});

test('non administrators cannot download the Meta catalogue export', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.products.meta-catalogue-export'))
        ->assertForbidden();
});

test('the Meta catalogue export creates one grouped row for each active product variant', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::factory()->create(['name' => Role::Admin, 'label' => 'Administrator']));
    $listing = Listing::factory()->create([
        'title' => 'Cotton Shirt',
        'slug' => 'cotton-shirt',
        'product_type' => 'variant',
    ]);
    ListingMedia::factory()->for($listing)->create(['disk' => 'r2', 'path' => 'listings/shirt.png', 'type' => 'image']);
    $color = ListingVariantOption::factory()->for($listing)->create(['name' => 'Color', 'position' => 0]);
    $blue = ListingVariantOptionValue::factory()->for($color, 'option')->create(['value' => 'Blue']);
    $variant = ListingVariant::factory()->for($listing)->create([
        'selling_price' => '4500.00',
        'market_price' => '5000.00',
        'stock_quantity' => 2,
        'reserved_quantity' => 1,
        'is_active' => true,
    ]);
    $variant->optionValues()->attach($blue);
    ListingVariant::factory()->for($listing)->create(['sku' => 'INACTIVE', 'is_active' => false, 'position' => 1]);

    $response = $this->actingAs($admin)->get(route('admin.products.meta-catalogue-export'))->assertOk();
    $rows = metaCatalogueRows($response->baseResponse->getFile()->getPathname());

    expect($rows)->toHaveCount(3)
        ->and($rows[2]['A'])->toBe((string) $variant->id)
        ->and($rows[2]['B'])->toBe('Cotton Shirt - Blue')
        ->and($rows[2]['I'])->toBe('5000.00 LKR')
        ->and($rows[2]['L'])->toBe('1')
        ->and($rows[2]['M'])->toBe('4500.00 LKR')
        ->and($rows[2]['O'])->toBe((string) $listing->id)
        ->and($rows[2]['Q'])->toBe('Blue')
        ->and(collect($rows)->flatten()->contains('INACTIVE'))->toBeFalse();
});

/** @return list<array<string, string>> */
function metaCatalogueRows(string $workbookPath): array
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
    $rows = [];

    foreach ($xpath->query('/x:worksheet/x:sheetData/x:row') ?: [] as $row) {
        $values = [];
        foreach ($xpath->query('x:c', $row) ?: [] as $cell) {
            $reference = $cell->attributes?->getNamedItem('r')?->nodeValue ?? '';
            $column = preg_replace('/\d+/', '', $reference);
            if ($column !== null) {
                $values[$column] = $xpath->evaluate('string(x:is/x:t)', $cell);
            }
        }
        $rows[] = $values;
    }

    return $rows;
}

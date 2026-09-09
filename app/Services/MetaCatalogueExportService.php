<?php

namespace App\Services;

use App\Contracts\Repositories\ListingRepository;
use App\Models\Listing;
use App\Models\ListingVariant;
use App\Rules\ValidGtin;
use App\Support\SeoText;
use Illuminate\Support\Str;
use RuntimeException;
use XMLWriter;
use ZipArchive;

class MetaCatalogueExportService
{
    /** @var list<string> */
    private const HEADERS = [
        'id', 'title', 'description', 'availability', 'condition', 'link', 'image_link', 'brand',
        'price', 'google_product_category', 'fb_product_category', 'quantity_to_sell_on_facebook',
        'sale_price', 'sale_price_effective_date', 'item_group_id', 'gender', 'color', 'size',
        'age_group', 'material', 'pattern', 'shipping', 'shipping_weight', 'offer_disclaimer',
        'offer_disclaimer_url', 'video[0].url', 'video[0].tag[0]', 'gtin', 'product_tags[0]',
        'product_tags[1]', 'style[0]',
    ];

    /** @var list<string> */
    private const INSTRUCTIONS = [
        "# Required | A unique content ID for the item. Use the item's SKU if you can. Each content ID must appear only once in your catalog. To run dynamic ads this ID must exactly match the content ID for the same item in your Meta Pixel code. Character limit: 100",
        '# Required | A specific and relevant title for the item. See title specifications: https://www.facebook.com/business/help/2104231189874655 Character limit: 200',
        "# Required | A short and relevant description of the item. Include specific or unique product features like material or color. Use plain text and don't enter text in all capital letters. See description specifications: https://www.facebook.com/business/help/2302017289821154 Character limit: 9999",
        '# Required | The current availability of the item. | Supported values: in stock; out of stock',
        '# Required | The current condition of the item. | Supported values: new; used',
        '# Required | The URL of the specific product page where people can buy the item.',
        '# Required | The URL for the main image of your item. Images must be in a supported format (JPG/GIF/PNG) and at least 500 x 500 pixels.',
        '# Required | The brand name of the item. Character limit: 100.',
        "# Optional | The price of the item. Format the price as a number followed by the 3-letter currency code (ISO 4217 standards). Use a period (.) as the decimal point; don't use a comma.",
        '# Optional | The Google product category for the item. Learn more about product categories: https://www.facebook.com/business/help/526764014610932.',
        '# Optional | The Facebook product category for the item. Learn more about product categories: https://www.facebook.com/business/help/526764014610932.',
        "# Optional | The quantity of this item you have to sell on Facebook and Instagram with checkout. Must be 1 or higher or the item won't be buyable",
        "# Optional | The discounted price of the item if it's on sale. Format the price as a number followed by the 3-letter currency code (ISO 4217 standards). Use a period (.) as the decimal point; don't use a comma. A sale price is required if you want to use an overlay for discounted prices.",
        "# Optional | The time range for your sale period. Includes the date and time/time zone when your sale starts and ends. If this field is blank any items with a sale_price remain on sale until you remove the sale price. Use this format: YYYY-MM-DDT23:59+00:00/YYYY-MM-DDT23:59+00:00. Enter the start date as YYYY-MM-DD. Enter a 'T'. Enter the start time in 24-hour format (00:00 to 23:59) followed by the UTC time zone (-12:00 to +14:00). Enter '/' and then repeat the same format for your end date and time. The example row below uses PST time zone (-08:00).",
        '# Optional | Use this field to create variants of the same item. Enter the same group ID for all variants within a group. Learn more about variants: https://www.facebook.com/business/help/2256580051262113 Character limit: 100.',
        '# Optional | The gender of a person that the item is targeted towards. | Supported values: female; male; unisex',
        "# Optional | The color of the item. Use one or more words to describe the color. Don't use a hex code. Character limit: 200.",
        '# Optional | The size of the item written as a word or abbreviation or number. For example: small; XL; 12. Character limit: 200.',
        '# Optional | The age group that the item is targeted towards. | Supported values: adult; all ages; infant; kids; newborn; teen; toddler',
        '# Optional | The material that the item is made from; such as cotton; denim or leather. Character limit: 200.',
        '# Optional | The pattern or graphic print on the item. Character limit: 100.',
        '# Optional | Delivery details for the item. Format as Country:Region:Service:Price. Include the 3-letter ISO 4217 currency code in the price. Enter the price as 0.0 to use the free delivery overlay in your ads. Use a semi-colon ";" or a comma ";" to separate multiple delivery details for different regions or countries. Only people in the specified region or country will see delivery details for that region or country. You can leave out the region (keep the double "::") if your delivery details are the same for an entire country.',
        '# Optional | The shipping weight of the item. Include the unit of measurement (lb/oz/g/kg).',
        '# Optional | Legal disclaimer text for product offers. This text provides important legal or regulatory information that must be displayed with the product offer. For example: "Valid while supplies last. Terms and conditions apply."',
        '# Optional | URL linking to the full disclaimer text. This provides a link to a page containing the complete disclaimer information for the product offer. For example: "https://example.com/terms-and-conditions"',
        '# Optional | The URL for a video of your product. Link should be a videos file on a file hosting website; not a video player. Videos must be in a supported format (.3g2; .3gp; .3gpp; .asf; .avi; .dat; .divx; .dv; .f4v; .flv; .gif; .m2ts; .m4v; .mkv; .mod; .mov; .mp4; .mpe; .mpeg; .mpeg4; .mpg; .mts; .nsv; .ogm; .ogv; .qt; .tod; .ts; .vob or .wmv).',
        '# Optional | The URL for a video of your product. Link should be a videos file on a file hosting website; not a video player. Videos must be in a supported format (.3g2; .3gp; .3gpp; .asf; .avi; .dat; .divx; .dv; .f4v; .flv; .gif; .m2ts; .m4v; .mkv; .mod; .mov; .mp4; .mpe; .mpeg; .mpeg4; .mpg; .mts; .nsv; .ogm; .ogv; .qt; .tod; .ts; .vob or .wmv).',
        "# Optional | The item's Global Trade Item Number (GTIN). Recommended to help classify the item. May appear on the barcode; packaging or book cover. Only provide GTIN if you're sure that it's correct. GTIN types include UPC (12 digits); EAN (13 digits); JAN (8 or 13 digits); ISBN (13 digits) or ITF-14 (14 digits)",
        '# Optional | Add labels to products to help filter them into product sets. Max characters: 110 per label; 5000 labels per product',
        '# Optional | Add labels to products to help filter them into product sets. Max characters: 110 per label; 5000 labels per product',
        '# Optional | Describe the fashion style of this item.',
    ];

    public function __construct(
        private readonly ListingRepository $listings,
        private readonly MarketplaceSettingsService $settings,
    ) {}

    public function createTemporaryFile(): string
    {
        $workbookPath = tempnam(sys_get_temp_dir(), 'meta-catalogue-');
        $worksheetPath = tempnam(sys_get_temp_dir(), 'meta-catalogue-sheet-');

        if ($workbookPath === false || $worksheetPath === false) {
            throw new RuntimeException('Unable to create the Meta catalogue export.');
        }

        try {
            $this->writeWorksheet($worksheetPath);
            $this->writeWorkbook($workbookPath, $worksheetPath);
        } catch (\Throwable $exception) {
            @unlink($workbookPath);
            throw $exception;
        } finally {
            @unlink($worksheetPath);
        }

        return $workbookPath;
    }

    private function writeWorksheet(string $path): void
    {
        $writer = new XMLWriter;
        if (! $writer->openUri($path)) {
            throw new RuntimeException('Unable to prepare the Meta catalogue worksheet.');
        }

        $writer->startDocument('1.0', 'UTF-8', 'yes');
        $writer->startElement('worksheet');
        $writer->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $writer->writeRaw('<dimension ref="A1:AE1048576"/><sheetViews><sheetView tabSelected="1" workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="14.4" defaultColWidth="12"/><sheetData>');
        $this->writeRow($writer, 1, self::INSTRUCTIONS, fn (int $index): int => $index < 8 ? 1 : 2);
        $this->writeRow($writer, 2, self::HEADERS, fn (int $index): int => $index < 8 ? 3 : 0);

        $rowNumber = 3;
        foreach ($this->rows() as $row) {
            $this->writeRow($writer, $rowNumber++, $row);
        }

        $writer->writeRaw('</sheetData>'.$this->worksheetControls());
        $writer->endElement();
        $writer->endDocument();
        $writer->flush();
    }

    /**
     * @param  list<string>  $values
     * @param  (callable(int): int)|null  $styleForIndex
     */
    private function writeRow(XMLWriter $writer, int $rowNumber, array $values, ?callable $styleForIndex = null): void
    {
        $writer->startElement('row');
        $writer->writeAttribute('r', (string) $rowNumber);
        $writer->writeAttribute('spans', '1:31');

        foreach ($values as $index => $value) {
            if ($value === '') {
                continue;
            }

            $writer->startElement('c');
            $writer->writeAttribute('r', $this->columnName($index + 1).$rowNumber);
            $style = $styleForIndex === null ? 0 : $styleForIndex($index);
            if ($style > 0) {
                $writer->writeAttribute('s', (string) $style);
            }
            $writer->writeAttribute('t', 'inlineStr');
            $writer->startElement('is');
            $writer->startElement('t');
            $writer->writeAttribute('xml:space', 'preserve');
            $writer->text($value);
            $writer->endElement();
            $writer->endElement();
            $writer->endElement();
        }

        $writer->endElement();
    }

    /** @return \Generator<int, list<string>> */
    private function rows(): \Generator
    {
        foreach ($this->listings->merchantProducts() as $listing) {
            if ($listing->product_type !== 'variant') {
                yield $this->rowForListing($listing);

                continue;
            }

            foreach ($listing->variants->where('is_active', true)->values() as $variant) {
                yield $this->rowForVariant($listing, $variant);
            }
        }
    }

    /** @return list<string> */
    private function rowForListing(Listing $listing): array
    {
        return $this->row(
            listing: $listing,
            id: (string) $listing->id,
            title: (string) $listing->title,
            imageUrl: (string) ($listing->media->firstWhere('type', 'image')?->urlForVariant('card_2x') ?? ''),
            price: (string) ($listing->price ?? ''),
            salePrice: $this->salePrice($listing->price, $listing->sale_price),
            quantity: max(0, $listing->stock_quantity - $listing->reserved_quantity),
            gtin: ValidGtin::isValid($listing->gtin) ? (string) $listing->gtin : '',
        );
    }

    /** @return list<string> */
    private function rowForVariant(Listing $listing, ListingVariant $variant): array
    {
        $options = $variant->optionValues
            ->sortBy(fn ($value): int => (int) $value->option->position)
            ->mapWithKeys(fn ($value): array => [Str::lower((string) $value->option->name) => (string) $value->value]);
        $optionLabel = $options->values()->implode(' / ');
        $sellingPrice = (string) ($variant->selling_price ?? '');
        $hasMarketPrice = $variant->market_price !== null && (float) $variant->market_price > (float) $variant->selling_price;

        return $this->row(
            listing: $listing,
            id: (string) $variant->id,
            title: $optionLabel === '' ? (string) $listing->title : $listing->title.' - '.$optionLabel,
            imageUrl: (string) ($variant->image?->urlForVariant('card_2x') ?? $listing->media->firstWhere('type', 'image')?->urlForVariant('card_2x') ?? ''),
            price: $hasMarketPrice ? (string) $variant->market_price : $sellingPrice,
            salePrice: $hasMarketPrice ? $sellingPrice : '',
            quantity: $variant->availableQuantity(),
            gtin: ValidGtin::isValid($variant->gtin) ? (string) $variant->gtin : '',
            itemGroupId: (string) $listing->id,
            options: $options->all(),
            variantId: $variant->id,
        );
    }

    /**
     * @param  array<string, string>  $options
     * @return list<string>
     */
    private function row(
        Listing $listing,
        string $id,
        string $title,
        string $imageUrl,
        string $price,
        string $salePrice,
        int $quantity,
        string $gtin,
        string $itemGroupId = '',
        array $options = [],
        ?int $variantId = null,
    ): array {
        $linkParameters = $variantId === null ? $listing->slug : ['listing' => $listing->slug, 'variant' => $variantId];
        $catalogBrand = data_get($listing, 'brand.name');
        $brand = filled($catalogBrand) ? $catalogBrand : ($listing->brand_name ?? $listing->sellerProfile->store_name);
        $currency = (string) config('marketplace.storefront.currency', 'LKR');

        return [
            Str::limit($id, 100, ''),
            Str::limit($title, 200, ''),
            Str::limit(SeoText::plain((string) (filled($listing->short_description) ? $listing->short_description : $listing->description)), 9999, ''),
            $quantity > 0 || $listing->allow_backorders ? 'in stock' : 'out of stock',
            $listing->condition === 'new' ? 'new' : 'used',
            route('listings.show', $linkParameters),
            $imageUrl,
            Str::limit((string) $brand, 100, ''),
            $this->formattedPrice($price, $currency),
            (string) ($listing->category->google_product_category_id ?? ''),
            '',
            (string) $quantity,
            $this->formattedPrice($salePrice, $currency),
            '',
            $itemGroupId,
            $this->option($options, 'gender'),
            $this->option($options, 'color', 'colour'),
            $this->option($options, 'size'),
            $this->option($options, 'age group', 'age_group'),
            $this->option($options, 'material'),
            $this->option($options, 'pattern'),
            $this->shipping($currency),
            '', '', '', '', '',
            Str::limit($gtin, 14, ''),
            Str::limit((string) $listing->category->name, 110, ''),
            Str::limit((string) $listing->sellerProfile->store_name, 110, ''),
            $this->option($options, 'style'),
        ];
    }

    private function salePrice(mixed $price, mixed $salePrice): string
    {
        return $price !== null && $salePrice !== null && (float) $salePrice < (float) $price
            ? (string) $salePrice
            : '';
    }

    private function formattedPrice(string $price, string $currency): string
    {
        return $price === '' ? '' : number_format((float) $price, 2, '.', '').' '.$currency;
    }

    /** @param array<string, string> $options */
    private function option(array $options, string ...$names): string
    {
        foreach ($names as $name) {
            if (filled($options[$name] ?? null)) {
                return (string) $options[$name];
            }
        }

        return '';
    }

    private function shipping(string $currency): string
    {
        $rate = $this->settings->integer('checkout.shipping_fee', (int) config('marketplace.seo.shipping.rate', 600));

        return 'LK::Standard:'.number_format($rate, 2, '.', '').' '.$currency;
    }

    private function columnName(int $column): string
    {
        $name = '';
        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function writeWorkbook(string $workbookPath, string $worksheetPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($workbookPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the Meta catalogue workbook.');
        }

        foreach ($this->workbookParts() as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->addFile($worksheetPath, 'xl/worksheets/sheet1.xml');

        if (! $zip->close()) {
            throw new RuntimeException('Unable to finalize the Meta catalogue workbook.');
        }
    }

    /** @return array<string, string> */
    private function workbookParts(): array
    {
        $createdAt = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ProDeals.lk</Application></Properties>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>ProDeals.lk</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">'.$createdAt.'</dcterms:created></cp:coreProperties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets><sheet name="Worksheet" sheetId="1" r:id="rId1"/></sheets><calcPr calcId="999999" calcMode="auto" fullCalcOnLoad="1"/></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $this->styles(),
        ];
    }

    private function styles(): string
    {
        $warning = '<dxf><fill><patternFill patternType="solid"><fgColor rgb="FFF1CC"/></patternFill></fill><border><left style="thin"><color rgb="FFFFBA00"/></left><right style="thin"><color rgb="FFFFBA00"/></right><top style="thin"><color rgb="FFFFBA00"/></top><bottom style="thin"><color rgb="FFFFBA00"/></bottom></border></dxf>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><numFmts count="0"/><fonts count="3"><font><sz val="11"/><color rgb="FF000000"/><name val="Calibri"/></font><font><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FF1461CC"/><name val="Calibri"/></font></fonts><fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="1877F2"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="F2F2F2"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="E8F1FE"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf xfId="0" fontId="0" fillId="0" borderId="0"/><xf xfId="0" fontId="1" fillId="2" borderId="0" applyFont="1" applyFill="1"/><xf xfId="0" fontId="0" fillId="3" borderId="0" applyFill="1"/><xf xfId="0" fontId="2" fillId="4" borderId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="8">'.str_repeat($warning, 8).'</dxfs><tableStyles defaultTableStyle="TableStyleMedium9" defaultPivotStyle="PivotTableStyle1"/></styleSheet>';
    }

    private function worksheetControls(): string
    {
        $controls = '';
        foreach (range('A', 'H') as $index => $column) {
            $controls .= '<conditionalFormatting sqref="'.$column.'3:'.$column.'1048576"><cfRule type="expression" dxfId="'.$index.'" priority="'.($index + 1).'"><formula>AND('.$column.'3 = &quot;&quot;, AND(COUNTBLANK(A3:C3) &lt;&gt; 3,COUNTBLANK(A4:C4) &lt;&gt; 3))</formula></cfRule></conditionalFormatting>';
        }

        return $controls.'<dataValidations count="16">'
            .$this->textValidation('A', 100).$this->textValidation('B', 200).$this->textValidation('C', 9999)
            .$this->listValidation('D', 'in stock, out of stock').$this->listValidation('E', 'new, used')
            .$this->textValidation('H', 100).$this->textValidation('J', 5000).$this->textValidation('K', 5000)
            .$this->listValidation('P', 'female, male, unisex, ').$this->textValidation('Q', 200)
            .$this->textValidation('R', 200).$this->listValidation('S', 'adult, all ages, infant, kids, newborn, teen, toddler, ')
            .$this->textValidation('T', 200).$this->textValidation('U', 100).$this->textValidation('X', 1000)
            .$this->textValidation('AB', 1000)
            .'</dataValidations><printOptions gridLines="false" gridLinesSet="true"/><pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/><pageSetup paperSize="1" scale="100" fitToHeight="1" fitToWidth="1"/>';
    }

    private function textValidation(string $column, int $maximum): string
    {
        return '<dataValidation type="textLength" errorStyle="stop" operator="lessThanOrEqual" allowBlank="1" showDropDown="1" showErrorMessage="1" errorTitle="Max Characters Reached" error="You can only enter a maximum of '.$maximum.' characters" sqref="'.$column.'3:'.$column.'1048576"><formula1>'.$maximum.'</formula1></dataValidation>';
    }

    private function listValidation(string $column, string $values): string
    {
        return '<dataValidation type="list" errorStyle="stop" allowBlank="1" showDropDown="0" showErrorMessage="1" errorTitle="Invalid Entry" error="Select one of the values from the dropdown." sqref="'.$column.'3:'.$column.'1048576"><formula1>&quot;'.$values.'&quot;</formula1></dataValidation>';
    }
}

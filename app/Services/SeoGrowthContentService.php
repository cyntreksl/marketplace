<?php

namespace App\Services;

use App\Contracts\Repositories\GuideRepository;
use App\Contracts\Repositories\SeoGrowthContentRepository;
use App\Contracts\Repositories\SeoRedirectRepository;
use App\Models\Guide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SeoGrowthContentService
{
    public function __construct(
        private readonly SeoGrowthContentRepository $content,
        private readonly GuideRepository $guides,
        private readonly SeoRedirectRepository $redirects,
        private readonly SeoRedirectService $redirectService,
        private readonly ListingSeoMetadataService $listingMetadata,
    ) {}

    /** @return array{categories: int, brands: int, stores: int, products: int, guides: int, redirects: int} */
    public function seed(): array
    {
        return DB::transaction(function (): array {
            return [
                'categories' => $this->seedCategories(),
                'brands' => $this->seedBrands(),
                'stores' => $this->seedStores(),
                'products' => $this->seedProducts(),
                'guides' => $this->seedGuides(),
                'redirects' => $this->seedRedirects(),
            ];
        });
    }

    private function seedCategories(): int
    {
        $updated = 0;

        foreach ($this->categoryContent() as $name => $attributes) {
            $category = $this->content->categoryByName($name);

            if ($category === null) {
                continue;
            }

            $changed = $this->fillMissing($category, $attributes);
            if ($changed) {
                $this->content->saveCategory($category);
                $updated++;
            }
        }

        return $updated;
    }

    private function seedBrands(): int
    {
        $updated = 0;

        foreach (['GERLAX', 'Kawashi', 'VEN-DENS', 'Celebrat', 'Mitshu'] as $name) {
            $brand = $this->content->brandByName($name);

            if ($brand === null) {
                continue;
            }

            $attributes = [
                'seo_title' => $name.' Products & Prices in Sri Lanka - ProDeals.lk',
                'seo_description' => 'Browse available '.$name.' products in Sri Lanka, compare current prices and product details, and order from approved sellers on ProDeals.lk.',
                'seo_intro' => 'Explore '.$name.' products currently offered by approved sellers on ProDeals.lk. Compare the listed specifications, prices, availability and warranty information before you choose.\n\nStock and seller offers can change, so check each product page for the latest purchase and delivery details.',
                'seo_focus_query' => strtolower($name).' products sri lanka',
                'seo_supporting_queries' => [strtolower($name).' price sri lanka', 'buy '.strtolower($name).' online'],
                'seo_researched_at' => '2026-09-12',
            ];

            if ($this->fillMissing($brand, $attributes)) {
                $this->content->saveBrand($brand);
                $updated++;
            }
        }

        return $updated;
    }

    private function seedStores(): int
    {
        $stores = $this->content->publicStoresWithoutAbout();

        foreach ($stores as $seller) {
            $seller->about = 'Browse products currently available from '.$seller->store_name.' on ProDeals.lk. Compare clear product details, prices and availability, then use the marketplace checkout for your order. Product selection and stock can change, so open an item for its latest seller, warranty and delivery information.';
            $this->content->saveSeller($seller);
        }

        return $stores->count();
    }

    private function seedProducts(): int
    {
        $updated = 0;
        $expandedDescriptions = [
            'hopper-pan-non-stick-heavy-diamond-brand-pan-no-1-quality' => '<p>This heavy non-stick hopper pan is designed for straightforward home cooking and easy cleaning. Its shaped cooking surface helps prepare hoppers, while the sturdy construction and handle support everyday kitchen use.</p><ul><li>Non-stick cooking surface</li><li>Heavy construction</li><li>Easy to use and clean</li><li>Includes one imported hopper pan</li></ul><p>Check the product photos, current price, availability and delivery details before ordering.</p>',
            'kawashi-18l-rice-cooker' => '<p>The Kawashi 1.8L rice cooker is sized for everyday household cooking and uses a 700W heating system. It is built for Sri Lankan 220–240V power and provides a simple way to prepare rice without monitoring a stovetop pot.</p><ul><li>Brand: Kawashi</li><li>Model: SCO 5045LHRC65</li><li>Capacity: 1.8 litres</li><li>Voltage: 220–240V</li><li>Power: 700W</li></ul><p>Choose capacity according to your usual serving size and review the current warranty, included accessories and delivery information on this listing.</p>',
            'osaka-national-sandwich-maker-non-stick-toaster' => '<p>The Osaka National sandwich maker is designed for preparing toasted sandwiches with non-stick cooking plates. The closed cooking format heats both sides while keeping the appliance compact enough for routine countertop use.</p><ul><li>Designed for toasted sandwiches</li><li>Non-stick cooking surfaces</li><li>Simple closed-plate operation</li></ul><p>Allow the appliance to cool and unplug it before cleaning the plates. Review the listing photos for the exact design, and check the current voltage, warranty, included items, stock and delivery details before ordering.</p>',
        ];

        foreach (['nonstick-fry-pan-nonstick-sauce-pan-tawa-pan-non-stick-frying-pan-14-cm', 'stainless-steel-electric-kettle-20l-fast-boil-auto-off'] as $slug) {
            $listing = $this->content->listingBySlug($slug);

            if ($listing === null || (filled($listing->meta_title) && filled($listing->meta_description))) {
                continue;
            }

            $listing->fill($this->listingMetadata->generate(
                $listing->title,
                $listing->short_description,
                $listing->description,
                $listing->meta_title,
                $listing->meta_description,
            ));
            $this->content->saveListing($listing);
            $updated++;
        }

        foreach ($expandedDescriptions as $slug => $description) {
            $listing = $this->content->listingBySlug($slug);

            if ($listing === null || mb_strlen(strip_tags((string) $listing->description)) >= 300) {
                continue;
            }

            $listing->description = $description;
            $listing->fill($this->listingMetadata->generate(
                $listing->title,
                $listing->short_description,
                $listing->description,
                $listing->meta_title,
                $listing->meta_description,
            ));
            $this->content->saveListing($listing);
            $updated++;
        }

        return $updated;
    }

    private function seedGuides(): int
    {
        $created = 0;

        foreach ($this->guideContent() as $attributes) {
            if ($this->guides->bySlug($attributes['slug']) !== null) {
                continue;
            }

            $categoryNames = $attributes['category_names'];
            unset($attributes['category_names']);
            /** @var array<int, string> $categoryNames */
            $categoryIds = [];

            foreach ($categoryNames as $categoryName) {
                $categoryId = $this->content->categoryByName($categoryName)?->id;

                if (is_int($categoryId)) {
                    $categoryIds[] = $categoryId;
                }
            }

            if ($categoryIds === []) {
                continue;
            }

            $guide = $this->guides->save(new Guide($attributes));
            $this->guides->syncCategories($guide, $categoryIds);
            $created++;
        }

        return $created;
    }

    private function seedRedirects(): int
    {
        $existing = $this->redirects->bySource('/shop');

        if ($existing !== null) {
            return 0;
        }

        $this->redirectService->create([
            'source_path' => '/shop',
            'destination_path' => '/listings',
            'is_active' => true,
        ]);

        return 1;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fillMissing(Model $model, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            $currentValue = $model->getAttribute($key);
            $normalizedValue = $this->normalizeLineBreaks($value);
            $normalizedCurrentValue = $this->normalizeLineBreaks($currentValue);

            if ($normalizedCurrentValue !== $currentValue) {
                $model->setAttribute($key, $normalizedCurrentValue);
            } elseif (blank($currentValue)) {
                $model->setAttribute($key, $normalizedValue);
            }
        }

        return $model->isDirty();
    }

    private function normalizeLineBreaks(mixed $value): mixed
    {
        return is_string($value) ? str_replace('\n', PHP_EOL, $value) : $value;
    }

    /** @return array<string, array<string, mixed>> */
    private function categoryContent(): array
    {
        $research = ['seo_researched_at' => '2026-09-12'];

        return [
            'Food Mixers & Blenders' => [...$research, 'seo_title' => 'Blenders & Food Mixers in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare blenders and food mixers in Sri Lanka for smoothies, spices and daily food preparation. Check capacity, power and current prices.', 'seo_intro' => 'Compare blenders and food mixers for smoothies, sauces, spices and everyday preparation. Look at motor power, jar capacity, blade design and cleaning needs—not only the headline price.\n\nChoose a model that matches what you actually prepare, and open each listing to confirm its included jars, warranty and current availability.', 'seo_focus_query' => 'blender price in sri lanka', 'seo_supporting_queries' => ['best blender for smoothies', 'food mixer price sri lanka', 'how to clean a blender']],
            'Headphones' => [...$research, 'seo_title' => 'Headphones & Headsets in Sri Lanka - ProDeals.lk', 'seo_description' => 'Shop headphones and headsets in Sri Lanka. Compare wired, wireless, gaming and noise-cancelling options from current sellers.', 'seo_intro' => 'Browse headphones for music, calls, gaming and travel. Compare connection type, microphone quality, comfort, battery life and noise isolation before choosing.\n\nCheck each listing for device compatibility, charging requirements, warranty details and the seller’s latest price.', 'seo_focus_query' => 'headphones price in sri lanka', 'seo_supporting_queries' => ['wireless headphones sri lanka', 'noise cancelling headphones', 'gaming headset price sri lanka']],
            'Power Adapters & Chargers' => [...$research, 'seo_title' => 'Phone Chargers & Power Adapters Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare phone chargers and power adapters in Sri Lanka, including USB-C and fast-charging options. Check output and compatibility.', 'seo_intro' => 'Compare phone chargers and power adapters by connector, power output and supported charging standard. The correct wattage and protocol matter for both charging speed and device compatibility.\n\nUse each product page to confirm the plug, cable inclusion, output ratings and supported phone or accessory models.', 'seo_focus_query' => 'phone charger price in sri lanka', 'seo_supporting_queries' => ['phone charger types', 'usb c charger sri lanka', 'fast charger price sri lanka']],
            'Storage & Data Transfer Cables' => [...$research, 'seo_title' => 'USB & Data Cables in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare USB-C, Lightning and USB-A data cables in Sri Lanka. Check connector type, charging support, length and compatibility.', 'seo_intro' => 'Find cables for charging, data transfer and connecting accessories. Match both ends of the cable to your devices, then compare length, supported power and data capability.\n\nConnector shape alone does not guarantee the same performance, so review each listing’s specifications and compatibility notes.', 'seo_focus_query' => 'usb cable price in sri lanka', 'seo_supporting_queries' => ['usb c to lightning cable', 'usb a to usb c cable', 'phone data cable sri lanka']],
            'Electric Kettles' => [...$research, 'seo_title' => 'Electric Kettle Prices in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare electric kettle prices in Sri Lanka. Review capacity, material, safety features, power and warranty before you buy.', 'seo_intro' => 'Compare electric kettles by capacity, body material, power, automatic shut-off and boil-dry protection. A practical choice balances fast boiling with safe handling and easy cleaning.\n\nOpen a listing to confirm the current Sri Lankan price, warranty, stock and delivery options.', 'seo_focus_query' => 'electric kettle price in sri lanka', 'seo_supporting_queries' => ['stainless steel electric kettle', '2 litre electric kettle price', 'electric kettle sri lanka']],
            'General Purpose Battery Chargers' => [...$research, 'seo_title' => 'Battery Chargers in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare general-purpose battery chargers in Sri Lanka by supported battery size, charging slots, safety and current price.', 'seo_intro' => 'Choose a battery charger that explicitly supports the rechargeable battery chemistry and sizes you use. Compare slot count, charge indicators and safety cut-offs.\n\nNever charge disposable cells, and check the product page for included batteries, input power and warranty information.', 'seo_focus_query' => 'battery charger price in sri lanka', 'seo_supporting_queries' => ['aa battery charger sri lanka', 'rechargeable battery charger', 'universal battery charger']],
            'Skillets & Frying Pans' => [...$research, 'seo_title' => 'Frying Pans & Skillets in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare frying pans and skillets in Sri Lanka by size, coating, material, hob compatibility and price.', 'seo_intro' => 'Compare frying pans by diameter, cooking surface, weight and compatibility with your stove. Non-stick, stainless steel and other materials suit different heat levels and care routines.\n\nCheck the exact dimensions, handle construction and cleaning guidance on each product listing before ordering.', 'seo_focus_query' => 'frying pan price in sri lanka', 'seo_supporting_queries' => ['non stick frying pan sri lanka', 'stainless steel frying pan', 'induction frying pan']],
            'Stovetop Kettles' => [...$research, 'seo_title' => 'Stovetop Kettles in Sri Lanka - ProDeals.lk', 'seo_description' => 'Browse stovetop kettles in Sri Lanka and compare capacity, material, handle design, stove compatibility and price.', 'seo_intro' => 'Stovetop kettles are a simple option where you prefer gas or hob heating. Compare capacity, material, lid fit, handle insulation and whether the base suits your cooktop.\n\nReview the listing photos and specifications for the latest price, availability and care instructions.', 'seo_focus_query' => 'stovetop kettle price sri lanka', 'seo_supporting_queries' => ['whistling kettle sri lanka', 'stainless steel kettle', 'kettle for gas stove']],
            'Food Grinders & Mills' => [...$research, 'seo_title' => 'Food Grinders & Mills in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare food grinders and mills in Sri Lanka for spices and preparation. Check capacity, motor, blade and cleaning details.', 'seo_intro' => 'Browse grinders and mills for spices and other food preparation tasks. Compare rated use, bowl capacity, motor power, blade material and how easily food-contact parts can be cleaned.\n\nUse the individual product details to confirm what the appliance is designed to process and what accessories are included.', 'seo_focus_query' => 'food grinder price in sri lanka', 'seo_supporting_queries' => ['spice grinder sri lanka', 'electric grinder price', 'food mill sri lanka']],
            'Rice Cookers' => [...$research, 'seo_title' => 'Rice Cooker Prices in Sri Lanka - ProDeals.lk', 'seo_description' => 'Compare rice cooker prices in Sri Lanka by capacity, power, keep-warm function, controls and included accessories.', 'seo_intro' => 'Compare rice cookers by usable capacity, household serving needs, power and keep-warm controls. Included measuring cups and the correct water ratio also affect day-to-day results.\n\nCheck each listing for voltage, accessories, warranty and current stock before choosing.', 'seo_focus_query' => 'rice cooker price in sri lanka', 'seo_supporting_queries' => ['how to use a rice cooker', '1.8l rice cooker sri lanka', 'best rice cooker sri lanka']],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function guideContent(): array
    {
        $common = ['status' => 'published', 'published_at' => '2026-09-12 09:00:00', 'trends_researched_at' => '2026-09-12'];

        return [
            [...$common, 'title' => 'Best Blenders for Smoothies: Sri Lanka Buying Guide', 'slug' => 'best-blenders-for-smoothies-sri-lanka', 'excerpt' => 'Choose a smoothie blender by motor strength, jar size, blade design and cleaning needs, with practical checks for Sri Lankan kitchens.', 'quick_answer' => 'For regular smoothies, prioritise a stable blender with enough power for your ingredients, a jar size that matches your servings, replaceable or easy-clean blade parts, and local warranty support. Ice and hard ingredients need more torque than soft fruit.', 'seo_title' => 'Best Blenders for Smoothies in Sri Lanka - Buying Guide', 'seo_description' => 'Compare blender power, jar capacity, blade design and cleaning features to choose a smoothie blender for a Sri Lankan home.', 'primary_query' => 'best blender for smoothies', 'supporting_queries' => ['blender price in sri lanka', 'smoothie blender sri lanka', 'how to clean a blender'], 'category_names' => ['Food Mixers & Blenders'], 'sections' => [
                ['heading' => 'Start with what you blend', 'paragraphs' => ['Soft fruit, yoghurt and protein drinks place a lighter load on a motor than ice, frozen fruit, nuts or fibrous greens. If hard ingredients are part of your routine, look beyond a peak wattage claim and check whether the seller specifically describes ice-crushing or heavy blending use.', 'A personal cup model can suit one serving and a small counter, while a larger jug is more practical for a family. Avoid choosing a large jar only for the number on the box: very small batches may not circulate well in an oversized jug.']],
                ['heading' => 'Power, blades and jar material', 'paragraphs' => ['Motor power is useful for comparison, but coupling quality, blade shape and a steady base also affect performance. Stainless steel blades should sit securely and the lid should seal without requiring excessive force.', 'Glass jars resist odours and scratching but are heavier. Plastic jars are easier to lift, although they benefit from prompt rinsing after strongly coloured ingredients. Confirm whether replacement jars, seals or blade assemblies are available before buying.']],
                ['heading' => 'Cleaning, safety and local use', 'paragraphs' => ['A removable blade assembly can make deep cleaning easier, while a fixed blade avoids handling a sharp loose component. In either case, unplug the blender before cleaning and do not immerse the motor base.', 'Check that the model is rated for Sri Lankan mains power, has a secure lid and offers a warranty you can actually use. Compare current listings rather than assuming the cheapest blender includes the same jars or accessories.']],
            ], 'buying_checklist' => ['Match the motor and stated use to ice, frozen fruit or soft ingredients.', 'Choose a jar capacity suited to your normal number of servings.', 'Check lid security, blade assembly and stable feet.', 'Confirm how the jar and blades are cleaned.', 'Verify included accessories, voltage and warranty.']],
            [...$common, 'title' => 'How to Choose and Use a Rice Cooker', 'slug' => 'how-to-choose-and-use-a-rice-cooker', 'excerpt' => 'Understand rice cooker capacity, water ratios, keep-warm settings and safe everyday use before comparing current Sri Lankan options.', 'quick_answer' => 'Choose capacity by the amount of uncooked rice you normally prepare, use the cooker’s supplied cup, rinse rice when appropriate, add water for the rice variety, and allow a short rest after cooking. Do not use metal tools on a non-stick inner pot.', 'seo_title' => 'How to Choose and Use a Rice Cooker in Sri Lanka', 'seo_description' => 'Learn how to choose rice cooker capacity and use water ratios, keep-warm settings and safe cleaning for reliable everyday rice.', 'primary_query' => 'how to use a rice cooker', 'supporting_queries' => ['rice cooker price in sri lanka', '1.8l rice cooker sri lanka', 'rice cooker water ratio'], 'category_names' => ['Rice Cookers'], 'sections' => [
                ['heading' => 'Choose a practical capacity', 'paragraphs' => ['Rice cooker capacity is often advertised in litres or in cups, and those figures are not always directly comparable. Base your choice on uncooked rice and the measuring cup included with the appliance, then allow room for expansion while cooking.', 'A 1.8-litre model is common for family use, while a smaller cooker can be easier for one or two people. Oversizing adds counter space and may be less effective with very small portions.']],
                ['heading' => 'A reliable cooking routine', 'paragraphs' => ['Measure rice with the supplied cup, rinse if the rice variety and your preference call for it, then add water according to the cooker markings or rice instructions. Level the inner pot and wipe its outside dry before placing it on the heating plate.', 'After the switch moves to keep-warm, let the rice rest for several minutes before opening. Fluff with the supplied paddle so steam escapes evenly and avoid leaving rice warm for longer than the manufacturer recommends.']],
                ['heading' => 'Care and electrical safety', 'paragraphs' => ['Never place the cooker body under running water. Remove and wash the inner pot and detachable lid parts after the unit cools, then clean the heating plate gently so grains do not interfere with contact.', 'Check the voltage, plug, power rating and warranty. If the cord, switch or inner pot is damaged, stop using the cooker rather than attempting an improvised repair.']],
            ], 'buying_checklist' => ['Compare capacity using uncooked-rice servings.', 'Check for cook and keep-warm controls.', 'Inspect the inner pot coating and included cup and paddle.', 'Confirm 220–240V compatibility and plug type.', 'Review warranty and replacement-pot availability.']],
            [...$common, 'title' => 'Electric Kettle Prices in Sri Lanka: What to Compare', 'slug' => 'electric-kettle-prices-sri-lanka', 'excerpt' => 'Price is only one part of choosing an electric kettle. Compare capacity, materials, safety controls, power and warranty.', 'quick_answer' => 'Compare kettles in the same capacity and material range, then check automatic shut-off, boil-dry protection, handle insulation, lid design and warranty. A lower price is not a saving if the kettle is awkward to pour or lacks basic safety information.', 'seo_title' => 'Electric Kettle Prices in Sri Lanka: Buying Guide', 'seo_description' => 'Compare electric kettle prices in Sri Lanka by capacity, material, safety features, power, pouring design and warranty.', 'primary_query' => 'electric kettle price in sri lanka', 'supporting_queries' => ['stainless steel electric kettle', '2 litre electric kettle price', 'electric kettle wattage'], 'category_names' => ['Electric Kettles'], 'sections' => [
                ['heading' => 'Compare like with like', 'paragraphs' => ['Capacity has a direct effect on size and convenience. A large 2-litre kettle can reduce repeat boiling for a household, while a smaller model may be easier to lift and can use less water when you usually make one or two drinks.', 'Body materials change the experience too. Stainless steel is durable but its exterior may become hot. Plastic can be lighter, while glass makes the water level easy to see. Compare products within the same broad construction before judging price.']],
                ['heading' => 'Safety and handling matter', 'paragraphs' => ['Automatic shut-off and boil-dry protection are important everyday safeguards. Also inspect the lid latch, handle clearance and spout shape: a controlled pour and a handle that stays comfortable matter every time the kettle is used.', 'A cordless jug with a 360-degree base is convenient for left- and right-handed users. Water-level markings should be readable and the minimum-fill line should suit the small quantities you expect to boil.']],
                ['heading' => 'Power, scale and warranty', 'paragraphs' => ['Higher wattage can shorten boiling time but should be considered alongside the capacity and the electrical circuit used. Confirm the appliance is rated for 220–240V and use a sound wall socket rather than a light extension lead.', 'Sri Lankan water conditions can leave scale inside a kettle. A wide opening and accessible filter make regular cleaning easier. Confirm the warranty and seller support before treating a small price difference as the deciding factor.']],
            ], 'buying_checklist' => ['Choose a capacity you can lift comfortably when full.', 'Check automatic shut-off and boil-dry protection.', 'Compare body material, lid and spout design.', 'Confirm voltage, wattage and plug suitability.', 'Check cleaning access, warranty and seller support.']],
            [...$common, 'title' => 'Phone Charger Types Explained', 'slug' => 'phone-charger-types-explained', 'excerpt' => 'Understand charger ports, wattage and fast-charging protocols so you can choose a compatible adapter without relying on connector shape alone.', 'quick_answer' => 'Match the charger’s output port to your cable, then verify the device’s supported power and fast-charging protocol. A higher maximum wattage is generally safe with standards-compliant devices, but it will not make a phone charge faster than the phone and protocol allow.', 'seo_title' => 'Phone Charger Types Explained - Sri Lanka Guide', 'seo_description' => 'Understand USB-A and USB-C chargers, wattage, Power Delivery and device compatibility before buying a phone charger in Sri Lanka.', 'primary_query' => 'phone charger types', 'supporting_queries' => ['phone charger price in sri lanka', 'usb c fast charger', 'charger wattage explained'], 'category_names' => ['Power Adapters & Chargers'], 'sections' => [
                ['heading' => 'USB-A and USB-C charger ports', 'paragraphs' => ['USB-A is the familiar rectangular port found on many older adapters. USB-C is smaller and reversible, and modern USB-C chargers can support higher negotiated power through standards such as USB Power Delivery.', 'The port on the adapter and the port on the phone may differ, which is why the cable ends matter. An iPhone with Lightning may use a USB-C-to-Lightning cable, while many current Android phones use USB-C at both ends.']],
                ['heading' => 'Wattage is not the whole story', 'paragraphs' => ['Power is expressed in watts and results from voltage and current. A device and standards-compliant charger negotiate an appropriate level, so a 30W charger does not continuously force 30W into every phone.', 'Fast charging also depends on a shared protocol. USB Power Delivery is widely used, while some phone brands add proprietary modes. If charger, cable and phone do not share a faster mode, charging falls back to a compatible lower rate.']],
                ['heading' => 'Choose a safe, complete setup', 'paragraphs' => ['Use a cable rated for the power you need and avoid damaged plugs, loose sockets or products with unclear electrical markings. Multiple-port adapters divide power differently, so check whether the advertised wattage applies to one port or the whole charger.', 'Confirm whether a cable is included and whether the plug fits local outlets without an unsafe adapter. Warranty and clear seller information are more useful than an unverified speed claim.']],
            ], 'buying_checklist' => ['Identify the ports required at both ends of the cable.', 'Check the phone’s supported charging standard and wattage.', 'Confirm whether maximum power is per port or shared.', 'Use a cable rated for the required charging power.', 'Verify electrical markings, plug type and warranty.']],
            [...$common, 'title' => 'USB-C, Lightning, and USB-A Cable Buying Guide', 'slug' => 'usb-c-lightning-usb-a-cable-buying-guide', 'excerpt' => 'Match cable connectors and capabilities for charging or data, including USB-C, Lightning and USB-A combinations.', 'quick_answer' => 'Identify the connector at each end, then check the cable’s stated charging power, data capability and length. USB-C describes a connector, not one guaranteed speed, and some low-cost cables support charging but only slow data—or no useful data at all.', 'seo_title' => 'USB-C, Lightning & USB-A Cable Buying Guide', 'seo_description' => 'Choose between USB-C, Lightning and USB-A cables by connector, charging power, data support, length and device compatibility.', 'primary_query' => 'usb c lightning usb a cable types', 'supporting_queries' => ['usb c to lightning cable', 'usb a to usb c cable', 'data cable price sri lanka'], 'category_names' => ['Storage & Data Transfer Cables'], 'sections' => [
                ['heading' => 'Name both cable ends', 'paragraphs' => ['USB-A is a larger rectangular plug, USB-C is a smaller reversible plug, and Lightning is Apple’s compact connector used on many earlier iPhones and accessories. Describe a cable by both ends, such as USB-A to USB-C or USB-C to Lightning.', 'Check the actual devices rather than relying on memory, especially when replacing a cable for a power bank, speaker, camera or older phone. Similar-looking connectors such as Micro-USB and USB-C are not interchangeable.']],
                ['heading' => 'Charging and data capabilities vary', 'paragraphs' => ['A USB-C connector does not guarantee a particular charging rate or data speed. The cable must be rated for the required current and, for higher USB-C power levels, may need an identification chip.', 'Some cables are intended mostly for charging and provide only basic data speed. If you transfer large photos, videos or backups, look for a clearly stated data standard rather than assuming every cable performs alike.']],
                ['heading' => 'Length, build and daily use', 'paragraphs' => ['Longer cables are convenient but can be bulkier and may perform poorly when construction is inadequate. Choose enough length for the intended socket or desk without creating unnecessary tangles.', 'Inspect strain relief where the cable meets each plug. Braided jackets can resist abrasion, while a flexible conventional jacket may be easier to coil. Compatibility, verified capability and seller warranty matter more than decorative styling.']],
            ], 'buying_checklist' => ['Identify both connector types before ordering.', 'Check required charging wattage and cable rating.', 'Confirm data support and speed if file transfer matters.', 'Choose an appropriate length and durable strain relief.', 'Verify compatibility with the charger and device.']],
        ];
    }
}

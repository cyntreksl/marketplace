<?php

return [
    'taxonomy' => [
        'source_path' => database_path('seeders/data/google-product-taxonomy-with-ids.en-US.txt'),
        'locale' => 'en-US',
        'checksum' => '30039729880ec5ac4851de088ad228a6898aa253f0de5d5ebea5bb1437478fce',
        'excluded_google_ids' => [
            3237, 543683, 543681, 5086,
            499676, 5777, 435, 518, 2496, 772,
            499969, 53, 1813, 543515, 269, 5032,
            220, 5543, 1695, 5460, 5555, 4282, 3622, 3997, 3495, 7343, 499824, 500005, 3627, 7115,
        ],
        'department_names' => [
            1 => 'Pets & Animal Supplies',
            8 => 'Arts, Crafts & Entertainment',
            166 => 'Fashion & Accessories',
            412 => 'Food & Beverages',
            632 => 'Hardware & DIY',
            783 => 'Books, Movies & Music',
            988 => 'Sports & Outdoors',
            2092 => 'Software & Video Games',
            5181 => 'Luggage & Travel',
        ],
        'department_path_names' => [
            'Animals & Pet Supplies' => 'Pets & Animal Supplies',
            'Apparel & Accessories' => 'Fashion & Accessories',
            'Arts & Entertainment' => 'Arts, Crafts & Entertainment',
            'Food, Beverages & Tobacco' => 'Food & Beverages',
            'Hardware' => 'Hardware & DIY',
            'Luggage & Bags' => 'Luggage & Travel',
            'Media' => 'Books, Movies & Music',
            'Software' => 'Software & Video Games',
            'Sporting Goods' => 'Sports & Outdoors',
        ],
        'department_order' => [
            222, 166, 536, 469, 1239, 988, 537, 141, 436, 888,
            412, 1, 5181, 922, 8, 632, 783, 2092, 111, 5605,
        ],
    ],
];

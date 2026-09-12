<?php

return [
    'enabled' => env('SEO_MONITORING_ENABLED', false),
    'cache_store' => env('SEO_MONITORING_CACHE_STORE', 'database'),
    'alert_email' => env('SEO_MONITORING_ALERT_EMAIL', 'prodealslk@gmail.com'),
    'timezone' => 'Asia/Colombo',
    'merchant' => [
        'account_id' => env('GOOGLE_MERCHANT_ACCOUNT_ID', '5849184229'),
        'source_id' => env('GOOGLE_MERCHANT_SOURCE_ID', '10722990075'),
        'credentials_path' => env('GOOGLE_MERCHANT_CREDENTIALS_PATH'),
    ],
    'search_console' => [
        'enabled' => env('GOOGLE_SEARCH_CONSOLE_SUBMIT_ENABLED', false),
        'site_url' => env('GOOGLE_SEARCH_CONSOLE_SITE_URL', 'sc-domain:prodeals.lk'),
        'sitemap_url' => env('GOOGLE_SEARCH_CONSOLE_SITEMAP_URL', 'https://prodeals.lk/sitemap.xml'),
        'credentials_path' => env('GOOGLE_SEARCH_CONSOLE_CREDENTIALS_PATH'),
    ],
    'cloudflare' => [
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
    ],
];

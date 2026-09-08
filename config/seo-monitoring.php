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
];

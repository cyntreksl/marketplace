<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_API_BASE', 'https://api.openai.com/v1'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'category_suggestions' => [
            'model' => env('CATEGORY_SUGGESTION_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('CATEGORY_SUGGESTION_TIMEOUT', 6),
            'max_results' => (int) env('CATEGORY_SUGGESTION_MAX_RESULTS', 5),
            'candidate_limit' => (int) env('CATEGORY_SUGGESTION_CANDIDATE_LIMIT', 80),
        ],
        'product_content' => [
            'seo_model' => env('PRODUCT_SEO_SUGGESTION_MODEL', 'gpt-5.6-terra'),
            'content_model' => env('PRODUCT_CONTENT_SUGGESTION_MODEL', env('CATEGORY_SUGGESTION_MODEL', 'gpt-4o-mini')),
            'timeout' => (int) env('PRODUCT_CONTENT_SUGGESTION_TIMEOUT', env('CATEGORY_SUGGESTION_TIMEOUT', 6)),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];

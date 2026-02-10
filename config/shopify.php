<?php

return [
    'store_domain' => env('SHOPIFY_STORE_DOMAIN'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'api_version' => env('SHOPIFY_API_VERSION', '2024-01'),
    'webhook_secret' => env('SHOPIFY_WEBHOOK_SECRET'),
];

<?php

/*
| Apptagg marketplace: sell this shop's products in the Apptagg app.
|
| 1. Apptagg admin adds the shop (Admin > Shops) and gives you its API key.
| 2. Put the key and the API URL in .env.
| 3. Write a ProductMapper (and an OrderHandler to receive app orders) and set them below.
| 4. php artisan apptagg:status   then   php artisan apptagg:sync
*/
return [
    // e.g. https://apptagg.com/api/v1
    'api_url' => rtrim((string) env('APPTAGG_API_URL', ''), '/'),

    // Shop API key (shk_...) from Apptagg Admin > Shops.
    'shop_key' => env('APPTAGG_SHOP_KEY'),

    // Public base URL for relative image paths. Must open on customers' phones.
    'image_base' => rtrim((string) env('APPTAGG_IMAGE_BASE', env('APP_URL', '')), '/'),

    // Class implementing Freebirds\ApptaggShop\Contracts\ProductMapper (required).
    'product_mapper' => null,

    // Class implementing Freebirds\ApptaggShop\Contracts\OrderHandler.
    // Leave null to only list products; app orders then stay on Apptagg.
    'order_handler' => null,

    // Push a product right after it is saved or deleted (models using the SyncsToApptagg trait).
    'auto_sync' => env('APPTAGG_AUTO_SYNC', true),

    // Scheduler frequency (any Schedule method name), null to turn off.
    'schedule' => [
        'sync'        => 'hourly',       // full catalogue sync, catches anything auto-sync missed
        'pull_orders' => 'everyMinute',  // only when an order_handler is set
    ],

    // Products per bulk request (Apptagg accepts up to 200).
    'bulk_size' => 100,

    'timeout' => 30,

    // Extra tries when the connection fails (not on API errors).
    'retries' => 2,
];

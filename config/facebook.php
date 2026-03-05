<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Facebook Integration Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all settings for the Facebook 
    | integration with the ATS module. Update these values when 
    | registering your Facebook App.
    |
    */

    'enabled' => env('FACEBOOK_INTEGRATION_ENABLED', false),

    'app_id' => env('FACEBOOK_APP_ID', ''),

    'app_secret' => env('FACEBOOK_APP_SECRET', ''),

    'page_id' => env('FACEBOOK_PAGE_ID', ''),

    'page_access_token' => env('FACEBOOK_PAGE_ACCESS_TOKEN', ''),

    'graph_url' => 'https://graph.facebook.com',

    'api_version' => env('FACEBOOK_API_VERSION', 'v18.0'),

    'development_mode' => env('APP_ENV') === 'local',

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration (for future implementations)
    |--------------------------------------------------------------------------
    |
    | These settings are reserved for webhook/real-time updates.
    |
    */

    'webhook' => [
        'verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN', ''),
        'enabled' => env('FACEBOOK_WEBHOOK_ENABLED', false),
    ],
];

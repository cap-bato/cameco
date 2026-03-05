<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | This file contains feature flags that control which features are enabled
    | or disabled in the application. Set values via environment variables to
    | toggle features without code changes.
    |
    */

    'enable_loan_crud_routes' => env('FEATURE_LOAN_CRUD_ROUTES', false),
];

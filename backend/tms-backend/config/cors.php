<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // 'broadcasting/auth' is registered outside the 'api' prefix (see
    // bootstrap/app.php's withBroadcasting call), so it's excluded by
    // Laravel's stock ['api/*', 'sanctum/csrf-cookie'] default — the
    // frontend's Echo client (src/lib/echo.ts) hits this endpoint directly
    // to authenticate private-channel subscriptions and was getting no CORS
    // headers back at all when frontend and backend are on different
    // origins (the Docker Compose topology: :8081 vs :8080).
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer-token auth only (see echo.ts's `auth.headers.Authorization`) —
    // no cookies ever cross this boundary, so allowed_origins can safely
    // stay '*' without needing supports_credentials.
    'supports_credentials' => false,

];

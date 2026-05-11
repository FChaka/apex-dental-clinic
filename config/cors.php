<?php

$platformDomain = trim((string) env('APP_PLATFORM_DOMAIN', 'apex.com'));

$patterns = [
    '#^http://localhost(:\d+)?$#',
    '#^http://[\w-]+\.localhost(:\d+)?$#',
];

if ($platformDomain !== '') {
    $patterns[] = '#^https://'.preg_quote($platformDomain, '#').'$#';
    $patterns[] = '#^https://[\w-]+\.'.preg_quote($platformDomain, '#').'$#';
}

$extraOrigins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | When the SPA sends cookies (credentials: "include"), you must not use
    | allowed_origins ["*"]; the browser requires a concrete Origin and
    | supports_credentials must be true. Use allowed_origins_patterns for
    | clinic subdomains (e.g. https://{slug}.{APP_PLATFORM_DOMAIN}).
    |
    */

    'paths' => ['api/*', 'broadcasting/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique($extraOrigins)),

    'allowed_origins_patterns' => $patterns,

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => env('CORS_SUPPORTS_CREDENTIALS', true),

];

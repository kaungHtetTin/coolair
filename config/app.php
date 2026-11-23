<?php

return [
    'name' => env('APP_NAME', 'Cool Air Solution'),
    'environment' => env('APP_ENV', 'local'),
    'debug' => env('APP_ENV', 'local') !== 'production' && filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'allow_setup' => filter_var(env('APP_ALLOW_SETUP', env('APP_ENV', 'local') !== 'production'), FILTER_VALIDATE_BOOL),
    'require_https' => filter_var(env('APP_REQUIRE_HTTPS', env('APP_ENV', 'local') === 'production'), FILTER_VALIDATE_BOOL),
    'timezone' => env('APP_TIMEZONE', 'Asia/Yangon'),
    'session_name' => env('SESSION_NAME', 'cool_air_session'),
    'layout' => 'layouts/app',
];

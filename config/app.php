<?php

return [
    'name' => env('APP_NAME', 'Cool Air Solution'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'timezone' => env('APP_TIMEZONE', 'Asia/Yangon'),
    'session_name' => env('SESSION_NAME', 'cool_air_session'),
    'layout' => 'layouts/app',
];

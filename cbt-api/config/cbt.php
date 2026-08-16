<?php

return [
    'health_rate_limit' => (int) env('HEALTH_RATE_LIMIT', 120),
    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'username' => env('SUPER_ADMIN_USERNAME'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],
];

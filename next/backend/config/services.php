<?php

return [
    'eways' => [
        'base_url' => env('EWAYS_API_BASE', 'https://company.eways.co'),
        'version' => env('EWAYS_API_VERSION', '1'),
        'api_token' => env('EWAYS_API_TOKEN'),
        'app_key' => env('EWAYS_APP_KEY', 'JXZYtqDmdPqpHkYL'),
        'deposit_url' => env('EWAYS_DEPOSIT_URL', 'https://panel.eways.co'),
    ],
];

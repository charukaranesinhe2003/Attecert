<?php
declare(strict_types=1);
return [
    'base_url' => (string) env('SMSLENZ_BASE_URL', 'https://smslenz.lk/api'),
    'user_id' => (string) env('SMSLENZ_USER_ID', ''),
    'api_key' => (string) env('SMSLENZ_API_KEY', ''),
    'sender_id' => (string) env('SMSLENZ_SENDER_ID', 'AtteCert'),
    'timeout' => (int) env('SMSLENZ_TIMEOUT', 15),
];

<?php
declare(strict_types=1);
return [
    'public_required' => filter_var(env('PUBLIC_OTP_REQUIRED', 'true'), FILTER_VALIDATE_BOOL),
    'public_grant_minutes' => 30,
    'required' => filter_var(env('CUSTOMER_OTP_REQUIRED', 'true'), FILTER_VALIDATE_BOOL),
    'ttl_minutes' => 10,
    'resend_seconds' => 60,
    'max_attempts' => 5,
    'hourly_limit' => 5,
];

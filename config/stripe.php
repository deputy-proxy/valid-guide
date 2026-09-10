<?php

declare(strict_types=1);

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'api_url' => env('STRIPE_API_URL', 'https://api.stripe.com'),
    'success_url' => env('STRIPE_SUCCESS_URL', '/creator/payment/success'),
    'cancel_url' => env('STRIPE_CANCEL_URL', '/creator/payment/cancelled'),
    'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
];

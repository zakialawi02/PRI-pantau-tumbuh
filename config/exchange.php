<?php

return [
    'api_base' => env('EXCHANGE_API_BASE_URL', 'https://cdn.jsdelivr.net/gh/fawazahmed0/exchange-api@1/latest/currencies'),
    'cache_ttl' => (int) env('EXCHANGE_RATE_CACHE_TTL', 60 * 60 * 24 * 7),
];

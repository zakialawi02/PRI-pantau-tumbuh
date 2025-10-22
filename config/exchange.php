<?php

return (function () {
    $usdIdrFallback = (float) env('FALLBACK_RATE_USD_IDR', 15500);
    $idrUsdFallback = (float) env('FALLBACK_RATE_IDR_USD', round(1 / max($usdIdrFallback, 1), 8));

    return [
        'api_base_url' => env('EXCHANGE_API_BASE_URL', 'https://latest.currency-api.pages.dev/v1/currencies'),

        'cache_ttl' => [
            'seconds' => (int) env('EXCHANGE_CACHE_TTL', 86400), // 24 hours
        ],

        'fallback_rates' => [
            'USD_IDR' => $usdIdrFallback,
            'IDR_USD' => $idrUsdFallback,
        ],
    ];
})();

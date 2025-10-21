<?php

namespace App\Models;

use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class Plan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plans';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'credit_points',
        'price',
        'currency',
        'isShow',
        'isFeatured',
    ];

    protected $attributes = [
        'currency' => 'IDR',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'credit_points' => 'integer',
        'isShow' => 'boolean',
        'isFeatured' => 'boolean'
    ];

    public function priceIn(string $currency, int $precision = 2): ?float
    {
        try {
            /** @var CurrencyService $currencyService */
            $currencyService = app(CurrencyService::class);

            $baseCurrency = $this->currency ?: $currencyService->getDefaultCurrency();

            return $currencyService->convert((float) $this->price, $baseCurrency, strtoupper($currency), $precision);
        } catch (\Throwable $exception) {
            Log::warning('Unable to convert plan price to target currency.', [
                'plan_id' => $this->id,
                'current_currency' => $this->currency,
                'target_currency' => $currency,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}

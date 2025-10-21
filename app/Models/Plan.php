<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'plans';
    public $incrementing = false;
    protected $keyType = 'string';

    protected array $displayPricingCache = [];

    protected $fillable = [
        'name',
        'credit_points',
        'price',
        'currency',
        'isShow',
        'isFeatured'
    ];

    protected $appends = [
        'display_price',
        'display_currency',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'credit_points' => 'integer',
        'isShow' => 'boolean',
        'isFeatured' => 'boolean'
    ];

    public function getDisplayPriceAttribute(): float
    {
        return (float) $this->resolveDisplayPricing()['amount'];
    }

    public function getDisplayCurrencyAttribute(): string
    {
        return (string) ($this->resolveDisplayPricing()['currency'] ?? $this->currency);
    }

    protected function resolveDisplayPricing(): array
    {
        if (!array_key_exists('default', $this->displayPricingCache)) {
            $currencyService = app(\App\Services\CurrencyService::class);
            $this->displayPricingCache['default'] = $currencyService->getAmountInDefaultCurrency(
                (float) $this->price,
                $this->currency
            );
        }

        return $this->displayPricingCache['default'];
    }
}

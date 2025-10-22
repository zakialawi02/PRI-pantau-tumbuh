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

    protected $fillable = [
        'name',
        'credit_points',
        'price',
        'currency',
        'price_idr',
        'price_usd',
        'base_currency',
        'isShow',
        'isFeatured',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_idr' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'credit_points' => 'integer',
        'isShow' => 'boolean',
        'isFeatured' => 'boolean',
    ];

    public function getPriceForCurrency(string $currency): ?float
    {
        $normalizedCurrency = strtoupper($currency);

        return match ($normalizedCurrency) {
            'IDR' => $this->price_idr !== null
                ? (float) $this->price_idr
                : ($this->currency === 'IDR' ? (float) $this->price : null),
            'USD' => $this->price_usd !== null
                ? (float) $this->price_usd
                : ($this->currency === 'USD' ? (float) $this->price : null),
            default => null,
        };
    }
}

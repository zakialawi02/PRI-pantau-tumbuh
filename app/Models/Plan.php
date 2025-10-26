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
        'price_idr',
        'price_usd',
        'currency',
        'isShow',
        'isFeatured'
    ];

    protected $attributes = [
        'currency' => 'IDR',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'price_idr' => 'decimal:2',
        'price_usd' => 'decimal:2',
        'credit_points' => 'integer',
        'isShow' => 'boolean',
        'isFeatured' => 'boolean'
    ];

    public function getPriceForCurrency(string $currency): float
    {
        $currency = strtoupper($currency);

        return match ($currency) {
            'IDR' => (float) ($this->price_idr ?? $this->price ?? 0),
            default => (float) ($this->price_usd ?? $this->price ?? 0),
        };
    }
}

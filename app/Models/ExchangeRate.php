<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'base_currency',
        'target_currency',
        'rate',
        'last_synced_at',
    ];

    protected $casts = [
        'rate' => 'decimal:8',
        'last_synced_at' => 'datetime',
    ];
}

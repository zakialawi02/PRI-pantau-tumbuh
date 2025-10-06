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
        'isShow',
        'isFeatured'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'credit_points' => 'integer',
        'isShow' => 'boolean',
        'isFeatured' => 'boolean'
    ];
}

<?php

namespace App\Models;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'plans';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'price_per_hectare',
        'currency',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}

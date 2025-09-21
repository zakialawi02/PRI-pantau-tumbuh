<?php

namespace App\Models;

use App\Models\Plan;
use App\Models\User;
use App\Models\Payment;
use App\Models\FieldArea;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'subscriptions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'field_area_id',
        'plan_id',
        'price_per_hectare',
        'total_price',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'price_per_hectare' => 'decimal:2',
        'total_price' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fieldArea()
    {
        return $this->belongsTo(FieldArea::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}

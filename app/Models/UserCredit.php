<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserCredit extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'user_id',
        'credits',
    ];

    protected $casts = [
        'credits' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function histories()
    {
        return $this->hasMany(UserCreditHistory::class, 'user_id', 'user_id')->latest();
    }
}

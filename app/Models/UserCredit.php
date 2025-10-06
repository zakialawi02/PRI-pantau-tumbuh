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
        'credits' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ImageryData extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'imagery_data';

    protected $fillable = [
        'id',
        'user_id',
        'source_type',
        'original_name',
        'stored_name',
        'size',
        'format',
        'path',
        'upload_status',
        'uploaded_at',
        'processing_status',
        'processed_path',
        'processed_at'
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

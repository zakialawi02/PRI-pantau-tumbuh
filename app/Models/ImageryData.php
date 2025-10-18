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
        'geoserver_store_name',
        'geoserver_layer_name',
        'chunk_id',
        'chunk_total',
        'upload_status',
        'uploaded_at',
        'processing_status',
        'processed_path',
        'processed_geoserver_store_name',
        'processed_geoserver_layer_name',
        'processed_at',
        'scheduled_deletion_at'
    ];

    protected $casts = [
        'size' => 'decimal:2',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'chunk_total' => 'integer',
        'scheduled_deletion_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

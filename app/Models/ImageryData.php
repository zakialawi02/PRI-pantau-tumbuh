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
        'chunk_id',
        'chunk_total',
        'upload_status',
        'uploaded_at',
        'processing_status',
        'processed_path',
        'processed_at',
        'geoserver_store',
        'geoserver_layer',
        'geoserver_bbox',
        'geoserver_published_at',
        'geoserver_source_store',
        'geoserver_source_layer',
        'geoserver_source_bbox',
        'geoserver_source_published_at',
        'geoserver_processed_store',
        'geoserver_processed_layer',
        'geoserver_processed_bbox',
        'geoserver_processed_published_at',
        'scheduled_deletion_at'
    ];

    protected $casts = [
        'size' => 'decimal:2',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'chunk_total' => 'integer',
        'geoserver_bbox' => 'array',
        'geoserver_published_at' => 'datetime',
        'geoserver_source_bbox' => 'array',
        'geoserver_source_published_at' => 'datetime',
        'geoserver_processed_bbox' => 'array',
        'geoserver_processed_published_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

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
        'geoserver_wms_url',
        'geoserver_wms_params',
        'geoserver_wmts_url',
        'geoserver_wmts_layer',
        'geoserver_native_bbox',
        'geoserver_latlon_bbox',
        'geoserver_published_at',
        'geoserver_error',
        'scheduled_deletion_at'
    ];

    protected $casts = [
        'size' => 'decimal:2',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'chunk_total' => 'integer',
        'geoserver_wms_params' => 'array',
        'geoserver_native_bbox' => 'array',
        'geoserver_latlon_bbox' => 'array',
        'geoserver_published_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

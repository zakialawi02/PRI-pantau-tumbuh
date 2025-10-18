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
        'scheduled_deletion_at',
        'geoserver_workspace',
        'source_geoserver_store',
        'source_geoserver_layer',
        'source_wms_url',
        'source_wmts_url',
        'source_bbox',
        'processed_geoserver_store',
        'processed_geoserver_layer',
        'processed_wms_url',
        'processed_wmts_url',
        'processed_bbox',
    ];

    protected $casts = [
        'size' => 'decimal:2',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'chunk_total' => 'integer',
        'scheduled_deletion_at' => 'datetime',
        'source_bbox' => 'array',
        'processed_bbox' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

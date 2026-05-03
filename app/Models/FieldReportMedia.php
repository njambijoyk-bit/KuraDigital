<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldReportMedia extends Model
{
    protected $table = 'field_report_media';

    protected $fillable = [
        'field_report_id',
        'filename',
        'original_filename',
        'mime_type',
        'size',
        'disk',
        'path',
        'url',
        'thumbnail_url',
        'duration',
        'processing_status',
        'processing_result',
        'sort_order',
    ];

    protected $casts = [
        'size' => 'integer',
        'duration' => 'integer',
        'sort_order' => 'integer',
        'processing_result' => 'array',
    ];

    public function fieldReport(): BelongsTo
    {
        return $this->belongsTo(FieldReport::class);
    }

    public function sizeForHumans(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

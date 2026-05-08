<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixelTrackAccess extends Model
{
    protected $fillable = [
        'pixel_track_id',
        'uuid',
        'endpoint',
        'ip',
        'porta',
        'gmt',
        'cidade',
        'regiao',
        'pais',
        'latitude',
        'longitude',
        'gps_latitude',
        'gps_longitude',
        'gps_accuracy',
        'isp',
        'user_agent',
        'ip_local',
        'idioma',
        'plataforma',
        'resolucao',
        'referer',
        'accessed_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_latitude' => 'float',
        'gps_longitude' => 'float',
        'gps_accuracy' => 'float',
        'accessed_at' => 'datetime',
    ];

    public function pixelTrack(): BelongsTo
    {
        return $this->belongsTo(PixelTrack::class);
    }
}

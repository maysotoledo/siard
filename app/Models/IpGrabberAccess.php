<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpGrabberAccess extends Model
{
    protected $table = 'pixel_track_accesses';

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
        'identidade_nome',
        'identidade_email',
        'identidade_telefone',
        'identidade_redes',
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
        'identidade_redes' => 'array',
    ];

    public function ipGrabber(): BelongsTo
    {
        return $this->belongsTo(IpGrabber::class, 'pixel_track_id');
    }

    public function pixelTrack(): BelongsTo
    {
        return $this->belongsTo(PixelTrack::class, 'pixel_track_id');
    }
}

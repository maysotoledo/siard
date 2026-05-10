<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IpGrabberFoto extends Model
{
    protected $table = 'ip_grabber_fotos';

    protected $fillable = [
        'pixel_track_id',
        'access_uuid',
        'path',
    ];

    protected static function booted(): void
    {
        static::deleted(function (IpGrabberFoto $foto): void {
            if ($foto->path) {
                Storage::disk('public')->delete($foto->path);
            }
        });
    }

    public function ipGrabber(): BelongsTo
    {
        return $this->belongsTo(IpGrabber::class, 'pixel_track_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

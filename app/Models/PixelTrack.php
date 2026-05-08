<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PixelTrack extends Model
{
    protected $fillable = [
        'token',
        'label',
        'preview_tipo',
        'mensagem',
        'noticia_url',
        'capture_gps',
        'og_titulo',
        'og_descricao',
        'og_imagem',
        'og_imagem_upload',
        'created_by',
        'ip',
        'ip_local',
        'porta',
        'gmt',
        'idioma',
        'plataforma',
        'resolucao',
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
        'total_acessos',
        'clicked_at',
    ];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'gps_latitude' => 'float',
        'gps_longitude' => 'float',
        'gps_accuracy' => 'float',
        'capture_gps' => 'boolean',
        'clicked_at'  => 'datetime',
        'total_acessos' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleted(function (PixelTrack $pixel): void {
            if ($pixel->og_imagem_upload) {
                Storage::disk('public')->delete($pixel->og_imagem_upload);
            }
        });
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acessos(): HasMany
    {
        return $this->hasMany(PixelTrackAccess::class);
    }

    public function statusLabel(): string
    {
        return $this->clicked_at ? 'Capturado' : 'Aguardando';
    }
}

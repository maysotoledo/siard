<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixelTrack extends Model
{
    protected $fillable = [
        'token',
        'label',
        'mensagem',
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
        'isp',
        'user_agent',
        'total_acessos',
        'clicked_at',
    ];

    protected $casts = [
        'latitude'    => 'float',
        'longitude'   => 'float',
        'clicked_at'  => 'datetime',
        'total_acessos' => 'integer',
    ];

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLabel(): string
    {
        return $this->clicked_at ? 'Capturado' : 'Aguardando';
    }
}

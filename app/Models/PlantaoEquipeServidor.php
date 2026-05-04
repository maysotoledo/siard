<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantaoEquipeServidor extends Model
{
    protected $table = 'plantao_equipe_servidores';

    protected $fillable = ['equipe_id', 'user_id', 'funcao_plantao', 'ativo'];

    protected $casts = ['ativo' => 'boolean'];

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(PlantaoEquipe::class, 'equipe_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

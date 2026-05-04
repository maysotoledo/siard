<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlantaoEquipe extends Model
{
    protected $table = 'plantao_equipes';

    protected $fillable = ['nome', 'ativo', 'observacao'];

    protected $casts = ['ativo' => 'boolean'];

    public function servidores(): HasMany
    {
        return $this->hasMany(PlantaoEquipeServidor::class, 'equipe_id');
    }

    public function escalas(): HasMany
    {
        return $this->hasMany(PlantaoEscala::class, 'equipe_id');
    }
}

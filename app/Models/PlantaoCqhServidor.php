<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantaoCqhServidor extends Model
{
    protected $table = 'plantao_cqh_servidores';

    protected $fillable = ['user_id', 'unidade_operacional', 'apto_cqh', 'ordem', 'ativo', 'observacao'];

    protected $casts = ['apto_cqh' => 'boolean', 'ativo' => 'boolean', 'ordem' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (PlantaoCqhServidor $servidor): void {
            $servidor->unidade_operacional ??= 'CONFRESA';
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

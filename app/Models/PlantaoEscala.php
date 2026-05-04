<?php

namespace App\Models;

use App\Enums\PlantaoStatus;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlantaoEscala extends Model
{
    protected $table = 'plantao_escalas';

    protected $fillable = [
        'equipe_id',
        'data_plantao',
        'horario_inicio',
        'horario_fim',
        'cqh_pessoa',
        'cqh_geral_type',
        'cqh_geral_id',
        'dpc_nome',
        'dpc_contato',
        'status',
        'observacao',
        'criado_por',
    ];

    protected $casts = [
        'data_plantao' => 'date',
        'status' => PlantaoStatus::class,
    ];

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(PlantaoEquipe::class, 'equipe_id');
    }

    public function cqhGeral(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'cqh_geral_type', 'cqh_geral_id');
    }

    public function permutas(): HasMany
    {
        return $this->hasMany(PlantaoPermuta::class, 'escala_id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(PlantaoHistorico::class, 'escala_id');
    }

    public function delegadoDelta(): HasOne
    {
        return $this->hasOne(PlantaoDelegadoEscala::class, 'data_plantao', 'data_plantao');
    }

    public function getCqhPessoaAttribute(): ?string
    {
        return match ($this->cqh_geral_type) {
            User::class => $this->cqh_geral_id ? 'user:'.$this->cqh_geral_id : null,
            PlantaoCqhExterno::class => $this->cqh_geral_id ? 'externo:'.$this->cqh_geral_id : null,
            default => null,
        };
    }

    public function setCqhPessoaAttribute(null|string|int $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['cqh_geral_type'] = null;
            $this->attributes['cqh_geral_id'] = null;
            return;
        }

        if (is_int($value) || ctype_digit((string) $value)) {
            $this->attributes['cqh_geral_type'] = User::class;
            $this->attributes['cqh_geral_id'] = (int) $value;
            return;
        }

        [$tipo, $id] = array_pad(explode(':', (string) $value, 2), 2, null);
        $this->attributes['cqh_geral_type'] = match ($tipo) {
            'user' => User::class,
            'externo' => PlantaoCqhExterno::class,
            default => throw new InvalidArgumentException('Tipo de CQH inválido.'),
        };
        $this->attributes['cqh_geral_id'] = (int) $id;
    }
}

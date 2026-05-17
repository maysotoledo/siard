<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AnaliseInvestigation;

class InvestigationContext extends Model
{
    use HasFactory;
    protected $fillable = [
        'analise_run_id',
        'analise_investigation_id',
        'user_id',
        'titulo',
        'numero_bo',
        'numero_procedimento',
        'natureza',
        'vitimas',
        'suspeitos',
        'unidade_policial',
        'arquivo_original',
        'arquivo_path',
        'arquivo_mime',
        'texto_extraido',
        'resumo_contexto',
        'metadata',
    ];

    protected $casts = [
        'vitimas'   => 'array',
        'suspeitos' => 'array',
        'metadata'  => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analiseRun(): BelongsTo
    {
        return $this->belongsTo(AnaliseRun::class);
    }

    public function analiseInvestigation(): BelongsTo
    {
        return $this->belongsTo(AnaliseInvestigation::class);
    }

    public function aiReports(): HasMany
    {
        return $this->hasMany(AiReport::class);
    }

    public function hasTextoExtraido(): bool
    {
        return trim((string) $this->texto_extraido) !== '';
    }
}

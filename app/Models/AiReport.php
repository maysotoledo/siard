<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReport extends Model
{
    use HasFactory;
    protected $fillable = [
        'investigation_context_id',
        'analise_run_id',
        'user_id',
        'tipo',
        'provider',
        'model',
        'prompt',
        'resposta',
        'status',
        'erro',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function investigationContext(): BelongsTo
    {
        return $this->belongsTo(InvestigationContext::class);
    }

    public function analiseRun(): BelongsTo
    {
        return $this->belongsTo(AnaliseRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

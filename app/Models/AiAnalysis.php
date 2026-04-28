<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class AiAnalysis extends Model
{
    private static ?bool $hasStatusColumnCache = null;
    private static ?bool $hasErroColumnCache = null;
    private static ?bool $hasProgressColumnCache = null;

    protected $fillable = [
        'analise_run_id',
        'user_id',
        'tipo',
        'status',
        'progress',
        'modelo',
        'pergunta',
        'contexto',
        'resposta',
        'erro',
    ];

    protected $casts = [
        'contexto' => 'array',
    ];

    public function analiseRun(): BelongsTo
    {
        return $this->belongsTo(AnaliseRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hasStatusColumn(): bool
    {
        if (self::$hasStatusColumnCache === null) {
            self::$hasStatusColumnCache = Schema::hasColumn('ai_analyses', 'status');
        }

        return self::$hasStatusColumnCache;
    }

    public static function hasErroColumn(): bool
    {
        if (self::$hasErroColumnCache === null) {
            self::$hasErroColumnCache = Schema::hasColumn('ai_analyses', 'erro');
        }

        return self::$hasErroColumnCache;
    }

    public static function hasProgressColumn(): bool
    {
        if (self::$hasProgressColumnCache === null) {
            self::$hasProgressColumnCache = Schema::hasColumn('ai_analyses', 'progress');
        }

        return self::$hasProgressColumnCache;
    }
}

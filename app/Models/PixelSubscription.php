<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixelSubscription extends Model
{
    public const PIX_KEY = 'andersontoledo@pjc.mt.gov.br';

    protected $fillable = [
        'user_id',
        'paid_at',
        'expires_at',
        'access_enabled',
        'released_by',
        'released_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'expires_at' => 'date',
            'access_enabled' => 'boolean',
            'released_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $subscription): void {
            if ($subscription->access_enabled) {
                $subscription->released_at ??= now();
                $subscription->released_by ??= auth()->id();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isActive(): bool
    {
        return (bool) $this->access_enabled
            && $this->expires_at !== null
            && $this->expires_at->endOfDay()->isFuture();
    }

    public static function pixKey(): string
    {
        return self::PIX_KEY;
    }
}

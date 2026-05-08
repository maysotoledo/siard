<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixelModuleSetting extends Model
{
    protected $fillable = [
        'payment_enabled',
    ];

    protected function casts(): array
    {
        return [
            'payment_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'payment_enabled' => true,
        ]);
    }

    public static function isPaymentEnabled(): bool
    {
        return static::current()->payment_enabled;
    }
}

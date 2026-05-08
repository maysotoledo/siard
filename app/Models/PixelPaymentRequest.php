<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PixelPaymentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'external_reference',
        'mercado_pago_payment_id',
        'amount',
        'status',
        'status_detail',
        'pix_copy_paste',
        'qr_code_base64',
        'ticket_url',
        'expires_at',
        'approved_at',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'in_process', 'waiting_payment', 'action_required'], true);
    }
}

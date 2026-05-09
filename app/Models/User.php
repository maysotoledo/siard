<?php

namespace App\Models;

use App\Mail\VerifyEmailMailable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\Auditable;


class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Auditable;

    protected $fillable = [
        'name',
        'email',
        'telefone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'                    => 'datetime',
            'email_verification_token_expires_at'  => 'datetime',
            'password'                             => 'hashed',
        ];
    }

    public function analiseRuns(): HasMany
    {
        return $this->hasMany(AnaliseRun::class);
    }

    public function pixelPaymentRequests(): HasMany
    {
        return $this->hasMany(PixelPaymentRequest::class);
    }

    public function pixelSubscription(): HasOne
    {
        return $this->hasOne(PixelSubscription::class);
    }

    public function hasActivePixelSubscription(): bool
    {
        return $this->pixelSubscription?->isActive() ?? false;
    }

    /**
     * super_admin nunca precisa verificar e-mail.
     */
    public function hasVerifiedEmail(): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->email_verified_at !== null;
    }

    /**
     * Envia e-mail de verificação com token próprio via SMTP do sistema.
     */
    public function sendEmailVerificationNotification(): void
    {
        $token = Str::random(64);

        $this->forceFill([
            'email_verification_token'            => $token,
            'email_verification_token_expires_at' => now()->addHours(24),
        ])->save();

        Mail::to($this->email)->send(new VerifyEmailMailable($this));
    }
}

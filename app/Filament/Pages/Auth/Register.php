<?php

namespace App\Filament\Pages\Auth;

use App\Mail\VerifyEmailMailable;
use App\Models\User;
use Filament\Auth\Pages\Register as BaseRegister;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class Register extends BaseRegister
{
    /**
     * Sobrescreve o envio de verificação do Filament para usar
     * Mail::send() direto (SMTP do sistema) em vez de notify() com fila.
     */
    protected function sendEmailVerificationNotification(Model $user): void
    {
        if (! $user instanceof MustVerifyEmail) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $token = Str::random(64);

        /** @var User $user */
        $user->forceFill([
            'email_verification_token'            => $token,
            'email_verification_token_expires_at' => now()->addHours(24),
        ])->save();

        Mail::to($user->email)->send(new VerifyEmailMailable($user));
    }
}

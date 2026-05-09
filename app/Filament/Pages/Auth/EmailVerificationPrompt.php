<?php

namespace App\Filament\Pages\Auth;

use App\Mail\VerifyEmailMailable;
use App\Models\User;
use Filament\Auth\Pages\EmailVerification\EmailVerificationPrompt as BasePrompt;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailVerificationPrompt extends BasePrompt
{
    public function mount(): void
    {
        if ((! Filament::auth()->check()) || $this->getVerifiable()->hasVerifiedEmail()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $sessionKey = 'email_verification_notification_sent_for_user_' . Filament::auth()->id();

        if (! session()->has($sessionKey)) {
            $this->sendEmailVerificationNotification($this->getVerifiable());

            session()->put($sessionKey, true);
        }
    }

    /**
     * Substitui o envio via notify() (fila) por Mail::send() direto (SMTP).
     */
    protected function sendEmailVerificationNotification(MustVerifyEmail $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $token = $user->email_verification_token;

        if (blank($token) || $user->email_verification_token_expires_at?->isPast()) {
            $token = Str::random(64);

            $user->forceFill([
                'email_verification_token'            => $token,
                'email_verification_token_expires_at' => now()->addHours(24),
            ])->save();
        }

        /** @var User $user */
        Mail::to($user->email)->send(new VerifyEmailMailable($user));
    }
}

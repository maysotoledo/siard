<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $verificationUrl;

    public function __construct(public User $user)
    {
        $this->verificationUrl = route('auth.verify-email', [
            'token' => $user->email_verification_token,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirme seu e-mail — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.verify-email',
            with: [
                'user'            => $this->user,
                'verificationUrl' => $this->verificationUrl,
            ],
        );
    }
}

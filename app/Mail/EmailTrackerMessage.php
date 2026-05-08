<?php

namespace App\Mail;

use App\Models\IpGrabber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmailTrackerMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public IpGrabber $tracker,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nova mensagem',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.email-tracker-message',
            with: [
                'tracker' => $this->tracker,
            ],
        );
    }
}

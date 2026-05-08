<?php

namespace App\Services\EmailTrackers;

use App\Mail\EmailTrackerMessage;
use App\Models\IpGrabber;
use Illuminate\Support\Facades\Mail;

class EmailTrackerMailer
{
    public function send(IpGrabber $tracker): void
    {
        Mail::to($tracker->target_email)->send(new EmailTrackerMessage($tracker));

        $tracker->forceFill([
            'sent_at' => now(),
        ])->save();
    }
}

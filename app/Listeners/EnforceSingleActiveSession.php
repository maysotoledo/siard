<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

class EnforceSingleActiveSession
{
    public function handle(Login $event): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $sessionId = session()->getId();

        if ($sessionId === '') {
            return;
        }

        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $event->user->getAuthIdentifier())
            ->where('id', '!=', $sessionId)
            ->delete();
    }
}

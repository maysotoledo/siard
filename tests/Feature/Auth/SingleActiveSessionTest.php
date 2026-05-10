<?php

use App\Listeners\EnforceSingleActiveSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('novo login remove outras sessoes ativas do mesmo usuario', function (): void {
    config()->set('session.driver', 'database');
    $currentSessionId = str_repeat('a', 40);

    session()->setId($currentSessionId);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'sessao-antiga',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => $currentSessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'sessao-outro-usuario',
            'user_id' => $otherUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ],
    ]);

    app(EnforceSingleActiveSession::class)->handle(new Login('web', $user, false));

    expect(DB::table('sessions')->pluck('id')->all())
        ->toBe([$currentSessionId, 'sessao-outro-usuario']);
});

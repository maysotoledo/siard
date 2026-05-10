<?php

use App\Models\PixelSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('usuario recebe role user ao validar email', function (): void {
    $user = User::factory()->unverified()->create([
        'email_verification_token' => 'token-valido',
        'email_verification_token_expires_at' => now()->addHour(),
    ]);

    $this->get('/auth/verify-email/token-valido')
        ->assertRedirect();

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasRole('user'))->toBeTrue()
        ->and(Role::where('name', 'user')->exists())->toBeTrue();
});

test('usuario ganha cinco dias de acesso ao validar email', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-09 10:00:00'));

    $user = User::factory()->unverified()->create([
        'email_verification_token' => 'token-acesso',
        'email_verification_token_expires_at' => now()->addHour(),
    ]);

    $this->get('/auth/verify-email/token-acesso')
        ->assertRedirect();

    $subscription = PixelSubscription::query()
        ->where('user_id', $user->id)
        ->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->access_enabled)->toBeTrue()
        ->and($subscription->expires_at->toDateString())->toBe('2026-05-14');

    Carbon::setTestNow();
});

test('validacao de email nao substitui role administrativa existente', function (): void {
    Role::findOrCreate('admin');

    $user = User::factory()->unverified()->create([
        'email_verification_token' => 'token-admin',
        'email_verification_token_expires_at' => now()->addHour(),
    ]);

    $user->assignRole('admin');

    $this->get('/auth/verify-email/token-admin')
        ->assertRedirect();

    $user->refresh();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasRole('admin'))->toBeTrue()
        ->and($user->hasRole('user'))->toBeFalse();
});

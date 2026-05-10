<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PixelSubscription;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class EmailVerificationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $user = User::query()
            ->where('email_verification_token', $token)
            ->first();

        $loginUrl = Filament::getPanel('admin')->getLoginUrl();

        if (! $user) {
            return redirect()->to($loginUrl)
                ->with('status', 'error')
                ->with('message', 'Link de verificação inválido ou já utilizado.');
        }

        if ($user->email_verification_token_expires_at->isPast()) {
            return redirect()->to($loginUrl)
                ->with('status', 'error')
                ->with('message', 'Link de verificação expirado. Faça login para receber um novo.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (! $user->hasAnyRole(['user', 'admin', 'super_admin'])) {
            Role::findOrCreate('user');
            $user->assignRole('user');
        }

        $subscription = PixelSubscription::firstOrNew([
            'user_id' => $user->id,
        ]);

        $trialExpiresAt = now()->addDays(5)->toDateString();

        if (! $subscription->exists || ! $subscription->expires_at || $subscription->expires_at->lt($trialExpiresAt)) {
            $subscription->expires_at = $trialExpiresAt;
        }

        $subscription->access_enabled = true;
        $subscription->released_at ??= now();
        $subscription->notes ??= 'Acesso liberado automaticamente por 5 dias após validação do e-mail.';
        $subscription->save();

        $user->forceFill([
            'email_verification_token'            => null,
            'email_verification_token_expires_at' => null,
        ])->save();

        return redirect()->to($loginUrl)
            ->with('status', 'success')
            ->with('message', 'E-mail confirmado com sucesso! Faça login para acessar o sistema.');
    }
}

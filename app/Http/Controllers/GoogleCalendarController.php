<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleCalendarController extends Controller
{
    private function canManageOwnGoogleCalendar($user): bool
    {
        return (bool) $user && ($user->hasRole('epc') || $user->hasRole('cartorio_central'));
    }

    public function redirect(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageOwnGoogleCalendar($user), 403);

        return redirect()->away($googleCalendar->getAuthorizationUrl($user));
    }

    public function callback(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageOwnGoogleCalendar($user), 403);

        if ($request->query('error')) {
            Notification::make()
                ->title('Google Agenda não conectado')
                ->body('A autorização foi cancelada ou recusada pelo Google.')
                ->danger()
                ->send();

            return redirect()->to(\Filament\Facades\Filament::getUrl());
        }

        if (
            ! hash_equals((string) session('google_calendar_oauth_state'), (string) $request->query('state'))
            || (int) session('google_calendar_oauth_user_id') !== (int) $user->getKey()
        ) {
            Notification::make()
                ->title('Google Agenda não conectado')
                ->body('A sessão de autorização expirou ou foi iniciada em outro domínio. Clique em conectar Google Agenda novamente.')
                ->danger()
                ->send();

            return redirect()->to(\Filament\Facades\Filament::getUrl());
        }

        $googleCalendar->connect($user, (string) $request->query('code'));

        $request->session()->forget([
            'google_calendar_oauth_state',
            'google_calendar_oauth_user_id',
        ]);

        Notification::make()
            ->title('Google Agenda conectado')
            ->body('Os próximos agendamentos criados para você serão enviados automaticamente para sua agenda Google.')
            ->success()
            ->send();

        return redirect()->to(\Filament\Facades\Filament::getUrl());
    }

    public function disconnect(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        $user = $request->user();

        abort_unless($this->canManageOwnGoogleCalendar($user), 403);

        $googleCalendar->disconnect($user);

        Notification::make()
            ->title('Google Agenda desconectado')
            ->success()
            ->send();

        return back();
    }
}

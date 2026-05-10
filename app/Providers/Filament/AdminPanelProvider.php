<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\EmailVerificationPrompt;
use App\Filament\Pages\Auth\Register;
use App\Filament\Widgets\DashboardAccountWidget;
use App\Filament\Widgets\SubscriptionStatusWidget;
use App\Http\Middleware\RequireActiveSubscription;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Carbon\Carbon;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->registration(Register::class)
            ->emailVerification(EmailVerificationPrompt::class)
            ->globalSearch(false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('5s')
            ->profile(ChangePassword::class)
            ->brandName('SIARD')
            ->brandLogo(asset('images/siard-logo.png'))
            ->brandLogoHeight('5rem')
            ->favicon(asset('images/siard-logo.png'))
            ->colors([
                'primary' => Color::Blue,
            ])
            ->navigationGroups([
                'Análise Telemática',
                'Investigação Telemática',
                'Rastreamento IP',
                'Inteligência Artificial',
                'Administração do Sistema',
                'Logs',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                DashboardAccountWidget::class,
                SubscriptionStatusWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Administração do Sistema')
                    ->navigationLabel('Funções'),
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireActiveSubscription::class,
            ], isPersistent: true)
            ->bootUsing(function () {
                app()->setLocale(config('app.locale'));
                Carbon::setLocale(config('app.locale'));
            });
    }
}

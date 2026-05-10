<?php

namespace App\Providers;

use App\Http\Responses\LogoutResponse;
use App\Services\Queue\QueueHealthService;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app['events']->listen(Looping::class, function (): void {
            app(QueueHealthService::class)->touchHeartbeat();
        });
    }
}

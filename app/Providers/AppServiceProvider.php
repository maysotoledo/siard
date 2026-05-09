<?php

namespace App\Providers;

use App\Services\Queue\QueueHealthService;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

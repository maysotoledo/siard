<?php

namespace App\Providers;

use App\Listeners\EnforceSingleActiveSession;
use App\Listeners\LogAccessEvents;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            EnforceSingleActiveSession::class,
            LogAccessEvents::class,
        ],
        Failed::class => [LogAccessEvents::class],
    ];
}

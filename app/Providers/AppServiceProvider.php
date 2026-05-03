<?php

namespace App\Providers;

use App\Models\Evento;
use App\Models\AfastamentoPeriodoAquisitivo;
use App\Models\AfastamentoPeriodoBloqueado;
use App\Models\AfastamentoRegra;
use App\Models\AfastamentoRegraOperacional;
use App\Models\AfastamentoSolicitacao;
use App\Policies\AfastamentoPeriodoAquisitivoPolicy;
use App\Policies\AfastamentoPeriodoBloqueadoPolicy;
use App\Policies\AfastamentoRegraOperacionalPolicy;
use App\Policies\AfastamentoRegraPolicy;
use App\Policies\AfastamentoSolicitacaoPolicy;
use App\Observers\EventoObserver;
use App\Policies\EventoPolicy;
use App\Services\Queue\QueueHealthService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\Looping;
use Symfony\Component\Mime\Address;

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
        Evento::observe(EventoObserver::class);
        Gate::policy(Evento::class, EventoPolicy::class);
        Gate::policy(AfastamentoSolicitacao::class, AfastamentoSolicitacaoPolicy::class);
        Gate::policy(AfastamentoPeriodoAquisitivo::class, AfastamentoPeriodoAquisitivoPolicy::class);
        Gate::policy(AfastamentoRegra::class, AfastamentoRegraPolicy::class);
        Gate::policy(AfastamentoRegraOperacional::class, AfastamentoRegraOperacionalPolicy::class);
        Gate::policy(AfastamentoPeriodoBloqueado::class, AfastamentoPeriodoBloqueadoPolicy::class);

        $this->app['events']->listen(MessageSending::class, function (MessageSending $event): void {
            Log::channel('agenda_mail')->info('Laravel iniciou envio SMTP.', [
                'mailer' => $event->data['mailer'] ?? config('mail.default'),
                'subject' => $event->message?->getSubject(),
                'to' => array_map(
                    fn (Address $address) => $address->getAddress(),
                    array_values($event->message?->getTo() ?? []),
                ),
            ]);
        });

        $this->app['events']->listen(MessageSent::class, function (MessageSent $event): void {
            Log::channel('agenda_mail')->info('Laravel confirmou envio ao transport.', [
                'mailer' => $event->data['mailer'] ?? config('mail.default'),
                'subject' => $event->message?->getSubject(),
                'to' => array_map(
                    fn (Address $address) => $address->getAddress(),
                    array_values($event->message?->getTo() ?? []),
                ),
            ]);
        });

        $this->app['events']->listen(Looping::class, function (): void {
            app(QueueHealthService::class)->touchHeartbeat();
        });
    }
}

<?php

namespace App\Providers;

use App\Filament\Widgets\AfastamentosAllWidget;
use App\Filament\Widgets\AfastamentosApprovalWidget;
use App\Filament\Widgets\AfastamentosCalendarWidget;
use App\Filament\Widgets\AfastamentosStatsWidget;
use App\Filament\Widgets\AfastamentosUpcomingWidget;
use App\Filament\Widgets\CalendarWidget;
use App\Filament\Widgets\FeriasCalendarWidget;
use App\Filament\Widgets\SelecionarUsuarioAgendaWidget;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class LivewireComponentsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Livewire::component(
            'app.filament.widgets.selecionar-usuario-agenda-widget',
            SelecionarUsuarioAgendaWidget::class
        );

        Livewire::component(
            'app.filament.widgets.calendar-widget',
            CalendarWidget::class
        );

        // ✅ REGISTRA O WIDGET DE FÉRIAS (resolve o erro do componente não encontrado)
        Livewire::component(
            'app.filament.widgets.ferias-calendar-widget',
            FeriasCalendarWidget::class
        );

        Livewire::component(
            'app.filament.widgets.afastamentos-stats-widget',
            AfastamentosStatsWidget::class
        );

        Livewire::component(
            'app.filament.widgets.afastamentos-upcoming-widget',
            AfastamentosUpcomingWidget::class
        );

        Livewire::component(
            'app.filament.widgets.afastamentos-approval-widget',
            AfastamentosApprovalWidget::class
        );

        Livewire::component(
            'app.filament.widgets.afastamentos-all-widget',
            AfastamentosAllWidget::class
        );

        Livewire::component(
            'app.filament.widgets.afastamentos-calendar-widget',
            AfastamentosCalendarWidget::class
        );
    }
}

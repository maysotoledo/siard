<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AfastamentosAllWidget;
use App\Filament\Widgets\AfastamentosApprovalWidget;
use App\Filament\Widgets\AfastamentosPriorityWidget;
use App\Filament\Widgets\AfastamentosStatsWidget;
use App\Filament\Widgets\AfastamentosUpcomingWidget;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use UnitEnum;

class DashboardAfastamentos extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'Administração de Afastamento';
    protected static ?string $title = 'Administração de Afastamento';
    protected static ?string $slug = 'dashboard-afastamentos';
    protected string $view = 'filament.pages.dashboard-afastamentos';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    public static function getNavigationSort(): ?int
    {
        return 60;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AfastamentosStatsWidget::class,
            AfastamentosApprovalWidget::class,
            AfastamentosPriorityWidget::class,
            AfastamentosAllWidget::class,
            AfastamentosUpcomingWidget::class,
        ];
    }
}

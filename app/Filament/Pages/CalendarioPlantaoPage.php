<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PlantaoCalendarWidget;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class CalendarioPlantaoPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationLabel = 'Calendário de Plantão';
    protected static ?string $title = 'Calendário de Plantão';
    protected static ?string $slug = 'calendario-plantao';
    protected string $view = 'filament.pages.calendario-plantao';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Gestão Administrativa';
    }

    protected function getHeaderWidgets(): array
    {
        return [PlantaoCalendarWidget::class];
    }
}

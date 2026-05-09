<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getColumns(): int | array
    {
        return 1;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sacat-dashboard'];
    }
}

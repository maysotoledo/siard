<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

class DashboardAccountWidget extends AccountWidget
{
    protected string $view = 'filament.widgets.dashboard-account-widget';

    protected int | string | array $columnSpan = 'full';
}

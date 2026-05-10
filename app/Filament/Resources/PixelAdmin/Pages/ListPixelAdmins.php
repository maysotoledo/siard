<?php

namespace App\Filament\Resources\PixelAdmin\Pages;

use App\Filament\Resources\PixelAdmin\PixelAdminResource;
use App\Filament\Resources\PixelAdmin\Widgets\AccessesOverview;
use App\Filament\Resources\PixelAdmin\Widgets\AccessEvolutionChart;
use App\Filament\Resources\PixelAdmin\Widgets\AdminOperationsOverview;
use App\Filament\Resources\PixelAdmin\Widgets\MonthlyReceivablesChart;
use App\Filament\Resources\PixelAdmin\Widgets\PaymentStatusChart;
use App\Filament\Resources\PixelAdmin\Widgets\ReceivablesOverview;
use App\Filament\Resources\PixelAdmin\Widgets\SubscriptionHealthChart;
use App\Filament\Resources\PixelAdmin\Widgets\UserGrowthChart;
use Filament\Resources\Pages\ListRecords;

class ListPixelAdmins extends ListRecords
{
    protected static string $resource = PixelAdminResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ReceivablesOverview::class,
            AccessesOverview::class,
            AdminOperationsOverview::class,
            MonthlyReceivablesChart::class,
            AccessEvolutionChart::class,
            PaymentStatusChart::class,
            SubscriptionHealthChart::class,
            UserGrowthChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

}

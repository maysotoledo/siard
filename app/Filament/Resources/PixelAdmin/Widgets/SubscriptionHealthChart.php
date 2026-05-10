<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\PixelSubscription;
use Filament\Widgets\ChartWidget;

class SubscriptionHealthChart extends ChartWidget
{
    protected ?string $heading = 'Saúde das assinaturas';

    protected ?string $description = 'Situação atual dos acessos aos rastreadores.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $today = now()->toDateString();
        $nextSevenDays = now()->addDays(7)->toDateString();

        $active = PixelSubscription::query()
            ->where('access_enabled', true)
            ->whereDate('expires_at', '>', $nextSevenDays)
            ->count();

        $expiring = PixelSubscription::query()
            ->where('access_enabled', true)
            ->whereBetween('expires_at', [$today, $nextSevenDays])
            ->count();

        $expired = PixelSubscription::query()
            ->whereDate('expires_at', '<', $today)
            ->count();

        $blocked = PixelSubscription::query()
            ->where('access_enabled', false)
            ->count();

        $labels = ['Ativas', 'Vencem em 7 dias', 'Expiradas', 'Bloqueadas'];
        $values = [$active, $expiring, $expired, $blocked];

        if (array_sum($values) === 0) {
            $labels = ['Sem dados'];
            $values = [1];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Assinaturas',
                    'data' => $values,
                    'backgroundColor' => [
                        '#16a34a',
                        '#f59e0b',
                        '#ef4444',
                        '#64748b',
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

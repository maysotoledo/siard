<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UserGrowthChart extends ChartWidget
{
    protected ?string $heading = 'Novos usuários';

    protected ?string $description = 'Cadastros criados nos últimos 12 meses.';

    protected string $color = 'info';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(11);
        $labels = [];
        $values = [];

        foreach (range(0, 11) as $offset) {
            $month = $start->copy()->addMonths($offset);
            $labels[] = ucfirst($month->translatedFormat('M/y'));
            $values[] = User::query()
                ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Usuários',
                    'data' => $values,
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

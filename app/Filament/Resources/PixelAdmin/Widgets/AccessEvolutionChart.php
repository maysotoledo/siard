<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\IpGrabberAccess;
use Filament\Widgets\ChartWidget;

class AccessEvolutionChart extends ChartWidget
{
    protected ?string $heading = 'Evolução de acessos';

    protected ?string $description = 'Acessos capturados nos últimos 30 dias.';

    protected string $color = 'primary';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $start = now()->copy()->startOfDay()->subDays(29);
        $labels = [];
        $values = [];

        foreach (range(0, 29) as $offset) {
            $day = $start->copy()->addDays($offset);
            $labels[] = $day->format('d/m');
            $values[] = IpGrabberAccess::query()
                ->whereBetween('accessed_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Acessos',
                    'data' => $values,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.14)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

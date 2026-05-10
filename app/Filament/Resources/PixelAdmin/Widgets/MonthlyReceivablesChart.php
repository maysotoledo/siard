<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\PixelPaymentRequest;
use Filament\Widgets\ChartWidget;

class MonthlyReceivablesChart extends ChartWidget
{
    protected ?string $heading = 'Recebimentos mensais';

    protected ?string $description = 'Pagamentos aprovados nos últimos 12 meses.';

    protected string $color = 'success';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $start = now()->copy()->startOfMonth()->subMonths(11);
        $labels = [];
        $values = [];

        foreach (range(0, 11) as $offset) {
            $month = $start->copy()->addMonths($offset);
            $labels[] = ucfirst($month->translatedFormat('M/y'));
            $values[] = (float) PixelPaymentRequest::query()
                ->where('status', 'approved')
                ->whereNotNull('approved_at')
                ->whereBetween('approved_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->sum('amount');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Recebido',
                    'data' => $values,
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.16)',
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

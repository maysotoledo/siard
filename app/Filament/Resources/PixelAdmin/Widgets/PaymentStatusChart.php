<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\PixelPaymentRequest;
use Filament\Widgets\ChartWidget;

class PaymentStatusChart extends ChartWidget
{
    protected ?string $heading = 'Status dos pagamentos';

    protected ?string $description = 'Distribuição dos pedidos de pagamento no ano atual.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $statuses = [
            'approved' => 'Aprovados',
            'pending' => 'Pendentes',
            'in_process' => 'Em análise',
            'waiting_payment' => 'Aguardando',
            'rejected' => 'Rejeitados',
            'cancelled' => 'Cancelados',
            'expired' => 'Expirados',
        ];

        $labels = [];
        $values = [];

        foreach ($statuses as $status => $label) {
            $count = PixelPaymentRequest::query()
                ->where('status', $status)
                ->whereBetween('created_at', [now()->copy()->startOfYear(), now()->copy()->endOfYear()])
                ->count();

            if ($count === 0) {
                continue;
            }

            $labels[] = $label;
            $values[] = $count;
        }

        if ($values === []) {
            $labels = ['Sem dados'];
            $values = [1];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Pagamentos',
                    'data' => $values,
                    'backgroundColor' => [
                        '#16a34a',
                        '#f59e0b',
                        '#3b82f6',
                        '#06b6d4',
                        '#ef4444',
                        '#64748b',
                        '#a855f7',
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

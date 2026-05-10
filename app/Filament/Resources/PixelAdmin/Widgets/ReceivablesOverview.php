<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\PixelPaymentRequest;
use App\Models\PixelSubscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReceivablesOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Dashboard de recebimentos';

    protected ?string $description = 'Resumo financeiro dos pagamentos aprovados dos rastreadores.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();
        $startOfPreviousMonth = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $endOfPreviousMonth = $now->copy()->subMonthNoOverflow()->endOfMonth();
        $startOfYear = $now->copy()->startOfYear();
        $endOfYear = $now->copy()->endOfYear();

        $totalReceived = $this->approvedPayments()->sum('amount');
        $monthReceived = $this->approvedPayments()
            ->whereBetween('approved_at', [$startOfMonth, $endOfMonth])
            ->sum('amount');
        $previousMonthReceived = $this->approvedPayments()
            ->whereBetween('approved_at', [$startOfPreviousMonth, $endOfPreviousMonth])
            ->sum('amount');
        $yearReceived = $this->approvedPayments()
            ->whereBetween('approved_at', [$startOfYear, $endOfYear])
            ->sum('amount');

        $approvedCount = $this->approvedPayments()->count();
        $activeSubscriptions = PixelSubscription::query()
            ->where('access_enabled', true)
            ->whereDate('expires_at', '>=', $now->toDateString())
            ->count();

        $monthDelta = (float) $monthReceived - (float) $previousMonthReceived;
        $monthDeltaDescription = $monthDelta >= 0
            ? '+' . $this->formatCurrency($monthDelta) . ' vs. mês anterior'
            : '-' . $this->formatCurrency(abs($monthDelta)) . ' vs. mês anterior';

        return [
            Stat::make('Total recebido', $this->formatCurrency($totalReceived))
                ->description($approvedCount . ' pagamento(s) aprovado(s)')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->monthlyRevenueSeries(8)),

            Stat::make('Recebido no mês', $this->formatCurrency($monthReceived))
                ->description($monthDeltaDescription)
                ->descriptionIcon($monthDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($monthDelta >= 0 ? 'success' : 'danger')
                ->chart($this->dailyRevenueSeries()),

            Stat::make('Recebido no ano', $this->formatCurrency($yearReceived))
                ->description($now->year . ' acumulado')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->chart($this->monthlyRevenueSeries(12)),

            Stat::make('Assinaturas ativas', (string) $activeSubscriptions)
                ->description('Usuários com acesso liberado e não expirado')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('primary'),
        ];
    }

    private function approvedPayments(): \Illuminate\Database\Eloquent\Builder
    {
        return PixelPaymentRequest::query()
            ->where('status', 'approved')
            ->whereNotNull('approved_at');
    }

    private function formatCurrency(float|int|string|null $value): string
    {
        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    /**
     * @return array<float>
     */
    private function monthlyRevenueSeries(int $months): array
    {
        $start = now()->copy()->startOfMonth()->subMonths($months - 1);

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start): float {
                $month = $start->copy()->addMonths($offset);

                return (float) $this->approvedPayments()
                    ->whereBetween('approved_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->sum('amount');
            })
            ->all();
    }

    /**
     * @return array<float>
     */
    private function dailyRevenueSeries(): array
    {
        $start = now()->copy()->startOfMonth();
        $daysElapsed = max(1, $start->diffInDays(now()) + 1);

        return collect(range(0, min($daysElapsed, 31) - 1))
            ->map(function (int $offset) use ($start): float {
                $day = $start->copy()->addDays($offset);

                return (float) $this->approvedPayments()
                    ->whereBetween('approved_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
                    ->sum('amount');
            })
            ->all();
    }
}

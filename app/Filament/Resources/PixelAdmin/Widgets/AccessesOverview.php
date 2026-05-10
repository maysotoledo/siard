<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\IpGrabberAccess;
use App\Models\SiteAccess;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AccessesOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Indicadores de acessos';

    protected ?string $description = 'Volume de acessos capturados pelos rastreadores.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $now = now();
        $startOfDay = $now->copy()->startOfDay();
        $endOfDay = $now->copy()->endOfDay();
        $startOfYesterday = $now->copy()->subDay()->startOfDay();
        $endOfYesterday = $now->copy()->subDay()->endOfDay();

        $totalAccesses = $this->accessCount();
        $todayAccesses = $this->accessCount($startOfDay, $endOfDay);
        $yesterdayAccesses = $this->accessCount($startOfYesterday, $endOfYesterday);
        $onlineUsers = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->distinct('user_id')
            ->count('user_id');

        $todayDelta = $todayAccesses - $yesterdayAccesses;
        $todayDescription = match (true) {
            $todayDelta > 0 => '+' . $todayDelta . ' vs. ontem',
            $todayDelta < 0 => $todayDelta . ' vs. ontem',
            default => 'Mesmo volume de ontem',
        };

        return [
            Stat::make('Quantidade de acessos', (string) $totalAccesses)
                ->description('Página inicial e rastreadores')
                ->descriptionIcon('heroicon-m-signal')
                ->color('primary')
                ->chart($this->dailyAccessSeries(8)),

            Stat::make('Acessos hoje', (string) $todayAccesses)
                ->description($todayDescription)
                ->descriptionIcon($todayDelta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todayDelta >= 0 ? 'success' : 'danger')
                ->chart($this->hourlyAccessSeries()),

            Stat::make('Usuários online', (string) $onlineUsers)
                ->description('Ativos nos últimos 5 minutos')
                ->descriptionIcon('heroicon-m-wifi')
                ->color($onlineUsers > 0 ? 'success' : 'gray'),
        ];
    }

    /**
     * @return array<int>
     */
    private function dailyAccessSeries(int $days): array
    {
        $start = now()->copy()->startOfDay()->subDays($days - 1);

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start): int {
                $day = $start->copy()->addDays($offset);

                return $this->accessCount($day->copy()->startOfDay(), $day->copy()->endOfDay());
            })
            ->all();
    }

    /**
     * @return array<int>
     */
    private function hourlyAccessSeries(): array
    {
        $start = now()->copy()->startOfDay();
        $currentHour = (int) now()->format('G');

        return collect(range(0, $currentHour))
            ->map(function (int $hour) use ($start): int {
                $period = $start->copy()->addHours($hour);

                return $this->accessCount($period, $period->copy()->endOfHour());
            })
            ->all();
    }

    private function accessCount(mixed $start = null, mixed $end = null): int
    {
        $trackerQuery = IpGrabberAccess::query();
        $siteQuery = SiteAccess::query();

        if ($start && $end) {
            $trackerQuery->whereBetween('accessed_at', [$start, $end]);
            $siteQuery->whereBetween('accessed_at', [$start, $end]);
        }

        return $trackerQuery->count() + $siteQuery->count();
    }
}

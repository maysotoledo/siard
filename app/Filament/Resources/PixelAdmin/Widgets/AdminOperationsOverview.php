<?php

namespace App\Filament\Resources\PixelAdmin\Widgets;

use App\Models\PixelPaymentRequest;
use App\Models\PixelSubscription;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminOperationsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Indicadores administrativos';

    protected ?string $description = 'Acompanhamento operacional de usuários, pagamentos pendentes e vencimentos.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $now = now();
        $nextSevenDays = $now->copy()->addDays(7);

        $totalUsers = User::query()->count();
        $verifiedUsers = User::query()->whereNotNull('email_verified_at')->count();
        $pendingPayments = PixelPaymentRequest::query()
            ->whereIn('status', ['pending', 'in_process', 'waiting_payment', 'action_required'])
            ->count();
        $releasedUsers = PixelSubscription::query()
            ->where('access_enabled', true)
            ->whereDate('expires_at', '>=', $now->toDateString())
            ->distinct('user_id')
            ->count('user_id');
        $expiringSoon = PixelSubscription::query()
            ->where('access_enabled', true)
            ->whereBetween('expires_at', [$now->toDateString(), $nextSevenDays->toDateString()])
            ->count();
        $expiredSubscriptions = PixelSubscription::query()
            ->whereDate('expires_at', '<', $now->toDateString())
            ->count();
        $admins = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['admin', 'super_admin']))
            ->count();
        $onlineUsers = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->distinct('user_id')
            ->count('user_id');

        $verifiedRate = $totalUsers > 0 ? round(($verifiedUsers / $totalUsers) * 100) : 0;

        return [
            Stat::make('Usuários cadastrados', (string) $totalUsers)
                ->description($verifiedRate . '% com e-mail validado')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($verifiedRate >= 80 ? 'success' : 'warning')
                ->chart($this->userGrowthSeries(8)),

            Stat::make('Pagamentos pendentes', (string) $pendingPayments)
                ->description('Aguardando confirmação ou ação')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingPayments > 0 ? 'warning' : 'success'),

            Stat::make('Usuários liberados', (string) $releasedUsers)
                ->description('Com acesso mensal ativo')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),

            Stat::make('Usuários online', (string) $onlineUsers)
                ->description('Ativos nos últimos 5 minutos')
                ->descriptionIcon('heroicon-m-wifi')
                ->color($onlineUsers > 0 ? 'success' : 'gray'),

            Stat::make('Expiram em 7 dias', (string) $expiringSoon)
                ->description('Assinaturas ativas próximas do vencimento')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($expiringSoon > 0 ? 'warning' : 'success'),

            Stat::make('Assinaturas expiradas', (string) $expiredSubscriptions)
                ->description('Registros com vencimento anterior a hoje')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($expiredSubscriptions > 0 ? 'danger' : 'success'),

            Stat::make('Administradores', (string) $admins)
                ->description('Usuários com papel admin ou super_admin')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('info'),
        ];
    }

    /**
     * @return array<int>
     */
    private function userGrowthSeries(int $months): array
    {
        $start = now()->copy()->startOfMonth()->subMonths($months - 1);

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start): int {
                $month = $start->copy()->addMonths($offset);

                return User::query()
                    ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                    ->count();
            })
            ->all();
    }
}

<?php

namespace App\Services\AnaliseInteligente\Platform;

use App\Models\AnaliseRun;
use App\Models\AnaliseRunEvent;
use App\Models\AnaliseRunIp;
use Carbon\Carbon;

class PlatformRunSummaryService
{
    public function buildSummary(AnaliseRun $run): array
    {
        $base = (array) ($run->summary ?? []);
        $accessEvents = $run->events()->where('event_type', 'access');
        $totalEvents = (clone $accessEvents)->count();
        $totalUniqueIps = $run->ips()->count();
        $totalUniqueIpv4 = $run->ips()->where('ip', 'not like', '%:%')->count();
        $totalUniqueIpv6 = $run->ips()->where('ip', 'like', '%:%')->count();
        $mapsCount = $run->events()->where('event_type', 'map')->count();
        $searchCount = $run->events()->where('event_type', 'search')->count();
        $nightTotalEvents = $this->countNightEvents($run->id);
        $mobileTotalEvents = $this->countMobileEvents($run->id);
        $userAgentsCount = $run->events()->where('event_type', 'access')->whereNotNull('user_agent')->distinct('user_agent')->count('user_agent');
        $deviceIdentifiersCount = $run->events()->where('event_type', 'access')->whereNotNull('device_identifier_value')->distinct('device_identifier_value')->count('device_identifier_value');

        $period = $run->events()
            ->where('event_type', 'access')
            ->selectRaw('MIN(occurred_at) as first_seen, MAX(occurred_at) as last_seen')
            ->first();

        $summary = array_merge($base, [
            'period_label' => $this->formatPeriodLabel($period?->first_seen, $period?->last_seen),
            'total_events' => $totalEvents,
            'total_unique_ips' => $totalUniqueIps,
            'total_unique_ipv4' => $totalUniqueIpv4,
            'total_unique_ipv6' => $totalUniqueIpv6,
            'platform_label' => $base['_platform_label'] ?? ucfirst((string) ($run->source ?? '')),
            'investigation_hints' => $this->buildHints(
                totalEvents: $totalEvents,
                totalUniqueIps: $totalUniqueIps,
                nightTotalEvents: $nightTotalEvents,
                mobileTotalEvents: $mobileTotalEvents,
                accountsFound: count((array) ($base['accounts_found'] ?? [])),
                identifiersFound: count((array) ($base['identifiers_found'] ?? [])),
                mapsCount: $mapsCount,
                searchCount: $searchCount,
            ),
            'night_total_events' => $nightTotalEvents,
            'mobile_total_events' => $mobileTotalEvents,
            '_counts' => [
                'timeline' => $totalEvents,
                'unique_ips' => $totalUniqueIps,
                'providers' => $this->countProviders($run->id),
                'cities' => $this->countCities($run->id),
                'maps' => $mapsCount,
                'search' => $searchCount,
                'user_agents' => $userAgentsCount + $deviceIdentifiersCount,
                'residencial' => $nightTotalEvents,
                'movel' => $mobileTotalEvents,
            ],
        ]);

        $run->forceFill([
            'summary' => $summary,
            'status' => 'done',
            'progress' => 100,
            'finished_at' => now(),
        ])->save();

        return $summary;
    }

    public function providerIpRows(int $runId, string $provider): array
    {
        return AnaliseRunIp::query()
            ->where('analise_run_id', $runId)
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->whereRaw("COALESCE(NULLIF(ip_enrichments.isp, ''), NULLIF(ip_enrichments.org, ''), 'Desconhecido') = ?", [$provider])
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->get([
                'analise_run_ips.ip',
                'analise_run_ips.occurrences as count',
                'analise_run_ips.last_seen_at',
                'ip_enrichments.city',
                'ip_enrichments.mobile',
            ])
            ->map(fn (AnaliseRunIp $row): array => [
                'ip' => $row->ip,
                'count' => (int) $row->count,
                'last_seen' => optional($row->last_seen_at)->timezone('America/Sao_Paulo')?->format('d/m/Y H:i:s'),
                'city' => trim((string) ($row->city ?? '')) ?: 'Desconhecida',
                'connection_type' => ($row->mobile ?? false) ? 'Movel' : 'Residencial',
            ])
            ->all();
    }

    private function countProviders(int $runId): int
    {
        return AnaliseRunIp::query()
            ->where('analise_run_id', $runId)
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->selectRaw("COALESCE(NULLIF(ip_enrichments.isp, ''), NULLIF(ip_enrichments.org, ''), 'Desconhecido') as provider")
            ->groupBy('provider')
            ->get()
            ->count();
    }

    private function countCities(int $runId): int
    {
        return AnaliseRunIp::query()
            ->where('analise_run_id', $runId)
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->selectRaw("COALESCE(NULLIF(ip_enrichments.city, ''), 'Desconhecida') as city")
            ->groupBy('city')
            ->get()
            ->count();
    }

    private function countNightEvents(int $runId): int
    {
        return AnaliseRunEvent::query()
            ->where('analise_run_id', $runId)
            ->where('event_type', 'access')
            ->where(function ($builder): void {
                $builder
                    ->whereRaw('HOUR(CONVERT_TZ(occurred_at, "+00:00", "-03:00")) >= 23')
                    ->orWhereRaw('HOUR(CONVERT_TZ(occurred_at, "+00:00", "-03:00")) <= 6');
            })
            ->count();
    }

    private function countMobileEvents(int $runId): int
    {
        return AnaliseRunEvent::query()
            ->where('analise_run_id', $runId)
            ->where('event_type', 'access')
            ->join('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_events.ip')
            ->where('ip_enrichments.mobile', true)
            ->count();
    }

    private function formatPeriodLabel(mixed $firstSeen, mixed $lastSeen): ?string
    {
        if (! $firstSeen || ! $lastSeen) {
            return null;
        }

        $start = Carbon::parse($firstSeen, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
        $end = Carbon::parse($lastSeen, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s');

        return "{$start} ate {$end}";
    }

    private function buildHints(
        int $totalEvents,
        int $totalUniqueIps,
        int $nightTotalEvents,
        int $mobileTotalEvents,
        int $accountsFound,
        int $identifiersFound,
        int $mapsCount,
        int $searchCount,
    ): array {
        $hints = [];

        if ($totalEvents > 0 && $totalUniqueIps === 0) {
            $hints[] = 'Ha eventos, mas nenhum IP unico consolidado.';
        }

        if ($nightTotalEvents > 0) {
            $hints[] = 'Ha eventos no periodo noturno, uteis para vinculo residencial.';
        }

        if ($mobileTotalEvents > 0) {
            $hints[] = 'Ha eventos em conexao movel, o que pode exigir requisicao a operadora.';
        }

        if ($accountsFound > 1) {
            $hints[] = 'Mais de uma conta/e-mail foi encontrada no material importado.';
        }

        if ($identifiersFound > 0) {
            $hints[] = 'Foram encontrados identificadores adicionais de dispositivo/conta.';
        }

        if ($mapsCount > 0) {
            $hints[] = 'Foram encontrados eventos de Maps.';
        }

        if ($searchCount > 0) {
            $hints[] = 'Foram encontradas pesquisas em mecanismos de busca.';
        }

        return $hints;
    }
}

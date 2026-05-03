<?php

namespace App\Services\AnaliseInteligente\Pdf;

use App\Filament\Pages\RelatoriosProcessados;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use App\Models\AnaliseRunEvent;
use App\Models\AnaliseRunIp;
use App\Models\Bilhetagem;
use App\Models\IpEnrichment;
use App\Services\AnaliseInteligente\Google\GoogleReportAggregator;
use App\Services\AnaliseInteligente\Instagram\ReportAggregator as InstagramReportAggregator;
use App\Services\AnaliseInteligente\Platform\PlatformRunSummaryService;
use App\Services\AnaliseInteligente\RunPayloadStorage;
use App\Services\AnaliseInteligente\Whatsapp\ReportAggregator as WhatsappReportAggregator;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class InvestigationPdfDataBuilder
{
    private const PDF_LIMITS = [
        'timeline_rows' => 120,
        'unique_ip_rows' => 80,
        'provider_stats_rows' => 50,
        'city_stats_rows' => 50,
        'groups_rows' => 80,
        'bilhetagem_cards' => 80,
        'vinculo_rows' => 80,
        'maps_rows' => 60,
        'search_rows' => 60,
        'user_agent_rows' => 40,
        'device_identifier_rows' => 40,
        'night_events_rows' => 60,
        'mobile_events_rows' => 60,
        'direct_threads' => 40,
        'followers' => 120,
        'following' => 120,
    ];

    public function build(AnaliseInvestigation $investigation): array
    {
        $this->assertCanView($investigation);

        $runs = $investigation->runs()
            ->orderBy('id')
            ->get();

        return [
            'investigation' => [
                'id' => $investigation->id,
                'name' => $investigation->name,
                'source' => $investigation->source,
                'source_label' => $this->sourceLabel((string) $investigation->source),
                'created_at' => $investigation->created_at?->timezone('America/Sao_Paulo')?->format('d/m/Y H:i:s'),
                'runs_count' => $runs->count(),
            ],
            'runs' => $runs->map(fn (AnaliseRun $run): array => $this->buildRun($investigation, $run))->all(),
            'generated_at' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'),
        ];
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            'instagram' => 'Instagram',
            'google' => 'Google',
            'apple' => 'Apple',
            'whatsapp' => 'WhatsApp',
            'generico' => 'Genérico',
            default => 'Genérico',
        };
    }

    private function assertCanView(AnaliseInvestigation $investigation): void
    {
        if ((int) $investigation->user_id === (int) auth()->id()) {
            return;
        }

        if (method_exists(RelatoriosProcessados::class, 'canAccess') && RelatoriosProcessados::canAccess()) {
            return;
        }

        throw new AuthorizationException();
    }

    private function buildRun(AnaliseInvestigation $investigation, AnaliseRun $run): array
    {
        $report = match ($investigation->source) {
            'whatsapp' => $this->buildWhatsappReport($run),
            'instagram' => $this->buildInstagramReport($run),
            default => $this->buildPlatformReport($investigation, $run),
        };

        if (is_array($report)) {
            $report = $this->applyPdfLimits($report);
        }

        return [
            'id' => (int) $run->id,
            'target' => $this->resolveTarget($investigation, $run, $report),
            'status' => (string) $run->status,
            'progress' => (int) $run->progress,
            'created_at' => $run->created_at?->timezone('America/Sao_Paulo')?->format('d/m/Y H:i:s'),
            'finished_at' => $run->finished_at?->timezone('America/Sao_Paulo')?->format('d/m/Y H:i:s'),
            'report' => $report,
        ];
    }

    private function buildWhatsappReport(AnaliseRun $run): ?array
    {
        $parsed = app(RunPayloadStorage::class)->loadParsedPayload($run);
        if (! is_array($parsed)) {
            return null;
        }

        $report = (new WhatsappReportAggregator())->buildReport($parsed, $this->loadEnrichedByIp($run));
        $report['bilhetagem_cards'] = $this->buildBilhetagemCardsFromDb($run);
        $report['vinculo_rows'] = $this->buildWhatsappVinculoRows($run);

        return $this->compactWhatsappPdfReport($this->enhanceWhatsappPdfReport($report));
    }

    private function buildInstagramReport(AnaliseRun $run): ?array
    {
        $parsed = app(RunPayloadStorage::class)->loadParsedPayload($run);
        if (! is_array($parsed)) {
            return null;
        }

        return $this->compactInstagramPdfReport(
            (new InstagramReportAggregator())->buildReport($parsed, $this->loadEnrichedByIp($run))
        );
    }

    private function buildPlatformReport(AnaliseInvestigation $investigation, AnaliseRun $run): array
    {
        $summary = (array) ($run->summary ?: app(PlatformRunSummaryService::class)->buildSummary($run));
        $enrichedByIp = $this->loadEnrichedByIp($run);

        $report = array_merge($summary, [
            'selected_target' => $this->resolvePlatformTarget($run),
            'timeline_rows' => $this->buildPlatformTimelineRows($run),
            'unique_ip_rows' => $this->buildPlatformUniqueIpRows($run),
            'provider_stats_rows' => $this->buildPlatformProviderRows($run),
            'city_stats_rows' => $this->buildPlatformCityRows($run),
            'maps_rows' => $this->buildPlatformMapRows($run),
            'search_rows' => $this->buildPlatformSearchRows($run),
            'user_agent_rows' => $this->buildPlatformUserAgentRows($run),
            'device_identifier_rows' => $this->buildPlatformDeviceIdentifierRows($run),
            'night_events_rows' => $this->buildPlatformTimelineRows($run, 'night'),
            'mobile_events_rows' => $this->buildPlatformTimelineRows($run, 'mobile'),
            'vinculo_rows' => $investigation->source === 'google' ? $this->buildGoogleVinculoRows($run) : [],
        ]);

        $report['_counts']['timeline'] = count($report['timeline_rows']);
        $report['_counts']['unique_ips'] = count($report['unique_ip_rows']);
        $report['_counts']['providers'] = count($report['provider_stats_rows']);
        $report['_counts']['cities'] = count($report['city_stats_rows']);
        $report['_counts']['maps'] = count($report['maps_rows']);
        $report['_counts']['search'] = count($report['search_rows']);
        $report['_counts']['user_agents'] = count($report['user_agent_rows']) + count($report['device_identifier_rows']);
        $report['_counts']['residencial'] = count($report['night_events_rows']);
        $report['_counts']['movel'] = count($report['mobile_events_rows']);
        $report['_counts']['vinculo'] = count($report['vinculo_rows']);

        if ($investigation->source === 'google') {
            $parsed = app(RunPayloadStorage::class)->loadParsedPayload($run);
            if (is_array($parsed)) {
                $googleReport = (new GoogleReportAggregator())->buildReport($parsed, $enrichedByIp);
                $report['subscriber_info'] = $googleReport['subscriber_info'] ?? ($report['subscriber_info'] ?? null);
                $report['accounts_found'] = $googleReport['accounts_found'] ?? ($report['accounts_found'] ?? []);
                $report['phones_found'] = $googleReport['phones_found'] ?? ($report['phones_found'] ?? []);
                $report['identifiers_found'] = $googleReport['identifiers_found'] ?? ($report['identifiers_found'] ?? []);
                $report['google_activity_is_supplemental'] = (bool) ($googleReport['google_activity_is_supplemental'] ?? true);
                $report['investigation_hints'] = $googleReport['investigation_hints'] ?? ($report['investigation_hints'] ?? []);
                $report['vinculo_rows'] = $this->buildGoogleVinculoRows($run);
                $report['_counts']['vinculo'] = count($report['vinculo_rows']);
            }
        }

        return $report;
    }

    private function loadEnrichedByIp(AnaliseRun $run): array
    {
        $ips = AnaliseRunIp::query()
            ->where('analise_run_id', $run->id)
            ->pluck('ip')
            ->all();

        if (count($ips) === 0) {
            return [];
        }

        $enrichments = IpEnrichment::query()
            ->whereIn('ip', $ips)
            ->get()
            ->keyBy('ip');

        $mapped = [];

        foreach ($ips as $ip) {
            $enrichment = $enrichments->get($ip);

            $mapped[$ip] = [
                'ip' => $ip,
                'city' => $enrichment?->city,
                'isp' => $enrichment?->isp,
                'org' => $enrichment?->org,
                'mobile' => $enrichment?->mobile,
            ];
        }

        return $mapped;
    }

    private function resolveTarget(AnaliseInvestigation $investigation, AnaliseRun $run, ?array $report): string
    {
        if ($investigation->source === 'whatsapp') {
            return trim((string) ($run->target ?: data_get($report, 'target') ?: 'Run ' . $run->id));
        }

        if ($investigation->source === 'instagram') {
            $target = trim((string) (
                data_get($report, 'vanity_name')
                ?: data_get($report, 'account_identifier')
                ?: data_get($report, 'target')
                ?: $run->target
            ));

            return $target !== '' ? $target : 'Run ' . $run->id;
        }

        $target = $this->resolvePlatformTarget($run);

        return $target !== '' ? $target : 'Run ' . $run->id;
    }

    private function resolvePlatformTarget(AnaliseRun $run): string
    {
        $target = trim((string) ($run->target ?? ''));
        if ($target !== '') {
            return $target;
        }

        return trim((string) (
            data_get($run->summary, 'subscriber_info.email')
            ?: data_get($run->summary, 'subscriber_info.account_id')
            ?: data_get($run->summary, 'accounts_found.0')
            ?: data_get($run->summary, 'identifiers_found.0.value')
            ?: ''
        ));
    }

    private function buildPlatformTimelineRows(AnaliseRun $run, ?string $scope = null): array
    {
        $limit = match ($scope) {
            'night' => self::PDF_LIMITS['night_events_rows'] + 1,
            'mobile' => self::PDF_LIMITS['mobile_events_rows'] + 1,
            default => self::PDF_LIMITS['timeline_rows'] + 1,
        };

        $query = AnaliseRunEvent::query()
            ->with('ipEnrichment')
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'access');

        if ($scope === 'night') {
            $query->where(function ($builder): void {
                $builder
                    ->whereRaw('HOUR(CONVERT_TZ(occurred_at, "+00:00", "-03:00")) >= 23')
                    ->orWhereRaw('HOUR(CONVERT_TZ(occurred_at, "+00:00", "-03:00")) <= 6');
            });
        }

        if ($scope === 'mobile') {
            $query->whereHas('ipEnrichment', fn ($builder) => $builder->where('mobile', true));
        }

        return $query
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (AnaliseRunEvent $event): array => [
                'datetime' => $event->datetime_local,
                'ip' => $event->ip,
                'provider' => $event->provider_label,
                'city' => $event->city_label,
                'type' => $event->connection_type,
                'period' => $event->period_flags,
                'logical_port' => $event->logical_port,
                'action' => $event->action,
            ])
            ->all();
    }

    private function buildPlatformUniqueIpRows(AnaliseRun $run): array
    {
        return AnaliseRunIp::query()
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->where('analise_run_id', $run->id)
            ->selectRaw("
                analise_run_ips.ip,
                analise_run_ips.occurrences as count,
                analise_run_ips.last_seen_at,
                COALESCE(NULLIF(ip_enrichments.isp, ''), NULLIF(ip_enrichments.org, ''), 'Desconhecido') as provider,
                COALESCE(NULLIF(ip_enrichments.city, ''), 'Desconhecida') as city,
                CASE WHEN ip_enrichments.mobile = 1 THEN 'Movel' ELSE 'Residencial' END as connection_type
            ")
            ->orderByDesc('count')
            ->orderByDesc('last_seen_at')
            ->limit(self::PDF_LIMITS['unique_ip_rows'] + 1)
            ->get()
            ->map(fn ($row): array => [
                'ip' => $row->ip,
                'provider' => $row->provider,
                'city' => $row->city,
                'type' => $row->connection_type,
                'count' => (int) $row->count,
                'last_seen' => $row->last_seen_at?->timezone('America/Sao_Paulo')?->format('d/m/Y H:i:s'),
            ])
            ->all();
    }

    private function buildPlatformProviderRows(AnaliseRun $run): array
    {
        return AnaliseRunIp::query()
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->where('analise_run_id', $run->id)
            ->selectRaw("
                COALESCE(NULLIF(ip_enrichments.isp, ''), NULLIF(ip_enrichments.org, ''), 'Desconhecido') as provider,
                SUM(analise_run_ips.occurrences) as occurrences,
                COUNT(*) as unique_ips,
                COUNT(DISTINCT COALESCE(NULLIF(ip_enrichments.city, ''), 'Desconhecida')) as cities,
                SUM(CASE WHEN ip_enrichments.mobile = 1 THEN analise_run_ips.occurrences ELSE 0 END) as mobile_occurrences,
                MAX(analise_run_ips.last_seen_at) as last_seen_at
            ")
            ->groupBy('provider')
            ->orderByDesc('occurrences')
            ->limit(self::PDF_LIMITS['provider_stats_rows'] + 1)
            ->get()
            ->map(fn ($row): array => [
                'provider' => $row->provider,
                'occurrences' => (int) $row->occurrences,
                'unique_ips' => (int) $row->unique_ips,
                'cities' => (int) $row->cities,
                'mobile_occurrences' => (int) $row->mobile_occurrences,
                'mobile_percent' => (int) $row->occurrences > 0
                    ? round(((int) $row->mobile_occurrences / (int) $row->occurrences) * 100, 2)
                    : 0,
                'last_seen' => $row->last_seen_at ? Carbon::parse($row->last_seen_at, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
            ])
            ->all();
    }

    private function buildPlatformCityRows(AnaliseRun $run): array
    {
        return AnaliseRunIp::query()
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->where('analise_run_id', $run->id)
            ->selectRaw("
                COALESCE(NULLIF(ip_enrichments.city, ''), 'Desconhecida') as city,
                SUM(analise_run_ips.occurrences) as occurrences,
                COUNT(*) as unique_ips,
                COUNT(DISTINCT COALESCE(NULLIF(ip_enrichments.isp, ''), NULLIF(ip_enrichments.org, ''), 'Desconhecido')) as providers,
                SUM(CASE WHEN ip_enrichments.mobile = 1 THEN analise_run_ips.occurrences ELSE 0 END) as mobile_occurrences,
                MAX(analise_run_ips.last_seen_at) as last_seen_at
            ")
            ->groupBy('city')
            ->orderByDesc('occurrences')
            ->limit(self::PDF_LIMITS['city_stats_rows'] + 1)
            ->get()
            ->map(fn ($row): array => [
                'city' => $row->city,
                'occurrences' => (int) $row->occurrences,
                'unique_ips' => (int) $row->unique_ips,
                'providers' => (int) $row->providers,
                'mobile_occurrences' => (int) $row->mobile_occurrences,
                'mobile_percent' => (int) $row->occurrences > 0
                    ? round(((int) $row->mobile_occurrences / (int) $row->occurrences) * 100, 2)
                    : 0,
                'last_seen' => $row->last_seen_at ? Carbon::parse($row->last_seen_at, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
            ])
            ->all();
    }

    private function buildPlatformMapRows(AnaliseRun $run): array
    {
        return AnaliseRunEvent::query()
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'map')
            ->orderByDesc('occurred_at')
            ->limit(self::PDF_LIMITS['maps_rows'] + 1)
            ->get()
            ->map(fn (AnaliseRunEvent $event): array => [
                'type' => $event->category,
                'description' => $event->description,
                'target' => $event->target,
                'origin' => $event->origin,
                'datetime' => $event->datetime_local,
                'url' => $event->url,
            ])
            ->all();
    }

    private function buildPlatformSearchRows(AnaliseRun $run): array
    {
        return AnaliseRunEvent::query()
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'search')
            ->orderByDesc('occurred_at')
            ->limit(self::PDF_LIMITS['search_rows'] + 1)
            ->get()
            ->map(fn (AnaliseRunEvent $event): array => [
                'datetime' => $event->datetime_local,
                'target' => $event->target,
            ])
            ->all();
    }

    private function buildPlatformUserAgentRows(AnaliseRun $run): array
    {
        return AnaliseRunEvent::query()
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'access')
            ->whereNotNull('user_agent')
            ->selectRaw('user_agent, COUNT(*) as occurrences, MAX(occurred_at) as occurred_at')
            ->groupBy('user_agent')
            ->orderByDesc('occurrences')
            ->limit(self::PDF_LIMITS['user_agent_rows'] + 1)
            ->get()
            ->map(fn ($row): array => [
                'user_agent' => $row->user_agent,
                'occurrences' => (int) $row->occurrences,
                'last_seen' => $row->occurred_at ? Carbon::parse($row->occurred_at, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
            ])
            ->all();
    }

    private function buildPlatformDeviceIdentifierRows(AnaliseRun $run): array
    {
        return AnaliseRunEvent::query()
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'access')
            ->whereNotNull('device_identifier_value')
            ->selectRaw('device_identifier_type as type, device_identifier_value as value, COUNT(*) as occurrences, MAX(occurred_at) as occurred_at')
            ->groupBy('device_identifier_type', 'device_identifier_value')
            ->orderByDesc('occurrences')
            ->limit(self::PDF_LIMITS['device_identifier_rows'] + 1)
            ->get()
            ->map(fn ($row): array => [
                'type' => $row->type,
                'value' => $row->value,
                'occurrences' => (int) $row->occurrences,
                'last_seen' => $row->occurred_at ? Carbon::parse($row->occurred_at, 'UTC')->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
            ])
            ->all();
    }

    private function buildBilhetagemCardsFromDb(AnaliseRun $run): array
    {
        $summaryRows = Bilhetagem::query()
            ->where('analise_run_id', $run->id)
            ->select('recipient')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('recipient')
            ->groupBy('recipient')
            ->orderByDesc('total')
            ->limit(self::PDF_LIMITS['bilhetagem_cards'] + 1)
            ->get();

        if ($summaryRows->isEmpty()) {
            return [];
        }

        $cards = [];
        $latestIps = [];

        foreach ($summaryRows as $summary) {
            $recipient = trim((string) $summary->recipient);
            if ($recipient === '') {
                continue;
            }

            $latest = Bilhetagem::query()
                ->where('analise_run_id', $run->id)
                ->where('recipient', $recipient)
                ->orderByDesc('timestamp_utc')
                ->orderByDesc('id')
                ->first(['timestamp_utc', 'message_id', 'sender_ip', 'sender_port', 'type']);

            $latestRow = null;
            if ($latest) {
                $ipBase = $this->extractIpBase($latest->sender_ip);
                if ($ipBase) {
                    $latestIps[$ipBase] = true;
                }

                $latestRow = [
                    'timestamp' => $latest->timestamp_utc?->timezone('America/Sao_Paulo')?->format('d/m/Y H:i:s'),
                    'message_id' => $latest->message_id,
                    'sender_ip' => $latest->sender_ip,
                    'sender_port' => $latest->sender_port,
                    'type' => $latest->type,
                ];
            }

            $cards[] = [
                'recipient' => $recipient,
                'total' => (int) $summary->total,
                'latest' => $latestRow,
            ];
        }

        $enrichments = count($latestIps) > 0
            ? IpEnrichment::query()->whereIn('ip', array_keys($latestIps))->get()->keyBy('ip')
            : collect();

        foreach ($cards as &$card) {
            $ipBase = $this->extractIpBase(data_get($card, 'latest.sender_ip'));
            $enrichment = $ipBase ? $enrichments->get($ipBase) : null;

            $card['latest_provider'] = trim((string) (($enrichment?->isp ?? '') ?: ($enrichment?->org ?? ''))) ?: 'Desconhecido';
            $card['latest_city'] = trim((string) ($enrichment?->city ?? '')) ?: 'Desconhecida';
            $card['latest_type'] = ($enrichment?->mobile ?? false) ? 'Movel' : 'Residencial';
        }
        unset($card);

        return $cards;
    }

    private function buildWhatsappVinculoRows(AnaliseRun $currentRun): array
    {
        if (! $currentRun->investigation_id) {
            return [];
        }

        $currentParsed = app(RunPayloadStorage::class)->loadParsedPayload($currentRun) ?? [];
        if (! is_array($currentParsed)) {
            return [];
        }

        $currentIps = $this->extractConnectionIpsForVinculo($currentParsed);
        if (count($currentIps) === 0) {
            return [];
        }

        $otherRuns = AnaliseRun::query()
            ->where('investigation_id', $currentRun->investigation_id)
            ->whereKeyNot($currentRun->id)
            ->get();

        if ($otherRuns->isEmpty()) {
            return [];
        }

        $byIp = [];

        foreach ($otherRuns as $run) {
            $parsed = app(RunPayloadStorage::class)->loadParsedPayload($run) ?? [];
            if (! is_array($parsed)) {
                continue;
            }

            $otherIps = $this->extractConnectionIpsForVinculo($parsed);
            if (count($otherIps) === 0) {
                continue;
            }

            $sharedIps = array_intersect(array_keys($currentIps), array_keys($otherIps));
            if (count($sharedIps) === 0) {
                continue;
            }

            $target = $run->target ?: ('Run ' . $run->id);

            foreach ($sharedIps as $ip) {
                $currentMeta = $currentIps[$ip] ?? null;
                $otherMeta = $otherIps[$ip] ?? null;

                if (! is_array($currentMeta) || ! is_array($otherMeta)) {
                    continue;
                }

                $byIp[$ip] ??= [
                    'ip' => $ip,
                    'accesses' => [[
                        'run_id' => $currentRun->id,
                        'target' => (string) ($currentRun->target ?: ('Run ' . $currentRun->id)),
                        'count' => (int) ($currentMeta['occurrences'] ?? 0),
                        'first_seen' => $this->formatCarbonForVinculo($currentMeta['first_seen_at'] ?? null),
                        'last_seen' => $this->formatCarbonForVinculo($currentMeta['last_seen_at'] ?? null),
                        'times' => $currentMeta['times'] ?? [],
                        'is_selected' => true,
                    ]],
                    'targets_count' => 1,
                    'total_occurrences' => (int) ($currentMeta['occurrences'] ?? 0),
                    'last_seen_at' => null,
                ];

                $byIp[$ip]['accesses'][] = [
                    'run_id' => $run->id,
                    'target' => (string) $target,
                    'count' => (int) ($otherMeta['occurrences'] ?? 0),
                    'first_seen' => $this->formatCarbonForVinculo($otherMeta['first_seen_at'] ?? null),
                    'last_seen' => $this->formatCarbonForVinculo($otherMeta['last_seen_at'] ?? null),
                    'times' => $otherMeta['times'] ?? [],
                    'is_selected' => false,
                ];
                $byIp[$ip]['targets_count']++;
                $byIp[$ip]['total_occurrences'] += (int) ($otherMeta['occurrences'] ?? 0);

                foreach (array_filter([
                    $currentMeta['last_seen_at'] ?? null,
                    $otherMeta['last_seen_at'] ?? null,
                ], fn ($value) => $value instanceof Carbon) as $lastSeenAt) {
                    if (
                        $byIp[$ip]['last_seen_at'] === null ||
                        $lastSeenAt->greaterThan($byIp[$ip]['last_seen_at'])
                    ) {
                        $byIp[$ip]['last_seen_at'] = $lastSeenAt;
                    }
                }
            }
        }

        if (count($byIp) === 0) {
            return [];
        }

        $enrichments = IpEnrichment::query()->whereIn('ip', array_keys($byIp))->get()->keyBy('ip');
        $rows = [];

        foreach ($byIp as $ip => $row) {
            $enrichment = $enrichments->get($ip);
            $provider = trim((string) (($enrichment?->isp ?? '') ?: ($enrichment?->org ?? '')));

            usort($row['accesses'], fn (array $left, array $right): int => (($right['is_selected'] ?? false) <=> ($left['is_selected'] ?? false))
                ?: strcmp((string) ($left['target'] ?? ''), (string) ($right['target'] ?? '')));

            $rows[] = [
                'ip' => $ip,
                'targets' => implode(' | ', array_map(fn (array $access): string => (string) ($access['target'] ?? ''), $row['accesses'])),
                'targets_count' => (int) ($row['targets_count'] ?? count($row['accesses'])),
                'total_occurrences' => (int) $row['total_occurrences'],
                'last_seen' => $row['last_seen_at'] ? $row['last_seen_at']->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
                'provider' => $provider !== '' ? $provider : 'Desconhecido',
                'city' => $enrichment?->city ?: 'Desconhecida',
                'type' => ($enrichment?->mobile ?? false) ? 'Movel' : 'Residencial',
                'accesses' => $row['accesses'],
            ];
        }

        usort($rows, fn (array $a, array $b): int => ($b['targets_count'] <=> $a['targets_count'])
            ?: ($b['total_occurrences'] <=> $a['total_occurrences'])
            ?: strcmp((string) $a['ip'], (string) $b['ip']));

        return $rows;
    }

    private function buildGoogleVinculoRows(AnaliseRun $currentRun): array
    {
        if (! $currentRun->investigation_id) {
            return [];
        }

        $currentTarget = $this->resolvePlatformTarget($currentRun);
        if ($currentTarget === '') {
            return [];
        }

        $currentIpRows = AnaliseRunIp::query()
            ->where('analise_run_id', $currentRun->id)
            ->get(['ip', 'occurrences', 'last_seen_at'])
            ->keyBy('ip');

        if ($currentIpRows->isEmpty()) {
            return [];
        }

        $otherRuns = AnaliseRun::query()
            ->where('investigation_id', $currentRun->investigation_id)
            ->whereKeyNot($currentRun->id)
            ->get();

        if ($otherRuns->isEmpty()) {
            return [];
        }

        $otherRunTargets = [];
        foreach ($otherRuns as $run) {
            $target = $this->resolvePlatformTarget($run);
            if ($target !== '') {
                $otherRunTargets[(int) $run->id] = $target;
            }
        }

        if (count($otherRunTargets) === 0) {
            return [];
        }

        $sharedIpRows = AnaliseRunIp::query()
            ->whereIn('analise_run_id', array_keys($otherRunTargets))
            ->whereIn('ip', $currentIpRows->keys()->all())
            ->get(['analise_run_id', 'ip', 'occurrences', 'last_seen_at']);

        if ($sharedIpRows->isEmpty()) {
            return [];
        }

        $sharedIps = $sharedIpRows->pluck('ip')->unique()->values()->all();
        $eventRows = AnaliseRunEvent::query()
            ->whereIn('analise_run_id', array_merge([(int) $currentRun->id], array_keys($otherRunTargets)))
            ->where('event_type', 'access')
            ->whereIn('ip', $sharedIps)
            ->orderBy('occurred_at')
            ->get(['analise_run_id', 'ip', 'occurred_at']);

        $eventMeta = [];
        foreach ($eventRows as $event) {
            $runId = (int) $event->analise_run_id;
            $ip = (string) $event->ip;
            $occurredAt = $event->occurred_at ? Carbon::parse($event->occurred_at, 'UTC') : null;

            if (! $occurredAt) {
                continue;
            }

            $eventMeta[$runId][$ip] ??= [
                'count' => 0,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
                'times' => [],
            ];

            $eventMeta[$runId][$ip]['count']++;

            if ($occurredAt->lessThan($eventMeta[$runId][$ip]['first_seen_at'])) {
                $eventMeta[$runId][$ip]['first_seen_at'] = $occurredAt;
            }

            if ($occurredAt->greaterThan($eventMeta[$runId][$ip]['last_seen_at'])) {
                $eventMeta[$runId][$ip]['last_seen_at'] = $occurredAt;
            }

            $eventMeta[$runId][$ip]['times'][] = $occurredAt
                ->copy()
                ->setTimezone('America/Sao_Paulo')
                ->format('d/m/Y H:i:s');
        }

        $rowsByIp = [];

        foreach ($sharedIpRows as $sharedIpRow) {
            $ip = (string) $sharedIpRow->ip;
            $otherRunId = (int) $sharedIpRow->analise_run_id;
            $otherTarget = $otherRunTargets[$otherRunId] ?? '';

            if ($otherTarget === '') {
                continue;
            }

            $currentIpRow = $currentIpRows->get($ip);
            if (! $currentIpRow) {
                continue;
            }

            $currentMeta = $eventMeta[(int) $currentRun->id][$ip] ?? [
                'count' => (int) $currentIpRow->occurrences,
                'first_seen_at' => $currentIpRow->last_seen_at ? Carbon::parse($currentIpRow->last_seen_at, 'UTC') : null,
                'last_seen_at' => $currentIpRow->last_seen_at ? Carbon::parse($currentIpRow->last_seen_at, 'UTC') : null,
                'times' => [],
            ];

            $otherMeta = $eventMeta[$otherRunId][$ip] ?? [
                'count' => (int) $sharedIpRow->occurrences,
                'first_seen_at' => $sharedIpRow->last_seen_at ? Carbon::parse($sharedIpRow->last_seen_at, 'UTC') : null,
                'last_seen_at' => $sharedIpRow->last_seen_at ? Carbon::parse($sharedIpRow->last_seen_at, 'UTC') : null,
                'times' => [],
            ];

            $rowsByIp[$ip] ??= [
                'ip' => $ip,
                'accesses' => [[
                    'run_id' => (int) $currentRun->id,
                    'target' => $currentTarget,
                    'count' => (int) ($currentMeta['count'] ?? 0),
                    'first_seen' => $this->formatCarbonForVinculo($currentMeta['first_seen_at'] ?? null),
                    'last_seen' => $this->formatCarbonForVinculo($currentMeta['last_seen_at'] ?? null),
                    'times' => (array) ($currentMeta['times'] ?? []),
                    'is_selected' => true,
                ]],
                'targets_count' => 1,
                'total_occurrences' => (int) ($currentMeta['count'] ?? 0),
                'last_seen_at' => $currentMeta['last_seen_at'] ?? null,
            ];

            $rowsByIp[$ip]['accesses'][] = [
                'run_id' => $otherRunId,
                'target' => $otherTarget,
                'count' => (int) ($otherMeta['count'] ?? 0),
                'first_seen' => $this->formatCarbonForVinculo($otherMeta['first_seen_at'] ?? null),
                'last_seen' => $this->formatCarbonForVinculo($otherMeta['last_seen_at'] ?? null),
                'times' => (array) ($otherMeta['times'] ?? []),
                'is_selected' => false,
            ];
            $rowsByIp[$ip]['targets_count']++;
            $rowsByIp[$ip]['total_occurrences'] += (int) ($otherMeta['count'] ?? 0);

            $otherLastSeen = $otherMeta['last_seen_at'] ?? null;
            if (
                $otherLastSeen instanceof Carbon
                && (! ($rowsByIp[$ip]['last_seen_at'] instanceof Carbon) || $otherLastSeen->greaterThan($rowsByIp[$ip]['last_seen_at']))
            ) {
                $rowsByIp[$ip]['last_seen_at'] = $otherLastSeen;
            }
        }

        if (count($rowsByIp) === 0) {
            return [];
        }

        $enrichments = IpEnrichment::query()
            ->whereIn('ip', array_keys($rowsByIp))
            ->get()
            ->keyBy('ip');

        $rows = [];

        foreach ($rowsByIp as $ip => $row) {
            usort($row['accesses'], fn (array $left, array $right): int => (($right['is_selected'] ?? false) <=> ($left['is_selected'] ?? false))
                ?: strcmp((string) ($left['target'] ?? ''), (string) ($right['target'] ?? '')));

            $enrichment = $enrichments->get($ip);
            $provider = trim((string) (($enrichment?->isp ?? '') ?: ($enrichment?->org ?? '')));

            $rows[] = [
                'ip' => $ip,
                'targets' => implode(' | ', array_map(fn (array $access): string => (string) ($access['target'] ?? ''), $row['accesses'])),
                'targets_count' => (int) ($row['targets_count'] ?? count($row['accesses'])),
                'total_occurrences' => (int) ($row['total_occurrences'] ?? 0),
                'last_seen' => $this->formatCarbonForVinculo($row['last_seen_at'] ?? null),
                '_last_seen_at' => $row['last_seen_at'] ?? null,
                'provider' => $provider !== '' ? $provider : 'Desconhecido',
                'city' => trim((string) ($enrichment?->city ?? '')) ?: 'Desconhecida',
                'type' => ($enrichment?->mobile ?? false) ? 'Movel' : 'Residencial',
                'accesses' => $row['accesses'],
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $leftAt = $left['_last_seen_at'] ?? null;
            $rightAt = $right['_last_seen_at'] ?? null;

            if ($leftAt instanceof Carbon && $rightAt instanceof Carbon) {
                return $rightAt->getTimestamp() <=> $leftAt->getTimestamp();
            }

            return 0;
        });

        foreach ($rows as &$row) {
            unset($row['_last_seen_at']);
        }
        unset($row);

        return $rows;
    }

    private function extractConnectionIpsForVinculo(array $parsed): array
    {
        $ips = [];

        foreach (($parsed['ip_events'] ?? []) as $event) {
            $ip = trim((string) ($event['ip'] ?? ''));
            if ($ip === '') {
                continue;
            }

            $lastSeenAt = $this->parseCarbonUtcForVinculo($event['time_utc'] ?? null);

            $ips[$ip] ??= [
                'occurrences' => 0,
                'first_seen_at' => $lastSeenAt,
                'last_seen_at' => $lastSeenAt,
                'times' => [],
            ];

            $ips[$ip]['occurrences']++;

            if ($lastSeenAt instanceof Carbon) {
                $ips[$ip]['times'][] = $lastSeenAt->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$ip]['first_seen_at'] === null ||
                $lastSeenAt->lessThan($ips[$ip]['first_seen_at'])
            )) {
                $ips[$ip]['first_seen_at'] = $lastSeenAt;
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$ip]['last_seen_at'] === null ||
                $lastSeenAt->greaterThan($ips[$ip]['last_seen_at'])
            )) {
                $ips[$ip]['last_seen_at'] = $lastSeenAt;
            }
        }

        $connectionIp = $this->extractIpBase(data_get($parsed, 'connection_info.last_ip'));
        if ($connectionIp) {
            $lastSeenAt = $this->parseCarbonUtcForVinculo(data_get($parsed, 'connection_info.last_seen_utc'));

            $ips[$connectionIp] ??= [
                'occurrences' => 0,
                'first_seen_at' => $lastSeenAt,
                'last_seen_at' => $lastSeenAt,
                'times' => [],
            ];

            if ($lastSeenAt instanceof Carbon) {
                $ips[$connectionIp]['times'][] = $lastSeenAt->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$connectionIp]['first_seen_at'] === null ||
                $lastSeenAt->lessThan($ips[$connectionIp]['first_seen_at'])
            )) {
                $ips[$connectionIp]['first_seen_at'] = $lastSeenAt;
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$connectionIp]['last_seen_at'] === null ||
                $lastSeenAt->greaterThan($ips[$connectionIp]['last_seen_at'])
            )) {
                $ips[$connectionIp]['last_seen_at'] = $lastSeenAt;
            }
        }

        return $ips;
    }

    private function parseCarbonUtcForVinculo(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone('UTC');
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value, 'UTC');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value, 'UTC')->setTimezone('UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function formatCarbonForVinculo(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $carbon = $value instanceof Carbon ? $value->copy() : Carbon::parse($value, 'UTC');

        return $carbon->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
    }

    private function extractIpBase(?string $ipWithPort): ?string
    {
        $ipWithPort = trim((string) $ipWithPort);
        if ($ipWithPort === '') {
            return null;
        }

        if (preg_match('/^\[([0-9a-fA-F:]+)\]:(\d{1,5})$/', $ipWithPort, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d{1,5})$/', $ipWithPort, $matches)) {
            return $matches[1];
        }

        return $ipWithPort;
    }

    private function applyPdfLimits(array $report): array
    {
        $truncated = [];

        foreach (self::PDF_LIMITS as $key => $limit) {
            $rows = $report[$key] ?? null;

            if (! is_array($rows)) {
                continue;
            }

            $total = count($rows);
            if ($total <= $limit) {
                continue;
            }

            $report[$key] = array_slice($rows, 0, $limit);
            $truncated[$key] = [
                'shown' => $limit,
                'total' => $total,
            ];
        }

        $report['_pdf_truncated'] = $truncated;

        return $report;
    }

    private function enhanceWhatsappPdfReport(array $report): array
    {
        $providerStats = array_values((array) ($report['provider_stats_rows'] ?? []));
        $cityStats = array_values((array) ($report['city_stats_rows'] ?? []));
        $providerIpMap = (array) ($report['provider_ip_map'] ?? []);
        $nightEvents = array_values((array) ($report['night_events_rows'] ?? []));
        $mobileEvents = array_values((array) ($report['mobile_events_rows'] ?? []));

        usort($providerStats, fn (array $a, array $b): int => ((int) ($b['occurrences'] ?? 0)) <=> ((int) ($a['occurrences'] ?? 0)));
        usort($cityStats, fn (array $a, array $b): int => ((int) ($b['occurrences'] ?? 0)) <=> ((int) ($a['occurrences'] ?? 0)));

        $report['provider_ranking_top'] = array_slice($providerStats, 0, 5);
        $report['city_ranking_top'] = array_slice($cityStats, 0, 5);

        $nightByProvider = [];
        foreach ($nightEvents as $row) {
            if (($row['type'] ?? null) !== 'Residencial') {
                continue;
            }

            $provider = trim((string) ($row['provider'] ?? '')) ?: 'Desconhecido';
            $nightByProvider[$provider] = ($nightByProvider[$provider] ?? 0) + 1;
        }

        arsort($nightByProvider);
        $report['fixed_night_top'] = collect($nightByProvider)
            ->map(fn (int $count, string $provider): array => [
                'provider' => $provider,
                'occurrences' => $count,
            ])
            ->values()
            ->take(5)
            ->all();

        $mobileByProvider = [];
        foreach ($mobileEvents as $row) {
            $provider = trim((string) ($row['provider'] ?? '')) ?: 'Desconhecido';
            $mobileByProvider[$provider] = ($mobileByProvider[$provider] ?? 0) + 1;
        }

        arsort($mobileByProvider);
        $report['mobile_top'] = collect($mobileByProvider)
            ->map(fn (int $count, string $provider): array => [
                'provider' => $provider,
                'occurrences' => $count,
            ])
            ->values()
            ->take(5)
            ->all();

        $fixedRecentProvider = data_get($report, 'fixed_night_top.0.provider');
        $mobileRecentProvider = data_get($report, 'mobile_top.0.provider');

        $report['fixed_recent_provider'] = $fixedRecentProvider;
        $report['mobile_recent_provider'] = $mobileRecentProvider;
        $report['fixed_recent_ips'] = $this->pickProviderRecentIps($providerIpMap, $fixedRecentProvider, 'Residencial');
        $report['mobile_recent_ips'] = $this->pickProviderRecentIps($providerIpMap, $mobileRecentProvider, 'Móvel');

        return $report;
    }

    private function compactWhatsappPdfReport(array $report): array
    {
        unset(
            $report['provider_ip_map'],
            $report['symmetric_contacts'],
            $report['asymmetric_contacts']
        );

        return $report;
    }

    private function compactInstagramPdfReport(array $report): array
    {
        $report['direct_threads'] = array_map(function (array $thread): array {
            $messages = array_values((array) ($thread['messages'] ?? []));
            $participants = array_values(array_filter(array_unique(array_map(
                fn (array $message): string => trim((string) ($message['author'] ?? '')),
                $messages
            ))));

            $lastMessageAt = '—';

            if (count($messages) > 0) {
                $lastMessageAt = (string) ($messages[count($messages) - 1]['datetime'] ?? '—');
            }

            return [
                'participants' => $participants,
                'messages_count' => count($messages),
                'last_message_at' => $lastMessageAt,
            ];
        }, array_values((array) ($report['direct_threads'] ?? [])));

        unset($report['provider_ip_map']);

        return $report;
    }

    private function pickProviderRecentIps(array $providerIpMap, ?string $provider, string $connectionType, int $limit = 6): array
    {
        if (! is_string($provider) || trim($provider) === '' || ! isset($providerIpMap[$provider])) {
            return [];
        }

        $rows = array_values(array_filter(
            (array) $providerIpMap[$provider],
            fn (array $row): bool => (string) ($row['connection_type'] ?? '') === $connectionType,
        ));

        usort($rows, fn (array $a, array $b): int => strcmp((string) ($b['last_seen'] ?? ''), (string) ($a['last_seen'] ?? '')));

        return array_slice($rows, 0, $limit);
    }
}

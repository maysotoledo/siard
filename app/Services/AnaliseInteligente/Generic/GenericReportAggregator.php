<?php

namespace App\Services\AnaliseInteligente\Generic;

use Carbon\Carbon;

class GenericReportAggregator
{
    public function buildReport(array $parsed, array $enrichedByIp): array
    {
        $tz = 'America/Sao_Paulo';

        $events = $parsed['events'] ?? [];
        $emails = $parsed['emails'] ?? [];
        $mapsRows = (array) ($parsed['maps_rows'] ?? []);
        usort($mapsRows, fn (array $a, array $b): int => ((int) ($b['datetime_ts'] ?? 0)) <=> ((int) ($a['datetime_ts'] ?? 0)));
        $searchRows = (array) ($parsed['search_rows'] ?? []);
        usort($searchRows, fn (array $a, array $b): int => ((int) ($b['datetime_ts'] ?? 0)) <=> ((int) ($a['datetime_ts'] ?? 0)));

        $timelineRows = [];
        $uniqueIpAgg = [];
        $providerAgg = [];
        $providerIpAgg = [];
        $cityAgg = [];
        $prefixAgg = [];
        $userAgentAgg = [];
        $androidIds = [];
        $iosIdfvs = [];

        $nightTotalEvents = 0;
        $nightRows = [];

        $mobileTotalEvents = 0;
        $mobileRows = [];
        $weekendTotalEvents = 0;

        $hourlyAgg = [];

        foreach ($events as $e) {
            $ip = $e['ip'] ?? null;
            $timeUtc = $this->toCarbonUtc($e['time_utc'] ?? null);

            if (! $ip || ! $timeUtc) {
                continue;
            }

            $info = $enrichedByIp[$ip] ?? ['city' => null, 'isp' => null, 'org' => null, 'mobile' => null];

            $providerRaw = trim(($info['isp'] ?? '') ?: ($info['org'] ?? ''));
            $cityRaw = trim((string) ($info['city'] ?? ''));
            $mobile = (bool) ($info['mobile'] ?? false);

            $provider = $providerRaw !== '' ? $providerRaw : 'Desconhecido';
            $city = $cityRaw !== '' ? $cityRaw : 'Desconhecida';
            $type = $mobile ? 'Móvel' : 'Residencial';

            $tzLabel = $e['tz_label'] ?? 'UTC';
            $logicalPort = $e['logical_port'] ?? null;

            $hourlyAgg[$timeUtc->format('Y-m-d H')] = ($hourlyAgg[$timeUtc->format('Y-m-d H')] ?? 0) + 1;

            $timeLocal = $timeUtc->copy()->setTimezone($tz);
            $hour = (int) $timeLocal->format('G');
            $isNight = ($hour >= 23 || $hour <= 6);
            $isWeekend = (int) $timeLocal->format('N') >= 6;
            $ipVersion = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? 6 : 4;
            $prefix = $this->networkPrefix($ip);

            $row = [
                'datetime_ts' => $timeUtc->timestamp,
                'datetime_gmt' => $timeUtc->format('d/m/Y H:i:s') . " ({$tzLabel})",
                'datetime_local' => $timeLocal->format('d/m/Y H:i:s') . ' (GMT-3)',
                'ip' => $ip,
                'ip_version' => "IPv{$ipVersion}",
                'provider' => $provider,
                'city' => $city,
                'connection_type' => $type,
                'logical_port' => $logicalPort,
                'action' => $e['action'] ?? null,
                'description' => $e['description'] ?? null,
                'period_flags' => implode(', ', array_filter([
                    $isNight ? 'Noturno' : null,
                    $isWeekend ? 'Fim de semana' : null,
                ])) ?: 'Regular',
            ];

            $timelineRows[] = $row;
            $prefixAgg[$prefix] ??= [
                'prefix' => $prefix,
                'occurrences' => 0,
                'unique_ips' => [],
                'provider' => $provider,
                'city' => $city,
                'connection_type' => $type,
                'last_seen' => $timeUtc,
            ];
            $prefixAgg[$prefix]['occurrences']++;
            $prefixAgg[$prefix]['unique_ips'][$ip] = true;
            if ($timeUtc->greaterThan($prefixAgg[$prefix]['last_seen'])) {
                $prefixAgg[$prefix]['last_seen'] = $timeUtc;
            }

            $userAgent = trim((string) ($e['user_agent'] ?? ''));
            if ($userAgent !== '') {
                $userAgentAgg[$userAgent] ??= ['user_agent' => $userAgent, 'occurrences' => 0, 'last_seen' => $timeUtc];
                $userAgentAgg[$userAgent]['occurrences']++;
                if ($timeUtc->greaterThan($userAgentAgg[$userAgent]['last_seen'])) {
                    $userAgentAgg[$userAgent]['last_seen'] = $timeUtc;
                }
            }

            foreach (['android_id' => &$androidIds, 'ios_idfv' => &$iosIdfvs] as $field => &$bucket) {
                $identifier = trim((string) ($e[$field] ?? ''));
                if ($identifier === '') {
                    continue;
                }

                $bucket[$identifier] ??= ['value' => $identifier, 'occurrences' => 0, 'last_seen' => $timeUtc];
                $bucket[$identifier]['occurrences']++;
                if ($timeUtc->greaterThan($bucket[$identifier]['last_seen'])) {
                    $bucket[$identifier]['last_seen'] = $timeUtc;
                }
            }
            unset($bucket);

            // ✅ NÃO APARECER "Desconhecido": só agrega se tiver provider real
            if ($providerRaw !== '') {
                $uniqueIpAgg[$ip] ??= [
                    'ip' => $ip,
                    'provider' => $provider,
                    'city' => $city,
                    'connection_type' => $type,
                    'count' => 0,
                    'last_seen' => $timeUtc,
                ];
                $uniqueIpAgg[$ip]['count']++;
                if ($timeUtc->greaterThan($uniqueIpAgg[$ip]['last_seen'])) {
                    $uniqueIpAgg[$ip]['last_seen'] = $timeUtc;
                }

                $providerAgg[$provider] ??= [
                    'provider' => $provider,
                    'occurrences' => 0,
                    'unique_ips' => [],
                    'cities' => [],
                    'mobile_occurrences' => 0,
                    'last_seen' => $timeUtc,
                ];
                $providerAgg[$provider]['occurrences']++;
                $providerAgg[$provider]['unique_ips'][$ip] = true;
                $providerAgg[$provider]['cities'][$city] = true;
                if ($mobile) $providerAgg[$provider]['mobile_occurrences']++;
                if ($timeUtc->greaterThan($providerAgg[$provider]['last_seen'])) {
                    $providerAgg[$provider]['last_seen'] = $timeUtc;
                }

                $providerIpAgg[$provider][$ip] ??= [
                    'ip' => $ip,
                    'count' => 0,
                    'last_seen' => $timeUtc,
                    'city' => $city,
                    'connection_type' => $type,
                ];
                $providerIpAgg[$provider][$ip]['count']++;
                if ($timeUtc->greaterThan($providerIpAgg[$provider][$ip]['last_seen'])) {
                    $providerIpAgg[$provider][$ip]['last_seen'] = $timeUtc;
                }

                $cityAgg[$city] ??= [
                    'city' => $city,
                    'occurrences' => 0,
                    'unique_ips' => [],
                    'providers' => [],
                    'mobile_occurrences' => 0,
                    'last_seen' => $timeUtc,
                ];
                $cityAgg[$city]['occurrences']++;
                $cityAgg[$city]['unique_ips'][$ip] = true;
                $cityAgg[$city]['providers'][$provider] = true;
                if ($mobile) $cityAgg[$city]['mobile_occurrences']++;
                if ($timeUtc->greaterThan($cityAgg[$city]['last_seen'])) {
                    $cityAgg[$city]['last_seen'] = $timeUtc;
                }
            }

            if ($isNight) {
                $nightTotalEvents++;
                $nightRows[] = $row;
            }

            if ($isWeekend) {
                $weekendTotalEvents++;
            }

            if ($mobile) {
                $mobileTotalEvents++;
                $mobileRows[] = $row;
            }
        }

        usort($timelineRows, fn ($a, $b) => strcmp($b['datetime_local'], $a['datetime_local']));

        $uniqueIpRows = array_values($uniqueIpAgg);
        usort($uniqueIpRows, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['last_seen']->timestamp <=> $a['last_seen']->timestamp));
        $uniqueIpRows = array_map(fn ($r) => [
            'ip' => $r['ip'],
            'provider' => $r['provider'],
            'city' => $r['city'],
            'connection_type' => $r['connection_type'],
            'count' => $r['count'],
            'last_seen_utc' => $r['last_seen']->format('d/m/Y H:i:s') . ' (UTC)',
            'last_seen_local' => $r['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
        ], $uniqueIpRows);

        $providerRows = [];
        foreach ($providerAgg as $prov => $s) {
            $occ = (int) $s['occurrences'];
            $mob = (int) $s['mobile_occurrences'];
            $providerRows[] = [
                'provider' => $prov,
                'occurrences' => $occ,
                'unique_ips' => count($s['unique_ips']),
                'cities' => count($s['cities']),
                'mobile_occurrences' => $mob,
                'mobile_percent' => $occ > 0 ? round(($mob / $occ) * 100, 2) : 0,
                'last_seen_utc' => $s['last_seen']->format('d/m/Y H:i:s') . ' (UTC)',
                'last_seen_local' => $s['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
            ];
        }
        usort($providerRows, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        $providerIpMap = [];
        foreach ($providerIpAgg as $provider => $rows) {
            $providerIpMap[$provider] = array_values(array_map(fn (array $row): array => [
                'ip' => $row['ip'],
                'count' => $row['count'],
                'last_seen' => $row['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
                'city' => $row['city'],
                'connection_type' => $row['connection_type'],
            ], $rows));

            usort($providerIpMap[$provider], fn ($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($b['last_seen'], $a['last_seen']));
        }

        $cityRows = [];
        foreach ($cityAgg as $city => $s) {
            $occ = (int) $s['occurrences'];
            $mob = (int) $s['mobile_occurrences'];
            $cityRows[] = [
                'city' => $city,
                'occurrences' => $occ,
                'unique_ips' => count($s['unique_ips']),
                'providers' => count($s['providers']),
                'mobile_occurrences' => $mob,
                'mobile_percent' => $occ > 0 ? round(($mob / $occ) * 100, 2) : 0,
                'last_seen_utc' => $s['last_seen']->format('d/m/Y H:i:s') . ' (UTC)',
                'last_seen_local' => $s['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
            ];
        }
        usort($cityRows, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        usort($nightRows, fn ($a, $b) => strcmp($b['datetime_local'], $a['datetime_local']));
        usort($mobileRows, fn ($a, $b) => strcmp($b['datetime_local'], $a['datetime_local']));

        $prefixRows = array_values(array_map(fn (array $row): array => [
            'prefix' => $row['prefix'],
            'occurrences' => $row['occurrences'],
            'unique_ips' => count($row['unique_ips']),
            'provider' => $row['provider'],
            'city' => $row['city'],
            'connection_type' => $row['connection_type'],
            'last_seen_utc' => $row['last_seen']->format('d/m/Y H:i:s') . ' (UTC)',
            'last_seen_local' => $row['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
        ], $prefixAgg));
        usort($prefixRows, fn ($a, $b) => ($b['occurrences'] <=> $a['occurrences']) ?: strcmp($a['prefix'], $b['prefix']));

        $userAgentRows = array_values(array_map(fn (array $row): array => [
            'user_agent' => $row['user_agent'],
            'occurrences' => $row['occurrences'],
            'last_seen_utc' => $row['last_seen']->format('d/m/Y H:i:s') . ' (UTC)',
            'last_seen_local' => $row['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
        ], $userAgentAgg));
        usort($userAgentRows, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        $identifierRows = array_merge(
            $this->formatIdentifierRows($androidIds, 'Android ID', $tz),
            $this->formatIdentifierRows($iosIdfvs, 'Apple iOS IDFV', $tz),
        );

        arsort($hourlyAgg);
        $hourlyRows = [];
        foreach ($hourlyAgg as $key => $count) {
            $hourlyRows[] = [
                'burst_hour' => $key,
                'label' => Carbon::createFromFormat('Y-m-d H', $key, 'UTC')
                    ->setTimezone($tz)
                    ->format('d/m/Y H:i'),
                'count' => $count,
            ];
        }

        $periodLabel = $this->buildPeriodLabel($parsed['range_start_utc'] ?? null, $parsed['range_end_utc'] ?? null, $tz);
        $ipv4Count = count(array_filter(array_keys($uniqueIpAgg), fn (string $ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)));
        $ipv6Count = count(array_filter(array_keys($uniqueIpAgg), fn (string $ip): bool => (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)));

        return [
            'period_label' => $periodLabel,
            'total_events' => count($timelineRows),
            'total_unique_ips' => count($uniqueIpRows),
            'total_unique_ipv4' => $ipv4Count,
            'total_unique_ipv6' => $ipv6Count,

            'emails_found' => $emails,
            'subscriber_info' => $parsed['subscriber_info'] ?? null,

            'timeline_rows' => $timelineRows,
            'unique_ip_rows' => $uniqueIpRows,
            'provider_stats_rows' => $providerRows,
            'provider_ip_map' => $providerIpMap,
            'city_stats_rows' => $cityRows,
            'prefix_stats_rows' => $prefixRows,
            'maps_rows' => $mapsRows,
            'search_rows' => $searchRows,
            'user_agent_rows' => $userAgentRows,
            'device_identifier_rows' => $identifierRows,

            'night_total_events' => $nightTotalEvents,
            'night_events_rows' => $nightRows,
            'weekend_total_events' => $weekendTotalEvents,

            'mobile_total_events' => $mobileTotalEvents,
            'mobile_events_rows' => $mobileRows,

            'hourly_rows' => $hourlyRows,
        ];
    }

    private function toCarbonUtc(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) return $value->copy()->setTimezone('UTC');
        if (is_int($value)) return Carbon::createFromTimestamp($value, 'UTC');
        if (is_string($value) && trim($value) !== '') {
            try { return Carbon::parse($value, 'UTC'); } catch (\Throwable) { return null; }
        }
        return null;
    }

    private function buildPeriodLabel(mixed $startUtc, mixed $endUtc, string $tz): ?string
    {
        $start = $this->toCarbonUtc($startUtc);
        $end = $this->toCarbonUtc($endUtc);

        if (! $start || ! $end) return null;

        return $start->copy()->setTimezone($tz)->format('d/m/Y H:i:s')
            . ' até '
            . $end->copy()->setTimezone($tz)->format('d/m/Y H:i:s')
            . ' (GMT-3)';
    }

    private function networkPrefix(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return implode('.', array_slice($parts, 0, 3)) . '.0/24';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $expanded = inet_ntop(inet_pton($ip)) ?: $ip;
            $parts = explode(':', $expanded);
            return implode(':', array_slice($parts, 0, 4)) . '::/64';
        }

        return $ip;
    }

    private function formatIdentifierRows(array $rows, string $type, string $tz): array
    {
        $out = array_values(array_map(fn (array $row): array => [
            'type' => $type,
            'value' => $row['value'],
            'occurrences' => $row['occurrences'],
            'last_seen_utc' => $row['last_seen']->format('d/m/Y H:i:s') . ' (UTC)',
            'last_seen_local' => $row['last_seen']->copy()->setTimezone($tz)->format('d/m/Y H:i:s') . ' (GMT-3)',
        ], $rows));

        usort($out, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        return $out;
    }
}

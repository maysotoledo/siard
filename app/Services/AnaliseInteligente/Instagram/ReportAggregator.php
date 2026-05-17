<?php

namespace App\Services\AnaliseInteligente\Instagram;

use Carbon\Carbon;

class ReportAggregator
{
    private const NIGHT_START = 23;

    private const NIGHT_END = 6;

    public function buildReport(array $parsed, array $enrichedByIp): array
    {
        $tz = 'America/Sao_Paulo';
        $events = $parsed['ip_events'] ?? [];

        $timelineRows = [];
        $uniqueIpAgg = [];
        $providerStatsAgg = [];
        $cityStatsAgg = [];
        $providerIpMap = [];

        $nightTotalEvents = 0;
        $nightEventsRows = [];

        $mobileTotalEvents = 0;
        $mobileEventsRows = [];

        $hourlyAgg = [];

        foreach ($events as $e) {
            $ipBase = $e['ip'] ?? null;
            if (! $ipBase) {
                continue;
            }

            $ipDisplay = $e['ip_with_port'] ?? $ipBase;

            $timeUtc = $this->toCarbonUtc($e['time_utc'] ?? null);
            if (! $timeUtc) {
                continue;
            }

            $timeLocal = $timeUtc->copy()->setTimezone($tz);

            $info = $enrichedByIp[$ipBase] ?? [
                'city' => null,
                'isp' => null,
                'org' => null,
                'mobile' => null,
            ];

            $provider = trim(($info['isp'] ?? '') ?: ($info['org'] ?? ''));
            $provider = preg_replace('/\s+/u', ' ', $provider ?? '') ?? '';
            if ($provider === '') {
                $provider = 'Desconhecido';
            }

            $city = trim((string) ($info['city'] ?? ''));
            $city = preg_replace('/\s+/u', ' ', $city ?? '') ?? '';
            if ($city === '') {
                $city = 'Desconhecida';
            }

            $mobile = (bool) ($info['mobile'] ?? false);
            $type = $mobile ? 'Móvel' : 'Residencial';

            // ✅ Timeline agora mostra IP:PORT (quando existir)
            $timelineRows[] = [
                'datetime' => $timeLocal->format('Y-m-d H:i:s'),
                'ip' => $ipDisplay,
                'provider' => $provider,
                'city' => $city,
                'type' => $type,
            ];

            // ✅ Unique IP continua por IP base (sem porta)
            $uniqueIpAgg[$ipBase] ??= [
                'ip' => $ipBase,
                'provider' => $provider,
                'city' => $city,
                'type' => $type,
                'count' => 0,
                'last_seen' => $timeLocal,
            ];
            $uniqueIpAgg[$ipBase]['count']++;
            if ($timeLocal->greaterThan($uniqueIpAgg[$ipBase]['last_seen'])) {
                $uniqueIpAgg[$ipBase]['last_seen'] = $timeLocal;
            }

            $providerStatsAgg[$provider] ??= [
                'provider' => $provider,
                'occurrences' => 0,
                'unique_ips' => [],
                'cities' => [],
                'mobile_occurrences' => 0,
                'last_seen' => $timeLocal,
            ];
            $providerStatsAgg[$provider]['occurrences']++;
            $providerStatsAgg[$provider]['unique_ips'][$ipBase] = true;
            $providerStatsAgg[$provider]['cities'][$city] = true;
            if ($mobile) {
                $providerStatsAgg[$provider]['mobile_occurrences']++;
            }
            if ($timeLocal->greaterThan($providerStatsAgg[$provider]['last_seen'])) {
                $providerStatsAgg[$provider]['last_seen'] = $timeLocal;
            }

            $cityStatsAgg[$city] ??= [
                'city' => $city,
                'occurrences' => 0,
                'unique_ips' => [],
                'providers' => [],
                'mobile_occurrences' => 0,
                'last_seen' => $timeLocal,
            ];
            $cityStatsAgg[$city]['occurrences']++;
            $cityStatsAgg[$city]['unique_ips'][$ipBase] = true;
            $cityStatsAgg[$city]['providers'][$provider] = true;
            if ($mobile) {
                $cityStatsAgg[$city]['mobile_occurrences']++;
            }
            if ($timeLocal->greaterThan($cityStatsAgg[$city]['last_seen'])) {
                $cityStatsAgg[$city]['last_seen'] = $timeLocal;
            }

            // ✅ provider_ip_map: agora agrupa por IP:PORT (quando existir)
            $providerIpMap[$provider] ??= [];
            $providerIpMap[$provider][$ipDisplay] ??= [
                'ip' => $ipDisplay,
                'count' => 0,
                'last_seen' => $timeLocal,
                'city' => $city,
                'mobile' => $mobile,
            ];
            $providerIpMap[$provider][$ipDisplay]['count']++;
            if ($timeLocal->greaterThan($providerIpMap[$provider][$ipDisplay]['last_seen'])) {
                $providerIpMap[$provider][$ipDisplay]['last_seen'] = $timeLocal;
            }

            $hourlyAgg[$timeUtc->format('Y-m-d H')] = ($hourlyAgg[$timeUtc->format('Y-m-d H')] ?? 0) + 1;

            $hour = (int) $timeLocal->format('G');
            $isNight = ($hour >= self::NIGHT_START || $hour <= self::NIGHT_END);

            if ($isNight) {
                $nightTotalEvents++;
                $nightEventsRows[] = [
                    'datetime' => $timeLocal->format('Y-m-d H:i:s'),
                    'ip' => $ipDisplay,
                    'provider' => $provider,
                    'city' => $city,
                    'type' => $type,
                ];
            }

            if ($mobile) {
                $mobileTotalEvents++;
                $mobileEventsRows[] = [
                    'datetime' => $timeLocal->format('Y-m-d H:i:s'),
                    'ip' => $ipDisplay,
                    'provider' => $provider,
                    'city' => $city,
                ];
            }
        }

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

        usort($timelineRows, fn ($a, $b) => strcmp($b['datetime'], $a['datetime']));

        $uniqueIpRows = array_values($uniqueIpAgg);
        usort($uniqueIpRows, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['last_seen']->timestamp <=> $a['last_seen']->timestamp));
        $uniqueIpRows = array_map(fn ($r) => [
            'ip' => $r['ip'],
            'provider' => $r['provider'],
            'city' => $r['city'],
            'type' => $r['type'],
            'count' => $r['count'],
            'last_seen' => $r['last_seen']->format('Y-m-d H:i:s'),
        ], $uniqueIpRows);

        $providerStatsRows = [];
        foreach ($providerStatsAgg as $provider => $s) {
            $occ = (int) $s['occurrences'];
            $mob = (int) $s['mobile_occurrences'];

            $providerStatsRows[] = [
                'provider' => $provider,
                'occurrences' => $occ,
                'unique_ips' => count($s['unique_ips']),
                'cities' => count($s['cities']),
                'mobile_occurrences' => $mob,
                'mobile_percent' => $occ > 0 ? round(($mob / $occ) * 100, 2) : 0,
                'last_seen' => $s['last_seen']->format('Y-m-d H:i:s'),
            ];
        }
        usort($providerStatsRows, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        $cityStatsRows = [];
        foreach ($cityStatsAgg as $city => $s) {
            $occ = (int) $s['occurrences'];
            $mob = (int) $s['mobile_occurrences'];

            $cityStatsRows[] = [
                'city' => $city,
                'occurrences' => $occ,
                'unique_ips' => count($s['unique_ips']),
                'providers' => count($s['providers']),
                'mobile_occurrences' => $mob,
                'mobile_percent' => $occ > 0 ? round(($mob / $occ) * 100, 2) : 0,
                'last_seen' => $s['last_seen']->format('Y-m-d H:i:s'),
            ];
        }
        usort($cityStatsRows, fn ($a, $b) => $b['occurrences'] <=> $a['occurrences']);

        $providerIpMapOut = [];
        foreach ($providerIpMap as $prov => $ipsAssoc) {
            $list = array_values($ipsAssoc);
            usort($list, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['last_seen']->timestamp <=> $a['last_seen']->timestamp));

            $providerIpMapOut[$prov] = array_map(fn ($r) => [
                'ip' => $r['ip'], // ip:port (ou [ipv6]:port)
                'count' => (int) $r['count'],
                'last_seen' => $r['last_seen']->format('Y-m-d H:i:s'),
                'city' => $r['city'] ?? '-',
                'connection_type' => ($r['mobile'] ?? false) ? 'Móvel' : 'Residencial',
            ], $list);
        }

        usort($nightEventsRows, fn ($a, $b) => strcmp($b['datetime'], $a['datetime']));
        usort($mobileEventsRows, fn ($a, $b) => strcmp($b['datetime'], $a['datetime']));

        // ✅ DIRECT
        $directThreads = $this->buildDirectThreads($parsed, $tz);
        $followers = $this->normalizeNameList($parsed['followers'] ?? []);
        $following = $this->normalizeNameList($parsed['following'] ?? []);

        return [
            'generated_at' => $this->formatDate($parsed['generated_at'] ?? null, $tz),

            'target' => $parsed['target'] ?? null,
            'account_identifier' => $parsed['account_identifier'] ?? null,
            'vanity_name' => $parsed['vanity_name'] ?? null,

            'first_name' => $parsed['first_name'] ?? null,

            'registration_date' => $this->formatDate($parsed['registration_date'] ?? null, $tz),
            'registration_ip' => $parsed['registration_ip'] ?? null,
            'registration_phone' => $parsed['registration_phone'] ?? null,
            'registration_phone_formatted' => $this->formatBrazilPhone($parsed['registration_phone'] ?? null),
            'registration_phone_verified_on' => $this->formatDate($parsed['registration_phone_verified_on'] ?? null, $tz),

            'last_location_time' => $this->formatDate($parsed['last_location_time'] ?? null, $tz),
            'last_location_latitude' => $parsed['last_location_latitude'] ?? null,
            'last_location_longitude' => $parsed['last_location_longitude'] ?? null,
            'last_location_maps_url' => $parsed['last_location_maps_url'] ?? null,
            'last_location_qr_url' => $this->makeQrUrl($parsed['last_location_maps_url'] ?? null),

            'total_ips' => count($events),

            'timeline_rows' => $timelineRows,
            'unique_ip_rows' => $uniqueIpRows,
            'provider_stats_rows' => $providerStatsRows,
            'city_stats_rows' => $cityStatsRows,

            'provider_ip_map' => $providerIpMapOut,

            'night_total_events' => $nightTotalEvents,
            'night_events_rows' => $nightEventsRows,

            'mobile_total_events' => $mobileTotalEvents,
            'mobile_events_rows' => $mobileEventsRows,

            'hourly_rows' => $hourlyRows,

            // ✅ NOVO
            'direct_threads' => $directThreads,
            'followers' => $followers,
            'following' => $following,
            'followers_count' => count($followers),
            'following_count' => count($following),

            'parse_stats' => $parsed['_parse_stats'] ?? null,
        ];
    }

    private function normalizeNameList(mixed $names): array
    {
        $out = [];

        foreach ((array) $names as $name) {
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name) ?? '');

            if ($name === '') {
                continue;
            }

            $out[mb_strtolower($name)] = $name;
        }

        natcasesort($out);

        return array_values($out);
    }

    private function buildDirectThreads(array $parsed, string $tz): array
    {
        $threads = (array) ($parsed['direct_threads'] ?? []);

        // ✅ alvo real: vanity_name
        $me = trim((string) ($parsed['vanity_name'] ?? ''));
        if ($me === '') {
            $me = trim((string) ($parsed['account_identifier'] ?? ''));
        }

        $out = [];

        foreach ($threads as $t) {
            if (! is_array($t)) continue;

            $participant = trim((string) ($t['participant'] ?? ''));
            if ($participant === '') continue;

            $messages = [];

            foreach ((array) ($t['messages'] ?? []) as $m) {
                if (! is_array($m)) continue;

                $author = trim((string) ($m['author'] ?? ''));
                $body = (string) ($m['body'] ?? '');
                $sentUtc = (string) ($m['sent_utc'] ?? '');

                $dt = $this->parseUtcString($sentUtc);
                $dtLocal = $dt ? $dt->copy()->setTimezone($tz) : null;

                $messages[] = [
                    'author' => $author !== '' ? $author : '—',
                    'datetime' => $dtLocal ? $dtLocal->format('d/m/Y H:i:s') : ($sentUtc ?: '—'),
                    'body' => trim($body) !== '' ? trim($body) : '—',
                    'from_target' => ($me !== '' && strcasecmp($author, $me) === 0),
                ];
            }

            $out[] = [
                'participant' => $participant,
                'messages' => $messages,
            ];
        }

        return $out;
    }

    private function parseUtcString(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $value = str_replace(' UTC', '', $value);

        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'UTC');
        } catch (\Throwable) {
            return null;
        }
    }

    private function toCarbonUtc(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone('UTC');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value, 'UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function formatDate(mixed $value, string $tz): ?string
    {
        $dt = $this->toCarbonUtc($value);

        if (! $dt) {
            return null;
        }

        return $dt->copy()->setTimezone($tz)->format('d/m/Y H:i:s');
    }

    private function formatBrazilPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return sprintf('+55 (%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (strlen($digits) === 10) {
            return sprintf('+55 (%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $phone;
    }

    private function makeQrUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return 'https://quickchart.io/qr?text=' . urlencode($url) . '&size=220';
    }
}

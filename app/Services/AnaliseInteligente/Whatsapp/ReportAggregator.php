<?php

namespace App\Services\AnaliseInteligente\Whatsapp;

use Carbon\Carbon;

class ReportAggregator
{
    public function buildReport(array $parsed, array $enrichedByIp): array
    {
        $tz = 'America/Sao_Paulo';

        $events = $parsed['ip_events'] ?? [];

        $generatedAt = $this->extractGeneratedAt($parsed, $tz);
        $fileHash = $this->extractFileHash($parsed);
        $device = $this->extractDevice($parsed);
        $periodLabel = $this->extractPeriodLabel($parsed, $events, $tz);

        $symmetricContacts = array_values($parsed['symmetric_contacts'] ?? []);
        $asymmetricContacts = array_values($parsed['asymmetric_contacts'] ?? []);

        $symmetricContactsCount = count($symmetricContacts) > 0
            ? count($symmetricContacts)
            : $this->extractSymmetricContactsCount($parsed);

        $asymmetricContactsCount = count($asymmetricContacts) > 0
            ? count($asymmetricContacts)
            : $this->extractAsymmetricContactsCount($parsed);

        $groupsRows = $this->buildGroupsRows($parsed['groups'] ?? [], $tz);

        // ✅ GARANTIA: connection_summary.last_seen vem SOMENTE do Connection do log (sem fallback)
        $connectionSummary = $this->buildConnectionSummary(
            (array) ($parsed['connection_info'] ?? []),
            $enrichedByIp,
            $tz
        );

        $agendaPhones = [];
        foreach (array_merge($symmetricContacts, $asymmetricContacts) as $p) {
            $agendaPhones[(string) $p] = true;
        }

        $bilhetagemCards = $this->buildBilhetagemCards(
            $parsed['message_log'] ?? [],
            $agendaPhones,
            $enrichedByIp,
            $tz
        );

        // --- IPs / Timeline / etc ---
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
            if (! $ipBase) continue;

            $ipDisplay = $e['ip_with_port'] ?? $ipBase;

            $timeUtc = $this->toCarbonUtc($e['time_utc'] ?? null);
            if (! $timeUtc) continue;

            $timeLocal = $timeUtc->copy()->setTimezone($tz);

            $info = $enrichedByIp[$ipBase] ?? ['city' => null, 'isp' => null, 'org' => null, 'mobile' => null];

            $provider = trim(($info['isp'] ?? '') ?: ($info['org'] ?? ''));
            $provider = preg_replace('/\s+/u', ' ', $provider ?? '') ?? '';
            if ($provider === '') $provider = 'Desconhecido';

            $city = trim((string) ($info['city'] ?? ''));
            $city = preg_replace('/\s+/u', ' ', $city ?? '') ?? '';
            if ($city === '') $city = 'Desconhecida';

            $mobile = (bool) ($info['mobile'] ?? false);
            $type = $mobile ? 'Móvel' : 'Residencial';

            $timelineRows[] = [
                'datetime' => $timeLocal->format('d/m/Y H:i:s'),
                'ip' => $ipDisplay,
                'provider' => $provider,
                'city' => $city,
                'type' => $type,
            ];

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
            if ($mobile) $providerStatsAgg[$provider]['mobile_occurrences']++;
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
            if ($mobile) $cityStatsAgg[$city]['mobile_occurrences']++;
            if ($timeLocal->greaterThan($cityStatsAgg[$city]['last_seen'])) {
                $cityStatsAgg[$city]['last_seen'] = $timeLocal;
            }

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
            $isNight = ($hour >= 23 || $hour <= 6);

            if ($isNight) {
                $nightTotalEvents++;
                $nightEventsRows[] = [
                    'datetime' => $timeLocal->format('d/m/Y H:i:s'),
                    'ip' => $ipDisplay,
                    'provider' => $provider,
                    'city' => $city,
                    'type' => $type,
                ];
            }

            if ($mobile) {
                $mobileTotalEvents++;
                $mobileEventsRows[] = [
                    'datetime' => $timeLocal->format('d/m/Y H:i:s'),
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

        $uniqueIpRows = array_values($uniqueIpAgg);
        usort($uniqueIpRows, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['last_seen']->timestamp <=> $a['last_seen']->timestamp));
        $uniqueIpRows = array_map(fn ($r) => [
            'ip' => $r['ip'],
            'provider' => $r['provider'],
            'city' => $r['city'],
            'type' => $r['type'],
            'count' => $r['count'],
            'last_seen' => $r['last_seen']->format('d/m/Y H:i:s'),
        ], $uniqueIpRows);

        $providerStatsRows = [];
        foreach ($providerStatsAgg as $prov => $s) {
            $occ = (int) $s['occurrences'];
            $mob = (int) $s['mobile_occurrences'];

            $providerStatsRows[] = [
                'provider' => $prov,
                'occurrences' => $occ,
                'unique_ips' => count($s['unique_ips']),
                'cities' => count($s['cities']),
                'mobile_occurrences' => $mob,
                'mobile_percent' => $occ > 0 ? round(($mob / $occ) * 100, 2) : 0,
                'last_seen' => $s['last_seen']->format('d/m/Y H:i:s'),
            ];
        }
        usort($providerStatsRows, fn ($a, $b) => ($b['occurrences'] <=> $a['occurrences']));

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
                'last_seen' => $s['last_seen']->format('H:i:s d/m/Y'),
            ];
        }
        usort($cityStatsRows, fn ($a, $b) => ($b['occurrences'] <=> $a['occurrences']));

        $providerIpMapOut = [];
        foreach ($providerIpMap as $prov => $ipsAssoc) {
            $list = array_values($ipsAssoc);
            usort($list, fn ($a, $b) => ($b['count'] <=> $a['count']) ?: ($b['last_seen']->timestamp <=> $a['last_seen']->timestamp));

            $providerIpMapOut[$prov] = array_map(fn ($r) => [
                'ip' => $r['ip'],
                'count' => (int) $r['count'],
                'last_seen' => $r['last_seen']->format('d/m/Y H:i:s'),
                'city' => $r['city'] ?? '-',
                'connection_type' => ($r['mobile'] ?? false) ? 'Móvel' : 'Residencial',
            ], $list);
        }

        $alerts = $this->detectAnomalies(
            $events,
            $uniqueIpAgg,
            $providerStatsAgg,
            $nightTotalEvents,
            $mobileTotalEvents,
            $tz
        );

        return [
            'generated_at' => $generatedAt,
            'file_hash' => $fileHash,
            'target' => $parsed['target'] ?? null,
            'total_ips' => count($events),
            'device' => $device,
            'period_label' => $periodLabel,
            'alerts' => $alerts,

            'registered_emails' => $parsed['registered_emails'] ?? [],

            'symmetric_contacts_count' => $symmetricContactsCount,
            'asymmetric_contacts_count' => $asymmetricContactsCount,
            'symmetric_contacts' => $symmetricContacts,
            'asymmetric_contacts' => $asymmetricContacts,

            'groups_rows' => $groupsRows,

            'connection_summary' => $connectionSummary,

            'bilhetagem_cards' => $bilhetagemCards,

            'timeline_rows' => $timelineRows,
            'unique_ip_rows' => $uniqueIpRows,
            'provider_stats_rows' => $providerStatsRows,
            'city_stats_rows' => $cityStatsRows,
            'provider_ip_map' => $providerIpMapOut,

            'night_total_events' => $nightTotalEvents,
            'night_events_rows' => $nightEventsRows,

            'mobile_total_events' => $mobileTotalEvents,
            'mobile_events_rows' => $mobileEventsRows,

            'mobile_top' => [],
            'mobile_recent_provider' => null,
            'mobile_recent_ips' => [],
            'fixed_night_top' => [],
            'fixed_recent_provider' => null,
            'fixed_recent_ips' => [],

            'hourly_rows' => $hourlyRows,
        ];
    }

    private function detectAnomalies(
        array $events,
        array $uniqueIpAgg,
        array $providerStatsAgg,
        int $nightTotalEvents,
        int $mobileTotalEvents,
        string $tz
    ): array {
        $alerts = [];
        $totalEvents = count($events);

        if ($totalEvents === 0) {
            return $alerts;
        }

        // Muitos IPs únicos
        $uniqueIpCount = count($uniqueIpAgg);
        if ($uniqueIpCount >= 80) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Volume elevado de IPs',
                'message' => "{$uniqueIpCount} endereços IP distintos registrados. No WhatsApp isso pode refletir intensa movimentação ao longo dos dias analisados (redes domésticas, trabalho, dados móveis de diferentes torres). Verifique na aba IPs Únicos se há endereços de outras cidades, estados ou países.",
            ];
        } elseif ($uniqueIpCount >= 40) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Alta rotatividade de redes',
                'message' => "{$uniqueIpCount} IPs distintos registrados — compatível com uso ativo do WhatsApp em diferentes redes ao longo do período.",
            ];
        }

        // Alto percentual de eventos noturnos
        $nightPercent = $totalEvents > 0 ? ($nightTotalEvents / $totalEvents) * 100 : 0;
        if ($nightPercent >= 40) {
            $pct = number_format($nightPercent, 1);
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Alta atividade noturna',
                'message' => "{$pct}% dos eventos ocorreram entre 23h e 06h (horário de Brasília).",
            ];
        }

        // Alto percentual de conexões móveis
        $mobilePercent = $totalEvents > 0 ? ($mobileTotalEvents / $totalEvents) * 100 : 0;
        if ($mobilePercent >= 70) {
            $pct = number_format($mobilePercent, 1);
            $alerts[] = [
                'level' => 'info',
                'title' => 'Uso predominantemente móvel',
                'message' => "{$pct}% das conexões são de redes móveis.",
            ];
        }

        // Múltiplos provedores distintos
        $providerCount = count($providerStatsAgg);
        if ($providerCount >= 15) {
            $alerts[] = [
                'level' => 'info',
                'title' => 'Diversidade de operadoras',
                'message' => "{$providerCount} operadoras/ISPs detectados. No WhatsApp é natural variar entre Wi-Fi doméstico, rede corporativa e dados móveis. Confira a aba Provedores para identificar ISPs de outras regiões ou países.",
            ];
        }

        // Burst: muitos acessos em curto período (> 20 eventos em 1 hora)
        $eventsByHour = [];
        foreach ($events as $e) {
            $t = $this->toCarbonUtc($e['time_utc'] ?? null);
            if (! $t) continue;
            $hourKey = $t->format('Y-m-d H');
            $eventsByHour[$hourKey] = ($eventsByHour[$hourKey] ?? 0) + 1;
        }
        if ($eventsByHour !== []) {
            $maxInHour = max($eventsByHour);
            $burstHour = (string) array_search($maxInHour, $eventsByHour);

            if ($maxInHour >= 20) {
                $localHour = Carbon::createFromFormat('Y-m-d H', $burstHour, 'UTC')
                    ->setTimezone($tz)
                    ->format('d/m/Y H:i');

                $alerts[] = [
                    'level'      => 'danger',
                    'title'      => 'Burst de acessos detectado',
                    'message'    => "{$maxInHour} conexões em uma única hora ({$localHour} horário de Brasília). Possível comportamento automatizado.",
                    'action'     => 'burst',
                    'burst_hour' => $burstHour,
                ];
            }
        }

        return $alerts;
    }

    /**
     * ✅ GARANTIA: last_seen vem SOMENTE do Connection do log (sem fallback)
     */
    private function buildConnectionSummary(array $conn, array $enrichedByIp, string $tz): array
    {
        $rawLastSeen =
            $conn['last_seen_utc'] ??
            $conn['last_seen'] ??
            $conn['last_seen_at'] ??
            $conn['lastSeenUtc'] ??
            $conn['lastSeen'] ??
            $conn['lastSeenAt'] ??
            null;

        $lastSeenUtc = $this->toCarbonUtc($rawLastSeen);

        $lastSeenLocal = $lastSeenUtc
            ? $lastSeenUtc->copy()->setTimezone($tz)->format('d/m/Y H:i:s')
            : null;

        $lastIp = $conn['last_ip'] ?? ($conn['lastIp'] ?? null);
        $ipBase = $this->extractIpBase(is_string($lastIp) ? $lastIp : null);

        $provider = null;
        if ($ipBase && isset($enrichedByIp[$ipBase])) {
            $info = $enrichedByIp[$ipBase];
            $provider = trim(($info['isp'] ?? '') ?: ($info['org'] ?? ''));
            $provider = preg_replace('/\s+/u', ' ', $provider ?? '') ?? '';
            if ($provider === '') $provider = null;
        }

        return [
            'last_ip' => $lastIp,
            'last_ip_provider' => $provider ?: 'Desconhecido',
            'last_seen' => $lastSeenLocal,
        ];
    }

    private function toCarbonUtc(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) return $value->copy()->setTimezone('UTC');
        if (is_int($value)) return Carbon::createFromTimestamp($value, 'UTC');

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value, 'UTC')->setTimezone('UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function extractIpBase(?string $ipWithPort): ?string
    {
        $ipWithPort = trim((string) $ipWithPort);
        if ($ipWithPort === '') return null;

        if (preg_match('/^\[([0-9a-fA-F:]+)\]:(\d{1,5})$/', $ipWithPort, $m)) return $m[1];
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d{1,5})$/', $ipWithPort, $m)) return $m[1];

        return $ipWithPort;
    }

    private function buildBilhetagemCards(array $messageLog, array $agendaPhones, array $enrichedByIp, string $tz): array
    {
        $byRecipient = [];

        foreach ($messageLog as $m) {
            $m = (array) $m;

            $recipient = trim((string) ($m['recipient'] ?? ''));
            if ($recipient === '') continue;

            $tsUtc = $this->toCarbonUtc($m['timestamp_utc'] ?? null);
            $tsLocal = $tsUtc ? $tsUtc->copy()->setTimezone($tz)->format('d/m/Y H:i:s') : null;

            $senderIp = $m['sender_ip'] ?? null;
            $senderPort = $m['sender_port'] ?? null;
            $type = $m['type'] ?? null;
            $messageId = $m['message_id'] ?? null;

            $ipBase = $this->extractIpBase(is_string($senderIp) ? $senderIp : null);

            $provider = null;
            if ($ipBase && isset($enrichedByIp[$ipBase])) {
                $info = $enrichedByIp[$ipBase];
                $provider = trim(($info['isp'] ?? '') ?: ($info['org'] ?? ''));
                $provider = preg_replace('/\s+/u', ' ', $provider ?? '') ?? '';
                if ($provider === '') $provider = null;
            }

            $row = [
                'timestamp' => $tsLocal,
                'sender_ip' => $senderIp,
                'sender_port' => $senderPort,
                'sender_provider' => $provider ?: 'Desconhecido',
                'type' => $type,
                'message_id' => $messageId,
            ];

            $byRecipient[$recipient] ??= [
                'recipient' => $recipient,
                'in_agenda' => isset($agendaPhones[$recipient]),
                'total' => 0,
                'rows' => [],
            ];

            $byRecipient[$recipient]['total']++;
            $byRecipient[$recipient]['rows'][] = $row;
        }

        $cards = [];
        foreach ($byRecipient as $recipient => $data) {
            $rows = $data['rows'];
            usort($rows, fn ($a, $b) => strcmp((string) ($b['timestamp'] ?? ''), (string) ($a['timestamp'] ?? '')));

            $cards[] = [
                'recipient' => $recipient,
                'in_agenda' => (bool) ($data['in_agenda'] ?? false),
                'total' => (int) ($data['total'] ?? 0),
                'latest' => $rows[0] ?? null,
                'others' => [],
            ];
        }

        usort($cards, fn ($a, $b) => ($b['total'] <=> $a['total']));

        return $cards;
    }

    private function buildGroupsRows(array $groups, string $tz): array
    {
        $rows = [];

        $owned = is_array($groups['owned'] ?? null) ? $groups['owned'] : [];
        $part = is_array($groups['participating'] ?? null) ? $groups['participating'] : [];

        $push = function (array $g, string $tipo) use (&$rows, $tz) {
            $createdUtc = $g['creation_utc'] ?? null;
            $createdLocal = null;

            if ($createdUtc instanceof Carbon) {
                $createdLocal = $createdUtc->copy()->setTimezone($tz)->format('d/m/Y H:i:s');
            }

            $rows[] = [
                'tipo' => $tipo,
                'id' => $g['id'] ?? null,
                'criacao' => $createdLocal,
                'membros' => is_numeric($g['size'] ?? null) ? (int) $g['size'] : null,
                'assunto' => $g['subject'] ?? null,
                'descricao' => $g['description'] ?? null,
            ];
        };

        foreach ($owned as $g) $push((array) $g, 'Criado (Owned)');
        foreach ($part as $g) $push((array) $g, 'Participa');

        return $rows;
    }

    private function extractGeneratedAt(array $parsed, string $tz): ?string
    {
        foreach ([
            $parsed['generated_at'] ?? null,
            $parsed['generatedAt'] ?? null,
            $parsed['meta']['generated_at'] ?? null,
            $parsed['summary']['generated_at'] ?? null,
        ] as $value) {
            $dt = $this->toCarbonUtc($value);
            if ($dt) return $dt->copy()->setTimezone($tz)->format('d/m/Y H:i:s');
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return null;
    }

    private function extractFileHash(array $parsed): ?string
    {
        foreach ([
            $parsed['file_hash'] ?? null,
            $parsed['hash'] ?? null,
            $parsed['meta']['file_hash'] ?? null,
            $parsed['meta']['hash'] ?? null,
        ] as $value) {
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return null;
    }

    private function extractDevice(array $parsed): ?string
    {
        foreach ([
            $parsed['device'] ?? null,
            $parsed['device_model'] ?? null,
            $parsed['meta']['device'] ?? null,
        ] as $value) {
            if (is_string($value) && trim($value) !== '') return trim($value);
        }
        return null;
    }

    private function extractPeriodLabel(array $parsed, array $events, string $tz): ?string
    {
        $start = $this->toCarbonUtc($parsed['range_start_utc'] ?? null);
        $end = $this->toCarbonUtc($parsed['range_end_utc'] ?? null);

        if ($start && $end) {
            // período em BR (mantém legível)
            return $start->copy()->setTimezone($tz)->format('d/m/Y H:i') . ' até ' . $end->copy()->setTimezone($tz)->format('d/m/Y H:i');
        }

        $raw = $parsed['date_range'] ?? null;
        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }

    private function extractSymmetricContactsCount(array $parsed): int
    {
        foreach ([
            $parsed['symmetric_contacts_count'] ?? null,
            $parsed['symmetric_contacts_total'] ?? null,
        ] as $value) {
            if (is_int($value)) return $value;
            if (is_numeric($value)) return (int) $value;
        }
        return 0;
    }

    private function extractAsymmetricContactsCount(array $parsed): int
    {
        foreach ([
            $parsed['asymmetric_contacts_count'] ?? null,
            $parsed['asymmetric_contacts_total'] ?? null,
        ] as $value) {
            if (is_int($value)) return $value;
            if (is_numeric($value)) return (int) $value;
        }
        return 0;
    }
}

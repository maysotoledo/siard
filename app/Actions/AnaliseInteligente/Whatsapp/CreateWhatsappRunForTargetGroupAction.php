<?php

namespace App\Actions\AnaliseInteligente\Whatsapp;

use App\Jobs\AnaliseInteligente\Platform\EnrichRunIpsJob;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use App\Services\AnaliseInteligente\RunPayloadStorage;
use App\Services\AnaliseInteligente\Whatsapp\RecordsHtmlParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateWhatsappRunForTargetGroupAction
{
    public function __construct(
        private readonly RunPayloadStorage $payloadStorage,
    ) {}

    public function execute(AnaliseInvestigation $investigation, int $userId, array $storedPaths, string $batchId): ?AnaliseRun
    {
        @ini_set('memory_limit', '512M');

        $disk = Storage::disk('public');
        $parsedList = [];

        foreach ($storedPaths as $storedPath) {
            if (! is_string($storedPath) || trim($storedPath) === '' || ! $disk->exists($storedPath)) {
                continue;
            }

            $html = $this->resolveHtmlFromUpload($storedPath);
            if (! is_string($html) || trim($html) === '') {
                continue;
            }

            $parsedList[] = [
                'stored_path' => $storedPath,
                'parsed' => (new RecordsHtmlParser())->parse($html),
            ];
        }

        if (count($parsedList) === 0) {
            return null;
        }

        $mainParsed = null;
        $maxIps = -1;

        foreach ($parsedList as $item) {
            $parsed = (array) ($item['parsed'] ?? []);
            $count = count((array) ($parsed['ip_events'] ?? []));

            if ($count > $maxIps) {
                $maxIps = $count;
                $mainParsed = $parsed;
            }
        }

        $mainParsed ??= (array) ($parsedList[0]['parsed'] ?? []);

        $ipsMap = $this->extractIpsMap($mainParsed);
        $connectionIpWithPort = data_get($mainParsed, 'connection_info.last_ip');
        $connectionIpBase = $this->extractIpBase(is_string($connectionIpWithPort) ? $connectionIpWithPort : null);

        $connectionLastSeenUtc = data_get($mainParsed, 'connection_info.last_seen_utc');
        $connectionLastSeenTs = null;
        if ($connectionLastSeenUtc instanceof Carbon) {
            $connectionLastSeenTs = $connectionLastSeenUtc->timestamp;
        } elseif (is_string($connectionLastSeenUtc) && trim($connectionLastSeenUtc) !== '') {
            $connectionLastSeenTs = strtotime($connectionLastSeenUtc) ?: null;
        }

        $runTargetRaw = $mainParsed['target'] ?? ($mainParsed['account_identifier'] ?? null);
        $ignoredBilhetagens = [];

        foreach ($parsedList as $item) {
            $parsed = (array) ($item['parsed'] ?? []);
            if (count((array) ($parsed['message_log'] ?? [])) === 0) {
                continue;
            }

            $fileTarget = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);
            if (! $this->targetsMatch(is_string($runTargetRaw) ? $runTargetRaw : null, is_string($fileTarget) ? $fileTarget : null)) {
                $ignoredBilhetagens[] = [
                    'arquivo' => (string) ($item['stored_path'] ?? ''),
                    'alvo_arquivo' => is_string($fileTarget) ? $fileTarget : '-',
                    'alvo_relatorio' => is_string($runTargetRaw) ? $runTargetRaw : '-',
                ];
            }
        }

        $resolvedTarget = is_string($runTargetRaw) && trim($runTargetRaw) !== ''
            ? trim($runTargetRaw)
            : (is_string($mainParsed['target'] ?? null) ? trim((string) $mainParsed['target']) : null);

        $this->validateAndLogTarget($resolvedTarget, $investigation->id, $storedPaths);

        $run = DB::transaction(function () use (
            $investigation,
            $userId,
            $batchId,
            $mainParsed,
            $resolvedTarget,
            $ipsMap,
            $connectionIpBase,
            $connectionLastSeenTs,
            $parsedList,
            $runTargetRaw,
            $ignoredBilhetagens
        ) {
            $runUuid = (string) Str::uuid();
            $parsedPath = $this->payloadStorage->storeParsedPayload($runUuid, $this->parsedForRunPayload($mainParsed));

            $run = AnaliseRun::create([
                'user_id' => $userId,
                'investigation_id' => $investigation->id,
                'uuid' => $runUuid,
                'source' => 'whatsapp',
                'target' => $resolvedTarget,
                'total_unique_ips' => count($ipsMap) + ($connectionIpBase && ! isset($ipsMap[$connectionIpBase]) ? 1 : 0),
                'processed_unique_ips' => 0,
                'progress' => count($ipsMap) === 0 ? 70 : 5,
                'status' => 'queued',
                'started_at' => now(),
                'report' => [
                    '_source' => 'whatsapp',
                    '_batch_id' => $batchId,
                    '_target_thread_id' => (string) Str::uuid(),
                    '_parsed_path' => $parsedPath,
                    '_warnings' => [
                        'ignored_bilhetagens' => $ignoredBilhetagens,
                    ],
                ],
                'summary' => [
                    '_source' => 'whatsapp',
                    '_platform_label' => 'WhatsApp',
                    'target' => $mainParsed['target'] ?? null,
                    'account_identifier' => $mainParsed['account_identifier'] ?? null,
                    'device' => $mainParsed['device'] ?? null,
                    'device_build' => $mainParsed['device_build'] ?? null,
                    'registered_emails' => array_values((array) ($mainParsed['registered_emails'] ?? [])),
                    'symmetric_contacts_count' => (int) ($mainParsed['symmetric_contacts_count'] ?? 0),
                    'asymmetric_contacts_count' => (int) ($mainParsed['asymmetric_contacts_count'] ?? 0),
                    'connection_info' => $mainParsed['connection_info'] ?? [],
                    'groups_rows' => array_values((array) ($mainParsed['groups'] ?? [])),
                ],
            ]);

            $now = now();
            $ipRows = [];

            foreach ($ipsMap as $ip => $meta) {
                $ipRows[] = [
                    'analise_run_id' => $run->id,
                    'ip' => $ip,
                    'occurrences' => (int) ($meta['occurrences'] ?? 0),
                    'last_seen_at' => ! empty($meta['last_seen_ts'])
                        ? now()->setTimestamp((int) $meta['last_seen_ts'])->toDateTimeString()
                        : null,
                    'enriched' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($connectionIpBase && ! isset($ipsMap[$connectionIpBase])) {
                $ipRows[] = [
                    'analise_run_id' => $run->id,
                    'ip' => $connectionIpBase,
                    'occurrences' => 0,
                    'last_seen_at' => $connectionLastSeenTs
                        ? now()->setTimestamp((int) $connectionLastSeenTs)->toDateTimeString()
                        : null,
                    'enriched' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($ipRows, 1000) as $chunk) {
                DB::table('analise_run_ips')->insert($chunk);
            }

            $eventRows = [];
            foreach ((array) ($mainParsed['ip_events'] ?? []) as $event) {
                $eventRows[] = [
                    'analise_run_id' => $run->id,
                    'event_type' => 'access',
                    'occurred_at' => $this->normalizeDate($event['time_utc'] ?? null)?->toDateTimeString(),
                    'timezone_label' => $event['tz_label'] ?? 'UTC',
                    'ip' => $event['ip'] ?? null,
                    'logical_port' => $event['logical_port'] ?? null,
                    'description' => $event['description'] ?? null,
                    'metadata' => json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($eventRows, 1000) as $chunk) {
                DB::table('analise_run_events')->insert($chunk);
            }

            $seen = [];
            $bilhetagemRows = [];

            foreach ($parsedList as $item) {
                $parsed = (array) ($item['parsed'] ?? []);
                $fileTarget = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);

                if (! $this->targetsMatch(is_string($runTargetRaw) ? $runTargetRaw : null, is_string($fileTarget) ? $fileTarget : null)) {
                    continue;
                }

                foreach ((array) ($parsed['message_log'] ?? []) as $message) {
                    $recipient = trim((string) ($message['recipient'] ?? ''));
                    if ($recipient === '') {
                        continue;
                    }

                    $timestampUtc = $message['timestamp_utc'] ?? null;
                    $tsKey = $timestampUtc instanceof Carbon ? $timestampUtc->format('Y-m-d H:i:s') : '-';
                    $messageId = trim((string) ($message['message_id'] ?? ''));
                    $key = $recipient . '|' . ($messageId !== '' ? $messageId : '-') . '|' . $tsKey;

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;

                    $bilhetagemRows[] = [
                        'analise_run_id' => $run->id,
                        'timestamp_utc' => $timestampUtc instanceof Carbon ? $timestampUtc->toDateTimeString() : null,
                        'message_id' => $messageId !== '' ? $messageId : null,
                        'sender' => $message['sender'] ?? null,
                        'recipient' => $recipient,
                        'sender_ip' => $message['sender_ip'] ?? null,
                        'sender_port' => $message['sender_port'] ?? null,
                        'type' => $message['type'] ?? null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($bilhetagemRows, 1000) as $chunk) {
                DB::table('bilhetagens')->insert($chunk);
            }

            return $run;
        });

        EnrichRunIpsJob::dispatch($run->id);

        return $run;
    }

    private function parsedForRunPayload(array $parsed): array
    {
        return [
            'target' => $parsed['target'] ?? null,
            'account_identifier' => $parsed['account_identifier'] ?? null,
            'generated_at' => $parsed['generated_at'] ?? null,
            'date_range' => $parsed['date_range'] ?? null,
            'range_start_utc' => $parsed['range_start_utc'] ?? null,
            'range_end_utc' => $parsed['range_end_utc'] ?? null,
            'device' => $parsed['device'] ?? null,
            'device_build' => $parsed['device_build'] ?? null,
            'registered_emails' => array_values(array_unique(array_map('strval', (array) ($parsed['registered_emails'] ?? [])))),
            'symmetric_contacts_total' => (int) ($parsed['symmetric_contacts_total'] ?? count((array) ($parsed['symmetric_contacts'] ?? []))),
            'asymmetric_contacts_total' => (int) ($parsed['asymmetric_contacts_total'] ?? count((array) ($parsed['asymmetric_contacts'] ?? []))),
            'symmetric_contacts_count' => (int) ($parsed['symmetric_contacts_count'] ?? count((array) ($parsed['symmetric_contacts'] ?? []))),
            'asymmetric_contacts_count' => (int) ($parsed['asymmetric_contacts_count'] ?? count((array) ($parsed['asymmetric_contacts'] ?? []))),
            'symmetric_contacts' => $this->sanitizePhoneList((array) ($parsed['symmetric_contacts'] ?? [])),
            'asymmetric_contacts' => $this->sanitizePhoneList((array) ($parsed['asymmetric_contacts'] ?? [])),
            'ip_events' => $this->sanitizeIpEvents((array) ($parsed['ip_events'] ?? [])),
            'groups' => $this->sanitizeGroups((array) ($parsed['groups'] ?? [])),
            'connection_info' => $this->sanitizeConnectionInfo((array) ($parsed['connection_info'] ?? [])),
            'message_log' => [],
        ];
    }

    private function sanitizePhoneList(array $phones): array
    {
        $out = [];

        foreach ($phones as $phone) {
            $value = trim((string) $phone);
            if ($value === '') {
                continue;
            }

            $out[$value] = $value;
        }

        return array_values($out);
    }

    private function sanitizeIpEvents(array $events): array
    {
        $rows = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $ip = trim((string) ($event['ip'] ?? ''));
            if ($ip === '') {
                continue;
            }

            $rows[] = [
                'ip' => $ip,
                'ip_with_port' => $event['ip_with_port'] ?? null,
                'port' => $event['port'] ?? null,
                'time_utc' => $event['time_utc'] ?? null,
            ];
        }

        return $rows;
    }

    private function sanitizeGroups(array $groups): array
    {
        $sanitize = function (array $items): array {
            $rows = [];

            foreach ($items as $group) {
                if (! is_array($group)) {
                    continue;
                }

                $rows[] = [
                    'id' => $group['id'] ?? null,
                    'creation_utc' => $group['creation_utc'] ?? null,
                    'size' => isset($group['size']) ? (int) $group['size'] : null,
                    'description' => $group['description'] ?? null,
                    'subject' => $group['subject'] ?? null,
                ];
            }

            return $rows;
        };

        return [
            'owned' => $sanitize((array) ($groups['owned'] ?? [])),
            'participating' => $sanitize((array) ($groups['participating'] ?? [])),
        ];
    }

    private function sanitizeConnectionInfo(array $connectionInfo): array
    {
        return [
            'last_ip' => $connectionInfo['last_ip'] ?? null,
            'last_seen_utc' => $connectionInfo['last_seen_utc'] ?? null,
        ];
    }

    private function extractIpsMap(array $parsed): array
    {
        $ipsMap = [];

        foreach ((array) ($parsed['ip_events'] ?? []) as $event) {
            $ip = trim((string) ($event['ip'] ?? ''));
            if ($ip === '') {
                continue;
            }

            $time = $event['time_utc'] ?? null;
            $ts = null;

            if ($time instanceof Carbon) {
                $ts = $time->timestamp;
            } elseif (is_string($time) && trim($time) !== '') {
                $ts = strtotime($time) ?: null;
            } elseif (is_int($time)) {
                $ts = $time;
            }

            $ipsMap[$ip] ??= ['occurrences' => 0, 'last_seen_ts' => $ts];
            $ipsMap[$ip]['occurrences']++;

            if ($ts && ($ipsMap[$ip]['last_seen_ts'] === null || $ts > $ipsMap[$ip]['last_seen_ts'])) {
                $ipsMap[$ip]['last_seen_ts'] = $ts;
            }
        }

        return $ipsMap;
    }

    private function resolveHtmlFromUpload(string $storedPath): ?string
    {
        $disk = Storage::disk('public');
        $fullPath = $disk->path($storedPath);

        if (! is_file($fullPath)) {
            return null;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($ext === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($fullPath) !== true) {
                return null;
            }

            $htmlContent = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (is_string($name) && str_ends_with(strtolower($name), 'records.html')) {
                    $htmlContent = $zip->getFromIndex($i);
                    break;
                }
            }

            if (! $htmlContent) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (! is_string($name)) {
                        continue;
                    }

                    $lower = strtolower($name);
                    if (str_ends_with($lower, '.html') || str_ends_with($lower, '.htm')) {
                        $htmlContent = $zip->getFromIndex($i);
                        break;
                    }
                }
            }

            $zip->close();

            return is_string($htmlContent) ? $htmlContent : null;
        }

        return $disk->get($storedPath);
    }

    private function extractIpBase(?string $ipWithPort): ?string
    {
        $ipWithPort = trim((string) $ipWithPort);
        if ($ipWithPort === '') {
            return null;
        }

        if (preg_match('/^\\[([0-9a-fA-F:]+)\\]:(\\d{1,5})$/', $ipWithPort, $match)) {
            return $match[1];
        }

        if (preg_match('/^(\\d{1,3}(?:\\.\\d{1,3}){3}):(\\d{1,5})$/', $ipWithPort, $match)) {
            return $match[1];
        }

        return $ipWithPort;
    }

    private function validateAndLogTarget(?string $target, int $investigationId, array $storedPaths): void
    {
        if ($target === null || trim($target) === '') {
            Log::warning('Alvo nao identificado no relatorio WhatsApp.', [
                'investigation_id' => $investigationId,
                'stored_paths' => $storedPaths,
            ]);
            return;
        }

        $digits = preg_replace('/\D+/', '', $target) ?? '';
        $isPhone = strlen($digits) >= 10 && strlen($digits) <= 15;

        if (! $isPhone && ! filter_var($target, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Alvo extraido nao parece ser telefone nem e-mail valido.', [
                'investigation_id' => $investigationId,
                'target' => $target,
                'stored_paths' => $storedPaths,
            ]);
        }
    }

    private function normalizeTarget(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\\D+/', '', $value) ?? '';
        $digits = trim($digits);

        if ($digits !== '') {
            return strlen($digits) > 10 ? substr($digits, -10) : $digits;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/\\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function targetsMatch(?string $runTargetRaw, ?string $fileTargetRaw): bool
    {
        $runTarget = $this->normalizeTarget($runTargetRaw);
        $fileTarget = $this->normalizeTarget($fileTargetRaw);

        if (! $runTarget || ! $fileTarget) {
            return false;
        }

        return $runTarget === $fileTarget;
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone('UTC');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value, 'UTC')->timezone('UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}

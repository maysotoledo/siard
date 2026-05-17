<?php

namespace App\Actions\AnaliseInteligente\Instagram;

use App\Jobs\AnaliseInteligente\Platform\EnrichRunIpsJob;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use App\Models\AnaliseRunEvent;
use App\Models\AnaliseRunIp;
use App\Services\AnaliseInteligente\Instagram\RecordsHtmlParser;
use App\Services\AnaliseInteligente\RunPayloadStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateInstagramRunsAction
{
    public function __construct(
        private readonly RunPayloadStorage $payloadStorage,
    ) {}

    public function execute(AnaliseInvestigation $investigation, int $userId, array $storedPaths, string $batchId): array
    {
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
            return [];
        }

        $groups = [];
        foreach ($parsedList as $item) {
            $parsed = (array) ($item['parsed'] ?? []);
            $targetRaw = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);
            $targetKey = $this->normalizeTarget(is_string($targetRaw) ? $targetRaw : null);

            if (! $targetKey) {
                $targetKey = 'sem-alvo:' . md5((string) ($item['stored_path'] ?? Str::uuid()));
            }

            $groups[$targetKey] ??= [];
            $groups[$targetKey][] = $item;
        }

        $runs = [];
        foreach ($groups as $items) {
            $mainParsed = $this->resolveMainParsed($items);
            if (! $mainParsed || count((array) ($mainParsed['ip_events'] ?? [])) === 0) {
                continue;
            }

            $ipsMap = $this->extractIpsMap($mainParsed);
            if (count($ipsMap) === 0) {
                continue;
            }

            $run = $this->createRun($investigation, $userId, $mainParsed, $ipsMap, $batchId);
            $runs[] = $run;
        }

        $runIds = array_map(fn (AnaliseRun $run): int => (int) $run->id, $runs);

        foreach ($runs as $run) {
            $report = is_array($run->report) ? $run->report : [];
            $report['_batch_run_ids'] = $runIds;
            $run->report = $report;
            $run->save();

            EnrichRunIpsJob::dispatch($run->id);
        }

        return $runs;
    }

    private function createRun(AnaliseInvestigation $investigation, int $userId, array $parsed, array $ipsMap, string $batchId): AnaliseRun
    {
        return DB::transaction(function () use ($investigation, $userId, $parsed, $ipsMap, $batchId) {
            $runUuid = (string) Str::uuid();
            $parsedPath = $this->payloadStorage->storeParsedPayload($runUuid, $parsed);

            $run = AnaliseRun::create([
                'user_id' => $userId,
                'investigation_id' => $investigation->id,
                'uuid' => $runUuid,
                'source' => 'instagram',
                'target' => $parsed['target'] ?? null,
                'total_unique_ips' => count($ipsMap),
                'processed_unique_ips' => 0,
                'progress' => count($ipsMap) === 0 ? 70 : 5,
                'status' => 'queued',
                'started_at' => now(),
                'report' => [
                    '_source' => 'instagram',
                    '_batch_id' => $batchId,
                    '_parsed_path' => $parsedPath,
                ],
                'summary' => [
                    '_source' => 'instagram',
                    '_platform_label' => 'Instagram',
                    'target' => $parsed['target'] ?? null,
                    'account_identifier' => $parsed['account_identifier'] ?? null,
                    'vanity_name' => $parsed['vanity_name'] ?? null,
                    'first_name' => $parsed['first_name'] ?? null,
                    'registration_date' => $this->serializeCarbon($parsed['registration_date'] ?? null),
                    'registration_ip' => $parsed['registration_ip'] ?? null,
                    'registration_phone' => $parsed['registration_phone'] ?? null,
                    'registration_phone_verified_on' => $this->serializeCarbon($parsed['registration_phone_verified_on'] ?? null),
                    'last_location_time' => $this->serializeCarbon($parsed['last_location_time'] ?? null),
                    'last_location_latitude' => $parsed['last_location_latitude'] ?? null,
                    'last_location_longitude' => $parsed['last_location_longitude'] ?? null,
                    'last_location_maps_url' => $parsed['last_location_maps_url'] ?? null,
                    'followers' => array_values((array) ($parsed['followers'] ?? [])),
                    'following' => array_values((array) ($parsed['following'] ?? [])),
                    'direct_threads' => array_values((array) ($parsed['direct_threads'] ?? [])),
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
                        ? now()->setTimestamp((int) $meta['last_seen_ts'])
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
            foreach ((array) ($parsed['ip_events'] ?? []) as $event) {
                $eventRows[] = [
                    'analise_run_id' => $run->id,
                    'event_type' => 'access',
                    'occurred_at' => $this->normalizeDate($event['time_utc'] ?? null),
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

            return $run;
        });
    }

    private function resolveMainParsed(array $items): ?array
    {
        $mainParsed = null;
        $maxIps = -1;

        foreach ($items as $item) {
            $parsed = (array) ($item['parsed'] ?? []);
            $count = count((array) ($parsed['ip_events'] ?? []));

            if ($count > $maxIps) {
                $maxIps = $count;
                $mainParsed = $parsed;
            }
        }

        return $mainParsed;
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

    private function normalizeTarget(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value);
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

        $html = @file_get_contents($fullPath);

        return is_string($html) ? $html : null;
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

    private function serializeCarbon(mixed $value): ?string
    {
        $date = $this->normalizeDate($value);

        return $date?->toIso8601String();
    }
}

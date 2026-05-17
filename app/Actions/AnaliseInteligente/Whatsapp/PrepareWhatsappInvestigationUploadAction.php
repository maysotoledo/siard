<?php

namespace App\Actions\AnaliseInteligente\Whatsapp;

use App\Jobs\AnaliseInteligente\Whatsapp\ProcessWhatsappTargetGroupJob;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use App\Models\Bilhetagem;
use App\Services\AnaliseInteligente\Whatsapp\RecordsHtmlParser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrepareWhatsappInvestigationUploadAction
{
    public function execute(AnaliseInvestigation $investigation, int $userId, array $storedPaths, string $batchId): void
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $groups = $this->groupUploadsByTarget($storedPaths);
        if (count($groups) === 0) {
            return;
        }

        $groupsWithIpLog = array_filter($groups, function (array $items): bool {
            foreach ($items as $item) {
                $parsed = (array) ($item['parsed'] ?? []);
                if (count((array) ($parsed['ip_events'] ?? [])) > 0) {
                    return true;
                }
            }

            return false;
        });

        $existingTargets = $this->existingTargetsByNormalizedKey($investigation);

        foreach ($groupsWithIpLog as $items) {
            $firstParsed = (array) data_get($items, '0.parsed', []);
            $targetRaw = $firstParsed['target'] ?? ($firstParsed['account_identifier'] ?? null);
            $targetKey = $this->normalizeTargetForDuplicateCheck(is_string($targetRaw) ? $targetRaw : null);

            if ($targetKey && isset($existingTargets[$targetKey])) {
                continue;
            }

            $paths = array_values(array_filter(array_map(
                fn (array $item): ?string => is_string($item['stored_path'] ?? null) ? $item['stored_path'] : null,
                $items,
            )));

            if (count($paths) === 0) {
                continue;
            }

            ProcessWhatsappTargetGroupJob::dispatch(
                investigationId: $investigation->id,
                userId: $userId,
                storedPaths: $paths,
                batchId: $batchId,
            );
        }

        $this->importBilhetagemOnlyGroupsIntoInvestigation($investigation, array_diff_key($groups, $groupsWithIpLog));
    }

    private function groupUploadsByTarget(array $storedPaths): array
    {
        $disk = Storage::disk('public');
        $parser = new RecordsHtmlParser();
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
                'parsed' => $parser->parse($html),
            ];
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

        return $groups;
    }

    private function importBilhetagemOnlyGroupsIntoInvestigation(AnaliseInvestigation $investigation, array $groups): int
    {
        $runs = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->get(['id', 'target']);

        $runsByTarget = [];
        foreach ($runs as $run) {
            $key = $this->normalizeTargetForDuplicateCheck(is_string($run->target) ? $run->target : null);
            if ($key) {
                $runsByTarget[$key] = $run;
            }
        }

        $inserted = 0;

        foreach ($groups as $items) {
            $parsed = (array) data_get($items, '0.parsed', []);
            $raw = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);
            $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);

            if (! $key || ! isset($runsByTarget[$key])) {
                continue;
            }

            foreach ($items as $item) {
                $parsed = (array) ($item['parsed'] ?? []);
                $inserted += $this->insertBilhetagemMessages($runsByTarget[$key], (array) ($parsed['message_log'] ?? []));
            }
        }

        return $inserted;
    }

    private function insertBilhetagemMessages(AnaliseRun $run, array $messageLog): int
    {
        $seen = [];
        $rowsByKey = [];

        foreach ($messageLog as $message) {
            $recipient = trim((string) ($message['recipient'] ?? ''));
            if ($recipient === '') {
                continue;
            }

            $timestampUtc = $message['timestamp_utc'] ?? null;
            $tsKey = $timestampUtc instanceof \Carbon\Carbon ? $timestampUtc->format('Y-m-d H:i:s') : '-';
            $messageId = trim((string) ($message['message_id'] ?? ''));
            $key = $recipient . '|' . ($messageId !== '' ? $messageId : '-') . '|' . $tsKey;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $rowsByKey[$key] = [
                'analise_run_id' => $run->id,
                'timestamp_utc' => $timestampUtc instanceof Carbon ? $timestampUtc->toDateTimeString() : null,
                'message_id' => $messageId !== '' ? $messageId : null,
                'sender' => $message['sender'] ?? null,
                'recipient' => $recipient,
                'sender_ip' => $message['sender_ip'] ?? null,
                'sender_port' => $message['sender_port'] ?? null,
                'type' => $message['type'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (count($rowsByKey) === 0) {
            return 0;
        }

        $existingKeys = [];
        $candidateRows = array_values($rowsByKey);

        foreach (array_chunk($candidateRows, 500) as $chunk) {
            $query = Bilhetagem::query()->where('analise_run_id', $run->id);

            $query->where(function ($outer) use ($chunk): void {
                foreach ($chunk as $row) {
                    $outer->orWhere(function ($inner) use ($row): void {
                        $inner->where('recipient', $row['recipient']);

                        $row['message_id'] === null
                            ? $inner->whereNull('message_id')
                            : $inner->where('message_id', $row['message_id']);

                        $row['timestamp_utc'] === null
                            ? $inner->whereNull('timestamp_utc')
                            : $inner->where('timestamp_utc', $row['timestamp_utc']);
                    });
                }
            });

            foreach ($query->get(['recipient', 'message_id', 'timestamp_utc']) as $existing) {
                $ts = $existing->timestamp_utc instanceof Carbon
                    ? $existing->timestamp_utc->format('Y-m-d H:i:s')
                    : '-';
                $msg = trim((string) ($existing->message_id ?? ''));
                $existingKeys[$existing->recipient . '|' . ($msg !== '' ? $msg : '-') . '|' . $ts] = true;
            }
        }

        $rows = array_values(array_filter(
            $rowsByKey,
            fn (array $row, string $key): bool => ! isset($existingKeys[$key]),
            ARRAY_FILTER_USE_BOTH,
        ));

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('bilhetagens')->insert($chunk);
        }

        return count($rows);
    }

    private function existingTargetsByNormalizedKey(AnaliseInvestigation $investigation): array
    {
        return AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->get(['target'])
            ->mapWithKeys(function (AnaliseRun $run): array {
                $key = $this->normalizeTargetForDuplicateCheck(is_string($run->target) ? $run->target : null);

                return $key ? [$key => true] : [];
            })
            ->all();
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
                if (! is_string($name)) {
                    continue;
                }

                if (str_ends_with(strtolower($name), 'records.html')) {
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

                    $lowerName = strtolower($name);
                    if (str_ends_with($lowerName, '.html') || str_ends_with($lowerName, '.htm')) {
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

    private function normalizeTarget(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $digits = trim($digits);

        if ($digits !== '') {
            return strlen($digits) > 10 ? substr($digits, -10) : $digits;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function normalizeTargetForDuplicateCheck(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $digits = trim($digits);

        if ($digits !== '') {
            return $digits;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = trim($value);

        return $value !== '' ? $value : null;
    }
}

<?php

namespace App\Jobs\AnaliseInteligente\Platform;

use App\Models\AnaliseRun;
use App\Models\AnaliseRunIp;
use App\Models\AnaliseRunStep;
use App\Models\IpEnrichment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EnrichRunIpsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;
    public int $tries = 3;
    public array $backoff = [60, 300];

    public function __construct(
        public int $runId,
        public int $chunkSize = 5,
    ) {
        $this->onConnection('database');
    }

    public function handle(): void
    {
        @set_time_limit(0);

        $run = AnaliseRun::find($this->runId);
        if (! $run) {
            return;
        }

        $step = AnaliseRunStep::firstOrCreate(
            ['analise_run_id' => $run->id, 'step' => 'enrich_ips'],
            ['status' => 'queued', 'total' => $run->total_unique_ips]
        );

        $step->forceFill([
            'status' => 'running',
            'started_at' => $step->started_at ?? now(),
            'message' => 'Enriquecendo IPs em fila.',
        ])->save();

        while (true) {
            $rows = AnaliseRunIp::query()
                ->where('analise_run_id', $run->id)
                ->where(function ($query): void {
                    $query->where('enriched', false)->orWhereNull('enriched');
                })
                ->limit($this->chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                $this->finish($run, $step);
                return;
            }

            foreach ($rows as $row) {
                $this->processIpRow($row);
            }

            $processedRows = AnaliseRunIp::query()
                ->where('analise_run_id', $run->id)
                ->where('enriched', true)
                ->count();

            $processed = AnaliseRunIp::query()
                ->where('analise_run_id', $run->id)
                ->where('enriched', true)
                ->distinct('ip')
                ->count('ip');

            $processedForProgress = min($processed, (int) $run->total_unique_ips);

            $run->forceFill([
                'processed_unique_ips' => $processedForProgress,
                'progress' => min(95, $run->total_unique_ips > 0 ? (int) floor(($processedForProgress / $run->total_unique_ips) * 90) + 5 : 95),
                'status' => 'running',
            ])->save();

            $step->forceFill([
                'processed' => $processedRows,
                'total' => (int) $run->total_unique_ips,
            ])->save();
        }
    }

    private function finish(AnaliseRun $run, AnaliseRunStep $step): void
    {
        $enriched = AnaliseRunIp::where('analise_run_id', $run->id)->where('enriched', true)->count();
        $failed = AnaliseRunIp::where('analise_run_id', $run->id)
            ->leftJoin('ip_enrichments', 'ip_enrichments.ip', '=', 'analise_run_ips.ip')
            ->where('analise_run_ips.enriched', true)
            ->where('ip_enrichments.status', 'fail')
            ->count();

        Log::info('EnrichRunIpsJob: enriquecimento concluido.', [
            'run_id' => $run->id,
            'total_ips' => (int) $run->total_unique_ips,
            'enriched' => $enriched,
            'failed_providers' => $failed,
        ]);

        $run->forceFill([
            'processed_unique_ips' => (int) $run->total_unique_ips,
            'progress' => 95,
            'status' => 'running',
        ])->save();

        $step->forceFill([
            'status' => 'done',
            'processed' => (int) $run->total_unique_ips,
            'total' => (int) $run->total_unique_ips,
            'finished_at' => now(),
            'message' => "Enriquecimento concluido. {$enriched} IPs processados, {$failed} sem provedor.",
        ])->save();

        app()->call([(new BuildPlatformRunSummaryJob($run->id)), 'handle']);
    }

    private function processIpRow(AnaliseRunIp $row): void
    {
        $ip = trim((string) ($row->ip ?? ''));
        if ($ip === '') {
            $row->forceFill(['enriched' => true])->save();
            return;
        }

        $existing = IpEnrichment::query()->where('ip', $ip)->first();
        if ($existing && (trim((string) ($existing->isp ?: $existing->org)) !== '' || $existing->status === 'fail')) {
            $row->forceFill(['enriched' => true])->save();
            return;
        }

        $data = $this->fetchEnrichment($ip);

        IpEnrichment::updateOrCreate(
            ['ip' => $ip],
            [
                'city' => $data['city'] ?? null,
                'isp' => $data['isp'] ?? null,
                'org' => $data['org'] ?? null,
                'mobile' => (bool) ($data['mobile'] ?? false),
                'status' => $data['status'] ?? 'success',
                'message' => $data['message'] ?? null,
                'fetched_at' => now(),
            ],
        );

        $row->forceFill(['enriched' => true])->save();
    }

    private function fetchEnrichment(string $ip): array
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [
                'status' => 'fail',
                'message' => 'IP privado, reservado ou invalido para enriquecimento publico',
            ];
        }

        foreach ([
            fn (): array => $this->fetchFromIpApi($ip),
            fn (): array => $this->fetchFromIpWhoIs($ip),
            fn (): array => $this->fetchFromIpApiCo($ip),
            fn (): array => $this->fetchFromIpInfo($ip),
        ] as $fetcher) {
            $data = $fetcher();
            if (trim((string) (($data['isp'] ?? '') ?: ($data['org'] ?? ''))) !== '') {
                return $data;
            }
        }

        return [
            'status' => 'fail',
            'message' => 'Nao foi possivel identificar provedor nos servicos consultados',
        ];
    }

    private function fetchFromIpApi(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)->timeout(1)->get('http://ip-api.com/json/' . $ip, [
                'fields' => 'status,message,city,isp,org,mobile',
            ]);

            $json = $response->json();
            if (! $response->successful() || ! is_array($json) || ($json['status'] ?? null) !== 'success') {
                return [];
            }

            return [
                'city' => $json['city'] ?? null,
                'isp' => $json['isp'] ?? null,
                'org' => $json['org'] ?? null,
                'mobile' => $json['mobile'] ?? false,
                'status' => 'success',
                'message' => 'ip-api',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Falha no provider ip-api.', ['ip' => $ip, 'error' => $exception->getMessage()]);
            return [];
        }
    }

    private function fetchFromIpWhoIs(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)->timeout(1)->get('https://ipwho.is/' . $ip, [
                'fields' => 'success,message,city,connection,type',
            ]);

            $json = $response->json();
            if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true) {
                return [];
            }

            $connection = is_array($json['connection'] ?? null) ? $json['connection'] : [];

            return [
                'city' => $json['city'] ?? null,
                'isp' => $connection['isp'] ?? null,
                'org' => $connection['org'] ?? null,
                'mobile' => str_contains(strtolower((string) ($json['type'] ?? '')), 'mobile'),
                'status' => 'success',
                'message' => 'ipwho.is',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Falha no provider ipwho.is.', ['ip' => $ip, 'error' => $exception->getMessage()]);
            return [];
        }
    }

    private function fetchFromIpApiCo(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)->timeout(1)->get('https://ipapi.co/' . $ip . '/json/');
            $json = $response->json();

            if (! $response->successful() || ! is_array($json) || isset($json['error'])) {
                return [];
            }

            return [
                'city' => $json['city'] ?? null,
                'isp' => $json['org'] ?? null,
                'org' => $json['org'] ?? null,
                'mobile' => false,
                'status' => 'success',
                'message' => 'ipapi.co',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Falha no provider ipapi.co.', ['ip' => $ip, 'error' => $exception->getMessage()]);
            return [];
        }
    }

    private function fetchFromIpInfo(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)->timeout(1)->get('https://ipinfo.io/' . $ip . '/json');
            $json = $response->json();

            if (! $response->successful() || ! is_array($json) || isset($json['bogon'])) {
                return [];
            }

            return [
                'city' => $json['city'] ?? null,
                'isp' => $json['org'] ?? null,
                'org' => $json['org'] ?? null,
                'mobile' => false,
                'status' => 'success',
                'message' => 'ipinfo.io',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Falha no provider ipinfo.io.', ['ip' => $ip, 'error' => $exception->getMessage()]);
            return [];
        }
    }
}

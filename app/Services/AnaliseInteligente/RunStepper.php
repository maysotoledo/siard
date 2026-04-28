<?php

namespace App\Services\AnaliseInteligente;

use App\Models\AnaliseRun;
use App\Models\AnaliseRunIp;
use App\Models\IpEnrichment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunStepper
{
    public function step(AnaliseRun $run, int $chunkSize = 5, float $sleepSeconds = 0.0): void
    {
        $deadline = microtime(true) + 5.0;
        @set_time_limit(20);

        if (($run->status ?? null) !== 'running') {
            return;
        }

        $total = (int) ($run->total_unique_ips ?? 0);
        if ($total <= 0) {
            $this->finishRun($run);
            return;
        }

        $ips = AnaliseRunIp::query()
            ->where('analise_run_id', $run->id)
            ->where(function ($query): void {
                $query->where('enriched', false)->orWhereNull('enriched');
            })
            ->limit(max(1, $chunkSize))
            ->get();

        if ($ips->count() === 0) {
            $this->finishRun($run);
            return;
        }

        $processedNow = 0;

        foreach ($ips as $row) {
            if (microtime(true) >= $deadline) {
                break;
            }

            try {
                $this->processIpRow($row);
            } catch (\Throwable $e) {
                Log::warning('RunStepper: erro ao processar IP', [
                    'run_id' => $run->id,
                    'ip' => $row->ip ?? null,
                    'error' => $e->getMessage(),
                ]);

                $row->enriched = true;
                $row->save();
            }

            $processedNow++;

            if ($sleepSeconds > 0 && microtime(true) < $deadline) {
                usleep((int) round($sleepSeconds * 1_000_000));
            }
        }

        $run->refresh();

        $already = (int) ($run->processed_unique_ips ?? 0);
        $newProcessed = min($total, $already + $processedNow);

        $run->processed_unique_ips = $newProcessed;
        $run->progress = (int) floor(($newProcessed / $total) * 100);

        if ($newProcessed >= $total) {
            $this->finishRun($run);
        } else {
            $run->status = 'running';
            $run->save();
        }
    }

    private function processIpRow(AnaliseRunIp $row): void
    {
        $ip = trim((string) ($row->ip ?? ''));
        if ($ip === '') {
            $row->enriched = true;
            $row->save();
            return;
        }

        $existing = IpEnrichment::query()->where('ip', $ip)->first();
        if ($existing && $this->hasProviderData($existing)) {
            $row->enriched = true;
            $row->save();
            return;
        }

        $data = $this->fetchEnrichment($ip);

        IpEnrichment::updateOrCreate(
            ['ip' => $ip],
            [
                'ip' => $ip,
                'city' => $data['city'] ?? null,
                'isp' => $data['isp'] ?? null,
                'org' => $data['org'] ?? null,
                'mobile' => (bool) ($data['mobile'] ?? false),
                'status' => $data['status'] ?? 'success',
                'message' => $data['message'] ?? null,
                'fetched_at' => now(),
            ],
        );

        $row->enriched = true;
        $row->save();
    }

    private function fetchEnrichment(string $ip): array
    {
        if (! $this->isPublicIp($ip)) {
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

            if ($this->hasProviderPayload($data)) {
                return $this->normalizeProviderPayload($data);
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
            $response = Http::connectTimeout(0.5)
                ->timeout(1)
                ->get('http://ip-api.com/json/' . $ip, [
                    'fields' => 'status,message,city,isp,org,mobile',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();
            if (! is_array($json) || ($json['status'] ?? null) !== 'success') {
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
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchFromIpWhoIs(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)
                ->timeout(1)
                ->get('https://ipwho.is/' . $ip, [
                    'fields' => 'success,message,city,connection,type',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();
            if (! is_array($json) || ($json['success'] ?? false) !== true) {
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
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchFromIpApiCo(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)
                ->timeout(1)
                ->get('https://ipapi.co/' . $ip . '/json/');

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();
            if (! is_array($json) || isset($json['error'])) {
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
        } catch (\Throwable) {
            return [];
        }
    }

    private function fetchFromIpInfo(string $ip): array
    {
        try {
            $response = Http::connectTimeout(0.5)
                ->timeout(1)
                ->get('https://ipinfo.io/' . $ip . '/json');

            if (! $response->successful()) {
                return [];
            }

            $json = $response->json();
            if (! is_array($json) || isset($json['bogon'])) {
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
        } catch (\Throwable) {
            return [];
        }
    }

    private function hasProviderData(IpEnrichment $enrichment): bool
    {
        return trim((string) ($enrichment->isp ?? '')) !== ''
            || trim((string) ($enrichment->org ?? '')) !== '';
    }

    private function hasProviderPayload(array $data): bool
    {
        return trim((string) ($data['isp'] ?? '')) !== ''
            || trim((string) ($data['org'] ?? '')) !== '';
    }

    private function normalizeProviderPayload(array $data): array
    {
        $isp = trim((string) ($data['isp'] ?? ''));
        $org = trim((string) ($data['org'] ?? ''));

        if ($isp === '' && $org !== '') {
            $isp = $org;
        }

        if ($org === '' && $isp !== '') {
            $org = $isp;
        }

        return [
            'city' => trim((string) ($data['city'] ?? '')) ?: null,
            'isp' => $isp ?: null,
            'org' => $org ?: null,
            'mobile' => (bool) ($data['mobile'] ?? false),
            'status' => $data['status'] ?? 'success',
            'message' => $data['message'] ?? null,
        ];
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    private function finishRun(AnaliseRun $run): void
    {
        $total = (int) ($run->total_unique_ips ?? 0);

        $run->processed_unique_ips = $total;
        $run->progress = 100;
        $run->status = 'done';
        $run->save();
    }
}

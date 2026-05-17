<?php

namespace App\Jobs\AnaliseInteligente\Platform;

use App\Actions\AnaliseInteligente\Platform\PersistPlatformRunAction;
use App\Models\AnaliseInvestigation;
use App\Services\AnaliseInteligente\Apple\AppleLogParser;
use App\Services\AnaliseInteligente\Google\GoogleLogParser;
use App\Services\AnaliseInteligente\Platform\PlatformLogParser;
use App\Services\AnaliseInteligente\Platform\PlatformUploadParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessPlatformInvestigationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(
        public int $investigationId,
        public int $userId,
        public string $source,
        public string $label,
        public array $storedPaths,
        public string $batchId,
    ) {
        $this->onConnection('database');
    }

    public function handle(PersistPlatformRunAction $persistRunAction): void
    {
        @set_time_limit(0);

        $investigation = AnaliseInvestigation::find($this->investigationId);
        if (! $investigation) {
            return;
        }

        $parser = new PlatformUploadParser(
            $this->source,
            $this->label,
            $this->makeParser(),
        );

        $groups = $parser->parseStoredUploads($this->storedPaths);

        foreach ($groups as $group) {
            $parsed = (array) ($group['parsed'] ?? []);
            $ipsMap = $parser->buildIpsMap((array) ($parsed['events'] ?? []));

            if ($this->shouldSkipGoogleSupplementOnlyGroup($parsed, $ipsMap)) {
                continue;
            }

            if (count($ipsMap) === 0 && count((array) ($parsed['maps_rows'] ?? [])) === 0 && count((array) ($parsed['search_rows'] ?? [])) === 0) {
                continue;
            }

            $run = $persistRunAction->execute(
                investigation: $investigation,
                userId: $this->userId,
                source: $this->source,
                label: $this->label,
                batchId: $this->batchId,
                group: $group,
                ipsMap: $ipsMap,
            );

            EnrichRunIpsJob::dispatch($run->id);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao processar investigacao de plataforma.', [
            'investigation_id' => $this->investigationId,
            'source' => $this->source,
            'error' => $exception->getMessage(),
        ]);
    }

    private function makeParser(): PlatformLogParser
    {
        return match ($this->source) {
            'google' => new GoogleLogParser(),
            'apple' => new AppleLogParser(),
            default => new PlatformLogParser($this->source, $this->label),
        };
    }

    private function shouldSkipGoogleSupplementOnlyGroup(array $parsed, array $ipsMap): bool
    {
        if ($this->source !== 'google') {
            return false;
        }

        $hasSubscriberInfo = is_array($parsed['google_subscriber_info'] ?? null) && count((array) $parsed['google_subscriber_info']) > 0;
        $hasAccessEvents = count((array) ($parsed['events'] ?? [])) > 0 || count($ipsMap) > 0;
        $hasSupplementalData = count((array) ($parsed['maps_rows'] ?? [])) > 0 || count((array) ($parsed['search_rows'] ?? [])) > 0;

        return ! $hasSubscriberInfo && ! $hasAccessEvents && $hasSupplementalData;
    }
}

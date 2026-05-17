<?php

namespace App\Jobs\AnaliseInteligente\Instagram;

use App\Actions\AnaliseInteligente\Instagram\CreateInstagramRunsAction;
use App\Models\AnaliseInvestigation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessInstagramInvestigationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;
    public int $tries = 3;
    public array $backoff = [30, 120];

    public function __construct(
        public int $investigationId,
        public int $userId,
        public array $storedPaths,
        public string $batchId,
    ) {
        $this->onConnection('database');
    }

    public function handle(CreateInstagramRunsAction $action): void
    {
        @set_time_limit(0);

        $investigation = AnaliseInvestigation::find($this->investigationId);
        if (! $investigation) {
            return;
        }

        $action->execute($investigation, $this->userId, $this->storedPaths, $this->batchId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao processar investigacao do Instagram.', [
            'investigation_id' => $this->investigationId,
            'error' => $exception->getMessage(),
        ]);
    }
}

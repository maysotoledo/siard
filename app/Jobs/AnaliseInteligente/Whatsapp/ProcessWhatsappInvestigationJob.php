<?php

namespace App\Jobs\AnaliseInteligente\Whatsapp;

use App\Actions\AnaliseInteligente\Whatsapp\PrepareWhatsappInvestigationUploadAction;
use App\Models\AnaliseInvestigation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessWhatsappInvestigationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;
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

    public function handle(PrepareWhatsappInvestigationUploadAction $action): void
    {
        $investigation = AnaliseInvestigation::find($this->investigationId);
        if (! $investigation) {
            return;
        }

        $action->execute($investigation, $this->userId, $this->storedPaths, $this->batchId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Falha ao preparar investigacao do WhatsApp.', [
            'investigation_id' => $this->investigationId,
            'stored_paths' => $this->storedPaths,
            'error' => $exception->getMessage(),
        ]);
    }
}

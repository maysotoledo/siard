<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Services\AI\AiManager;
use App\Services\IA\OllamaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessAiAnalysisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function __construct(public int $aiAnalysisId)
    {
    }

    public function handle(OllamaService $ollamaService): void
    {
        $analysis = AiAnalysis::query()->find($this->aiAnalysisId);

        if (! $analysis) {
            return;
        }

        $this->atualizarAnalise($analysis, 'processing', 10, null);
        $this->atualizarAnalise($analysis, 'processing', 25, null);

        // Tenta usar AiManager (multi-provider); cai em OllamaService em caso de falha.
        try {
            $manager  = new AiManager();
            $contexto = is_array($analysis->contexto) ? $analysis->contexto : [];
            $prompt   = (string) $analysis->pergunta . "\n\nCONTEXTO:\n" . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $resposta = $manager->generate($prompt);
        } catch (\Throwable) {
            $resposta = $ollamaService->chat(
                pergunta: (string) $analysis->pergunta,
                contexto: is_array($analysis->contexto) ? $analysis->contexto : [],
                tipo: $analysis->tipo,
                modelo: $analysis->modelo
            );
        }

        $this->atualizarAnalise($analysis, 'processing', 85, null);

        $completedPayload = ['resposta' => $resposta];

        if (AiAnalysis::hasStatusColumn()) {
            $completedPayload['status'] = 'completed';
        }

        if (AiAnalysis::hasProgressColumn()) {
            $completedPayload['progress'] = 100;
        }

        if (AiAnalysis::hasErroColumn()) {
            $completedPayload['erro'] = null;
        }

        $analysis->forceFill($completedPayload)->save();
    }

    public function failed(Throwable $exception): void
    {
        $analysis = AiAnalysis::query()->find($this->aiAnalysisId);

        if (! $analysis) {
            return;
        }

        $failedPayload = [];

        if (AiAnalysis::hasStatusColumn()) {
            $failedPayload['status'] = 'failed';
        }

        if (AiAnalysis::hasProgressColumn()) {
            $failedPayload['progress'] = 100;
        }

        if (AiAnalysis::hasErroColumn()) {
            $failedPayload['erro'] = $exception->getMessage();
        }

        if ($failedPayload !== []) {
            $analysis->forceFill($failedPayload)->save();
        }
    }

    private function atualizarAnalise(AiAnalysis $analysis, string $status, int $progress, ?string $erro): void
    {
        $payload = [];

        if (AiAnalysis::hasStatusColumn()) {
            $payload['status'] = $status;
        }

        if (AiAnalysis::hasProgressColumn()) {
            $payload['progress'] = max(0, min(100, $progress));
        }

        if (AiAnalysis::hasErroColumn()) {
            $payload['erro'] = $erro;
        }

        if ($payload !== []) {
            $analysis->forceFill($payload)->save();
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\AiReport;
use App\Models\AnaliseRun;
use App\Services\AI\AiManager;
use App\Services\AI\RelatorioIaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class GerarRelatorioIaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(public int $aiReportId) {}

    public function handle(): void
    {
        $report = AiReport::find($this->aiReportId);

        if (! $report) {
            Log::warning('GerarRelatorioIaJob: AiReport não encontrado', ['id' => $this->aiReportId]);
            return;
        }

        $report->update(['status' => 'processing']);

        $context = $report->investigationContext;

        if (! $context) {
            $report->update(['status' => 'failed', 'erro' => 'InvestigationContext não encontrado.']);
            return;
        }

        // Run direto ou null — o RelatorioIaService busca pela investigação se necessário
        $run = $report->analise_run_id ? AnaliseRun::find($report->analise_run_id) : null;

        try {
            $manager  = new AiManager();
            $service  = new RelatorioIaService($manager);

            $resposta = match ($report->tipo) {
                'resumo_tecnico'     => $service->gerarResumoTecnico($context, $run),
                'linha_investigacao' => $service->gerarLinhaInvestigacao($context, $run),
                'relatorio_completo' => $service->gerarRelatorioCompleto($context, $run),
                'conclusao'          => $service->gerarConclusao($context, $run),
                'minuta_autoridade'  => $service->gerarMinutaAutoridade($context, $run),
                default              => throw new \InvalidArgumentException("Tipo de relatório inválido: {$report->tipo}"),
            };

            $report->update([
                'status'   => 'done',
                'resposta' => $resposta,
                'provider' => $manager->providerName(),
                'model'    => $manager->modelName(),
                'erro'     => null,
            ]);

            Log::info('GerarRelatorioIaJob: concluído', [
                'id'       => $this->aiReportId,
                'tipo'     => $report->tipo,
                'provider' => $manager->providerName(),
            ]);
        } catch (Throwable $e) {
            Log::error('GerarRelatorioIaJob: falhou', [
                'id'    => $this->aiReportId,
                'error' => $e->getMessage(),
            ]);

            $report->update([
                'status' => 'failed',
                'erro'   => $e->getMessage(),
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $report = AiReport::find($this->aiReportId);

        if ($report) {
            $report->update([
                'status' => 'failed',
                'erro'   => 'Job falhou na fila: ' . $exception->getMessage(),
            ]);
        }
    }
}

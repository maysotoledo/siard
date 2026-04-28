<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAiAnalysisJob;
use Illuminate\Console\Command;

class ProcessAiAnalysisCommand extends Command
{
    protected $signature = 'ai-analysis:process {analysisId}';

    protected $description = 'Processa uma analise de IA especifica em background';

    public function handle(): int
    {
        $analysisId = (int) $this->argument('analysisId');

        ProcessAiAnalysisJob::dispatchSync($analysisId);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\Plantao\PlantaoCqhService;
use App\Services\Plantao\PlantaoEscalaService;
use Illuminate\Console\Command;

class GerarPlantaoCompletaCommand extends Command
{
    protected $signature = 'plantao:gerar-completa {--mes=} {--ano=} {--equipe-inicial=} {--force}';
    protected $description = 'Gera escala mensal de plantão e CQH.';

    public function handle(PlantaoEscalaService $escalaService, PlantaoCqhService $cqhService): int
    {
        $escalaService->gerarEscalaMensal((int) $this->option('mes'), (int) $this->option('ano'), (int) $this->option('equipe-inicial'), (bool) $this->option('force'));
        $total = $cqhService->gerarEscalaCqhMensal((int) $this->option('mes'), (int) $this->option('ano'));
        $this->info("Escala completa gerada. CQH atualizado em {$total} dia(s).");

        return self::SUCCESS;
    }
}

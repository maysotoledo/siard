<?php

namespace App\Console\Commands;

use App\Services\Plantao\PlantaoEscalaService;
use Illuminate\Console\Command;

class GerarPlantaoEscalaCommand extends Command
{
    protected $signature = 'plantao:gerar-escala {--mes=} {--ano=} {--equipe-inicial=} {--force}';
    protected $description = 'Gera escala mensal de plantão 24x72.';

    public function handle(PlantaoEscalaService $service): int
    {
        $summary = $service->gerarEscalaMensal((int) $this->option('mes'), (int) $this->option('ano'), (int) $this->option('equipe-inicial'), (bool) $this->option('force'));
        $this->table(['Criados', 'Atualizados', 'Ignorados'], [[$summary['criados'], $summary['atualizados'], $summary['ignorados']]]);

        return self::SUCCESS;
    }
}

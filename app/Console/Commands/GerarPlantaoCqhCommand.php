<?php

namespace App\Console\Commands;

use App\Services\Plantao\PlantaoCqhService;
use Illuminate\Console\Command;

class GerarPlantaoCqhCommand extends Command
{
    protected $signature = 'plantao:gerar-cqh {--mes=} {--ano=}';
    protected $description = 'Gera escala mensal de CQH Geral.';

    public function handle(PlantaoCqhService $service): int
    {
        $total = $service->gerarEscalaCqhMensal((int) $this->option('mes'), (int) $this->option('ano'));
        $this->info("Dias atualizados: {$total}");

        return self::SUCCESS;
    }
}

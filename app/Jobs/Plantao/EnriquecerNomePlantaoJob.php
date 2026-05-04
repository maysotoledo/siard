<?php

namespace App\Jobs\Plantao;

use App\Services\Plantao\PlantaoNomeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job que chama a IA em background para enriquecer o cache de nomes do calendário.
 * Roda de forma assíncrona — o calendário nunca espera por ele.
 */
class EnriquecerNomePlantaoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(private readonly string $nomeCompleto) {}

    public function handle(PlantaoNomeService $service): void
    {
        $service->enriquecerViaIA($this->nomeCompleto);
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\TipoAfastamento;
use App\Models\User;
use App\Services\Afastamentos\AfastamentoPeriodoAquisitivoService;
use Illuminate\Console\Command;
use Throwable;

class GerarAfastamentoPeriodosCommand extends Command
{
    protected $signature = 'afastamentos:gerar-periodos
        {--servidor= : ID do servidor/usuário}
        {--tipo= : ferias ou licenca_premio}
        {--todos : Gerar para todos os servidores}
        {--dry-run : Simular sem salvar}
        {--force : Atualizar períodos gerados automaticamente e manuais sem solicitações}';

    protected $description = 'Gera, atualiza e recalcula períodos aquisitivos de afastamentos funcionais.';

    public function handle(AfastamentoPeriodoAquisitivoService $service): int
    {
        try {
            $tipo = $this->resolveTipo();
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($this->option('servidor')) {
            $servidor = User::query()->find((int) $this->option('servidor'));

            if (! $servidor) {
                $this->error('Servidor não encontrado.');

                return self::FAILURE;
            }

            $summary = $service->gerarParaServidor($servidor, $tipo, $dryRun, $force);
            $this->printSummary($summary);

            return self::SUCCESS;
        }

        if (! $this->option('todos')) {
            $this->error('Informe --todos ou --servidor=ID.');

            return self::FAILURE;
        }

        $summary = $service->gerarParaTodos($tipo, $dryRun, $force);
        $this->printSummary($summary);

        return self::SUCCESS;
    }

    private function resolveTipo(): ?TipoAfastamento
    {
        $tipo = $this->option('tipo');

        if (! $tipo) {
            return null;
        }

        return TipoAfastamento::from($tipo);
    }

    private function printSummary(array $summary): void
    {
        if ($summary['dry_run'] ?? false) {
            $this->warn('DRY-RUN: nenhuma alteração foi salva.');
        }

        $this->table(
            ['Criados', 'Atualizados', 'Ignorados', 'Erros'],
            [[
                $summary['criados'] ?? 0,
                $summary['atualizados'] ?? 0,
                $summary['ignorados'] ?? 0,
                $summary['erros'] ?? 0,
            ]],
        );

        foreach ($summary['avisos'] ?? [] as $aviso) {
            $this->warn($aviso);
        }
    }
}

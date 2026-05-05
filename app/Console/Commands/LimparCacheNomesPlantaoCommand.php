<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LimparCacheNomesPlantaoCommand extends Command
{
    protected $signature = 'plantao:limpar-cache-nomes
                            {--dry-run : Apenas mostra o que seria removido, sem remover}';

    protected $description = 'Remove o cache de nomes do calendário de plantão para forçar reprocessamento com o dicionário atualizado.';

    public function handle(): int
    {
        $prefixes = ['plantao_nome_v2_', 'plantao_nome_ia_v2_', 'plantao_nome_curto_v1_'];
        $driver   = config('cache.default');
        $dry      = (bool) $this->option('dry-run');

        if ($driver === 'database') {
            return $this->limparDatabase($prefixes, $dry);
        }

        $this->warn("Driver de cache '{$driver}' não suportado diretamente. Use php artisan cache:clear para limpar tudo.");
        return self::FAILURE;
    }

    private function limparDatabase(array $prefixes, bool $dry): int
    {
        $table = config('cache.stores.database.table', 'cache');

        $total = 0;
        foreach ($prefixes as $prefix) {
            $count = DB::table($table)->where('key', 'like', "%{$prefix}%")->count();
            $total += $count;

            $this->line("  Prefixo <comment>{$prefix}</comment>: <info>{$count}</info> entrada(s)");

            if (! $dry && $count > 0) {
                DB::table($table)->where('key', 'like', "%{$prefix}%")->delete();
            }
        }

        if ($dry) {
            $this->info("Dry-run: {$total} entrada(s) seriam removidas.");
        } else {
            $this->info("Cache limpo: {$total} entrada(s) removidas.");
            $this->line('Na próxima renderização do calendário, o dicionário <comment>config/plantao_nomes_sociais.php</comment> será aplicado imediatamente.');
        }

        return self::SUCCESS;
    }
}

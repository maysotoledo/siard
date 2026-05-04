<?php

namespace App\Services\Plantao;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gera a forma curta de nomes para exibição no calendário de plantão.
 *
 * ESTRATÉGIA (nunca bloqueia o request):
 *  1. Cache hit → retorna instantaneamente.
 *  2. Cache miss → retorna o fallback local IMEDIATO (sem I/O externo).
 *  3. Dispara um job em background para consultar a IA e sobrescrever o cache.
 *  4. Na próxima renderização do calendário, o nome melhorado pela IA já aparece.
 *
 * O fallback local ignora artigos/preposições e retorna "PrimeiroNome UltimoSobrenome".
 */
class PlantaoNomeService
{
    private const IGNORADOS = ['da', 'de', 'do', 'das', 'dos', 'di', 'du', 'e', 'a', 'o'];
    private const CACHE_PREFIX = 'plantao_nome_v2_';
    // Flag no cache que indica "já foi enriquecido pela IA"
    private const CACHE_PREFIX_IA = 'plantao_nome_ia_v2_';

    /**
     * Retorna a forma curta do nome — SEMPRE rápido, nunca bloqueia.
     * Se a IA ainda não processou o nome, usa o fallback local e agenda o job.
     */
    public function abreviar(string $nomeCompleto): string
    {
        $nomeCompleto = trim($nomeCompleto);

        if ($nomeCompleto === '' || $nomeCompleto === '-') {
            return '-';
        }

        $key = self::CACHE_PREFIX . md5(mb_strtolower($nomeCompleto));

        // Retorna do cache (pode ser fallback ou versão IA)
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Cache miss: salva o fallback local imediatamente
        $fallback = $this->abreviarFallback($nomeCompleto);
        Cache::forever($key, $fallback);

        // Agenda job em background para enriquecer com IA (se ainda não foi enriquecido)
        $keyIa = self::CACHE_PREFIX_IA . md5(mb_strtolower($nomeCompleto));
        if (! Cache::has($keyIa)) {
            dispatch(new \App\Jobs\Plantao\EnriquecerNomePlantaoJob($nomeCompleto))
                ->onQueue('default');
        }

        return $fallback;
    }

    /**
     * Chamado pelo job em background: consulta a IA e atualiza o cache se a resposta for boa.
     */
    public function enriquecerViaIA(string $nomeCompleto): void
    {
        $keyIa = self::CACHE_PREFIX_IA . md5(mb_strtolower($nomeCompleto));

        // Evita chamadas duplicadas da IA para o mesmo nome
        if (Cache::has($keyIa)) {
            return;
        }

        // Marca como "em processamento / processado" antes de chamar a IA
        Cache::forever($keyIa, true);

        $resultado = $this->chamarOllama($nomeCompleto);

        if ($resultado !== null) {
            $key = self::CACHE_PREFIX . md5(mb_strtolower($nomeCompleto));
            Cache::forever($key, $resultado);

            Log::debug('PlantaoNomeService: nome enriquecido pela IA.', [
                'nome'      => $nomeCompleto,
                'resultado' => $resultado,
            ]);
        }
    }

    /**
     * Chama o Ollama com timeout curto. Retorna null se indisponível ou resposta inválida.
     */
    private function chamarOllama(string $nome): ?string
    {
        $url   = rtrim(config('services.ollama.url', 'http://localhost:11434'), '/');
        $model = config('services.ollama.model', 'qwen2.5:3b');

        $prompt = <<<PROMPT
Você recebe um nome completo brasileiro e deve retornar exatamente 2 palavras para exibição compacta em calendário.

Regras:
- Ignore artigos e preposições: da, de, do, das, dos, di, du, e, a, o
- Prefira o primeiro nome + o sobrenome mais identificável (geralmente o último)
- Retorne SOMENTE as 2 palavras em MAIÚSCULAS, sem pontos, vírgulas ou explicações

Nome: {$nome}
PROMPT;

        try {
            $response = Http::timeout(8)->post($url . '/api/chat', [
                'model'   => $model,
                'stream'  => false,
                'options' => ['temperature' => 0.0, 'num_predict' => 15],
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Abrevia nomes brasileiros para calendários. Responda SOMENTE 2 palavras em maiúsculas.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                return null;
            }

            $resposta = trim($response->json('message.content') ?? '');

            return $this->validar($resposta);
        } catch (Throwable $e) {
            Log::debug('PlantaoNomeService: Ollama indisponível.', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Valida a resposta da IA: aceita apenas 1–2 palavras em letras.
     */
    private function validar(string $resposta): ?string
    {
        $limpa    = trim(preg_replace('/[^a-zA-ZÀ-ÿ\s]/u', '', $resposta));
        $palavras = array_values(array_filter(preg_split('/\s+/', $limpa) ?: []));

        if (count($palavras) < 1 || count($palavras) > 3) {
            return null;
        }

        return mb_strtoupper(implode(' ', array_slice($palavras, 0, 2)));
    }

    /**
     * Fallback 100% local e síncrono: ignora artigos/preposições,
     * retorna PrimeiroNome + UltimoSobrenomeSignificativo.
     */
    private function abreviarFallback(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $significativas = array_values(
            array_filter($partes, fn (string $p): bool => ! in_array(mb_strtolower($p), self::IGNORADOS, true))
        );

        if (empty($significativas)) {
            return mb_strtoupper(implode(' ', array_slice($partes, 0, 2)));
        }

        if (count($significativas) === 1) {
            return mb_strtoupper($significativas[0]);
        }

        $primeiro = $significativas[0];
        $ultimo   = end($significativas);

        return mb_strtoupper("$primeiro $ultimo");
    }
}

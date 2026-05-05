<?php

namespace App\Services\Plantao;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gera a forma curta de nomes para exibição no calendário de plantão.
 *
 * PRIORIDADES (em ordem):
 *  1. Dicionário de nomes sociais (config/plantao_nomes_sociais.php) — efeito imediato, sem banco.
 *  2. Cache local — retorna instantaneamente se já processado.
 *  3. Fallback local síncrono — nunca bloqueia o request.
 *  4. Job em background (IA / Ollama) — enriquece o cache para a próxima exibição.
 */
class PlantaoNomeService
{
    private const IGNORADOS = ['da', 'de', 'do', 'das', 'dos', 'di', 'du', 'e', 'a', 'o'];
    private const CACHE_PREFIX    = 'plantao_nome_v2_';
    private const CACHE_PREFIX_IA = 'plantao_nome_ia_v2_';

    /** @var array<string,string>|null */
    private static ?array $dicionario = null;

    /**
     * Retorna a forma curta do nome — SEMPRE rápido, nunca bloqueia.
     */
    public function abreviar(string $nomeCompleto): string
    {
        $nomeCompleto = trim($nomeCompleto);

        if ($nomeCompleto === '' || $nomeCompleto === '-') {
            return '-';
        }

        // 1. Dicionário de nomes sociais configurado em config/plantao_nomes_sociais.php
        $social = $this->buscarNomeSocial($nomeCompleto);
        if ($social !== null) {
            return $social;
        }

        // 2. Cache (pode conter resultado do fallback ou da IA)
        $key    = self::CACHE_PREFIX . md5(mb_strtolower($nomeCompleto));
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // 3. Fallback local imediato — salva no cache
        $fallback = $this->abreviarFallback($nomeCompleto);
        Cache::forever($key, $fallback);

        // 4. Agenda job de IA em background (se ainda não foi processado)
        $keyIa = self::CACHE_PREFIX_IA . md5(mb_strtolower($nomeCompleto));
        if (! Cache::has($keyIa)) {
            dispatch(new \App\Jobs\Plantao\EnriquecerNomePlantaoJob($nomeCompleto))
                ->onQueue('default');
        }

        return $fallback;
    }

    /**
     * Chamado pelo job em background: consulta a IA e atualiza o cache se a resposta for válida.
     * Nunca sobrescreve nomes que estão no dicionário social.
     */
    public function enriquecerViaIA(string $nomeCompleto): void
    {
        // Nomes no dicionário já estão corretos — não sobrescreve
        if ($this->buscarNomeSocial($nomeCompleto) !== null) {
            return;
        }

        $keyIa = self::CACHE_PREFIX_IA . md5(mb_strtolower($nomeCompleto));

        if (Cache::has($keyIa)) {
            return;
        }

        // Marca antes de chamar para evitar chamadas duplicadas concorrentes
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
     * Consulta o dicionário de nomes sociais (config/plantao_nomes_sociais.php).
     * Busca case-insensitive e retorna em maiúsculas.
     */
    private function buscarNomeSocial(string $nome): ?string
    {
        if (self::$dicionario === null) {
            $raw = config('plantao_nomes_sociais', []);
            // Normaliza chaves para maiúsculas sem espaços extras
            self::$dicionario = [];
            foreach ($raw as $chave => $valor) {
                $chaveNorm = mb_strtoupper(trim((string) $chave));
                self::$dicionario[$chaveNorm] = mb_strtoupper(trim((string) $valor));
            }
        }

        $nomeNorm = mb_strtoupper(trim($nome));

        return self::$dicionario[$nomeNorm] ?? null;
    }

    /**
     * Chama o Ollama com timeout curto.
     * Usa os exemplos do dicionário como few-shot learning para novos nomes.
     */
    private function chamarOllama(string $nome): ?string
    {
        $url   = rtrim(config('services.ollama.url', 'http://localhost:11434'), '/');
        $model = config('services.ollama.model', 'qwen2.5:3b');

        // Monta exemplos dinamicamente do dicionário (máx. 6 para não inflar o prompt)
        $exemplos = collect(config('plantao_nomes_sociais', []))
            ->take(6)
            ->map(fn (string $social, string $completo): string => "{$completo} → {$social}")
            ->implode("\n");

        $prompt = <<<PROMPT
Você recebe um nome completo brasileiro e deve retornar exatamente 2 palavras para exibição compacta em calendário.

Regras obrigatórias:
- Ignore artigos e preposições: da, de, do, das, dos, di, du, e, a, o
- Se as duas primeiras palavras formam um nome composto (ex: ANA BEATRIZ, MARIA LUIZA), mantenha ambas
- Se o primeiro nome tem apelido consagrado (ROSEMERI → ROSE, DULCIMARIA → DULCE), use apelido + sobrenome
- Caso contrário: primeiro nome + último sobrenome significativo
- Retorne SOMENTE as 2 palavras em MAIÚSCULAS, sem pontos, vírgulas ou explicações

Exemplos:
{$exemplos}

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
                        'content' => 'Você abrevia nomes brasileiros para calendários seguindo os exemplos fornecidos. Responda SOMENTE 2 palavras em maiúsculas.',
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            if ($response->failed()) {
                return null;
            }

            return $this->validar(trim($response->json('message.content') ?? ''));
        } catch (Throwable $e) {
            Log::debug('PlantaoNomeService: Ollama indisponível.', ['erro' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Valida resposta da IA: aceita apenas 1–2 palavras em letras.
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
     * retorna PrimeiroNome + ÚltimoSobrenomeSignificativo.
     */
    private function abreviarFallback(string $nome): string
    {
        $partes        = preg_split('/\s+/', trim($nome)) ?: [];
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

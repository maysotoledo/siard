<?php

namespace App\Services\IA;

use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaService
{
    public function chat(
        string $pergunta,
        array $contexto = [],
        ?string $tipo = null,
        ?string $modelo = null
    ): string {
        $this->prepararExecucaoLonga();

        $url = rtrim(config('services.ollama.url'), '/');
        $model = $modelo ?: config('services.ollama.model', 'llama3.2:3b');

        $contextoTexto = json_encode(
            $contexto,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );

        $userPrompt = $this->montarPromptUsuario(
            pergunta: $pergunta,
            contextoTexto: $contextoTexto ?: '{}',
            tipo: $tipo
        );

        try {
            $options = [
                'temperature' => 0.2,
                'top_p' => 0.9,
            ];

            if ($tipo === 'pergunta_livre') {
                $options['num_predict'] = 320;
            } elseif ($tipo === 'resumo_tecnico') {
                $options['num_predict'] = 650;
            } elseif ($tipo === 'linha_investigacao') {
                $options['num_predict'] = 420;
            } elseif ($tipo === 'relatorio_policial') {
                $options['num_predict'] = 520;
            } elseif ($tipo === 'analise_noturna' || $tipo === 'analise_ips_moveis') {
                $options['num_predict'] = 360;
            }

            $response = Http::timeout(180)
                ->post($url . '/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($tipo),
                        ],
                        [
                            'role' => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                    'options' => $options,
                ]);

            if ($response->failed()) {
                return 'Erro ao consultar o Ollama. Verifique se o servico esta rodando em: ' . $url;
            }

            return trim($response->json('message.content') ?? 'A IA nao retornou conteudo.');
        } catch (Throwable $e) {
            return 'Erro ao conectar ao Ollama: ' . $e->getMessage();
        }
    }

    private function montarPromptUsuario(string $pergunta, string $contextoTexto, ?string $tipo): string
    {
        if ($tipo === 'pergunta_livre') {
            return <<<PROMPT
TIPO DA ANALISE:
{$tipo}

PERGUNTA DO USUARIO:
{$pergunta}

DADOS DISPONIVEIS DO ALVO SELECIONADO:
{$contextoTexto}

INSTRUCOES DE RESPOSTA:
- Responda diretamente a pergunta do usuario.
- Nao transforme a resposta em resumo generico do relatorio.
- Identifique quais acessos, horarios, IPs, provedores, locais, eventos moveis ou mudancas de padrao sustentam a resposta.
- Priorize os IPs mais relevantes e, em regra, limite a resposta aos 6 IPs mais importantes.
- Diferencie dado objetivo de interpretacao.
- Se afirmar que ha dois possiveis administradores, informe os IPs exatos atribuidos a cada um deles ou diga claramente que os logs nao permitem essa separacao.
- Se os dados nao forem suficientes para concluir, diga exatamente isso e informe o que faltou.
PROMPT;
        }

        if ($tipo === 'linha_investigacao') {
            return <<<PROMPT
TIPO DA ANALISE:
{$tipo}

DADOS DISPONIVEIS DO ALVO SELECIONADO:
{$contextoTexto}

INSTRUCOES DE RESPOSTA:
- Sugira diligencias curtas, objetivas e praticas.
- Priorize cruzamentos com IPs, provedores, horarios e localizacoes.
- Limite a resposta ao que seja realmente util para a investigacao.
PROMPT;
        }

        if ($tipo === 'relatorio_policial') {
            return <<<PROMPT
TIPO DA ANALISE:
{$tipo}

DADOS DISPONIVEIS DO ALVO SELECIONADO:
{$contextoTexto}

INSTRUCOES DE RESPOSTA:
- Gere minuta curta e formal.
- Use tom tecnico e objetivo.
- Nao estenda desnecessariamente os paragrafos.
PROMPT;
        }

        if ($tipo === 'analise_noturna') {
            return <<<PROMPT
TIPO DA ANALISE:
{$tipo}

DADOS DISPONIVEIS DO ALVO SELECIONADO:
{$contextoTexto}

INSTRUCOES DE RESPOSTA:
- Foque apenas em acessos noturnos.
- Destaque IPs, recorrencia, provedores e cidades mais relevantes.
- Se nao houver base suficiente, diga isso claramente.
PROMPT;
        }

        if ($tipo === 'analise_ips_moveis') {
            return <<<PROMPT
TIPO DA ANALISE:
{$tipo}

DADOS DISPONIVEIS DO ALVO SELECIONADO:
{$contextoTexto}

INSTRUCOES DE RESPOSTA:
- Foque apenas em IPs moveis e provedores moveis.
- Destaque IPs, recorrencia, cidades e horarios quando houver.
- Se nao houver base suficiente, diga isso claramente.
PROMPT;
        }

        return <<<PROMPT
TIPO DA ANALISE:
{$tipo}

DADOS DISPONIVEIS DO RELATORIO:
{$contextoTexto}

SOLICITACAO:
{$pergunta}

INSTRUCOES DE RESPOSTA:
- Use no maximo 4 secoes curtas.
- Limite, em regra, a no maximo 5 IPs principais.
- Nao repita provedores ja listados.
- Se houver muitos provedores, liste todos os provedores encontrados, mas resuma os IPs para evitar resposta truncada.
PROMPT;
    }

    private function prepararExecucaoLonga(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        @ini_set('max_execution_time', '180');
    }

    private function systemPrompt(?string $tipo = null): string
    {
        $formato = $tipo === 'pergunta_livre'
            ? <<<TEXT
FORMATO DE RESPOSTA PARA PERGUNTA LIVRE:
1. Resposta objetiva a pergunta
2. IPs e acessos que sustentam a resposta
3. Limitacoes ou pontos que exigem validacao
TEXT
            : ($tipo === 'resumo_tecnico'
                ? <<<TEXT
FORMATO DE RESPOSTA PARA RESUMO TECNICO:
1. Sintese curta
2. Provedores encontrados
3. IPs mais relevantes
4. Pontos de atencao
TEXT
                : ($tipo === 'linha_investigacao'
                    ? <<<TEXT
FORMATO DE RESPOSTA PARA LINHA DE INVESTIGACAO:
1. Sintese
2. Diligencias sugeridas
3. Prioridades imediatas
TEXT
                    : ($tipo === 'relatorio_policial'
                        ? <<<TEXT
FORMATO DE RESPOSTA PARA MINUTA:
1. Relatorio
2. Pontos tecnicos relevantes
3. Limitacoes
TEXT
                        : (($tipo === 'analise_noturna' || $tipo === 'analise_ips_moveis')
                            ? <<<TEXT
FORMATO DE RESPOSTA:
1. Sintese curta
2. IPs mais relevantes
3. Pontos de atencao
TEXT
                : <<<TEXT
FORMATO DE RESPOSTA:
Use estrutura organizada, exemplo:

1. Sintese
2. Dados relevantes
3. Padroes identificados
4. Pontos de atencao
5. Diligencias sugeridas
TEXT)
                        )))
        ;

        return <<<PROMPT
Voce e um agente local de apoio a investigacao policial e analise telematica.

REGRAS OBRIGATORIAS:
- Responda em portugues do Brasil.
- Use linguagem tecnica, objetiva e formal.
- Responda SOMENTE com base nos dados fornecidos.
- Nao invente dados, nomes, IPs, horarios ou vinculos.
- Nao conclua autoria, culpa ou participacao criminosa.
- Utilize termos como: "indicio", "possivel", "sugere", "compativel com" e "necessita validacao".

DIRETRIZES:
- Separe claramente DADOS OBJETIVOS de INTERPRETACOES.
- Aponte padroes relevantes de horario, IP, recorrencia e localizacao.
- Identifique possiveis inconsistencias.
- Sugira diligencias investigativas quando isso ajudar a responder.
- Sempre indique quando houver limitacao de dados.

{$formato}

CONTEXTO:
Sistema de analise telematica com dados como IPs, provedores, horarios, bilhetagem, contatos, localizacao, acessos noturnos e eventos moveis.

IMPORTANTE:
A resposta e apenas apoio tecnico e depende de validacao humana.
PROMPT;
    }
}

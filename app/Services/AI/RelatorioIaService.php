<?php

namespace App\Services\AI;

use App\Models\AnaliseRun;
use App\Models\InvestigationContext;
use App\Models\AnaliseInvestigation;

class RelatorioIaService
{
    public function __construct(private AiManager $ai) {}

    // ─────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────

    public function gerarResumoTecnico(InvestigationContext $context, ?AnaliseRun $run = null): string
    {
        $ctx = $this->montarContextoInvestigacao($context, $run);

        $prompt = <<<PROMPT
Você é um analista técnico policial especializado em investigação digital.

Com base no contexto abaixo, elabore um RESUMO TÉCNICO objetivo e formal, adequado para juntar ao procedimento policial.

O resumo deve conter:
1. Síntese dos fatos narrados no BO.
2. Principais dados técnicos encontrados (IPs, provedores, horários).
3. Pontos de atenção investigativa.
4. Limitações dos dados disponíveis.

Use linguagem formal e termos como "indício", "sugere", "compatível com".
NÃO invente dados. NÃO conclua autoria.

CONTEXTO DA INVESTIGAÇÃO:
{$ctx}
PROMPT;

        return $this->ai->generate($prompt);
    }

    public function gerarLinhaInvestigacao(InvestigationContext $context, ?AnaliseRun $run = null): string
    {
        $ctx = $this->montarContextoInvestigacao($context, $run);

        $prompt = <<<PROMPT
Você é um Investigador de Polícia Civil especializado em análise digital.

Com base no contexto abaixo, elabore uma LINHA DE INVESTIGAÇÃO com diligências práticas e objetivas.

Inclua:
1. Síntese da situação investigativa.
2. Diligências sugeridas (requisição de IP, identificação de titular, oitiva, etc.).
3. Prioridades imediatas.
4. Cruzamentos possíveis.

Use linguagem formal. NÃO afirme autoria. NÃO invente dados.

CONTEXTO DA INVESTIGAÇÃO:
{$ctx}
PROMPT;

        return $this->ai->generate($prompt);
    }

    public function gerarRelatorioCompleto(InvestigationContext $context, ?AnaliseRun $run = null): string
    {
        $ctx = $this->montarContextoInvestigacao($context, $run);

        $prompt = $this->buildPromptRelatorioCompleto($ctx);

        return $this->ai->generate($prompt);
    }

    public function gerarConclusao(InvestigationContext $context, ?AnaliseRun $run = null): string
    {
        $ctx = $this->montarContextoInvestigacao($context, $run);

        $prompt = <<<PROMPT
Você é um Investigador de Polícia Civil especializado em análise digital.

Com base no contexto abaixo, elabore exclusivamente a CONCLUSÃO TÉCNICA do relatório de investigação.

A conclusão deve ser:
- Objetiva e prudente.
- Baseada apenas nos dados fornecidos.
- Indicar o que os dados apontam, sem afirmar autoria.
- Indicar o que não foi possível concluir com os dados disponíveis.
- Sugerir providências finais, se pertinente.

Use expressões como "em tese", "há indícios de", "não foi possível concluir".
NÃO cite que o texto foi gerado por IA.

CONTEXTO DA INVESTIGAÇÃO:
{$ctx}
PROMPT;

        return $this->ai->generate($prompt);
    }

    public function gerarMinutaAutoridade(InvestigationContext $context, ?AnaliseRun $run = null): string
    {
        $ctx = $this->montarContextoInvestigacao($context, $run);

        $prompt = <<<PROMPT
Você é um Investigador de Polícia Civil especializado em análise digital.

Com base no contexto abaixo, elabore uma MINUTA FORMAL dirigida à autoridade policial competente,
resumindo os dados técnicos analisados e sugerindo as providências cabíveis.

A minuta deve conter:
1. Apresentação do caso (BO, natureza, partes).
2. Síntese dos dados técnicos analisados pelo sistema SIARD.
3. Elementos de interesse investigativo.
4. Diligências recomendadas.
5. Encerramento formal.

Use linguagem formal policial. NÃO invente dados. NÃO conclua autoria.
NÃO cite que o texto foi gerado por IA.

CONTEXTO DA INVESTIGAÇÃO:
{$ctx}
PROMPT;

        return $this->ai->generate($prompt);
    }

    // ─────────────────────────────────────────────────────────────
    // Context builder
    // ─────────────────────────────────────────────────────────────

    private function montarContextoInvestigacao(InvestigationContext $context, ?AnaliseRun $run): string
    {
        // Se não veio run direto, busca o mais recente da investigação vinculada
        if (! $run && $context->analise_investigation_id) {
            $run = AnaliseRun::query()
                ->where('investigation_id', $context->analise_investigation_id)
                ->where('status', 'done')
                ->orderByDesc('id')
                ->first();
        }

        $lines = [];

        // ── Dados do BO ──────────────────────────────────────────
        $lines[] = '=== DADOS DO BOLETIM DE OCORRÊNCIA ===';

        // Usa nome da investigação como título se não houver título próprio
        $titulo = $context->titulo
            ?: ($context->analiseInvestigation?->name ?? 'Não informado');
        $lines[] = 'Investigação: ' . $titulo;
        $lines[] = 'Plataforma: ' . strtoupper($context->analiseInvestigation?->source ?? 'Não informada');
        $lines[] = 'Número do BO: ' . ($context->numero_bo ?: 'Não informado');
        $lines[] = 'Número do Procedimento: ' . ($context->numero_procedimento ?: 'Não informado');
        $lines[] = 'Natureza: ' . ($context->natureza ?: 'Não informada');
        $lines[] = 'Unidade Policial: ' . ($context->unidade_policial ?: 'Não informada');

        $vitimas = $this->formatList($context->vitimas);
        $lines[] = 'Vítima(s): ' . ($vitimas ?: 'Não informada(s)');

        $suspeitos = $this->formatList($context->suspeitos);
        $lines[] = 'Suspeito(s): ' . ($suspeitos ?: 'Não informado(s)');

        $lines[] = '';

        $textoBO = trim((string) $context->texto_extraido);
        if ($textoBO !== '') {
            $lines[] = '--- TEXTO INTEGRAL DO BO ---';
            $lines[] = mb_substr($textoBO, 0, 6000);
            if (mb_strlen($textoBO) > 6000) {
                $lines[] = '[... texto truncado ...]';
            }
        } else {
            $lines[] = '[Boletim de Ocorrência não anexado. O relatório será baseado exclusivamente nos dados técnicos do log de acesso abaixo.]';
        }

        // ── Dados técnicos SIARD ─────────────────────────────────
        if ($run) {
            $lines[] = '';
            $lines[] = '=== DADOS TÉCNICOS DO SIARD ===';
            $lines[] = 'Run ID: ' . $run->id;
            $lines[] = 'Alvo: ' . ($run->target ?: 'Não identificado');
            $lines[] = 'Status: ' . $run->status;
            $lines[] = 'Total de eventos/IPs analisados: ' . ($run->total_unique_ips ?? 'N/D');

            $report = is_array($run->report) ? $run->report : [];

            // Timeline (primeiros 50 eventos)
            $timeline = array_slice($report['timeline_rows'] ?? [], 0, 50);
            if (count($timeline) > 0) {
                $lines[] = '';
                $lines[] = '--- TIMELINE DOS EVENTOS (primeiros 50) ---';
                foreach ($timeline as $row) {
                    $lines[] = sprintf(
                        '[%s] IP: %s | %s | %s | %s',
                        $row['datetime'] ?? '-',
                        $row['ip'] ?? '-',
                        $row['provider'] ?? '-',
                        $row['city'] ?? '-',
                        $row['type'] ?? '-'
                    );
                }
                if (count($report['timeline_rows'] ?? []) > 50) {
                    $lines[] = '... e mais ' . (count($report['timeline_rows']) - 50) . ' evento(s).';
                }
            }

            // IPs únicos (top 20)
            $uniqueIps = array_slice($report['unique_ip_rows'] ?? [], 0, 20);
            if (count($uniqueIps) > 0) {
                $lines[] = '';
                $lines[] = '--- IPs ÚNICOS (top 20 por frequência) ---';
                foreach ($uniqueIps as $r) {
                    $lines[] = sprintf(
                        'IP: %s | Ocorrências: %d | %s | %s | %s | Último: %s',
                        $r['ip'] ?? '-',
                        $r['count'] ?? 0,
                        $r['provider'] ?? '-',
                        $r['city'] ?? '-',
                        $r['type'] ?? '-',
                        $r['last_seen'] ?? '-'
                    );
                }
            }

            // Provedores (top 10)
            $providers = array_slice($report['provider_stats_rows'] ?? [], 0, 10);
            if (count($providers) > 0) {
                $lines[] = '';
                $lines[] = '--- PROVEDORES ---';
                foreach ($providers as $r) {
                    $lines[] = sprintf(
                        '%s | Ocorrências: %d | IPs únicos: %d | Móvel: %d%%',
                        $r['provider'] ?? '-',
                        $r['occurrences'] ?? 0,
                        $r['unique_ips'] ?? 0,
                        $r['mobile_percent'] ?? 0
                    );
                }
            }

            // Cidades (top 10)
            $cities = array_slice($report['city_stats_rows'] ?? [], 0, 10);
            if (count($cities) > 0) {
                $lines[] = '';
                $lines[] = '--- CIDADES ---';
                foreach ($cities as $r) {
                    $lines[] = sprintf('%s | Ocorrências: %d | IPs únicos: %d', $r['city'] ?? '-', $r['occurrences'] ?? 0, $r['unique_ips'] ?? 0);
                }
            }

            // Acessos noturnos
            $nightTotal = (int) ($report['night_total_events'] ?? 0);
            if ($nightTotal > 0) {
                $lines[] = '';
                $lines[] = "--- ACESSOS NOTURNOS (23h–06h) ---";
                $lines[] = "Total de acessos noturnos: {$nightTotal}";
                foreach (array_slice($report['night_events_rows'] ?? [], 0, 10) as $r) {
                    $lines[] = sprintf('[%s] IP: %s | %s', $r['datetime'] ?? '-', $r['ip'] ?? '-', $r['provider'] ?? '-');
                }
            }

            // Burst
            $bursts = array_slice($report['hourly_rows'] ?? [], 0, 10);
            if (count($bursts) > 0) {
                $lines[] = '';
                $lines[] = '--- BURSTS DE ACESSO (horas com maior volume) ---';
                foreach ($bursts as $r) {
                    $lines[] = sprintf('Hora: %s | Conexões: %d', $r['label'] ?? '-', $r['count'] ?? 0);
                }
            }

            // Plataforma/fonte
            $source = $report['_source'] ?? null;
            if ($source) {
                $lines[] = '';
                $lines[] = 'Fonte dos dados: ' . strtoupper((string) $source);
            }
        }

        return implode("\n", $lines);
    }

    private function buildPromptRelatorioCompleto(string $contexto): string
    {
        return <<<PROMPT
Você é um Investigador de Polícia Civil especializado em análise de dados digitais, logs de provedores, rastreamento digital, análise de IPs e elaboração de relatórios formais de investigação.

Com base exclusivamente no contexto abaixo, elabore um RELATÓRIO DE INVESTIGAÇÃO completo, formal, técnico e objetivo.

O relatório deve seguir o padrão do "Investigador de Polícia" e ser pronto para copiar e colar em procedimento policial.

CONTEXTO DA INVESTIGAÇÃO:
{$contexto}

ESTRUTURA OBRIGATÓRIA:

1. IDENTIFICAÇÃO
- Número do BO, IP ou procedimento, se disponível.
- Unidade policial, se disponível.
- Natureza da ocorrência.
- Vítima(s), se disponível.
- Suspeito(s), se disponível.
- Data da análise.

2. OBJETIVO
Descrever a finalidade da análise, vinculando expressamente ao fato narrado no BO.

3. HISTÓRICO DOS FATOS
Resumir os fatos conforme narrados no BO, sem acrescentar informações inexistentes.

4. METODOLOGIA
Descrever os dados analisados e a forma de análise técnica realizada pelo sistema.

5. DADOS ANALISADOS
Apresentar registros, datas, horários, IPs, provedores, contatos, dispositivos ou demais elementos disponíveis.

6. ANÁLISE TÉCNICA
Interpretar objetivamente os dados disponíveis, destacando:
- IPs recorrentes;
- horários relevantes;
- acessos noturnos;
- acessos móveis;
- provedores/ASN;
- correlações entre eventos;
- padrões de comportamento;
- inconsistências ou lacunas.

7. TIMELINE DOS EVENTOS
Organizar cronologicamente os principais eventos relacionados ao BO e aos dados técnicos.

8. ELEMENTOS DE INTERESSE INVESTIGATIVO
Apontar elementos úteis à investigação, sempre de forma prudente.

9. DILIGÊNCIAS SUGERIDAS
Sugerir diligências possíveis, como:
- requisição de dados ao provedor;
- identificação de titular de IP;
- preservação de dados;
- cruzamento com outras bases;
- oitiva de envolvidos;
- análise complementar;
- representação por medidas cabíveis, se tecnicamente justificado.

10. CONCLUSÃO
Apresentar conclusão técnica, objetiva e prudente.

REGRAS OBRIGATÓRIAS:
- NÃO invente dados.
- NÃO afirme autoria sem prova.
- NÃO use linguagem acusatória.
- NÃO cite que o texto foi gerado por IA.
- Use linguagem formal policial.
- Use expressões como "em tese", "indica", "sugere", "há indícios", "não foi possível concluir".
- Quando faltar informação, escreva que não foi possível concluir com os dados disponíveis.
- O relatório deve ser focado na investigação descrita no BO.
- O BO é o contexto principal da investigação.
- Os dados técnicos do SIARD devem ser usados como apoio analítico.
PROMPT;
    }

    private function formatList(?array $items): string
    {
        if (! $items || count($items) === 0) {
            return '';
        }

        return implode('; ', array_filter(array_map('trim', $items)));
    }
}

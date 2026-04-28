<?php

namespace App\Filament\Pages;

use App\Jobs\ProcessAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use App\Models\Bilhetagem;
use App\Models\IpEnrichment;
use App\Services\Queue\QueueHealthService;
use App\Services\Queue\QueueWorkerStarter;
use App\Services\Support\BackgroundArtisanRunner;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class AgenteIaInvestigativo extends Page
{
    use HasPageShield;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;
    protected static string|UnitEnum|null $navigationGroup = 'Investigação Telemática';
    protected static ?string $navigationLabel = 'Agente IA';
    protected static ?string $title = 'Agente IA Investigativo';

    protected string $view = 'filament.pages.agente-ia-investigativo';

    public ?int $analise_investigation_id = null;
    public ?int $analise_run_id = null;
    public string $perguntaLivre = '';
    public ?int $ultimaAnaliseId = null;
    public ?string $ultimoStatus = null;
    public int $ultimoProgresso = 0;
    public ?string $ultimoErro = null;
    public ?string $ultimaResposta = null;
    public ?string $ultimoTipo = null;
    public bool $tentouRelancarUltimaAnalise = false;

    public function mount(): void
    {
        // Mantido para compatibilidade com o ciclo de vida da page.
    }

    public function getInvestigacoesDisponiveis(): Collection
    {
        return AnaliseInvestigation::query()
            ->when(
                ! Auth::user()?->hasRole('super_admin'),
                fn ($query) => $query->where('user_id', Auth::id())
            )
            ->with(['runs' => fn ($query) => $query->latest('id')])
            ->latest()
            ->limit(100)
            ->get();
    }

    public function getAlvosDisponiveis(): Collection
    {
        if (! $this->analise_investigation_id) {
            return new Collection();
        }

        return AnaliseRun::query()
            ->where('investigation_id', $this->analise_investigation_id)
            ->orderBy('id')
            ->get(['id', 'target', 'status', 'created_at']);
    }

    public function updatedAnaliseInvestigationId(): void
    {
        $this->analise_run_id = null;
    }

    public function gerarAnalise(string $tipo): void
    {
        $this->prepararExecucaoLonga();
        $modeloIa = $this->getModeloIaConfigurado();

        if (! $modeloIa) {
            Notification::make()
                ->title('Configure o modelo da IA no arquivo .env.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->analise_investigation_id) {
            Notification::make()
                ->title('Selecione uma investigacao primeiro.')
                ->warning()
                ->send();

            return;
        }

        $investigation = AnaliseInvestigation::query()
            ->when(
                ! Auth::user()?->hasRole('super_admin'),
                fn ($query) => $query->where('user_id', Auth::id())
            )
            ->find($this->analise_investigation_id);

        if (! $investigation) {
            Notification::make()
                ->title('Investigacao nao encontrada ou sem permissao de acesso.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->analise_run_id) {
            Notification::make()
                ->title('Selecione um alvo da investigacao.')
                ->warning()
                ->send();

            return;
        }

        $run = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->whereKey($this->analise_run_id)
            ->first();

        if (! $run) {
            Notification::make()
                ->title('Alvo nao encontrado para a investigacao selecionada.')
                ->danger()
                ->send();

            return;
        }

        if ($tipo === 'pergunta_livre' && trim($this->perguntaLivre) === '') {
            Notification::make()
                ->title('Digite uma pergunta para o agente.')
                ->warning()
                ->send();

            return;
        }

        $pergunta = $tipo === 'pergunta_livre'
            ? trim($this->perguntaLivre)
            : $this->montarPerguntaPorTipo($tipo);

        $contexto = $this->montarContextoAlvo($investigation, $run, $tipo);

        $payload = [
            'analise_run_id' => $run->id,
            'user_id' => Auth::id(),
            'tipo' => $tipo,
            'modelo' => $modeloIa,
            'pergunta' => $pergunta,
            'contexto' => $contexto,
            'resposta' => null,
        ];

        if (AiAnalysis::hasStatusColumn()) {
            $payload['status'] = 'queued';
        }

        if (AiAnalysis::hasProgressColumn()) {
            $payload['progress'] = 0;
        }

        if (AiAnalysis::hasErroColumn()) {
            $payload['erro'] = null;
        }

        $analysis = AiAnalysis::query()->create($payload);

        $this->ultimaAnaliseId = $analysis->id;
        $this->ultimoStatus = AiAnalysis::hasStatusColumn() ? 'queued' : 'processing';
        $this->ultimoProgresso = 0;
        $this->ultimoErro = null;
        $this->ultimaResposta = null;
        $this->ultimoTipo = $tipo;
        $this->tentouRelancarUltimaAnalise = false;

        Notification::make()
            ->title('Analise enviada para processamento.')
            ->info()
            ->send();

        $this->despacharAnaliseEmFila($analysis->id);
    }

    public function getModeloIaConfigurado(): string
    {
        return (string) config('services.ollama.model', '');
    }

    public function atualizarUltimaAnalise(): void
    {
        if (! $this->ultimaAnaliseId) {
            return;
        }

        $analysis = AiAnalysis::query()->find($this->ultimaAnaliseId);

        if (! $analysis) {
            return;
        }

        $this->ultimoStatus = AiAnalysis::hasStatusColumn()
            ? ($analysis->status ?: 'processing')
            : ($analysis->resposta ? 'completed' : 'processing');
        $this->ultimoProgresso = AiAnalysis::hasProgressColumn()
            ? max(0, min(100, (int) ($analysis->progress ?? 0)))
            : ($analysis->resposta ? 100 : ($this->ultimoStatus === 'queued' ? 0 : 50));

        $this->ultimoErro = AiAnalysis::hasErroColumn() ? $analysis->erro : null;

        if ($this->ultimoStatus === 'queued' && $this->ultimoProgresso === 0) {
            $segundosNaFila = now()->diffInSeconds($analysis->created_at ?? now(), absolute: true);

            if ($segundosNaFila >= 20 && config('queue.default') === 'database') {
                $workerAtivo = app(QueueHealthService::class)->isWorkerAlive();

                if (! $workerAtivo) {
                    $this->ultimoErro = 'A fila ainda nao iniciou o worker desta analise. Verifique o worker do Laravel ou rode as migrations novas da IA.';
                }
            }
        }

        if (
            $this->ultimoStatus === 'processing'
            && $this->ultimoProgresso <= 15
            && ! $analysis->resposta
            && ! $this->tentouRelancarUltimaAnalise
        ) {
            $segundosProcessando = now()->diffInSeconds($analysis->updated_at ?? $analysis->created_at ?? now(), absolute: true);

            if ($segundosProcessando >= 20) {
                $this->tentouRelancarUltimaAnalise = true;
                $this->iniciarProcessamentoEmBackground($analysis->id, false);
                $this->ultimoErro = 'A analise aparentava estar travada e o processamento em background foi relancado automaticamente.';
            }
        }

        if ($analysis->resposta) {
            $this->ultimaResposta = $analysis->resposta;
        }
    }

    public function deveAtualizarUltimaAnalise(): bool
    {
        return in_array($this->ultimoStatus, ['queued', 'processing'], true);
    }

    private function prepararExecucaoLonga(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        @ini_set('max_execution_time', '180');
    }

    private function despacharAnaliseEmFila(int $analysisId): void
    {
        if (config('queue.default') === 'sync') {
            $this->iniciarProcessamentoEmBackground($analysisId);

            return;
        }

        if (config('queue.default') === 'database') {
            $health = app(QueueHealthService::class);

            if ($health->isWorkerAlive()) {
                ProcessAiAnalysisJob::dispatch($analysisId);

                return;
            }

            $analysis = AiAnalysis::query()->find($analysisId);

            if ($analysis) {
                $payload = [];

                if (AiAnalysis::hasStatusColumn()) {
                    $payload['status'] = 'processing';
                }

                if (AiAnalysis::hasProgressColumn()) {
                    $payload['progress'] = 5;
                }

                if ($payload !== []) {
                    $analysis->forceFill($payload)->save();
                }
            }

            $this->ultimoStatus = 'processing';
            $this->ultimoProgresso = max($this->ultimoProgresso, 5);

            try {
                app(QueueWorkerStarter::class)->start();
            } catch (\Throwable) {
                // Se falhar ao subir o worker, usamos o comando em background abaixo.
            }

            $this->iniciarProcessamentoEmBackground($analysisId);

            return;
        }

        ProcessAiAnalysisJob::dispatch($analysisId);
    }

    private function iniciarProcessamentoEmBackground(int $analysisId, bool $marcarProcessing = true): void
    {
        $analysis = AiAnalysis::query()->find($analysisId);

        if (! $analysis) {
            return;
        }

        $payload = [];

        if ($marcarProcessing && AiAnalysis::hasStatusColumn()) {
            $payload['status'] = 'processing';
        }

        if ($marcarProcessing && AiAnalysis::hasProgressColumn()) {
            $payload['progress'] = 15;
        }

        if (AiAnalysis::hasErroColumn()) {
            $payload['erro'] = null;
        }

        if ($payload !== []) {
            $analysis->forceFill($payload)->save();
        }

        app(BackgroundArtisanRunner::class)->run(
            ['ai-analysis:process', (string) $analysisId],
            'ai-analysis.log',
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resumo_tecnico')
                ->label('Gerar resumo tecnico')
                ->icon(Heroicon::OutlinedDocumentText)
                ->action(fn () => $this->gerarAnalise('resumo_tecnico')),

            Action::make('linha_investigacao')
                ->label('Gerar linha de investigacao')
                ->icon(Heroicon::OutlinedMap)
                ->action(fn () => $this->gerarAnalise('linha_investigacao')),

            Action::make('relatorio_policial')
                ->label('Gerar minuta de relatorio')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->action(fn () => $this->gerarAnalise('relatorio_policial')),

            Action::make('analise_noturna')
                ->label('Analisar acessos noturnos')
                ->icon(Heroicon::OutlinedMoon)
                ->action(fn () => $this->gerarAnalise('analise_noturna')),

            Action::make('analise_ips_moveis')
                ->label('Analisar IPs moveis')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->action(fn () => $this->gerarAnalise('analise_ips_moveis')),
        ];
    }

    private function montarPerguntaPorTipo(string $tipo): string
    {
        return match ($tipo) {
            'resumo_tecnico' => 'Gere um resumo tecnico objetivo da analise telematica, destacando dados objetivos, padroes observados, pontos relevantes e limitacoes da analise. Liste de forma explicita todos os provedores que possuem acessos no log, sem omitir nenhum provedor presente nos dados fornecidos.',
            'linha_investigacao' => 'Sugira uma linha de investigacao com base nos dados fornecidos, indicando possiveis diligencias, cruzamentos uteis e pontos que exigem validacao humana.',
            'relatorio_policial' => 'Gere uma minuta formal de relatorio policial de analise telematica, com linguagem tecnica, objetiva, sem afirmar autoria, culpa ou conclusao definitiva.',
            'analise_noturna' => 'Analise os acessos noturnos, destacando horarios incomuns, recorrencia, possiveis padroes e necessidade de validacao.',
            'analise_ips_moveis' => 'Analise especificamente os IPs moveis, provedores moveis, eventos classificados como mobile, recorrencia, horarios e possiveis padroes relevantes. Nao invente dados ausentes.',
            default => 'Analise os dados fornecidos de forma tecnica, objetiva e cautelosa.',
        };
    }

    private function montarContextoAlvo(AnaliseInvestigation $investigation, AnaliseRun $run, string $tipo): array
    {
        $compacto = $tipo === 'resumo_tecnico';
        $report = is_array($run->report) ? $run->report : [];
        $summary = is_array($run->summary) ? $run->summary : [];
        $providers = $this->extrairProvedoresDoRun($run);

        $alvo = [
            'id_relatorio' => $run->id,
            'alvo' => $run->target ?? null,
            'status' => $run->status ?? null,
            'total_unique_ips' => $run->total_unique_ips ?? null,
            'processed_unique_ips' => $run->processed_unique_ips ?? null,
            'provedores_encontrados' => $providers,
        ];

        if (in_array($tipo, ['resumo_tecnico', 'pergunta_livre'], true)) {
            $alvo['periodo'] = $summary['period'] ?? $summary['periodo'] ?? $report['period'] ?? $report['periodo'] ?? null;
            $alvo['dispositivo'] = $summary['device'] ?? $summary['dispositivo'] ?? $report['device'] ?? $report['dispositivo'] ?? null;
            if ($tipo === 'resumo_tecnico') {
                $alvo['summary'] = $this->montarContextoResumoTecnicoDoBanco($run, $report, $summary, $providers);
                $alvo['report'] = [
                    'providers' => array_slice($providers, 0, 15),
                ];
            } else {
                $alvo['summary'] = $this->limitarContexto($summary, false);
                $alvo['report'] = $this->montarContextoPerguntaLivreDoBanco($run, $report, $summary, $providers);
            }
        } elseif (in_array($tipo, ['linha_investigacao', 'relatorio_policial', 'analise_noturna', 'analise_ips_moveis'], true)) {
            $alvo['periodo'] = $summary['period'] ?? $summary['periodo'] ?? $report['period'] ?? $report['periodo'] ?? null;
            $alvo['dispositivo'] = $summary['device'] ?? $summary['dispositivo'] ?? $report['device'] ?? $report['dispositivo'] ?? null;
            $alvo['summary'] = $this->montarContextoAnaliticoDoBanco($run, $report, $summary, $providers, $tipo);
            $alvo['report'] = [
                'providers' => array_slice($providers, 0, 15),
            ];
        } else {
            $alvo['uuid'] = $run->uuid ?? null;
            $alvo['created_at'] = optional($run->created_at)->format('d/m/Y H:i:s');
            $alvo['updated_at'] = optional($run->updated_at)->format('d/m/Y H:i:s');
            $alvo['summary'] = $this->limitarContexto($summary, $compacto);
            $alvo['report'] = $this->limitarContexto($report, $compacto);
        }

        $contexto = [
            'id_investigacao' => $investigation->id,
            'nome_investigacao' => $investigation->name,
            'origem' => $investigation->source,
            'total_alvos' => AnaliseRun::query()->where('investigation_id', $investigation->id)->count(),
            'provedores_encontrados' => $providers,
            'alvo_selecionado' => $alvo,
        ];

        if ($tipo === 'resumo_tecnico') {
            $contexto['instrucoes_extras'] = [
                'foco' => 'resumo tecnico consolidado da investigacao',
                'obrigatorio_listar_todos_os_provedores' => true,
                'nao_omitir_provedores' => true,
            ];
        } elseif ($tipo === 'linha_investigacao') {
            $contexto['instrucoes_extras'] = [
                'foco' => 'sugerir linha de investigacao objetiva e curta',
                'priorizar_diligencias_praticas' => true,
            ];
        } elseif ($tipo === 'relatorio_policial') {
            $contexto['instrucoes_extras'] = [
                'foco' => 'minuta curta e objetiva de relatorio policial',
                'nao_fazer_texto_longo_demais' => true,
            ];
        } elseif ($tipo === 'analise_noturna') {
            $contexto['instrucoes_extras'] = [
                'foco' => 'avaliar apenas acessos noturnos e seus IPs mais recorrentes',
            ];
        } elseif ($tipo === 'analise_ips_moveis') {
            $contexto['instrucoes_extras'] = [
                'foco' => 'avaliar apenas IPs moveis e provedores moveis mais relevantes',
            ];
        } elseif ($tipo === 'pergunta_livre') {
            $contexto['instrucoes_extras'] = [
                'foco' => 'responder exatamente a pergunta do usuario usando apenas o alvo selecionado',
                'priorizar_resposta_direta_a_pergunta' => true,
                'nao_transformar_em_resumo_generico' => true,
                'citar_quais_dados_sustentam_a_resposta' => true,
                'se_nao_houver_base_suficiente_dizer_isso_com_clareza' => true,
                'se_houver_hipotese_de_multiplos_administradores_citar_ips_exatos' => true,
            ];
        }

        return $contexto;
    }

    private function montarContextoAnaliticoDoBanco(
        AnaliseRun $run,
        array $report,
        array $summary,
        array $providers,
        string $tipo
    ): array {
        $base = $this->montarContextoResumoTecnicoDoBanco($run, $report, $summary, $providers);

        if ($tipo === 'linha_investigacao' || $tipo === 'relatorio_policial') {
            $eventos = $run->events()
                ->orderByDesc('occurred_at')
                ->limit(12)
                ->get(['occurred_at', 'event_type', 'category', 'ip', 'title']);

            $eventIpMetadata = $this->carregarMetadadosIps($eventos->pluck('ip')->filter()->values()->all());

            $base['eventos_recentes'] = $eventos->map(function ($event) use ($eventIpMetadata): array {
                $meta = $event->ip ? ($eventIpMetadata[$event->ip] ?? null) : null;

                return [
                    'quando' => optional($event->occurred_at)->format('d/m/Y H:i:s'),
                    'tipo' => $event->event_type,
                    'categoria' => $event->category,
                    'ip' => $event->ip,
                    'provedor' => $meta['provedor'] ?? null,
                    'titulo' => $event->title,
                ];
            })->values()->all();
        }

        if ($tipo === 'analise_noturna') {
            $noturnos = $run->events()
                ->selectRaw('ip, COUNT(*) as total')
                ->whereNotNull('ip')
                ->whereNotNull('occurred_at')
                ->where(function ($query) {
                    $query->whereRaw('HOUR(occurred_at) >= 23')
                        ->orWhereRaw('HOUR(occurred_at) <= 6');
                })
                ->groupBy('ip')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            $meta = $this->carregarMetadadosIps($noturnos->pluck('ip')->filter()->values()->all());

            $base['ips_noturnos'] = $noturnos->map(function ($row) use ($meta): array {
                $ipMeta = $meta[$row->ip] ?? null;

                return [
                    'ip' => $row->ip,
                    'ocorrencias' => (int) $row->total,
                    'provedor' => $ipMeta['provedor'] ?? 'Desconhecido',
                    'cidade' => $ipMeta['cidade'] ?? null,
                ];
            })->values()->all();
        }

        if ($tipo === 'analise_ips_moveis') {
            $moveis = IpEnrichment::query()
                ->where('mobile', true)
                ->whereIn('ip', $run->ips()->pluck('ip'))
                ->get(['ip', 'isp', 'org', 'city']);

            $runIps = $run->ips()
                ->whereIn('ip', $moveis->pluck('ip'))
                ->orderByDesc('occurrences')
                ->limit(10)
                ->get(['ip', 'occurrences', 'last_seen_at']);

            $meta = $moveis->mapWithKeys(fn ($item): array => [
                $item->ip => [
                    'provedor' => trim((string) ($item->isp ?: $item->org)) ?: 'Desconhecido',
                    'cidade' => $item->city ?: null,
                ],
            ])->all();

            $base['ips_moveis'] = $runIps->map(function ($row) use ($meta): array {
                $ipMeta = $meta[$row->ip] ?? null;

                return [
                    'ip' => $row->ip,
                    'ocorrencias' => $row->occurrences,
                    'ultimo_acesso' => optional($row->last_seen_at)->format('d/m/Y H:i:s'),
                    'provedor' => $ipMeta['provedor'] ?? 'Desconhecido',
                    'cidade' => $ipMeta['cidade'] ?? null,
                ];
            })->values()->all();
        }

        return $base;
    }

    private function montarContextoResumoTecnicoDoBanco(
        AnaliseRun $run,
        array $report,
        array $summary,
        array $providers
    ): array {
        $topIps = $run->ips()
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->limit(8)
            ->get(['ip', 'occurrences', 'last_seen_at']);

        $ipMetadata = $this->carregarMetadadosIps($topIps->pluck('ip')->filter()->values()->all());

        $ips = $topIps->map(function ($ipRow) use ($ipMetadata): array {
            $meta = $ipMetadata[$ipRow->ip] ?? null;

            return [
                'ip' => $ipRow->ip,
                'ocorrencias' => $ipRow->occurrences,
                'provedor' => $meta['provedor'] ?? 'Desconhecido',
                'cidade' => $meta['cidade'] ?? null,
                'tipo_conexao' => $meta['tipo_conexao'] ?? null,
            ];
        })->values()->all();

        $provedoresPrincipais = collect($ips)
            ->groupBy(fn (array $row) => $row['provedor'] ?: 'Desconhecido')
            ->map(fn ($rows, $provider): array => [
                'provedor' => $provider,
                'ips' => $rows->pluck('ip')->filter()->unique()->values()->take(4)->all(),
                'ocorrencias' => $rows->sum('ocorrencias'),
            ])
            ->sortByDesc('ocorrencias')
            ->values()
            ->take(6)
            ->all();

        return [
            'target' => $run->target ?? $report['target'] ?? null,
            'period' => $summary['period'] ?? $summary['periodo'] ?? $report['period'] ?? $report['periodo'] ?? null,
            'device' => $summary['device'] ?? $summary['dispositivo'] ?? $report['device'] ?? $report['dispositivo'] ?? null,
            'providers' => array_slice($providers, 0, 15),
            'ips_principais' => $ips,
            'provedores_principais' => $provedoresPrincipais,
            'resumo_numerico' => [
                'total_unique_ips' => $run->total_unique_ips,
                'processed_unique_ips' => $run->processed_unique_ips,
                'total_eventos' => $run->events()->count(),
                'total_mensagens' => $run->messages()->count(),
                'total_registros_bilhetagem' => Bilhetagem::query()->where('analise_run_id', $run->id)->count(),
            ],
        ];
    }

    private function montarContextoPerguntaLivreDoBanco(
        AnaliseRun $run,
        array $report,
        array $summary,
        array $providers
    ): array {
        $topIps = $run->ips()
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->limit(12)
            ->get(['ip', 'occurrences', 'last_seen_at']);

        $ipMetadata = $this->carregarMetadadosIps($topIps->pluck('ip')->filter()->values()->all());

        $ips = $topIps->map(function ($ipRow) use ($ipMetadata): array {
            $meta = $ipMetadata[$ipRow->ip] ?? null;

            return [
                'ip' => $ipRow->ip,
                'ocorrencias' => $ipRow->occurrences,
                'ultimo_acesso' => optional($ipRow->last_seen_at)->format('d/m/Y H:i:s'),
                'provedor' => $meta['provedor'] ?? 'Desconhecido',
                'cidade' => $meta['cidade'] ?? null,
                'tipo_conexao' => $meta['tipo_conexao'] ?? null,
            ];
        })->values()->all();

        $bilhetagem = Bilhetagem::query()
            ->where('analise_run_id', $run->id)
            ->whereNotNull('sender_ip')
            ->selectRaw('sender_ip as ip, COUNT(*) as ocorrencias, MAX(timestamp_utc) as ultimo_acesso')
            ->groupBy('sender_ip')
            ->orderByDesc('ocorrencias')
            ->limit(12)
            ->get();

        $billingIpMetadata = $this->carregarMetadadosIps($bilhetagem->pluck('ip')->filter()->values()->all());

        $ipsBilhetagem = $bilhetagem->map(function ($row) use ($billingIpMetadata): array {
            $meta = $billingIpMetadata[$row->ip] ?? null;

            return [
                'ip' => $row->ip,
                'ocorrencias' => (int) $row->ocorrencias,
                'ultimo_acesso' => $row->ultimo_acesso ? date('d/m/Y H:i:s', strtotime((string) $row->ultimo_acesso)) : null,
                'provedor' => $meta['provedor'] ?? 'Desconhecido',
                'cidade' => $meta['cidade'] ?? null,
                'tipo_conexao' => $meta['tipo_conexao'] ?? null,
            ];
        })->values()->all();

        $distribuicaoHoraria = $run->events()
            ->selectRaw('HOUR(occurred_at) as hora_local, COUNT(*) as total')
            ->whereNotNull('occurred_at')
            ->groupBy(DB::raw('HOUR(occurred_at)'))
            ->orderBy('hora_local')
            ->limit(24)
            ->get()
            ->map(fn ($row): array => [
                'hora_local' => (int) $row->hora_local,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $acessosNoturnosPorIp = $run->events()
            ->selectRaw('ip, COUNT(*) as total')
            ->whereNotNull('ip')
            ->whereNotNull('occurred_at')
            ->where(function ($query) {
                $query->whereRaw('HOUR(occurred_at) >= 23')
                    ->orWhereRaw('HOUR(occurred_at) <= 6');
            })
            ->groupBy('ip')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $acessosNoturnosMetadados = $this->carregarMetadadosIps($acessosNoturnosPorIp->pluck('ip')->filter()->values()->all());

        $ipsNoturnos = $acessosNoturnosPorIp->map(function ($row) use ($acessosNoturnosMetadados): array {
            $meta = $acessosNoturnosMetadados[$row->ip] ?? null;

            return [
                'ip' => $row->ip,
                'ocorrencias' => (int) $row->total,
                'provedor' => $meta['provedor'] ?? 'Desconhecido',
                'cidade' => $meta['cidade'] ?? null,
            ];
        })->values()->all();

        $provedoresPrincipais = collect($ips)
            ->groupBy(fn (array $row) => $row['provedor'] ?: 'Desconhecido')
            ->map(fn ($rows, $provider): array => [
                'provedor' => $provider,
                'ips' => $rows->pluck('ip')->filter()->unique()->values()->take(5)->all(),
                'ocorrencias' => $rows->sum('ocorrencias'),
            ])
            ->sortByDesc('ocorrencias')
            ->values()
            ->take(8)
            ->all();

        return [
            'target' => $run->target ?? $report['target'] ?? null,
            'period' => $summary['period'] ?? $summary['periodo'] ?? $report['period'] ?? $report['periodo'] ?? null,
            'device' => $summary['device'] ?? $summary['dispositivo'] ?? $report['device'] ?? $report['dispositivo'] ?? null,
            'providers' => array_slice($providers, 0, 15),
            'ips_principais' => $ips,
            'ips_bilhetagem' => $ipsBilhetagem,
            'provedores_principais' => $provedoresPrincipais,
            'ips_noturnos' => $ipsNoturnos,
            'distribuicao_horaria' => $distribuicaoHoraria,
            'resumo_numerico' => [
                'total_unique_ips' => $run->total_unique_ips,
                'processed_unique_ips' => $run->processed_unique_ips,
                'total_eventos' => $run->events()->count(),
                'total_mensagens' => $run->messages()->count(),
                'total_registros_bilhetagem' => Bilhetagem::query()->where('analise_run_id', $run->id)->count(),
            ],
        ];
    }

    private function carregarMetadadosIps(array $ips): array
    {
        if ($ips === []) {
            return [];
        }

        return IpEnrichment::query()
            ->whereIn('ip', $ips)
            ->get(['ip', 'isp', 'org', 'city', 'mobile'])
            ->mapWithKeys(fn ($item): array => [
                $item->ip => [
                    'provedor' => trim((string) ($item->isp ?: $item->org)) ?: 'Desconhecido',
                    'cidade' => $item->city ?: null,
                    'tipo_conexao' => $item->mobile ? 'Movel' : 'Residencial',
                ],
            ])
            ->all();
    }

    private function extrairProvedoresDoRun(AnaliseRun $run): array
    {
        $sources = [];

        if (is_array($run->summary)) {
            $sources[] = $run->summary;
        }

        if (is_array($run->report)) {
            $sources[] = $run->report;
        }

        $providers = [];

        foreach ($sources as $source) {
            foreach ((array) ($source['providers'] ?? $source['provedores'] ?? []) as $providerRow) {
                if (is_array($providerRow)) {
                    $name = trim((string) ($providerRow['provider'] ?? $providerRow['name'] ?? ''));
                } else {
                    $name = trim((string) $providerRow);
                }

                if ($name !== '') {
                    $providers[$name] = $name;
                }
            }

            foreach ((array) ($source['provider_stats_rows'] ?? []) as $providerRow) {
                $name = trim((string) ($providerRow['provider'] ?? ''));
                if ($name !== '') {
                    $providers[$name] = $name;
                }
            }

            foreach (array_keys((array) ($source['provider_ip_map'] ?? [])) as $providerName) {
                $name = trim((string) $providerName);
                if ($name !== '') {
                    $providers[$name] = $name;
                }
            }
        }

        return array_values($providers);
    }

    private function limitarContexto(array $report, bool $compacto = false): array
    {
        $data = [
            'summary' => $report['summary'] ?? $report['resumo'] ?? null,
            'target' => $report['target'] ?? null,
            'period' => $report['period'] ?? $report['periodo'] ?? null,
            'device' => $report['device'] ?? $report['dispositivo'] ?? null,
            'providers' => array_slice($report['providers'] ?? $report['provedores'] ?? [], 0, 30),
            'provider_stats_rows' => array_slice($report['provider_stats_rows'] ?? [], 0, 50),
            'provider_ip_map' => array_slice((array) ($report['provider_ip_map'] ?? []), 0, $compacto ? 20 : 50, true),
            'contacts' => [
                'symmetric' => array_slice($report['contacts']['symmetric'] ?? $report['contatos_simetricos'] ?? [], 0, $compacto ? 20 : 80),
                'asymmetric' => array_slice($report['contacts']['asymmetric'] ?? $report['contatos_assimetricos'] ?? [], 0, $compacto ? 20 : 80),
            ],
            'billing' => array_slice($report['billing'] ?? $report['bilhetagem'] ?? [], 0, $compacto ? 20 : 100),
            'locations' => array_slice($report['locations'] ?? $report['localizacoes'] ?? [], 0, $compacto ? 20 : 50),
        ];

        if ($compacto) {
            $data['timeline'] = array_slice($report['timeline'] ?? $report['linha_do_tempo'] ?? [], 0, 20);
            $data['night_events'] = array_slice($report['night_events'] ?? $report['acessos_noturnos'] ?? [], 0, 20);
            $data['mobile_events'] = array_slice($report['mobile_events'] ?? $report['eventos_moveis'] ?? [], 0, 20);
        } else {
            $data['timeline'] = array_slice($report['timeline'] ?? $report['linha_do_tempo'] ?? [], 0, 80);
            $data['night_events'] = array_slice($report['night_events'] ?? $report['acessos_noturnos'] ?? [], 0, 80);
            $data['mobile_events'] = array_slice($report['mobile_events'] ?? $report['eventos_moveis'] ?? [], 0, 80);
        }

        return $data;
    }
}

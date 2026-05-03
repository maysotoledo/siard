<?php

namespace App\Filament\Pages\Concerns;

use App\Actions\AnaliseInteligente\Platform\ResolveInvestigationAction;
use App\Jobs\AnaliseInteligente\Platform\ProcessPlatformInvestigationJob;
use App\Filament\Pages\RelatoriosProcessados;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRun;
use App\Models\AnaliseRunContact;
use App\Models\AnaliseRunEvent;
use App\Models\AnaliseRunIp;
use App\Models\AnaliseRunMedia;
use App\Models\AnaliseRunMessage;
use App\Models\AnaliseRunStep;
use App\Models\IpEnrichment;
use App\Services\AnaliseInteligente\Platform\PlatformRunSummaryService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Str;
use Illuminate\Bus\Dispatcher;
use Livewire\Attributes\On;

trait HandlesPlatformLogAnalysis
{
    public ?array $data = [];
    public ?int $investigationId = null;
    public ?array $investigation = null;
    public ?int $runId = null;
    public ?int $selectedTargetRunId = null;
    public array $targetRuns = [];
    public int $progress = 0;
    public bool $running = false;
    public ?array $report = null;
    public string $tab = 'timeline';
    public ?string $selectedProvider = null;
    public array $selectedProviderIps = [];
    public ?string $vinculoModalIp = null;
    public ?string $vinculoModalTarget = null;
    public array $vinculoModalTimes = [];
    public int $vinculoPage = 1;
    public int $vinculoPerPage = 10;

    abstract protected function platformSource(): string;
    abstract protected function platformLabel(): string;

    public function mount(): void
    {
        $investigationId = request()->integer('investigation');
        if ($investigationId) {
            $this->loadExistingInvestigation($investigationId);
        }

        $runId = request()->integer('run');
        if ($runId) {
            $this->loadExistingRun($runId);
        }

        $this->form->fill([
            'investigation_name' => $this->investigation['name'] ?? null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('investigation_name')
                    ->label('Nome da investigacao')
                    ->required(fn () => $this->investigationId === null)
                    ->disabled(fn () => $this->investigationId !== null)
                    ->dehydrated(fn () => $this->investigationId === null)
                    ->maxLength(160),

                FileUpload::make('log_files')
                    ->label('Arquivos de log ' . $this->platformLabel() . ' (PDF/TXT/LOG/CSV/JSON/HTML/ZIP)')
                    ->required()
                    ->multiple()
                    ->disk('public')
                    ->directory('uploads/' . $this->platformSource() . '-logs')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'text/plain',
                        'text/csv',
                        'text/html',
                        'application/json',
                        'application/zip',
                        'application/x-zip-compressed',
                        '.pdf',
                        '.txt',
                        '.log',
                        '.csv',
                        '.json',
                        '.html',
                        '.htm',
                        '.zip',
                    ])
                    ->preserveFilenames()
                    ->maxSize(150_000),
            ])
            ->statePath('data');
    }

    public function gerar(): void
    {
        $this->report = null;
        $this->progress = 0;
        $this->running = false;
        $this->tab = 'timeline';
        $this->targetRuns = [];
        $this->selectedTargetRunId = null;
        $this->selectedProvider = null;
        $this->selectedProviderIps = [];

        $state = $this->form->getState();
        $storedPaths = $state['log_files'] ?? [];

        if (is_string($storedPaths)) {
            $storedPaths = [$storedPaths];
        }

        if (! is_array($storedPaths) || count($storedPaths) === 0) {
            Notification::make()->title('Envie pelo menos 1 arquivo de log')->danger()->send();
            return;
        }

        try {
            $investigation = app(ResolveInvestigationAction::class)->execute(
                investigationId: $this->investigationId,
                userId: (int) auth()->id(),
                source: $this->platformSource(),
                name: (string) ($state['investigation_name'] ?? ''),
            );
        } catch (\RuntimeException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
            return;
        }

        $batchId = (string) Str::uuid();

        app(Dispatcher::class)->dispatchAfterResponse(
            new ProcessPlatformInvestigationJob(
                investigationId: $investigation->id,
                userId: (int) auth()->id(),
                source: $this->platformSource(),
                label: $this->platformLabel(),
                storedPaths: array_values($storedPaths),
                batchId: $batchId,
            )
        );

        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
            'source' => $investigation->source,
            'platform_label' => $this->platformLabel(),
        ];
        $this->running = true;
        $this->progress = 0;

        Notification::make()
            ->title('Processamento enviado para a fila')
            ->body('Os arquivos foram enfileirados; a tela agora acompanha o progresso pelo banco.')
            ->success()
            ->send();
    }

    public function poll(): void
    {
        if (! $this->runId && ! $this->investigationId) {
            return;
        }

        if ($this->investigationId) {
            $this->reconcileGoogleSupplementalRuns($this->investigationId);
        }

        $runs = AnaliseRun::query()
            ->when($this->investigationId, fn ($query) => $query->where('investigation_id', $this->investigationId))
            ->when(! $this->investigationId && $this->runId, fn ($query) => $query->whereKey($this->runId))
            ->orderBy('id')
            ->get();

        if ($runs->isEmpty()) {
            $this->running = false;
            $this->progress = 0;
            return;
        }

        $this->targetRuns = $this->formatTargetRuns($runs->all());
        $this->running = $runs->contains(fn (AnaliseRun $run): bool => in_array($run->status, ['queued', 'running'], true));
        $this->progress = (int) floor($runs->avg('progress') ?? 0);

        $visibleRunIds = array_values(array_filter(array_map(fn (array $row): int => (int) ($row['id'] ?? 0), $this->targetRuns)));
        $preferredRunId = in_array((int) ($this->selectedTargetRunId ?: $this->runId), $visibleRunIds, true)
            ? (int) ($this->selectedTargetRunId ?: $this->runId)
            : ($visibleRunIds[0] ?? null);

        $selected = $preferredRunId
            ? ($runs->firstWhere('id', $preferredRunId) ?? $runs->first())
            : $runs->first();
        if ($selected) {
            $this->runId = $selected->id;
            $this->selectedTargetRunId = $selected->id;

            if ($selected->status === 'done') {
                $this->hydrateReportFromRun($selected);
            }
        }
    }

    public function selectTargetRun(int $runId): void
    {
        $run = AnaliseRun::query()
            ->whereKey($runId)
            ->when($this->investigationId, fn ($query) => $query->where('investigation_id', $this->investigationId))
            ->first();

        if (! $run) {
            return;
        }

        $this->runId = $run->id;
        $this->selectedTargetRunId = $run->id;
        $this->report = null;
        $this->tab = 'timeline';

        if ($run->status === 'done') {
            $this->hydrateReportFromRun($run);
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, $this->availableTabs(), true)) {
            return;
        }

        $this->tab = $tab;

        if ($tab === 'vinculo') {
            $this->vinculoPage = 1;
        }

        if (! $this->runId || ! $this->report) {
            return;
        }

        $run = AnaliseRun::query()->find($this->runId);
        if ($run && $run->status === 'done') {
            $this->hydrateReportFromRun($run);
        }
    }

    public function limpar(): void
    {
        if ($this->running) {
            return;
        }

        $this->runId = null;
        $this->selectedTargetRunId = null;
        $this->progress = 0;
        $this->running = false;
        $this->report = null;
        $this->targetRuns = [];
        $this->tab = 'timeline';
        $this->selectedProvider = null;
        $this->selectedProviderIps = [];
        $this->vinculoModalIp = null;
        $this->vinculoModalTarget = null;
        $this->vinculoModalTimes = [];
        $this->vinculoPage = 1;

        $this->form->fill();
    }

    #[On('open-provider-ips-modal')]
    public function openProviderIpsModal(string $provider): void
    {
        if (! $this->runId) {
            return;
        }

        $provider = trim($provider);
        $this->selectedProvider = $provider !== '' ? $provider : 'Desconhecido';
        $this->selectedProviderIps = app(PlatformRunSummaryService::class)->providerIpRows($this->runId, $this->selectedProvider);

        $this->mountAction('providerIpsModal');
    }

    public function providerIpsModal(): Action
    {
        return Action::make('providerIpsModal')
            ->label('IPs do Provedor')
            ->modalHeading(fn () => 'IPs - ' . ($this->selectedProvider ?? 'Desconhecido'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->after(fn () => $this->closeProviderModalState())
            ->modalContent(fn () => view('filament.pages.partials.modal-provider-ips', [
                'rows' => $this->selectedProviderIps,
                'provider' => $this->selectedProvider,
            ]));
    }

    protected function closeProviderModalState(): void
    {
        $this->selectedProvider = null;
        $this->selectedProviderIps = [];
    }

    protected function loadExistingRun(int $runId): void
    {
        $run = AnaliseRun::find($runId);

        if (! $run) {
            Notification::make()->title('Relatorio processado nao encontrado')->danger()->send();
            return;
        }

        $source = strtolower((string) ($run->source ?: data_get($run->summary, '_source', '')));
        if ($source !== $this->platformSource()) {
            Notification::make()->title('Este relatorio pertence a outro tipo de analise')->danger()->send();
            return;
        }

        $this->runId = $run->id;
        $this->selectedTargetRunId = $run->id;
        if ($run->investigation_id) {
            $this->reconcileGoogleSupplementalRuns((int) $run->investigation_id);
            $this->investigationId = (int) $run->investigation_id;
            $investigation = AnaliseInvestigation::find($run->investigation_id);
            if ($investigation) {
                $this->investigation = [
                    'id' => $investigation->id,
                    'name' => $investigation->name,
                    'source' => $investigation->source,
                    'platform_label' => $this->platformLabel(),
                ];
            }

            $this->targetRuns = $this->formatTargetRuns(
                AnaliseRun::query()->where('investigation_id', $run->investigation_id)->orderBy('id')->get()->all(),
            );
        }
        $this->progress = (int) $run->progress;
        $this->running = in_array((string) $run->status, ['queued', 'running'], true);

        if ($run->status === 'done') {
            $this->tab = 'timeline';
            $this->hydrateReportFromRun($run);
        }
    }

    protected function loadExistingInvestigation(int $investigationId): void
    {
        $this->reconcileGoogleSupplementalRuns($investigationId);

        $investigation = AnaliseInvestigation::query()->whereKey($investigationId)->first();

        if (! $investigation || ! $this->canViewInvestigation($investigation)) {
            Notification::make()->title('Investigacao nao encontrada')->danger()->send();
            return;
        }

        if ($investigation->source !== $this->platformSource()) {
            Notification::make()->title('Esta investigacao pertence a outra plataforma')->danger()->send();
            return;
        }

        $runs = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->orderBy('id')
            ->get();

        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
            'source' => $investigation->source,
            'platform_label' => $this->platformLabel(),
        ];
        $this->targetRuns = $this->formatTargetRuns($runs->all());
        $this->running = $runs->contains(fn (AnaliseRun $run): bool => in_array($run->status, ['queued', 'running'], true));
        $this->progress = (int) floor($runs->avg('progress') ?? 0);

        $visibleRunIds = array_values(array_filter(array_map(fn (array $row): int => (int) ($row['id'] ?? 0), $this->targetRuns)));
        $first = count($visibleRunIds) > 0
            ? $runs->firstWhere('id', $visibleRunIds[0])
            : $runs->first();
        if ($first) {
            $this->runId = $first->id;
            $this->selectedTargetRunId = $first->id;

            if ($first->status === 'done') {
                $this->hydrateReportFromRun($first);
            }
        }
    }

    protected function hydrateReportFromRun(AnaliseRun $run): void
    {
        $report = (array) app(PlatformRunSummaryService::class)->buildSummary($run);
        $report['selected_target'] = $this->resolveRunTarget($run);
        $report['selected_run_id'] = (int) $run->id;

        if ($this->platformSource() === 'google') {
            $report['_counts']['vinculo'] = $this->countVinculoRows($run);
            $report['vinculo_rows'] = $this->tab === 'vinculo'
                ? $this->buildVinculoRows($run)
                : [];
        }

        $this->report = $report;
    }

    protected function formatTargetRuns(array $runs): array
    {
        $rows = array_map(function (AnaliseRun $run): ?array {
            $target = $this->resolveRunTarget($run);

            if ($this->platformSource() === 'google' && $target === '') {
                return null;
            }

            return [
                'id' => (int) $run->id,
                'target' => $target,
                'status' => (string) $run->status,
                'progress' => (int) $run->progress,
                'total_ips' => isset($run->events_count) ? (int) $run->events_count : $run->events()->count(),
                'unique_ips' => (int) $run->total_unique_ips,
            ];
        }, $runs);

        $rows = array_values(array_filter($rows));

        if ($this->platformSource() !== 'google') {
            return $rows;
        }

        $grouped = [];

        foreach ($rows as $row) {
            $key = mb_strtolower(trim((string) ($row['target'] ?? '')));
            if ($key === '') {
                continue;
            }

            if (! isset($grouped[$key]) || (int) $row['id'] > (int) $grouped[$key]['id']) {
                $grouped[$key] = $row;
            }
        }

        return array_values($grouped);
    }

    protected function resolveRunTarget(AnaliseRun $run): string
    {
        $target = trim((string) ($run->target ?? ''));
        if ($target !== '') {
            return $target;
        }

        return trim((string) (data_get($run->summary, 'subscriber_info.email')
            ?: data_get($run->summary, 'subscriber_info.account_id')
            ?: data_get($run->summary, 'accounts_found.0')
            ?: data_get($run->summary, 'identifiers_found.0.value')
            ?: ''));
    }

    protected function availableTabs(): array
    {
        $tabs = ['timeline', 'unique_ips', 'providers', 'cities', 'maps', 'search', 'user_agents', 'residencial', 'movel'];

        if ($this->platformSource() === 'google') {
            $tabs[] = 'vinculo';
        }

        return $tabs;
    }

    protected function canViewInvestigation(AnaliseInvestigation $investigation): bool
    {
        if ((int) $investigation->user_id === (int) auth()->id()) {
            return true;
        }

        return method_exists(RelatoriosProcessados::class, 'canAccess')
            ? (bool) RelatoriosProcessados::canAccess()
            : false;
    }

    protected function reconcileGoogleSupplementalRuns(int $investigationId): void
    {
        if ($this->platformSource() !== 'google') {
            return;
        }

        $runs = AnaliseRun::query()
            ->where('investigation_id', $investigationId)
            ->orderBy('id')
            ->get();

        if ($runs->count() < 2) {
            return;
        }

        $mainRunsByEmail = [];

        foreach ($runs as $run) {
            $email = $this->extractGoogleRunEmail($run);
            if (! $email) {
                continue;
            }

            $hasSubscriber = is_array(data_get($run->summary, 'subscriber_info'))
                && count((array) data_get($run->summary, 'subscriber_info')) > 0;
            $hasAccessEvents = $run->events()->where('event_type', 'access')->exists();

            if ($hasSubscriber || $hasAccessEvents || trim((string) $run->target) !== '') {
                $mainRunsByEmail[$email] = $run;
            }
        }

        foreach ($runs as $run) {
            $email = $this->extractGoogleRunEmail($run);
            if (! $email) {
                continue;
            }

            $hasSubscriber = is_array(data_get($run->summary, 'subscriber_info'))
                && count((array) data_get($run->summary, 'subscriber_info')) > 0;
            $hasAccessEvents = $run->events()->where('event_type', 'access')->exists();
            $hasSupplementalEvents = $run->events()->whereIn('event_type', ['map', 'search'])->exists();

            if ($hasSubscriber || $hasAccessEvents || ! $hasSupplementalEvents) {
                continue;
            }

            $mainRun = $mainRunsByEmail[$email] ?? null;
            if (! $mainRun || (int) $mainRun->id === (int) $run->id) {
                continue;
            }

            AnaliseRunEvent::query()
                ->where('analise_run_id', $run->id)
                ->update(['analise_run_id' => $mainRun->id]);

            $mainSummary = is_array($mainRun->summary) ? $mainRun->summary : [];
            $supplementalSummary = is_array($run->summary) ? $run->summary : [];

            $mainSummary['_files'] = array_values(array_unique(array_merge(
                (array) ($mainSummary['_files'] ?? []),
                (array) ($supplementalSummary['_files'] ?? []),
            )));
            $mainSummary['_fragments'] = array_values(array_unique(array_merge(
                (array) ($mainSummary['_fragments'] ?? []),
                (array) ($supplementalSummary['_fragments'] ?? []),
            )));

            $mainRun->summary = $mainSummary;
            $mainRun->save();

            AnaliseRunStep::query()->where('analise_run_id', $run->id)->delete();
            AnaliseRunContact::query()->where('analise_run_id', $run->id)->delete();
            AnaliseRunIp::query()->where('analise_run_id', $run->id)->delete();
            AnaliseRunMessage::query()->where('analise_run_id', $run->id)->delete();
            AnaliseRunMedia::query()->where('analise_run_id', $run->id)->delete();
            AnaliseRunEvent::query()->where('analise_run_id', $run->id)->delete();
            $run->delete();

            $mainRun->forceFill([
                'summary' => null,
                'finished_at' => null,
            ])->save();
        }
    }

    protected function extractGoogleRunEmail(AnaliseRun $run): ?string
    {
        $target = trim((string) ($run->target ?? ''));
        if (filter_var($target, FILTER_VALIDATE_EMAIL)) {
            return strtolower($target);
        }

        $subscriberEmail = trim((string) data_get($run->summary, 'subscriber_info.email'));
        if (filter_var($subscriberEmail, FILTER_VALIDATE_EMAIL)) {
            return strtolower($subscriberEmail);
        }

        foreach ((array) data_get($run->summary, '_files', []) as $file) {
            if (! is_string($file)) {
                continue;
            }

            if (preg_match('/([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})(?=\.\d+\.(?:MyActivity|GoogleAccount)\.)/i', $file, $match)) {
                return strtolower($match[1]);
            }
        }

        return null;
    }

    public function setVinculoPage(int $page): void
    {
        $this->vinculoPage = max(1, $page);
    }

    public function openVinculoTimesModal(string $ip, string $target): void
    {
        if (! $this->runId) {
            Notification::make()->title('Run invalido')->danger()->send();
            return;
        }

        $run = AnaliseRun::query()->find($this->runId);
        if (! $run || ! $run->investigation_id) {
            Notification::make()->title('Relatorio nao encontrado')->danger()->send();
            return;
        }

        $normalizedTarget = trim($target);
        $matchedRunId = null;

        if ($normalizedTarget === $this->resolveRunTarget($run)) {
            $matchedRunId = (int) $run->id;
        } else {
            $otherRuns = AnaliseRun::query()
                ->where('investigation_id', $run->investigation_id)
                ->whereKeyNot($run->id)
                ->get();

            foreach ($otherRuns as $otherRun) {
                if ($this->resolveRunTarget($otherRun) === $normalizedTarget) {
                    $matchedRunId = (int) $otherRun->id;
                    break;
                }
            }
        }

        if (! $matchedRunId) {
            Notification::make()->title('Horarios nao encontrados para este alvo')->warning()->send();
            return;
        }

        $times = AnaliseRunEvent::query()
            ->where('analise_run_id', $matchedRunId)
            ->where('event_type', 'access')
            ->where('ip', $ip)
            ->orderBy('occurred_at')
            ->pluck('occurred_at')
            ->filter()
            ->map(fn ($occurredAt): string => Carbon::parse($occurredAt, 'UTC')->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s'))
            ->values()
            ->all();

        if (count($times) === 0) {
            Notification::make()->title('Horarios nao encontrados para este alvo')->warning()->send();
            return;
        }

        $this->vinculoModalIp = $ip;
        $this->vinculoModalTarget = $normalizedTarget;
        $this->vinculoModalTimes = $times;
        $this->mountAction('vinculoTimesModal');
    }

    public function vinculoTimesModal(): Action
    {
        return Action::make('vinculoTimesModal')
            ->label('Horarios')
            ->modalHeading(fn (): string => 'Horarios - ' . ($this->vinculoModalTarget ?? '-') . ' / ' . ($this->vinculoModalIp ?? '-'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn () => view('filament.pages.partials.modal-vinculo-times', [
                'ip' => $this->vinculoModalIp,
                'target' => $this->vinculoModalTarget,
                'times' => $this->vinculoModalTimes,
            ]))
            ->after(function (): void {
                $this->vinculoModalIp = null;
                $this->vinculoModalTarget = null;
                $this->vinculoModalTimes = [];
            });
    }

    protected function countVinculoRows(AnaliseRun $currentRun): int
    {
        return count($this->buildVinculoRows($currentRun));
    }

    protected function buildVinculoRows(AnaliseRun $currentRun): array
    {
        if (! $currentRun->investigation_id) {
            return [];
        }

        $currentTarget = $this->resolveRunTarget($currentRun);
        if ($currentTarget === '') {
            return [];
        }

        $currentIpRows = AnaliseRunIp::query()
            ->where('analise_run_id', $currentRun->id)
            ->get(['ip', 'occurrences', 'last_seen_at'])
            ->keyBy('ip');

        if ($currentIpRows->isEmpty()) {
            return [];
        }

        $otherRuns = AnaliseRun::query()
            ->where('investigation_id', $currentRun->investigation_id)
            ->whereKeyNot($currentRun->id)
            ->get();

        if ($otherRuns->isEmpty()) {
            return [];
        }

        $otherRunTargets = [];
        foreach ($otherRuns as $run) {
            $target = $this->resolveRunTarget($run);
            if ($target !== '') {
                $otherRunTargets[(int) $run->id] = $target;
            }
        }

        if (count($otherRunTargets) === 0) {
            return [];
        }

        $sharedIpRows = AnaliseRunIp::query()
            ->whereIn('analise_run_id', array_keys($otherRunTargets))
            ->whereIn('ip', $currentIpRows->keys()->all())
            ->get(['analise_run_id', 'ip', 'occurrences', 'last_seen_at']);

        if ($sharedIpRows->isEmpty()) {
            return [];
        }

        $sharedIps = $sharedIpRows->pluck('ip')->unique()->values()->all();
        $eventRows = AnaliseRunEvent::query()
            ->whereIn('analise_run_id', array_merge([(int) $currentRun->id], array_keys($otherRunTargets)))
            ->where('event_type', 'access')
            ->whereIn('ip', $sharedIps)
            ->orderBy('occurred_at')
            ->get(['analise_run_id', 'ip', 'occurred_at']);

        $eventMeta = [];
        foreach ($eventRows as $event) {
            $runId = (int) $event->analise_run_id;
            $ip = (string) $event->ip;
            $occurredAt = $event->occurred_at
                ? Carbon::parse($event->occurred_at, 'UTC')
                : null;

            if (! $occurredAt) {
                continue;
            }

            $eventMeta[$runId][$ip] ??= [
                'count' => 0,
                'first_seen_at' => $occurredAt,
                'last_seen_at' => $occurredAt,
                'times' => [],
            ];

            $eventMeta[$runId][$ip]['count']++;

            if ($occurredAt->lessThan($eventMeta[$runId][$ip]['first_seen_at'])) {
                $eventMeta[$runId][$ip]['first_seen_at'] = $occurredAt;
            }

            if ($occurredAt->greaterThan($eventMeta[$runId][$ip]['last_seen_at'])) {
                $eventMeta[$runId][$ip]['last_seen_at'] = $occurredAt;
            }

            $eventMeta[$runId][$ip]['times'][] = $occurredAt
                ->copy()
                ->setTimezone('America/Sao_Paulo')
                ->format('d/m/Y H:i:s');
        }

        $rowsByIp = [];

        foreach ($sharedIpRows as $sharedIpRow) {
            $ip = (string) $sharedIpRow->ip;
            $otherRunId = (int) $sharedIpRow->analise_run_id;
            $otherTarget = $otherRunTargets[$otherRunId] ?? '';

            if ($otherTarget === '') {
                continue;
            }

            $currentIpRow = $currentIpRows->get($ip);
            if (! $currentIpRow) {
                continue;
            }

            $currentMeta = $eventMeta[(int) $currentRun->id][$ip] ?? [
                'count' => (int) $currentIpRow->occurrences,
                'first_seen_at' => $currentIpRow->last_seen_at ? Carbon::parse($currentIpRow->last_seen_at, 'UTC') : null,
                'last_seen_at' => $currentIpRow->last_seen_at ? Carbon::parse($currentIpRow->last_seen_at, 'UTC') : null,
                'times' => [],
            ];

            $otherMeta = $eventMeta[$otherRunId][$ip] ?? [
                'count' => (int) $sharedIpRow->occurrences,
                'first_seen_at' => $sharedIpRow->last_seen_at ? Carbon::parse($sharedIpRow->last_seen_at, 'UTC') : null,
                'last_seen_at' => $sharedIpRow->last_seen_at ? Carbon::parse($sharedIpRow->last_seen_at, 'UTC') : null,
                'times' => [],
            ];

            $rowsByIp[$ip] ??= [
                'ip' => $ip,
                'accesses' => [
                    [
                        'run_id' => (int) $currentRun->id,
                        'target' => $currentTarget,
                        'count' => (int) ($currentMeta['count'] ?? 0),
                        'first_seen' => $this->formatCarbonForVinculo($currentMeta['first_seen_at'] ?? null),
                        'last_seen' => $this->formatCarbonForVinculo($currentMeta['last_seen_at'] ?? null),
                        'times' => (array) ($currentMeta['times'] ?? []),
                        'is_selected' => true,
                    ],
                ],
                'targets_count' => 1,
                'total_occurrences' => (int) ($currentMeta['count'] ?? 0),
                'last_seen_at' => $currentMeta['last_seen_at'] ?? null,
            ];

            $rowsByIp[$ip]['accesses'][] = [
                'run_id' => $otherRunId,
                'target' => $otherTarget,
                'count' => (int) ($otherMeta['count'] ?? 0),
                'first_seen' => $this->formatCarbonForVinculo($otherMeta['first_seen_at'] ?? null),
                'last_seen' => $this->formatCarbonForVinculo($otherMeta['last_seen_at'] ?? null),
                'times' => (array) ($otherMeta['times'] ?? []),
                'is_selected' => false,
            ];
            $rowsByIp[$ip]['targets_count']++;
            $rowsByIp[$ip]['total_occurrences'] += (int) ($otherMeta['count'] ?? 0);

            $otherLastSeen = $otherMeta['last_seen_at'] ?? null;
            if (
                $otherLastSeen instanceof Carbon
                && (! ($rowsByIp[$ip]['last_seen_at'] instanceof Carbon) || $otherLastSeen->greaterThan($rowsByIp[$ip]['last_seen_at']))
            ) {
                $rowsByIp[$ip]['last_seen_at'] = $otherLastSeen;
            }
        }

        if (count($rowsByIp) === 0) {
            return [];
        }

        $enrichments = IpEnrichment::query()
            ->whereIn('ip', array_keys($rowsByIp))
            ->get()
            ->keyBy('ip');

        $rows = [];

        foreach ($rowsByIp as $ip => $row) {
            usort($row['accesses'], function (array $left, array $right): int {
                $leftSelected = (bool) ($left['is_selected'] ?? false);
                $rightSelected = (bool) ($right['is_selected'] ?? false);

                if ($leftSelected !== $rightSelected) {
                    return $leftSelected ? -1 : 1;
                }

                return strcmp((string) ($left['target'] ?? ''), (string) ($right['target'] ?? ''));
            });

            $enrichment = $enrichments->get($ip);
            $provider = trim((string) (($enrichment?->isp ?? '') ?: ($enrichment?->org ?? '')));

            $rows[] = [
                'ip' => $ip,
                'targets' => implode(' | ', array_map(
                    fn (array $access): string => (string) ($access['target'] ?? ''),
                    $row['accesses'],
                )),
                'targets_count' => (int) ($row['targets_count'] ?? count($row['accesses'])),
                'total_occurrences' => (int) ($row['total_occurrences'] ?? 0),
                'last_seen' => $this->formatCarbonForVinculo($row['last_seen_at'] ?? null),
                '_last_seen_at' => $row['last_seen_at'] ?? null,
                'provider' => $provider !== '' ? $provider : 'Desconhecido',
                'city' => trim((string) ($enrichment?->city ?? '')) ?: 'Desconhecida',
                'type' => ($enrichment?->mobile ?? false) ? 'Movel' : 'Residencial',
                'accesses' => $row['accesses'],
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $leftAt = $left['_last_seen_at'] ?? null;
            $rightAt = $right['_last_seen_at'] ?? null;

            if ($leftAt instanceof Carbon && $rightAt instanceof Carbon) {
                return $rightAt->getTimestamp() <=> $leftAt->getTimestamp();
            }

            return 0;
        });

        foreach ($rows as &$row) {
            unset($row['_last_seen_at']);
        }
        unset($row);

        return $rows;
    }

    protected function formatCarbonForVinculo(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        $carbon = $value instanceof Carbon ? $value->copy() : Carbon::parse($value, 'UTC');

        return $carbon->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
    }
}

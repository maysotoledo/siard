<?php

namespace App\Filament\Pages;

use App\Jobs\AnaliseInteligente\Instagram\ProcessInstagramInvestigationJob;
use App\Models\AnaliseRun;
use App\Models\AnaliseInvestigation;
use App\Models\AnaliseRunEvent;
use App\Models\AnaliseRunIp;
use App\Filament\Pages\RelatoriosProcessados;
use App\Models\IpEnrichment;
use App\Services\AnaliseInteligente\RunPayloadStorage;
use App\Services\AnaliseInteligente\Instagram\RecordsHtmlParser;
use App\Services\AnaliseInteligente\Instagram\ReportAggregator;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Bus\Dispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class AnaliseInteligenteInsta extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-camera';
    protected static ?string $navigationLabel = 'Análise log INSTAGRAM';
    protected static ?string $title = 'Análise de log do INSTAGRAM';
    protected static ?string $slug = 'analise-inteligente-insta';

    protected string $view = 'filament.pages.analise-inteligente-insta-planilhas';

    public ?array $data = [];

    public ?int $investigationId = null;
    public ?array $investigation = null;
    public ?int $runId = null;
    public ?int $selectedTargetRunId = null;
    public array $targetRuns = [];
    public int $progress = 0;
    public bool $running = false;
    public ?array $report = null;

    public int $chunkSize = 10;
    public string $tab = 'timeline';

    public ?string $selectedProvider = null;
    public array $selectedProviderIps = [];

    // Direct modal
    public ?string $selectedDirectParticipant = null;
    public array $selectedDirectMessages = [];

    // Followers / following modal
    public ?string $selectedRelationshipType = null;
    public array $selectedRelationshipNames = [];

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Análise Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 20;
    }

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

                FileUpload::make('html_file')
                    ->label('Arquivos (ZIP/HTML): records.html')
                    ->required()
                    ->multiple()
                    ->disk('public')
                    ->directory('uploads/records-html-instagram')
                    ->acceptedFileTypes([
                        'text/html',
                        'text/plain',
                        'application/zip',
                        'application/x-zip-compressed',
                        '.html',
                        '.htm',
                        '.zip',
                    ])
                    ->preserveFilenames()
                    ->maxSize(20_000),
            ])
            ->statePath('data');
    }

    public function gerar(): void
    {
        if ($this->running) return;

        $this->report = null;
        $this->progress = 0;
        $this->running = false;
        $this->targetRuns = [];
        $this->selectedTargetRunId = null;
        $this->tab = 'timeline';

        $this->selectedProvider = null;
        $this->selectedProviderIps = [];
        $this->closeDirectModalState();
        $this->closeRelationshipModalState();

        $state = $this->form->getState();
        $investigation = $this->resolveInvestigationForUpload($state);
        if (! $investigation) {
            return;
        }

        $storedPaths = $state['html_file'] ?? null;

        if (is_string($storedPaths)) $storedPaths = [$storedPaths];

        if (! is_array($storedPaths) || count($storedPaths) === 0) {
            Notification::make()->title('Envie pelo menos 1 arquivo')->danger()->send();
            return;
        }

        $batchId = (string) Str::uuid();

        app(Dispatcher::class)->dispatchAfterResponse(
            new ProcessInstagramInvestigationJob(
                investigationId: $investigation->id,
                userId: (int) auth()->id(),
                storedPaths: array_values($storedPaths),
                batchId: $batchId,
            )
        );

        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
            'source' => $investigation->source,
            'platform_label' => 'Instagram',
        ];
        $this->runId = null;
        $this->selectedTargetRunId = null;
        $this->targetRuns = [];
        $this->running = true;
        $this->progress = 2;

        Notification::make()
            ->title('Processamento enviado para a fila')
            ->body('Os arquivos foram enfileirados; a tela agora acompanha o progresso pelo banco.')
            ->success()
            ->send();

        return;

        $disk = Storage::disk('public');

        $parsedList = [];
        foreach ($storedPaths as $storedPath) {
            if (! $storedPath || ! $disk->exists($storedPath)) continue;

            $html = $this->resolveHtmlFromUpload($storedPath);
            if (! is_string($html) || trim($html) === '') continue;

            $parsedList[] = [
                'stored_path' => $storedPath,
                'parsed' => (new RecordsHtmlParser())->parse($html),
            ];
        }

        if (count($parsedList) === 0) {
            Notification::make()->title('Nenhum HTML válido / ZIP com records.html')->danger()->send();
            return;
        }

        // principal = maior ip_events
        $groups = [];
        foreach ($parsedList as $item) {
            $p = (array) ($item['parsed'] ?? []);
            $targetRaw = $p['target'] ?? ($p['account_identifier'] ?? null);
            $targetKey = $this->normalizeInstagramTarget(is_string($targetRaw) ? $targetRaw : null);

            if (! $targetKey) {
                $targetKey = 'sem-alvo:' . md5((string) ($item['stored_path'] ?? Str::uuid()));
            }

            $groups[$targetKey] ??= [];
            $groups[$targetKey][] = $item;
        }

        if (count($groups) > 1) {
            $runs = [];
            $batchId = (string) Str::uuid();

            foreach ($groups as $items) {
                $mainParsed = $this->resolveMainParsedFromInstagramItems($items);
                if (! $mainParsed || count($mainParsed['ip_events'] ?? []) === 0) {
                    continue;
                }

                $ipsMap = $this->extractIpsMapFromInstagramParsed($mainParsed);
                if (count($ipsMap) === 0) {
                    continue;
                }

                $runs[] = $this->createInstagramRun($investigation, $mainParsed, $ipsMap, $batchId);
            }

            if (count($runs) === 0) {
                Notification::make()->title('Nenhum IP encontrado no HTML')->warning()->send();
                return;
            }

            $runIds = array_map(fn (AnaliseRun $run): int => (int) $run->id, $runs);
            foreach ($runs as $run) {
                $payload = $run->report ?: [];
                $payload['_batch_run_ids'] = $runIds;
                $run->report = $payload;
                $run->save();
            }

            $this->investigationId = $investigation->id;
            $this->investigation = [
                'id' => $investigation->id,
                'name' => $investigation->name,
                'source' => $investigation->source,
                'platform_label' => 'Instagram',
            ];
            $this->runId = $runs[0]->id;
            $this->selectedTargetRunId = $runs[0]->id;
            $this->targetRuns = $this->formatTargetRuns(
                AnaliseRun::query()->where('investigation_id', $investigation->id)->orderBy('id')->get()->all(),
            );
            $this->running = true;

            Notification::make()
                ->title('Processamento iniciado para ' . count($runs) . ' alvos')
                ->success()
                ->send();

            return;
        }

        $mainParsed = null;
        $maxIps = -1;

        foreach ($parsedList as $item) {
            $p = (array) ($item['parsed'] ?? []);
            $n = count($p['ip_events'] ?? []);
            if ($n > $maxIps) {
                $maxIps = $n;
                $mainParsed = $p;
            }
        }

        if (! $mainParsed || count($mainParsed['ip_events'] ?? []) === 0) {
            Notification::make()->title('Não encontrei arquivo com IPs (ip_events vazio)')->danger()->send();
            return;
        }

        $ipsMap = [];
        foreach (($mainParsed['ip_events'] ?? []) as $e) {
            $ip = trim((string) ($e['ip'] ?? ''));
            if ($ip === '') continue;

            $time = $e['time_utc'] ?? null;
            $ts = null;

            if ($time instanceof \Carbon\Carbon) $ts = $time->timestamp;
            elseif (is_string($time) && trim($time) !== '') $ts = strtotime($time) ?: null;
            elseif (is_int($time)) $ts = $time;

            $ipsMap[$ip] ??= ['occurrences' => 0, 'last_seen_ts' => $ts];
            $ipsMap[$ip]['occurrences']++;

            if ($ts && ($ipsMap[$ip]['last_seen_ts'] === null || $ts > $ipsMap[$ip]['last_seen_ts'])) {
                $ipsMap[$ip]['last_seen_ts'] = $ts;
            }
        }

        if (count($ipsMap) === 0) {
            Notification::make()->title('Nenhum IP encontrado no HTML')->warning()->send();
            return;
        }

        $run = DB::transaction(function () use ($mainParsed, $ipsMap, $investigation) {
            $run = AnaliseRun::create([
                'user_id' => auth()->id(),
                'investigation_id' => $investigation->id,
                'uuid' => (string) Str::uuid(),
                'target' => $mainParsed['target'] ?? null,
                'total_unique_ips' => count($ipsMap),
                'processed_unique_ips' => 0,
                'progress' => 0,
                'status' => 'running',
                'report' => [
                    '_source' => 'instagram',
                    '_parsed' => $mainParsed,
                ],
            ]);

            foreach ($ipsMap as $ip => $meta) {
                AnaliseRunIp::create([
                    'analise_run_id' => $run->id,
                    'ip' => $ip,
                    'occurrences' => (int) $meta['occurrences'],
                    'last_seen_at' => $meta['last_seen_ts']
                        ? now()->setTimestamp((int) $meta['last_seen_ts'])
                        : null,
                    'enriched' => false,
                ]);
            }

            return $run;
        });

        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
            'source' => $investigation->source,
            'platform_label' => 'Instagram',
        ];
        $this->runId = $run->id;
        $this->selectedTargetRunId = $run->id;
        $this->targetRuns = $this->formatTargetRuns([$run]);
        $this->running = true;

        Notification::make()->title('Processamento iniciado')->success()->send();
    }

    protected function instagramInvestigationName(array $parsed): string
    {
        $handle = trim((string) ($parsed['account_identifier'] ?? ''));
        if ($handle !== '' && ! preg_match('/^\d+$/', $handle)) {
            return 'Instagram ' . (str_starts_with($handle, '@') ? $handle : "@{$handle}");
        }

        $target = trim((string) ($parsed['target'] ?? ''));
        if ($target !== '') {
            return 'Instagram ' . $target;
        }

        return 'Investigação Instagram';
    }

    protected function resolveMainParsedFromInstagramItems(array $items): ?array
    {
        $mainParsed = null;
        $maxIps = -1;

        foreach ($items as $item) {
            $p = (array) ($item['parsed'] ?? []);
            $n = count($p['ip_events'] ?? []);
            if ($n > $maxIps) {
                $maxIps = $n;
                $mainParsed = $p;
            }
        }

        return $mainParsed;
    }

    protected function extractIpsMapFromInstagramParsed(array $parsed): array
    {
        $ipsMap = [];

        foreach (($parsed['ip_events'] ?? []) as $event) {
            $ip = trim((string) ($event['ip'] ?? ''));
            if ($ip === '') {
                continue;
            }

            $time = $event['time_utc'] ?? null;
            $ts = null;

            if ($time instanceof Carbon) {
                $ts = $time->timestamp;
            } elseif (is_string($time) && trim($time) !== '') {
                $ts = strtotime($time) ?: null;
            } elseif (is_int($time)) {
                $ts = $time;
            }

            $ipsMap[$ip] ??= ['occurrences' => 0, 'last_seen_ts' => $ts];
            $ipsMap[$ip]['occurrences']++;

            if ($ts && ($ipsMap[$ip]['last_seen_ts'] === null || $ts > $ipsMap[$ip]['last_seen_ts'])) {
                $ipsMap[$ip]['last_seen_ts'] = $ts;
            }
        }

        return $ipsMap;
    }

    protected function createInstagramRun(AnaliseInvestigation $investigation, array $parsed, array $ipsMap, string $batchId): AnaliseRun
    {
        return DB::transaction(function () use ($investigation, $parsed, $ipsMap, $batchId) {
            $run = AnaliseRun::create([
                'user_id' => auth()->id(),
                'investigation_id' => $investigation->id,
                'uuid' => (string) Str::uuid(),
                'target' => $parsed['target'] ?? null,
                'total_unique_ips' => count($ipsMap),
                'processed_unique_ips' => 0,
                'progress' => 0,
                'status' => 'running',
                'report' => [
                    '_source' => 'instagram',
                    '_batch_id' => $batchId,
                    '_parsed' => $parsed,
                ],
            ]);

            foreach ($ipsMap as $ip => $meta) {
                AnaliseRunIp::create([
                    'analise_run_id' => $run->id,
                    'ip' => $ip,
                    'occurrences' => (int) $meta['occurrences'],
                    'last_seen_at' => $meta['last_seen_ts']
                        ? now()->setTimestamp((int) $meta['last_seen_ts'])
                        : null,
                    'enriched' => false,
                ]);
            }

            return $run;
        });
    }

    protected function normalizeInstagramTarget(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return mb_strtolower(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    protected function resolveInvestigationForUpload(array $state): ?AnaliseInvestigation
    {
        if ($this->investigationId) {
            $investigation = AnaliseInvestigation::query()
                ->whereKey($this->investigationId)
                ->where('user_id', auth()->id())
                ->first();

            if (! $investigation) {
                Notification::make()->title('Investigacao nao encontrada')->danger()->send();
                return null;
            }

            if ($investigation->source !== 'instagram') {
                Notification::make()->title('Esta investigacao pertence a outra plataforma')->danger()->send();
                return null;
            }

            return $investigation;
        }

        $name = trim((string) ($state['investigation_name'] ?? ''));
        if ($name === '') {
            Notification::make()->title('Informe o nome da investigacao')->danger()->send();
            return null;
        }

        return AnaliseInvestigation::create([
            'user_id' => auth()->id(),
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'source' => 'instagram',
        ]);
    }

    protected function formatTargetRuns(array $runs): array
    {
        return array_values(array_map(function (AnaliseRun $run): array {
            $target = trim((string) (
                data_get($run->summary, 'vanity_name')
                ?: data_get($run->summary, 'account_identifier')
                ?: data_get($run->report, '_parsed.vanity_name')
                ?: data_get($run->report, '_parsed.account_identifier')
                ?: $run->target
                ?: data_get($run->report, '_parsed.target')
                ?: 'Alvo nao identificado'
            ));

            return [
                'id' => (int) $run->id,
                'target' => $target,
                'status' => (string) $run->status,
                'progress' => (int) $run->progress,
                'total_ips' => isset($run->events_count) ? (int) $run->events_count : $run->events()->count(),
                'unique_ips' => (int) $run->total_unique_ips,
            ];
        }, $runs));
    }

    public function poll(): void
    {
        if ($this->selectedProvider !== null) return;
        if ($this->investigationId || $this->runId) {
            $runs = AnaliseRun::query()
                ->when($this->investigationId, fn ($query) => $query->where('investigation_id', $this->investigationId))
                ->when(! $this->investigationId && $this->runId, fn ($query) => $query->whereKey($this->runId))
                ->orderBy('id')
                ->get();

            if ($runs->isEmpty()) {
                if ($this->running && $this->investigationId) {
                    $this->progress = max($this->progress, 2);
                    return;
                }

                $this->running = false;
                $this->progress = 0;
                return;
            }

            $this->targetRuns = $this->formatTargetRuns($runs->all());
            $this->running = $runs->contains(fn (AnaliseRun $item): bool => in_array((string) $item->status, ['queued', 'running'], true));
            $this->progress = (int) floor($runs->avg('progress') ?? 0);

            $selected = $runs->firstWhere('id', $this->selectedTargetRunId ?: $this->runId) ?: $runs->first();
            if ($selected) {
                $this->runId = (int) $selected->id;
                $this->selectedTargetRunId = (int) $selected->id;

                if ($selected->status === 'done' && ($this->report === null || (int) $this->runId !== (int) $selected->id)) {
                    $this->hydrateReportFromRun($selected, $this->tab ?: 'timeline');
                }
            }

            return;
        }

        if ($this->investigationId) {
            $runs = AnaliseRun::query()
                ->where('investigation_id', $this->investigationId)
                ->orderBy('id')
                ->get();

            foreach ($runs as $item) {
                if ($item->status === 'running') {
                    app(RunStepper::class)->step($item, $this->chunkSize, 0.0);
                }
            }

            $runs = AnaliseRun::query()
                ->where('investigation_id', $this->investigationId)
                ->orderBy('id')
                ->get();

            $this->targetRuns = $this->formatTargetRuns($runs->all());
            $this->running = $runs->contains(fn (AnaliseRun $item): bool => $item->status === 'running');
            $this->progress = (int) floor($runs->avg('progress') ?? 0);

            $selected = AnaliseRun::find($this->selectedTargetRunId ?: $this->runId);
            if ($selected && $selected->status === 'done' && $this->report === null) {
                $this->hydrateReportFromRun($selected, 'timeline');
                Notification::make()->title('Relatorio pronto')->success()->send();
            }

            return;
        }

        if (! $this->runId) return;

        $run = AnaliseRun::find($this->runId);
        if (! $run) return;

        $this->progress = (int) $run->progress;
        $this->running = ($run->status === 'running');

        if ($run->status === 'running') {
            app(RunStepper::class)->step($run, $this->chunkSize, 0.0);

            $run->refresh();
            $this->progress = (int) $run->progress;
            $this->running = ($run->status === 'running');
        }

        if ($run->status === 'done' && $this->report === null) {
            $this->hydrateReportFromRun($run, 'timeline');
            Notification::make()->title('Relatório pronto')->success()->send();
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, $this->availableTabs(), true)) {
            return;
        }

        $this->tab = $tab;

        if (! $this->runId || ! $this->report) {
            return;
        }

        $run = AnaliseRun::find($this->runId);
        if ($run && $run->status === 'done') {
            $this->hydrateReportFromRun($run, $tab);
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
        $this->progress = (int) $run->progress;
        $this->report = null;
        $this->tab = 'timeline';

        if ($run->status === 'done') {
            $this->hydrateReportFromRun($run, 'timeline');
        }
    }

    public function limpar(): void
    {
        if ($this->running) return;

        $this->runId = null;
        $this->progress = 0;
        $this->running = false;
        $this->report = null;
        $this->tab = 'timeline';

        $this->selectedProvider = null;
        $this->selectedProviderIps = [];
        $this->closeDirectModalState();
        $this->closeRelationshipModalState();

        $this->form->fill();
    }

    protected function loadExistingInvestigation(int $investigationId): void
    {
        $investigation = AnaliseInvestigation::query()
            ->whereKey($investigationId)
            ->first();

        if (! $investigation || ! $this->canViewInvestigation($investigation)) {
            Notification::make()->title('Investigacao nao encontrada')->danger()->send();
            return;
        }

        if ($investigation->source !== 'instagram') {
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
            'platform_label' => 'Instagram',
        ];
        $this->targetRuns = $this->formatTargetRuns($runs->all());
        $this->running = $runs->contains(fn (AnaliseRun $run): bool => in_array((string) $run->status, ['queued', 'running'], true));
        $this->progress = (int) floor($runs->avg('progress') ?? 0);

        $first = $runs->first();
        if ($first) {
            $this->runId = $first->id;
            $this->selectedTargetRunId = $first->id;

            if ($first->status === 'done') {
                $this->tab = 'timeline';
                $this->hydrateReportFromRun($first, 'timeline');
            }
        }
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

    protected function loadExistingRun(int $runId): void
    {
        $run = AnaliseRun::find($runId);

        if (! $run) {
            Notification::make()->title('Relatório processado não encontrado')->danger()->send();
            return;
        }

        $this->runId = $run->id;
        $this->selectedTargetRunId = $run->id;
        if ($run->investigation_id) {
            $this->investigationId = (int) $run->investigation_id;
            $investigation = AnaliseInvestigation::find($run->investigation_id);
            if ($investigation) {
                $this->investigation = [
                    'id' => $investigation->id,
                    'name' => $investigation->name,
                    'source' => $investigation->source,
                    'platform_label' => 'Instagram',
                ];
            }

            $this->targetRuns = $this->formatTargetRuns(
                AnaliseRun::query()->where('investigation_id', $run->investigation_id)->orderBy('id')->get()->all(),
            );
        } else {
            $this->targetRuns = $this->formatTargetRuns([$run]);
        }
        $this->progress = (int) $run->progress;
        $this->running = in_array((string) $run->status, ['queued', 'running'], true);

        if ($run->status === 'done') {
            $this->tab = 'timeline';
            $this->hydrateReportFromRun($run, 'timeline');
        }
    }

    protected function hydrateReportFromRun(AnaliseRun $run, ?string $activeTab = null): void
    {
        $report = Cache::remember(
            $this->reportCacheKey($run),
            now()->addHour(),
            fn () => $this->buildReportFromRun($run)
        );

        if (! is_array($report)) {
            return;
        }

        $this->report = $this->filterReportForActiveTab($report, $activeTab ?? $this->tab);
    }

    protected function buildReportFromRun(AnaliseRun $run): ?array
    {
        $parsed = app(RunPayloadStorage::class)->loadParsedPayload($run);

        if (! is_array($parsed)) {
            $parsed = $this->buildParsedPayloadFromDatabase($run);
        }

        if (! is_array($parsed)) {
            return $this->existingReportWithSheets($run);
        }

        $ips = AnaliseRunIp::where('analise_run_id', $run->id)->pluck('ip')->all();
        $enrs = IpEnrichment::whereIn('ip', $ips)->get()->keyBy('ip');

        $enrichedByIp = [];
        foreach ($ips as $ip) {
            $e = $enrs->get($ip);

            $enrichedByIp[$ip] = [
                'ip' => $ip,
                'city' => $e?->city,
                'isp' => $e?->isp,
                'org' => $e?->org,
                'mobile' => $e?->mobile,
            ];
        }

        return (new ReportAggregator())->buildReport($parsed, $enrichedByIp);
    }

    protected function buildParsedPayloadFromDatabase(AnaliseRun $run): ?array
    {
        $events = AnaliseRunEvent::query()
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'access')
            ->whereNotNull('occurred_at')
            ->orderBy('occurred_at')
            ->get(['occurred_at', 'timezone_label', 'ip', 'logical_port', 'description', 'metadata']);

        if ($events->isEmpty()) {
            return null;
        }

        $summary = is_array($run->summary) ? $run->summary : [];

        return [
            'target' => $run->target ?: data_get($summary, 'target'),
            'account_identifier' => data_get($summary, 'account_identifier'),
            'vanity_name' => data_get($summary, 'vanity_name'),
            'first_name' => data_get($summary, 'first_name'),
            'registration_date' => data_get($summary, 'registration_date'),
            'registration_ip' => data_get($summary, 'registration_ip'),
            'registration_phone' => data_get($summary, 'registration_phone'),
            'registration_phone_verified_on' => data_get($summary, 'registration_phone_verified_on'),
            'last_location_time' => data_get($summary, 'last_location_time'),
            'last_location_latitude' => data_get($summary, 'last_location_latitude'),
            'last_location_longitude' => data_get($summary, 'last_location_longitude'),
            'last_location_maps_url' => data_get($summary, 'last_location_maps_url'),
            'followers' => array_values((array) data_get($summary, 'followers', [])),
            'following' => array_values((array) data_get($summary, 'following', [])),
            'direct_threads' => array_values((array) data_get($summary, 'direct_threads', [])),
            'ip_events' => $events->map(function (AnaliseRunEvent $event): array {
                $metadata = is_array($event->metadata) ? $event->metadata : [];
                $ip = trim((string) ($event->ip ?: data_get($metadata, 'ip')));
                $logicalPort = $event->logical_port ?: data_get($metadata, 'logical_port');
                $ipWithPort = data_get($metadata, 'ip_with_port');

                if (! is_string($ipWithPort) || trim($ipWithPort) === '') {
                    $ipWithPort = $logicalPort ? "{$ip}:{$logicalPort}" : $ip;
                }

                return [
                    'ip' => $ip,
                    'ip_with_port' => $ipWithPort,
                    'logical_port' => $logicalPort,
                    'description' => $event->description ?: data_get($metadata, 'description'),
                    'time_utc' => $event->occurred_at?->copy()->setTimezone('UTC')->toIso8601String(),
                    'tz_label' => $event->timezone_label ?: data_get($metadata, 'tz_label', 'UTC'),
                ];
            })->filter(fn (array $event): bool => trim((string) ($event['ip'] ?? '')) !== '')->values()->all(),
        ];
    }

    protected function existingReportWithSheets(AnaliseRun $run): ?array
    {
        $report = is_array($run->report) ? $run->report : null;
        if (! is_array($report)) {
            return null;
        }

        $sheetKeys = [
            'timeline_rows',
            'unique_ip_rows',
            'provider_stats_rows',
            'city_stats_rows',
            'night_events_rows',
            'mobile_events_rows',
            'direct_threads',
            'followers',
            'following',
        ];

        foreach ($sheetKeys as $key) {
            if (array_key_exists($key, $report)) {
                return $report;
            }
        }

        return null;
    }

    protected function fullCachedReport(): array
    {
        if (! $this->runId) {
            return $this->report ?? [];
        }

        $run = AnaliseRun::find($this->runId);
        if (! $run) {
            return $this->report ?? [];
        }

        $report = Cache::remember(
            $this->reportCacheKey($run),
            now()->addHour(),
            fn () => $this->buildReportFromRun($run)
        );

        return is_array($report) ? $report : ($this->report ?? []);
    }

    protected function filterReportForActiveTab(array $report, string $activeTab): array
    {
        $counts = [
            'timeline' => count($report['timeline_rows'] ?? []),
            'unique_ips' => count($report['unique_ip_rows'] ?? []),
            'providers' => count($report['provider_stats_rows'] ?? []),
            'cities' => count($report['city_stats_rows'] ?? []),
            'residencial' => (int) ($report['night_total_events'] ?? 0),
            'movel' => (int) ($report['mobile_total_events'] ?? 0),
            'direct' => count($report['direct_threads'] ?? []),
            'followers' => (int) ($report['followers_count'] ?? count($report['followers'] ?? [])),
            'following' => (int) ($report['following_count'] ?? count($report['following'] ?? [])),
        ];

        $heavyKeys = [
            'timeline_rows',
            'unique_ip_rows',
            'provider_stats_rows',
            'city_stats_rows',
            'provider_ip_map',
            'night_events_rows',
            'mobile_events_rows',
            'direct_threads',
            'followers',
            'following',
        ];

        $keysByTab = [
            'timeline' => ['timeline_rows'],
            'unique_ips' => ['unique_ip_rows'],
            'providers' => ['provider_stats_rows', 'provider_ip_map'],
            'cities' => ['city_stats_rows'],
            'residencial' => ['night_events_rows'],
            'movel' => ['mobile_events_rows'],
            'direct' => ['direct_threads'],
        ];

        $keep = $keysByTab[$activeTab] ?? [];

        foreach ($heavyKeys as $key) {
            if (! in_array($key, $keep, true)) {
                $report[$key] = [];
            }
        }

        $report['_counts'] = $counts;
        $report['followers_count'] = $counts['followers'];
        $report['following_count'] = $counts['following'];

        return $report;
    }

    protected function reportCacheKey(AnaliseRun $run): string
    {
        return 'analise-insta-report:v2:' . $run->getKey();
    }

    protected function availableTabs(): array
    {
        return ['timeline', 'unique_ips', 'providers', 'cities', 'residencial', 'movel', 'direct'];
    }

    // ==========================
    // ✅ Direct modal
    // ==========================
    public function openDirectModal(string $participant): void
    {
        $participant = trim($participant);

        $threads = $this->fullCachedReport()['direct_threads'] ?? [];
        $found = null;

        foreach ((array) $threads as $t) {
            if (! is_array($t)) continue;
            if (($t['participant'] ?? null) === $participant) {
                $found = $t;
                break;
            }
        }

        $messages = is_array($found['messages'] ?? null) ? $found['messages'] : [];

        // ✅ ordena por datetime (fica natural)
        usort($messages, function ($a, $b) {
            $da = $this->safeParseDirectDatetime($a['datetime'] ?? null);
            $db = $this->safeParseDirectDatetime($b['datetime'] ?? null);

            if (! $da && ! $db) return 0;
            if (! $da) return -1;
            if (! $db) return 1;

            return $da->timestamp <=> $db->timestamp;
        });

        $this->selectedDirectParticipant = $participant;
        $this->selectedDirectMessages = $messages;

        $this->mountAction('directModal');
    }

    protected function safeParseDirectDatetime(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '—') return null;

        // formato vindo do aggregator: d/m/Y H:i:s
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function directModal(): Action
    {
        return Action::make('directModal')
            ->label('Conversa Direct')
            ->modalHeading(fn () => $this->selectedDirectParticipant ? $this->selectedDirectParticipant : 'Direct')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->after(fn () => $this->closeDirectModalState())
            ->modalContent(fn () => view('filament.pages.partials.modal-direct', [
                'participant' => $this->selectedDirectParticipant,
                'messages' => $this->selectedDirectMessages,
                // ✅ alvo para alinhar no modal
                'target' => $this->report['vanity_name'] ?? ($this->report['account_identifier'] ?? null),
            ]));
    }

    protected function closeDirectModalState(): void
    {
        $this->selectedDirectParticipant = null;
        $this->selectedDirectMessages = [];
    }

    // ==========================
    // Seguidores / seguindo modal
    // ==========================
    public function openRelationshipModal(string $type): void
    {
        if (! in_array($type, ['followers', 'following'], true)) {
            return;
        }

        $this->selectedRelationshipType = $type;
        $this->selectedRelationshipNames = array_values((array) ($this->fullCachedReport()[$type] ?? []));

        $this->mountAction('relationshipModal');
    }

    public function relationshipModal(): Action
    {
        return Action::make('relationshipModal')
            ->label('Contas')
            ->modalHeading(fn () => $this->selectedRelationshipType === 'followers' ? 'Seguidores' : 'Seguindo')
            ->modalWidth(Width::Large)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->after(fn () => $this->closeRelationshipModalState())
            ->modalContent(fn () => view('filament.pages.partials.modal-relationship-names', [
                'title' => $this->selectedRelationshipType === 'followers' ? 'Seguidores' : 'Seguindo',
                'names' => $this->selectedRelationshipNames,
            ]));
    }

    protected function closeRelationshipModalState(): void
    {
        $this->selectedRelationshipType = null;
        $this->selectedRelationshipNames = [];
    }

    // ==========================
    // Provider modal
    // ==========================
    #[On('open-provider-ips-modal')]
    public function openProviderIpsModal(string $provider): void
    {
        $provider = trim($provider);

        $this->selectedProvider = $provider !== '' ? $provider : 'Desconhecido';
        $this->selectedProviderIps = ($this->fullCachedReport()['provider_ip_map'][$this->selectedProvider] ?? []);

        $this->mountAction('providerIpsModal');
    }

    public function providerIpsModal(): Action
    {
        return Action::make('providerIpsModal')
            ->label('IPs do Provedor')
            ->modalHeading(fn () => "IPs - {$this->selectedProvider}")
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->after(fn () => $this->closeProviderModalState())
            ->modalContent(fn () => view('filament.pages.partials.modal-provider-ips', [
                'rows' => $this->selectedProviderIps,
            ]));
    }

    protected function closeProviderModalState(): void
    {
        $this->selectedProvider = null;
        $this->selectedProviderIps = [];
    }

    // ==========================
    // ZIP/HTML helper
    // ==========================
    protected function resolveHtmlFromUpload(string $storedPath): ?string
    {
        $disk = Storage::disk('public');
        $fullPath = $disk->path($storedPath);

        if (! is_file($fullPath)) return null;

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($ext === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($fullPath) !== true) return null;

            $htmlContent = null;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! is_string($name)) continue;

                if (str_ends_with(strtolower($name), 'records.html')) {
                    $htmlContent = $zip->getFromIndex($i);
                    break;
                }
            }

            if (! $htmlContent) {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if (! is_string($name)) continue;

                    if (str_ends_with(strtolower($name), '.html') || str_ends_with(strtolower($name), '.htm')) {
                        $htmlContent = $zip->getFromIndex($i);
                        break;
                    }
                }
            }

            $zip->close();

            return is_string($htmlContent) ? $htmlContent : null;
        }

        $html = @file_get_contents($fullPath);

        return is_string($html) ? $html : null;
    }
}

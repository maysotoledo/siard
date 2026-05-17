<?php

namespace App\Filament\Pages;

use App\Filament\Pages\RelatoriosProcessados;
use App\Jobs\AnaliseInteligente\Platform\EnrichRunIpsJob;
use App\Jobs\AnaliseInteligente\Whatsapp\ProcessWhatsappInvestigationJob;
use App\Jobs\AnaliseInteligente\Whatsapp\ProcessWhatsappTargetGroupJob;
use App\Models\AnaliseRun;
use App\Models\AnaliseRunContact;
use App\Models\AnaliseRunEvent;
use App\Models\AnaliseRunIp;
use App\Models\AnaliseInvestigation;
use App\Models\Bilhetagem;
use App\Models\IpEnrichment;
use App\Services\AnaliseInteligente\RunStepper;
use App\Services\AnaliseInteligente\Whatsapp\RecordsHtmlParser;
use App\Services\AnaliseInteligente\Whatsapp\ReportAggregator;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;

class AnaliseInteligenteWPP extends Page implements HasSchemas
{
    use InteractsWithSchemas;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Análise log WHATSAPP';
    protected static ?string $title = 'Análise de log do WHATSAPP';
    protected static ?string $slug = 'analise-inteligente-wpp';

    protected string $view = 'filament.pages.analise-inteligente-wpp-planilhas';

    public ?array $data = [];
    public ?int $investigationId = null;
    public ?array $investigation = null;

    public ?int $runId = null;
    public int $progress = 0;
    public bool $running = false;
    public ?array $report = null;
    public array $targetRuns = [];
    public ?int $selectedTargetRunId = null;

    public int $chunkSize = 1;
    public string $tab = 'timeline';

    public ?string $selectedContactType = null;
    public array $selectedContacts = [];

    public ?string $selectedProvider = null;
    public array $selectedProviderIps = [];

    public array $runWarnings = [];

    // ====== nomes ======
    public array $contactNames = [];
    public ?string $selectedContactPhone = null;

    // ====== MODAL BILHETAGEM (paginado) ======
    public ?string $bilhetagemModalPhone = null;     // digits
    public ?string $bilhetagemModalPhoneRaw = null;  // raw
    public int $bilhetagemModalPage = 1;
    public int $bilhetagemModalPerPage = 10;
    public int $bilhetagemModalTotal = 0;
    public array $bilhetagemModalRows = [];

    public ?string $vinculoModalIp = null;
    public ?string $vinculoModalTarget = null;
    public array $vinculoModalTimes = [];
    public int $vinculoPage = 1;
    public int $vinculoPerPage = 10;
    public ?string $pendingUploadCacheKey = null;
    public array $pendingDuplicateTargetKeys = [];
    public array $pendingDuplicateTargetLabels = [];
    public ?int $awaitingRunCreationUntil = null;
    public ?int $awaitingRunCreationBaseCount = null;

    // ====== MODAL BURST ======
    public ?string $burstHour = null;
    public array $burstModalRows = [];


    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Investigação Telemática';
    }

    public static function getNavigationSort(): ?int
    {
        return 10;
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
                    ->label('Nome da investigação')
                    ->required(fn () => $this->investigationId === null)
                    ->disabled(fn () => $this->investigationId !== null)
                    ->dehydrated(fn () => $this->investigationId === null)
                    ->maxLength(160),

                FileUpload::make('html_file')
                    ->label('Arquivos (ZIP/HTML): log/bilhetagens')
                    ->required()
                    ->multiple()
                    ->disk('public')
                    ->directory('uploads/records-html')
                    ->acceptedFileTypes([
                        'text/html',
                        'text/plain',
                        'application/zip',
                        'application/x-zip-compressed',
                        '.html',
                        '.htm',
                        '.zip',
                    ])
                    ->preserveFilenames(),
            ])
            ->statePath('data');
    }

    // =========================================================
    // ✅ GERAR
    // =========================================================
    public function gerar(): void
    {
        if ($this->running) return;

        $this->report = null;
        $this->progress = 0;
        $this->running = false;
        $this->targetRuns = [];
        $this->selectedTargetRunId = null;
        $this->tab = 'timeline';
        $this->runWarnings = [];

        $this->selectedContactType = null;
        $this->selectedContacts = [];
        $this->selectedProvider = null;
        $this->selectedProviderIps = [];

        $this->resetBilhetagemModalState();

        $state = $this->form->getState();
        $investigation = $this->resolveInvestigationForUpload($state);
        if (! $investigation || ! $this->canViewInvestigation($investigation)) {
            return;
        }

        $storedPaths = $state['html_file'] ?? null;
        if (is_string($storedPaths)) $storedPaths = [$storedPaths];

        if (! is_array($storedPaths) || count($storedPaths) === 0) {
            Notification::make()->title('Envie pelo menos 1 arquivo')->danger()->send();
            return;
        }

        $batchId = (string) Str::uuid();
        $existingRunCount = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->count();

        ProcessWhatsappInvestigationJob::dispatch(
            investigationId: $investigation->id,
            userId: (int) auth()->id(),
            storedPaths: array_values($storedPaths),
            batchId: $batchId,
        );

        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
        ];
        $this->runId = null;
        $this->selectedTargetRunId = null;
        $this->progress = 2;
        $this->running = true;
        $this->awaitingRunCreationUntil = now()->addMinutes(2)->timestamp;
        $this->awaitingRunCreationBaseCount = $existingRunCount;

        Notification::make()
            ->title('Processamento enviado para a fila')
            ->body('Os arquivos do WhatsApp foram enfileirados; a preparação e o agrupamento agora ocorrem em segundo plano.')
            ->success()
            ->send();
    }

    protected function processUploadGroups(AnaliseInvestigation $investigation, array $groups, array $groupsWithIpLog): void
    {
        $batchId = (string) Str::uuid();
        $existingRunCount = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->count();

        foreach ($groupsWithIpLog as $items) {
            $storedPaths = array_values(array_filter(array_map(
                fn (array $item): ?string => is_string($item['stored_path'] ?? null) ? $item['stored_path'] : null,
                $items,
            )));

            if (count($storedPaths) === 0) {
                continue;
            }

            ProcessWhatsappTargetGroupJob::dispatch(
                investigationId: $investigation->id,
                userId: (int) auth()->id(),
                storedPaths: $storedPaths,
                batchId: $batchId,
            );
        }

        $this->importBilhetagemOnlyGroupsIntoInvestigation($investigation, array_diff_key($groups, $groupsWithIpLog));

        $allInvestigationRuns = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->orderBy('id')
            ->get(['id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at'])
            ->all();

        $this->targetRuns = $this->formatTargetRuns($allInvestigationRuns);
        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
        ];
        $this->runId = null;
        $this->selectedTargetRunId = null;
        $this->progress = (int) floor(array_sum(array_column($this->targetRuns, 'progress')) / max(1, count($this->targetRuns)));
        $this->running = true;
        $this->awaitingRunCreationUntil = now()->addMinutes(2)->timestamp;
        $this->awaitingRunCreationBaseCount = $existingRunCount;

        Notification::make()
            ->title('Processamento enviado para a fila')
            ->body('Os arquivos do WhatsApp foram enfileirados; a tela agora acompanha o progresso pelo banco.')
            ->success()
            ->send();
    }

    public function confirmRemoveDuplicateTargets(): Action
    {
        return Action::make('confirmRemoveDuplicateTargets')
            ->label('Iniciar processamento')
            ->modalHeading('Remover alvos já existentes?')
            ->modalDescription(fn () => 'Os seguintes alvos já existem nesta investigação: ' . implode(', ', $this->pendingDuplicateTargetLabels))
            ->modalSubmitActionLabel('Sim')
            ->modalHeading('A análise será processada sem incluir alvo já existente nesta investigação')
            ->modalDescription(fn () => 'Alvos: ' . implode(', ', $this->pendingDuplicateTargetLabels))
            ->modalSubmitActionLabel('OK')
            ->modalCancelActionLabel('Cancelar')
            ->modalCancelActionLabel('Não')
            ->color('warning')
            ->action(function () {
                $pendingGroups = $this->pullPendingUploadGroups();

                if (! $this->investigationId || count($pendingGroups) === 0) {
                    Notification::make()->title('Upload pendente não encontrado')->danger()->send();
                    return;
                }

                $investigation = AnaliseInvestigation::query()
                    ->whereKey($this->investigationId)
                    ->where('user_id', auth()->id())
                    ->first();

                if (! $investigation) {
                    Notification::make()->title('Investigação não encontrada')->danger()->send();
                    return;
                }

                $groups = $this->removeExistingTargetGroups($investigation, $pendingGroups);

                $this->pendingUploadCacheKey = null;
                $this->pendingDuplicateTargetKeys = [];
                $this->pendingDuplicateTargetLabels = [];

                $groupsWithIpLog = array_filter($groups, function (array $items): bool {
                    foreach ($items as $item) {
                        $p = (array) ($item['parsed'] ?? []);
                        if (count($p['ip_events'] ?? []) > 0) {
                            return true;
                        }
                    }

                    return false;
                });

                if (count($groupsWithIpLog) === 0) {
                    $this->loadExistingInvestigation($investigation->id);
                    Notification::make()->title('Não há alvos novos para processar')->warning()->send();
                    return;
                }

                $this->processUploadGroups($investigation, $groups, $groupsWithIpLog);
            });
    }

    protected function removeExistingTargetGroups(AnaliseInvestigation $investigation, array $groups): array
    {
        $existing = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->get(['target'])
            ->mapWithKeys(function (AnaliseRun $run): array {
                $raw = $run->target;
                $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);

                return $key ? [$key => true] : [];
            })
            ->all();

        if (count($existing) === 0) {
            return $groups;
        }

        return array_filter($groups, function (array $items) use ($existing): bool {
            $parsed = (array) data_get($items, '0.parsed', []);
            $raw = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);
            $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);

            return ! $key || ! isset($existing[$key]);
        });
    }

    protected function resolveInvestigationForUpload(array $state): ?AnaliseInvestigation
    {
        if ($this->investigationId) {
            $investigation = AnaliseInvestigation::query()
                ->whereKey($this->investigationId)
                ->where('user_id', auth()->id())
                ->first();

            if (! $investigation) {
                Notification::make()->title('Investigação não encontrada')->danger()->send();
                return null;
            }

            return $investigation;
        }

        $name = trim((string) ($state['investigation_name'] ?? ''));
        if ($name === '') {
            Notification::make()->title('Informe o nome da investigação')->danger()->send();
            return null;
        }

        return AnaliseInvestigation::create([
            'user_id' => auth()->id(),
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'source' => 'whatsapp',
        ]);
    }

    protected function createRunForTargetGroup(array $parsedList, string $batchId, AnaliseInvestigation $investigation): AnaliseRun
    {
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

        $mainParsed ??= (array) ($parsedList[0]['parsed'] ?? []);
        $runTargetRaw = $mainParsed['target'] ?? ($mainParsed['account_identifier'] ?? null);

        $ipsMap = $this->extractIpsMapFromParsed($mainParsed);

        $connectionIpWithPort = data_get($mainParsed, 'connection_info.last_ip');
        $connectionIpBase = $this->extractIpBase(is_string($connectionIpWithPort) ? $connectionIpWithPort : null);

        $connectionLastSeenUtc = data_get($mainParsed, 'connection_info.last_seen_utc');
        $connectionLastSeenTs = null;
        if ($connectionLastSeenUtc instanceof Carbon) $connectionLastSeenTs = $connectionLastSeenUtc->timestamp;
        elseif (is_string($connectionLastSeenUtc) && trim($connectionLastSeenUtc) !== '') $connectionLastSeenTs = strtotime($connectionLastSeenUtc) ?: null;

        $ignoredBilhetagens = [];
        foreach ($parsedList as $item) {
            $p = (array) ($item['parsed'] ?? []);
            if (count($p['message_log'] ?? []) === 0) continue;

            $fileTarget = $p['target'] ?? ($p['account_identifier'] ?? null);
            if (! $this->targetsMatch(is_string($runTargetRaw) ? $runTargetRaw : null, is_string($fileTarget) ? $fileTarget : null)) {
                $ignoredBilhetagens[] = [
                    'arquivo' => (string) ($item['stored_path'] ?? ''),
                    'alvo_arquivo' => is_string($fileTarget) ? $fileTarget : '-',
                    'alvo_relatorio' => is_string($runTargetRaw) ? $runTargetRaw : '-',
                ];
            }
        }

        $parsedForReport = $this->parsedForRunPayload($mainParsed);
        $resolvedTarget = is_string($runTargetRaw) && trim($runTargetRaw) !== ''
            ? trim($runTargetRaw)
            : (is_string($mainParsed['target'] ?? null) ? trim((string) $mainParsed['target']) : null);

        return DB::transaction(function () use (
            $mainParsed,
            $parsedForReport,
            $ipsMap,
            $connectionIpBase,
            $connectionLastSeenTs,
            $parsedList,
            $runTargetRaw,
            $ignoredBilhetagens,
            $batchId,
            $investigation,
            $resolvedTarget
        ) {
            $runUuid = (string) Str::uuid();
            $parsedPath = $this->storeParsedPayload($runUuid, $parsedForReport);

            $run = AnaliseRun::create([
                'user_id' => auth()->id(),
                'investigation_id' => $investigation->id,
                'uuid' => $runUuid,
                'target' => $resolvedTarget,
                'total_unique_ips' => count($ipsMap) + ($connectionIpBase && ! isset($ipsMap[$connectionIpBase]) ? 1 : 0),
                'processed_unique_ips' => 0,
                'progress' => 0,
                'status' => 'running',
                'report' => [
                    '_source' => 'whatsapp',
                    '_batch_id' => $batchId,
                    '_target_thread_id' => (string) Str::uuid(),
                    '_parsed_path' => $parsedPath,
                    '_warnings' => [
                        'ignored_bilhetagens' => $ignoredBilhetagens,
                    ],
                ],
            ]);

            foreach ($ipsMap as $ip => $meta) {
                AnaliseRunIp::create([
                    'analise_run_id' => $run->id,
                    'ip' => $ip,
                    'occurrences' => (int) $meta['occurrences'],
                    'last_seen_at' => $meta['last_seen_ts'] ? now()->setTimestamp((int) $meta['last_seen_ts']) : null,
                    'enriched' => false,
                ]);
            }

            if ($connectionIpBase && ! isset($ipsMap[$connectionIpBase])) {
                AnaliseRunIp::create([
                    'analise_run_id' => $run->id,
                    'ip' => $connectionIpBase,
                    'occurrences' => 0,
                    'last_seen_at' => $connectionLastSeenTs ? now()->setTimestamp((int) $connectionLastSeenTs) : null,
                    'enriched' => false,
                ]);
            }

            // importa bilhetagem só se alvo bater
            $seen = [];

            foreach ($parsedList as $item) {
                $p = (array) ($item['parsed'] ?? []);
                $fileTarget = $p['target'] ?? ($p['account_identifier'] ?? null);

                $match = $this->targetsMatch(
                    is_string($runTargetRaw) ? $runTargetRaw : null,
                    is_string($fileTarget) ? $fileTarget : null
                );

                if (! $match) continue;

                foreach (($p['message_log'] ?? []) as $m) {
                    $recipient = trim((string) ($m['recipient'] ?? ''));
                    if ($recipient === '') continue;

                    $tsUtc = $m['timestamp_utc'] ?? null;
                    $tsKey = $tsUtc instanceof Carbon ? $tsUtc->format('Y-m-d H:i:s') : '-';

                    $messageId = trim((string) ($m['message_id'] ?? ''));
                    $key = $recipient . '|' . ($messageId !== '' ? $messageId : '-') . '|' . $tsKey;

                    if (isset($seen[$key])) continue;
                    $seen[$key] = true;

                    Bilhetagem::create([
                        'analise_run_id' => $run->id,
                        'timestamp_utc' => $tsUtc instanceof Carbon ? $tsUtc : null,
                        'message_id' => $messageId !== '' ? $messageId : null,
                        'sender' => $m['sender'] ?? null,
                        'recipient' => $recipient,
                        'sender_ip' => $m['sender_ip'] ?? null,
                        'sender_port' => $m['sender_port'] ?? null,
                        'type' => $m['type'] ?? null,
                    ]);
                }
            }

            return $run;
        });
    }

    protected function parsedForRunPayload(array $parsed): array
    {
        $parsed['message_log'] = [];

        return $parsed;
    }

    protected function extractIpsMapFromParsed(array $parsed): array
    {
        $ipsMap = [];
        foreach (($parsed['ip_events'] ?? []) as $event) {
            $ip = trim((string) ($event['ip'] ?? ''));
            if ($ip === '') continue;

            $time = $event['time_utc'] ?? null;
            $ts = null;

            if ($time instanceof Carbon) $ts = $time->timestamp;
            elseif (is_string($time) && trim($time) !== '') $ts = strtotime($time) ?: null;
            elseif (is_int($time)) $ts = $time;

            if (! isset($ipsMap[$ip])) {
                $ipsMap[$ip] = ['occurrences' => 0, 'last_seen_ts' => $ts];
            }

            $ipsMap[$ip]['occurrences']++;

            if ($ts && ($ipsMap[$ip]['last_seen_ts'] === null || $ts > $ipsMap[$ip]['last_seen_ts'])) {
                $ipsMap[$ip]['last_seen_ts'] = $ts;
            }
        }

        return $ipsMap;
    }

    // =========================================================
    // ✅ POLL
    // =========================================================
    public function poll(): void
    {
        if (! $this->runId && ! $this->investigationId) return;
        if ($this->selectedContactType !== null) return;
        if ($this->selectedProvider !== null) return;
        if ($this->vinculoModalIp !== null) return;
        if ($this->isTempDiskCriticallyFull()) {
            $this->running = false;

            Notification::make()
                ->title('Processamento pausado: pouco espaço em disco')
                ->body('Libere espaço no disco antes de continuar a análise.')
                ->danger()
                ->send();

            return;
        }

        $runs = AnaliseRun::query()
            ->when($this->investigationId, fn ($query) => $query->where('investigation_id', $this->investigationId))
            ->when(! $this->investigationId && $this->runId, fn ($query) => $query->whereKey($this->runId))
            ->orderBy('id')
            ->get(['id', 'investigation_id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at']);

        $awaitingRunCreation = $this->awaitingRunCreationUntil !== null
            && now()->timestamp < $this->awaitingRunCreationUntil;
        $awaitingBaseCount = (int) ($this->awaitingRunCreationBaseCount ?? 0);

        if ($runs->isEmpty()) {
            if (($this->running || $awaitingRunCreation) && $this->investigationId) {
                $this->running = true;
                $this->progress = max($this->progress, 2);
                return;
            }

            $this->running = false;
            $this->progress = 0;
            return;
        }

        $this->targetRuns = $this->formatTargetRuns($runs->all());
        $this->progress = (int) floor(array_sum(array_column($this->targetRuns, 'progress')) / max(1, count($this->targetRuns)));
        $this->running = $runs->contains(fn (AnaliseRun $run) => in_array((string) $run->status, ['queued', 'running'], true));

        if ($this->running || count($this->targetRuns) > $awaitingBaseCount) {
            $this->awaitingRunCreationUntil = null;
            $this->awaitingRunCreationBaseCount = null;
        }

        if (! $this->running && $awaitingRunCreation) {
            $this->running = true;
            $this->progress = max($this->progress, 2);
        }

        $selectedId = $this->selectedTargetRunId ?: $this->runId ?: ($this->targetRuns[0]['id'] ?? null);
        $selected = $this->loadRunLight($selectedId);
        if ($selected && $this->isRunCompleted($selected) && ($this->report === null || $this->runId !== $selected->id)) {
            $selectedRun = $this->loadRunForReport($selected->id);
            if (! $selectedRun) {
                return;
            }

            $this->runId = $selectedRun->id;
            $this->selectedTargetRunId = $selectedRun->id;
            $this->hydrateReportFromRun($selectedRun);
            $this->tab = 'timeline';

            $ignored = data_get($selectedRun->report, '_warnings.ignored_bilhetagens', []);
            $this->runWarnings = is_array($ignored) ? $ignored : [];
        }

        return;

        $runIds = array_values(array_unique(array_filter(array_map(
            fn ($row) => (int) ($row['id'] ?? 0),
            $this->targetRuns
        ))));

        if (count($runIds) === 0) {
            $runIds = [$this->runId];
        }

        $runs = AnaliseRun::whereIn('id', $runIds)
            ->get(['id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at'])
            ->keyBy('id');
        if ($runs->isEmpty()) return;

        $processedRuns = 0;
        foreach ($runs as $run) {
            if ($run->status === 'running') {
                app(RunStepper::class)->step($run, $this->chunkSize, 0.0);
                $run->refresh();
                $processedRuns++;
            }

            if ($processedRuns >= 3) {
                break;
            }
        }

        $runs = AnaliseRun::whereIn('id', $runIds)
            ->get(['id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at']);
        $this->targetRuns = $this->formatTargetRuns($runs->all());

        $this->progress = (int) floor(array_sum(array_column($this->targetRuns, 'progress')) / max(1, count($this->targetRuns)));
        $this->running = $runs->contains(fn (AnaliseRun $run) => in_array((string) $run->status, ['queued', 'running'], true));

        $selected = $this->loadRunLight($this->selectedTargetRunId ?: $this->runId);
        if ($selected && $this->isRunCompleted($selected) && ($this->report === null || $this->runId !== $selected->id)) {
            $selectedRun = $this->loadRunForReport($selected->id);
            if (! $selectedRun) {
                return;
            }

            $this->runId = $selectedRun->id;
            $this->selectedTargetRunId = $selectedRun->id;
            $this->hydrateReportFromRun($selectedRun);
            $this->tab = 'timeline';

            $ignored = data_get($selectedRun->report, '_warnings.ignored_bilhetagens', []);
            $this->runWarnings = is_array($ignored) ? $ignored : [];

            if (is_array($ignored) && count($ignored) > 0) {
                $lines = [];
                foreach ($ignored as $w) {
                    $lines[] = "Arquivo: " . ($w['arquivo'] ?? '-') .
                        " | Alvo do arquivo: " . ($w['alvo_arquivo'] ?? '-') .
                        " | Alvo do relatório: " . ($w['alvo_relatorio'] ?? '-');
                }

                Notification::make()
                    ->title('Bilhetagem ignorada: alvo diferente do log')
                    ->body(implode("\n", $lines))
                    ->warning()
                    ->send();
            }
        }

        if (! $this->running && $this->report !== null) {
            Notification::make()->title('Relatórios prontos')->success()->send();
        }
    }

    public function selectTargetReport(int $runId): void
    {
        $run = $this->loadRunLight($runId);
        if (! $run) {
            Notification::make()->title('Relatório do alvo não encontrado')->danger()->send();
            return;
        }

        $this->runId = $run->id;
        $this->selectedTargetRunId = $run->id;
        $this->progress = (int) $run->progress;
        $this->tab = 'timeline';
        $this->runWarnings = [];

        if (! $this->isRunCompleted($run)) {
            $this->report = null;
            Notification::make()->title('Este alvo ainda está processando')->warning()->send();
            return;
        }

        $fullRun = $this->loadRunForReport($run->id);
        if (! $fullRun) {
            Notification::make()->title('Relatório do alvo não encontrado')->danger()->send();
            return;
        }

        $this->runWarnings = data_get($fullRun->report, '_warnings.ignored_bilhetagens', []) ?: [];
        $this->hydrateReportFromRun($fullRun, 'timeline');
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

        $run = $this->loadRunForReport($this->runId);
        if ($run && $this->isRunCompleted($run)) {
            $this->hydrateReportFromRun($run, $tab);
        }
    }

    protected function formatTargetRuns(array $runs): array
    {
        return array_values(array_map(function (AnaliseRun $run): array {
            return [
                'id' => $run->id,
                'target' => $run->target ?: 'Alvo não identificado',
                'status' => $run->status,
                'is_completed' => $this->isRunCompleted($run),
                'progress' => (int) $run->progress,
                'total_ips' => isset($run->events_count) ? (int) $run->events_count : $run->events()->count(),
                'unique_ips' => (int) $run->total_unique_ips,
                'created_at' => $run->created_at?->format('d/m/Y H:i:s'),
            ];
        }, $runs));
    }

    public function getConsolidatedTargetGroups(): array
    {
        if (count($this->targetRuns) < 2) {
            return [];
        }

        $groups = [];
        foreach ($this->targetRuns as $run) {
            $key = $this->normalizeTargetForDuplicateCheck((string) ($run['target'] ?? '')) ?? ($run['target'] ?? '');
            $groups[$key] ??= ['target' => $run['target'], 'runs' => [], 'total_ips' => 0, 'unique_ips' => 0];
            $groups[$key]['runs'][] = $run['id'];
            $groups[$key]['total_ips'] += (int) ($run['total_ips'] ?? 0);
            $groups[$key]['unique_ips'] += (int) ($run['unique_ips'] ?? 0);
        }

        return array_values(array_filter($groups, fn ($g) => count($g['runs']) > 1));
    }

    protected function isRunCompleted(AnaliseRun $run): bool
    {
        return (string) $run->status === 'done' || (int) $run->progress >= 100;
    }

    public function setVinculoPage(int $page): void
    {
        $this->vinculoPage = max(1, $page);
    }

    protected function findDuplicateTargetsForInvestigation(AnaliseInvestigation $investigation, array $groupsWithIpLog): array
    {
        $existing = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->get(['target'])
            ->mapWithKeys(function (AnaliseRun $run): array {
                $raw = $run->target;
                $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);

                return $key ? [$key => (string) $raw] : [];
            })
            ->all();

        if (count($existing) === 0) {
            return [];
        }

        $duplicates = [];
        foreach ($groupsWithIpLog as $groupKey => $items) {
            $parsed = (array) data_get($items, '0.parsed', []);
            $raw = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);
            $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);

            if ($key && isset($existing[$key])) {
                $duplicates[(string) $groupKey] = is_string($raw) && trim($raw) !== '' ? trim($raw) : $existing[$key];
            }
        }

        return array_unique($duplicates);
    }

    protected function importBilhetagemOnlyGroupsIntoInvestigation(AnaliseInvestigation $investigation, array $groups): array
    {
        $runs = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->get(['id', 'investigation_id', 'target'])
            ;

        $runsByTarget = [];
        foreach ($runs as $run) {
            $raw = $run->target;
            $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);
            if ($key) {
                $runsByTarget[$key] = $run;
            }
        }

        $inserted = 0;
        $matchedTargets = 0;
        $missingTargets = [];

        foreach ($groups as $items) {
            $parsed = (array) data_get($items, '0.parsed', []);
            $raw = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);
            $key = $this->normalizeTargetForDuplicateCheck(is_string($raw) ? $raw : null);

            if (! $key || ! isset($runsByTarget[$key])) {
                $missingTargets[] = is_string($raw) && trim($raw) !== '' ? trim($raw) : 'Alvo não identificado';
                continue;
            }

            $matchedTargets++;

            foreach ($items as $item) {
                $p = (array) ($item['parsed'] ?? []);
                $inserted += $this->insertBilhetagemMessages($runsByTarget[$key], $p['message_log'] ?? []);
            }

            Cache::forget($this->reportCacheKey($runsByTarget[$key], 'bilhetagem'));
        }

        return [
            'inserted' => $inserted,
            'matched_targets' => $matchedTargets,
            'missing_targets' => array_values(array_unique($missingTargets)),
        ];
    }

    protected function isTempDiskCriticallyFull(): bool
    {
        $free = @disk_free_space(sys_get_temp_dir());

        return is_int($free) || is_float($free)
            ? $free < 256 * 1024 * 1024
            : false;
    }

    // =========================================================
    // ✅ Contatos (Simétricos / Assimétricos) - Modal (CORRIGIDO)
    // =========================================================
    public function openContactsModal(string $type): void
    {
        $type = trim($type);

        if (! in_array($type, ['simetricos', 'assimetricos'], true)) {
            Notification::make()->title('Tipo de contato inválido')->danger()->send();
            return;
        }

        $this->selectedContactType = $type;
        $this->selectedContacts = $this->loadContactsForType($type);

        $this->mountAction('contactsModal');
    }

    public function contactsModal(): Action
    {
        return Action::make('contactsModal')
            ->label('Contatos')
            ->modalHeading(fn () => $this->selectedContactType === 'simetricos'
                ? 'Contatos Simétricos'
                : 'Contatos Assimétricos'
            )
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn () => view('filament.pages.partials.modal-contacts', [
                'contacts' => $this->selectedContacts,
                'type' => $this->selectedContactType,
            ]));
    }

    // =========================================================
    // ✅ Provedor -> Modal IPs (caso seu front dispare)
    // =========================================================
    #[On('open-provider-ips-modal')]
    public function openProviderIpsModal(string $provider): void
    {
        $provider = trim((string) $provider);
        $this->selectedProvider = $provider !== '' ? $provider : 'Desconhecido';
        $this->selectedProviderIps = $this->report['provider_ip_map'][$this->selectedProvider] ?? [];

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

    #[On('open-burst-modal')]
    public function openBurstModal(string $burstHour): void
    {
        $this->burstHour = $burstHour;

        $run = $this->loadRunForReport($this->selectedTargetRunId ?: $this->runId);
        if (! $run) {
            return;
        }

        [$date, $hour] = explode(' ', $burstHour);
        $start = \Carbon\Carbon::createFromFormat('Y-m-d H', $burstHour, 'UTC');
        $end   = $start->copy()->addHour();

        $this->burstModalRows = AnaliseRunEvent::query()
            ->with('ipEnrichment')
            ->where('analise_run_id', $run->id)
            ->where('event_type', 'access')
            ->whereBetween('occurred_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (AnaliseRunEvent $e): array => [
                'datetime' => $e->occurred_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s'),
                'ip'       => $e->ip ?? '-',
                'port'     => $e->logical_port ?? '-',
                'provider' => $e->provider_label,
                'city'     => $e->city_label,
                'type'     => $e->connection_type,
            ])
            ->all();

        $this->mountAction('burstModal');
    }

    public function burstModal(): Action
    {
        return Action::make('burstModal')
            ->label('Detalhes do Burst')
            ->modalHeading(fn (): string => 'Burst de acessos — ' . (
                $this->burstHour
                    ? \Carbon\Carbon::createFromFormat('Y-m-d H', $this->burstHour, 'UTC')
                        ->setTimezone('America/Sao_Paulo')
                        ->format('d/m/Y H:i') . 'h (GMT-3)'
                    : '-'
            ))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->after(fn () => $this->burstHour = null)
            ->modalContent(fn () => view('filament.pages.partials.modal-burst', [
                'rows' => $this->burstModalRows,
            ]));
    }

    public function openVinculoTimesModal(string $ip, string $target): void
    {
        if (! $this->runId) {
            Notification::make()->title('Run inválido')->danger()->send();
            return;
        }

        $run = $this->loadRunForReport($this->runId);
        if (! $run) {
            Notification::make()->title('Relatório não encontrado')->danger()->send();
            return;
        }

        foreach ($this->buildVinculoRows($run) as $row) {
            if (($row['ip'] ?? null) !== $ip) continue;

            foreach (($row['accesses'] ?? []) as $access) {
                if (($access['target'] ?? null) !== $target) continue;

                    $this->vinculoModalIp = $ip;
                    $this->vinculoModalTarget = $target;
                    $this->vinculoModalTimes = $access['times'] ?? [];
                    $this->mountAction('vinculoTimesModal');
                    return;
            }
        }

        Notification::make()->title('Horários não encontrados para este alvo')->warning()->send();
    }

    public function vinculoTimesModal(): Action
    {
        return Action::make('vinculoTimesModal')
            ->label('Horários')
            ->modalHeading(fn () => 'Horários - ' . ($this->vinculoModalTarget ?? '-') . ' / ' . ($this->vinculoModalIp ?? '-'))
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn () => view('filament.pages.partials.modal-vinculo-times', [
                'ip' => $this->vinculoModalIp,
                'target' => $this->vinculoModalTarget,
                'times' => $this->vinculoModalTimes,
            ]))
            ->after(function () {
                $this->vinculoModalIp = null;
                $this->vinculoModalTarget = null;
                $this->vinculoModalTimes = [];
            });
    }

    // =========================================================
    // LIMPAR
    // =========================================================
    public function limpar(): void
    {
        if ($this->running) return;

        $this->runId = null;
        $this->progress = 0;
        $this->running = false;
        $this->report = null;
        $this->targetRuns = [];
        $this->selectedTargetRunId = null;
        $this->runWarnings = [];

        $this->tab = 'timeline';

        $this->selectedContactType = null;
        $this->selectedContacts = [];
        $this->selectedProvider = null;
        $this->selectedProviderIps = [];

        $this->contactNames = [];
        $this->selectedContactPhone = null;
        $this->pendingUploadCacheKey = null;
        $this->pendingDuplicateTargetKeys = [];
        $this->pendingDuplicateTargetLabels = [];
        $this->awaitingRunCreationUntil = null;
        $this->awaitingRunCreationBaseCount = null;

        $this->resetBilhetagemModalState();

        $this->form->fill();
    }

    protected function storePendingUploadGroups(array $groups): string
    {
        $cacheKey = 'wpp-pending-upload:' . Str::uuid();
        Cache::put($cacheKey, $groups, now()->addMinutes(30));

        return $cacheKey;
    }

    protected function pullPendingUploadGroups(): array
    {
        $cacheKey = $this->pendingUploadCacheKey;
        if (! is_string($cacheKey) || trim($cacheKey) === '') {
            return [];
        }

        $groups = Cache::pull($cacheKey, []);
        $this->pendingUploadCacheKey = null;

        return is_array($groups) ? $groups : [];
    }

    // =========================================================
    // Upload bilhetagem (Action)
    // =========================================================
    public function bilhetagemUpload(): Action
    {
        return Action::make('bilhetagemUpload')
            ->label('Upload bilhetagem')
            ->modalHeading('Enviar arquivo de bilhetagem (ZIP/HTML)')
            ->modalSubmitActionLabel('Importar')
            ->form([
                FileUpload::make('bilhetagem_file')
                    ->label('Arquivo (ZIP/HTML)')
                    ->required()
                    ->multiple()
                    ->disk('public')
                    ->directory('uploads/records-html-bilhetagem')
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
            ->action(function (array $data) {
                $this->importarSomenteBilhetagem($data['bilhetagem_file'] ?? []);
            });
    }

    protected function importarSomenteBilhetagem(array|string|null $storedPaths): void
    {
        if (! $this->runId) {
            Notification::make()->title('Você precisa gerar um relatório antes.')->danger()->send();
            return;
        }

        $run = $this->loadRunLight($this->runId);
        if (! $run) {
            Notification::make()->title('Run não encontrado.')->danger()->send();
            return;
        }

        if (is_string($storedPaths)) $storedPaths = [$storedPaths];
        if (! is_array($storedPaths) || count($storedPaths) === 0) {
            Notification::make()->title('Envie pelo menos 1 arquivo.')->danger()->send();
            return;
        }

        $disk = Storage::disk('public');
        $runTargetRaw = $run->target;

        foreach ($storedPaths as $storedPath) {
            if (! $storedPath || ! $disk->exists($storedPath)) continue;

            $html = $this->resolveHtmlFromUpload($storedPath);
            if (! is_string($html) || trim($html) === '') {
                Notification::make()->title('Arquivo inválido (não foi possível ler HTML/ZIP).')->danger()->send();
                return;
            }

            $parsed = (new RecordsHtmlParser())->parse($html);
            $fileTargetRaw = $parsed['target'] ?? ($parsed['account_identifier'] ?? null);

            if (! $this->targetsMatch(is_string($runTargetRaw) ? $runTargetRaw : null, is_string($fileTargetRaw) ? $fileTargetRaw : null)) {
                Notification::make()
                    ->title('Bilhetagem não pertence ao alvo deste relatório')
                    ->body("Alvo do relatório: {$runTargetRaw}\nAlvo do arquivo: {$fileTargetRaw}")
                    ->danger()
                    ->send();
                return;
            }
        }

        $inserted = 0;

        foreach ($storedPaths as $storedPath) {
            if (! $storedPath || ! $disk->exists($storedPath)) continue;

            $html = $this->resolveHtmlFromUpload($storedPath);
            if (! is_string($html) || trim($html) === '') continue;

            $parser = new RecordsHtmlParser();
            $messageLog = $parser->parseBilhetagemOnly($html);
            $inserted += $this->insertBilhetagemMessages($run, $messageLog ?? []);
        }

        Cache::forget($this->reportCacheKey($run, 'bilhetagem'));

        $this->tab = 'bilhetagem';
        $fullRun = $this->loadRunForReport($run->id);
        if ($fullRun) {
            $this->hydrateReportFromRun($fullRun, 'bilhetagem');
        }

        Notification::make()
            ->title("Bilhetagem importada: {$inserted} registros novos")
            ->success()
            ->send();
    }

    protected function insertBilhetagemMessages(AnaliseRun $run, array $messageLog): int
    {
        $seen = [];
        $inserted = 0;

        foreach ($messageLog as $m) {
            $recipient = trim((string) ($m['recipient'] ?? ''));
            if ($recipient === '') continue;

            $tsUtc = $m['timestamp_utc'] ?? null;
            $tsKey = $tsUtc instanceof Carbon ? $tsUtc->format('Y-m-d H:i:s') : '-';

            $messageId = trim((string) ($m['message_id'] ?? ''));
            $key = $recipient . '|' . ($messageId !== '' ? $messageId : '-') . '|' . $tsKey;

            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $alreadyExists = Bilhetagem::query()
                ->where('analise_run_id', $run->id)
                ->where('recipient', $recipient)
                ->where('message_id', $messageId !== '' ? $messageId : null)
                ->where('timestamp_utc', $tsUtc instanceof Carbon ? $tsUtc : null)
                ->exists();

            if ($alreadyExists) continue;

            Bilhetagem::create([
                'analise_run_id' => $run->id,
                'timestamp_utc' => $tsUtc instanceof Carbon ? $tsUtc : null,
                'message_id' => $messageId !== '' ? $messageId : null,
                'sender' => $m['sender'] ?? null,
                'recipient' => $recipient,
                'sender_ip' => $m['sender_ip'] ?? null,
                'sender_port' => $m['sender_port'] ?? null,
                'type' => $m['type'] ?? null,
            ]);

            $inserted++;
        }

        return $inserted;
    }

    // =========================================================
    // Modal mensagens bilhetagem
    // =========================================================
    public function openBilhetagemMessagesModal(string $phone): void
    {
        if (! $this->runId) {
            Notification::make()->title('Run inválido')->danger()->send();
            return;
        }

        $raw = trim($phone);
        $k = $this->normalizePhoneKey($raw);
        if (! $k) {
            Notification::make()->title('Contato inválido')->danger()->send();
            return;
        }

        $this->bilhetagemModalPhone = $k;
        $this->bilhetagemModalPhoneRaw = $raw;
        $this->bilhetagemModalPage = 1;

        $this->loadBilhetagemModalPage();
        $this->mountAction('bilhetagemMessagesModal');
    }

    public function bilhetagemMessagesModal(): Action
    {
        return Action::make('bilhetagemMessagesModal')
            ->label('Mensagens')
            ->modalHeading(fn () => 'Mensagens do contato: ' . ($this->bilhetagemModalPhoneRaw ?? $this->bilhetagemModalPhone ?? '-'))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->modalContent(fn () => view('filament.pages.partials.modal-bilhetagem-messages', [
                'phone' => $this->bilhetagemModalPhoneRaw ?? $this->bilhetagemModalPhone,
                'rows' => $this->bilhetagemModalRows,
                'page' => $this->bilhetagemModalPage,
                'perPage' => $this->bilhetagemModalPerPage,
                'total' => $this->bilhetagemModalTotal,
                'lastPage' => $this->bilhetagemModalTotal > 0
                    ? (int) ceil($this->bilhetagemModalTotal / $this->bilhetagemModalPerPage)
                    : 1,
                'contactName' => $this->contactNames[$this->bilhetagemModalPhone]
                    ?? ($this->bilhetagemModalPhoneRaw ?? $this->bilhetagemModalPhone ?? 'Desconhecido'),
            ]))
            ->after(fn () => $this->resetBilhetagemModalState());
    }

    public function bilhetagemModalNextPage(): void
    {
        $last = $this->bilhetagemModalTotal > 0
            ? (int) ceil($this->bilhetagemModalTotal / $this->bilhetagemModalPerPage)
            : 1;

        if ($this->bilhetagemModalPage < $last) {
            $this->bilhetagemModalPage++;
            $this->loadBilhetagemModalPage();
        }
    }

    public function bilhetagemModalPrevPage(): void
    {
        if ($this->bilhetagemModalPage > 1) {
            $this->bilhetagemModalPage--;
            $this->loadBilhetagemModalPage();
        }
    }

    protected function loadBilhetagemModalPage(): void
    {
        if (! $this->runId || ! $this->bilhetagemModalPhone) {
            $this->bilhetagemModalRows = [];
            $this->bilhetagemModalTotal = 0;
            return;
        }

        $this->loadContactNames($this->runId);

        $candidates = array_values(array_unique(array_filter([
            $this->bilhetagemModalPhoneRaw,
            $this->bilhetagemModalPhone,
            '+' . $this->bilhetagemModalPhone,
        ], fn ($v) => is_string($v) && trim($v) !== '')));

        $q = Bilhetagem::query()
            ->where('analise_run_id', $this->runId)
            ->whereIn('recipient', $candidates);

        $this->bilhetagemModalTotal = (int) (clone $q)->count();

        $offset = ($this->bilhetagemModalPage - 1) * $this->bilhetagemModalPerPage;

        $rows = $q->orderByDesc('timestamp_utc')
            ->skip($offset)
            ->take($this->bilhetagemModalPerPage)
            ->get(['timestamp_utc', 'message_id', 'sender_ip', 'sender_port', 'type']);

        $pageIps = $rows
            ->pluck('sender_ip')
            ->map(fn ($ip) => $this->extractIpBase(is_string($ip) ? $ip : null))
            ->filter(fn ($ip) => is_string($ip) && trim($ip) !== '')
            ->unique()
            ->values()
            ->all();

        $enrs = count($pageIps) > 0
            ? IpEnrichment::whereIn('ip', $pageIps)->get()->keyBy('ip')
            : collect();

        // ✅ Brasília
        $tz = 'America/Sao_Paulo';

        $this->bilhetagemModalRows = $rows->map(function (Bilhetagem $b) use ($tz, $enrs) {
            $ipBase = $this->extractIpBase($b->sender_ip);

            $prov = null;
            if ($ipBase && $enrs->has($ipBase)) {
                $en = $enrs->get($ipBase);
                $prov = trim(($en?->isp ?? '') ?: ($en?->org ?? ''));
                $prov = preg_replace('/\s+/u', ' ', $prov ?? '') ?? '';
                if ($prov === '') $prov = null;
            }

            return [
                // ✅ formato BR
                'timestamp' => $b->timestamp_utc ? $b->timestamp_utc->copy()->setTimezone($tz)->format('d/m/Y H:i:s ') : null,
                'sender_ip' => $b->sender_ip,
                'sender_port' => $b->sender_port,
                'sender_provider' => $prov ?: 'Desconhecido',
                'type' => $b->type,
                'message_id' => $b->message_id,
            ];
        })->values()->all();
    }

    protected function resetBilhetagemModalState(): void
    {
        $this->bilhetagemModalPhone = null;
        $this->bilhetagemModalPhoneRaw = null;
        $this->bilhetagemModalPage = 1;
        $this->bilhetagemModalTotal = 0;
        $this->bilhetagemModalRows = [];
    }

    // =========================================================
    // Nome do contato (modal)
    // =========================================================
    public function openContactNameModal(string $phone): void
    {
        $k = $this->normalizePhoneKey($phone);
        if (! $k) {
            Notification::make()->title('Contato inválido')->danger()->send();
            return;
        }

        $this->selectedContactPhone = $k;
        $this->mountAction('contactNameModal');
    }

    public function contactNameModal(): Action
    {
        return Action::make('contactNameModal')
            ->label('Editar nome')
            ->modalHeading('Editar nome do contato')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Salvar')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                TextInput::make('name')
                    ->label('Nome')
                    ->maxLength(120)
                    ->helperText('Deixe em branco para remover o nome salvo.')
                    ->default(function () {
                        $k = $this->selectedContactPhone;
                        $current = $k ? ($this->contactNames[$k] ?? null) : null;
                        return ($current && $current !== 'Desconhecido') ? $current : '';
                    }),
            ])
            ->action(function (array $data) {
                if (! $this->runId || ! $this->selectedContactPhone) {
                    Notification::make()->title('Run/Contato inválido')->danger()->send();
                    return;
                }

                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    AnaliseRunContact::query()
                        ->where('analise_run_id', $this->runId)
                        ->where('phone', $this->selectedContactPhone)
                        ->delete();

                    $this->loadContactNames($this->runId);

                    $run = $this->loadRunForReport($this->runId);
                    if ($run) {
                        $this->tab = 'bilhetagem';
                        $this->hydrateReportFromRun($run, 'bilhetagem');
                    }

                    Notification::make()->title('Nome removido')->success()->send();
                    return;
                }

                AnaliseRunContact::updateOrCreate(
                    [
                        'analise_run_id' => $this->runId,
                        'phone' => $this->selectedContactPhone,
                    ],
                    [
                        'name' => $name,
                    ]
                );

                $this->loadContactNames($this->runId);

                $run = $this->loadRunForReport($this->runId);
                if ($run) {
                    $this->tab = 'bilhetagem';
                    $this->hydrateReportFromRun($run, 'bilhetagem');
                }

                Notification::make()->title('Nome salvo')->success()->send();
            });
    }

    protected function loadContactNames(int $runId): void
    {
        $this->contactNames = AnaliseRunContact::query()
            ->where('analise_run_id', $runId)
            ->pluck('name', 'phone')
            ->toArray();
    }

    // =========================================================
    // Hydrate report (puxa message_log do banco + garante enrichment)
    // =========================================================
    protected function hydrateReportFromRun(AnaliseRun $run, ?string $activeTab = null): void
    {
        $this->loadContactNames($run->id);

        $activeTab = $activeTab ?? $this->tab;

        $report = $activeTab === 'vinculo'
            ? $this->buildReportFromRun($run, $activeTab)
            : Cache::remember(
                $this->reportCacheKey($run, $activeTab),
                now()->addHour(),
                fn () => $this->buildReportFromRun($run, $activeTab)
            );

        if (! is_array($report)) {
            return;
        }

        // injeta contact_name fora do cache para edição/remoção aparecer sem reconstruir o relatório.
        if (isset($report['bilhetagem_cards']) && is_array($report['bilhetagem_cards'])) {
            foreach ($report['bilhetagem_cards'] as &$card) {
                $k = $this->normalizePhoneKey((string) ($card['recipient'] ?? ''));
                $card['contact_name'] = ($k && isset($this->contactNames[$k]))
                    ? $this->contactNames[$k]
                    : (string) ($card['recipient'] ?? '');
            }
            unset($card);
        }

        if ($activeTab !== 'vinculo') {
            $report['_counts']['vinculo'] = $this->countVinculoRows($run);
        }

        if ($activeTab !== 'bilhetagem') {
            $report['_counts']['bilhetagem'] = $this->countBilhetagemCards($run);
        }

        $this->report = $this->filterReportForActiveTab($report, $activeTab);
    }

    protected function buildReportFromRun(AnaliseRun $run, string $activeTab): ?array
    {
        $parsed = $this->loadParsedPayload($run);
        if (! is_array($parsed)) {
            $parsed = $this->buildParsedPayloadFromDatabase($run);
        }

        if (! is_array($parsed)) {
            return $this->existingReportWithSheets($run);
        }

        if ($this->ensureRunIpsForBilhetagem($run) > 0) {
            EnrichRunIpsJob::dispatch($run->id);
        }

        $parsed['message_log'] = [];

        $ips = AnaliseRunIp::where('analise_run_id', $run->id)->pluck('ip')->all();
        $enrs = collect();
        foreach (array_chunk($ips, 500) as $ipChunk) {
            $enrs = $enrs->merge(IpEnrichment::whereIn('ip', $ipChunk)->get()->keyBy('ip'));
        }

        $enrichedByIp = [];
        foreach ($ips as $ip) {
            $en = $enrs->get($ip);
            $enrichedByIp[$ip] = [
                'ip' => $ip,
                'city' => $en?->city,
                'isp' => $en?->isp,
                'org' => $en?->org,
                'mobile' => $en?->mobile,
            ];
        }

        $report = (new ReportAggregator())->buildReport($parsed, $enrichedByIp);
        $report['bilhetagem_cards'] = $activeTab === 'bilhetagem'
            ? $this->buildBilhetagemCardsFromDb($run, $parsed)
            : [];
        $report['vinculo_rows'] = $activeTab === 'vinculo'
            ? $this->buildVinculoRows($run)
            : [];

        $this->persistReportSnapshot($run, $report);

        return $report;
    }

    protected function persistReportSnapshot(AnaliseRun $run, array $report): void
    {
        $existing = is_array($run->report) ? $run->report : [];

        $snapshot = array_merge($existing, array_filter([
            'timeline_rows'      => $report['timeline_rows'] ?? null,
            'unique_ip_rows'     => $report['unique_ip_rows'] ?? null,
            'provider_stats_rows'=> $report['provider_stats_rows'] ?? null,
            'city_stats_rows'    => $report['city_stats_rows'] ?? null,
            'night_events_rows'  => $report['night_events_rows'] ?? null,
            'mobile_events_rows' => $report['mobile_events_rows'] ?? null,
            'groups_rows'        => $report['groups_rows'] ?? null,
            'connection_summary' => $report['connection_summary'] ?? null,
            'period_label'       => $report['period_label'] ?? null,
            'total_ips'          => $report['total_ips'] ?? null,
            'night_total_events' => $report['night_total_events'] ?? null,
            'mobile_total_events'=> $report['mobile_total_events'] ?? null,
            '_cached_at'         => now()->toIso8601String(),
        ], fn ($v) => $v !== null));

        $run->forceFill(['report' => $snapshot])->save();
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
            'device' => data_get($summary, 'device'),
            'device_build' => data_get($summary, 'device_build'),
            'registered_emails' => array_values((array) data_get($summary, 'registered_emails', [])),
            'symmetric_contacts' => [],
            'asymmetric_contacts' => [],
            'symmetric_contacts_count' => (int) data_get($summary, 'symmetric_contacts_count', 0),
            'asymmetric_contacts_count' => (int) data_get($summary, 'asymmetric_contacts_count', 0),
            'connection_info' => (array) data_get($summary, 'connection_info', []),
            'groups' => array_values((array) data_get($summary, 'groups_rows', [])),
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
            'message_log' => [],
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
            'groups_rows',
            'bilhetagem_cards',
            'vinculo_rows',
        ];

        foreach ($sheetKeys as $key) {
            if (array_key_exists($key, $report)) {
                return $report;
            }
        }

        return null;
    }

    protected function buildBilhetagemCardsFromDb(AnaliseRun $run, array $parsed): array
    {
        $agendaPhones = [];
        foreach (array_merge($parsed['symmetric_contacts'] ?? [], $parsed['asymmetric_contacts'] ?? []) as $phone) {
            $agendaPhones[(string) $phone] = true;
        }

        $summaryRows = Bilhetagem::query()
            ->where('analise_run_id', $run->id)
            ->select('recipient')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('recipient')
            ->groupBy('recipient')
            ->orderByDesc('total')
            ->limit(500)
            ->get();

        if ($summaryRows->isEmpty()) {
            return [];
        }

        $recipients = $summaryRows
            ->pluck('recipient')
            ->map(fn ($recipient) => trim((string) $recipient))
            ->filter(fn ($recipient) => $recipient !== '')
            ->values()
            ->all();

        $rankedLatestRows = DB::table('bilhetagens as b')
            ->select([
                'b.recipient',
                'b.timestamp_utc',
                'b.message_id',
                'b.sender_ip',
                'b.sender_port',
                'b.type',
            ])
            ->where('b.analise_run_id', $run->id)
            ->whereIn('b.recipient', $recipients)
            ->whereRaw(
                'b.id = (
                    select b2.id
                    from bilhetagens as b2
                    where b2.analise_run_id = b.analise_run_id
                        and b2.recipient = b.recipient
                    order by b2.timestamp_utc desc, b2.id desc
                    limit 1
                )'
            )
            ->get()
            ->keyBy('recipient');

        $cards = [];
        $latestIps = [];

        foreach ($summaryRows as $summary) {
            $recipient = trim((string) $summary->recipient);
            if ($recipient === '') continue;

            $latest = $rankedLatestRows->get($recipient);

            $latestRow = null;
            if ($latest) {
                $ipBase = $this->extractIpBase(is_string($latest->sender_ip) ? $latest->sender_ip : null);
                if ($ipBase) {
                    $latestIps[$ipBase] = true;
                }

                $timestamp = null;
                if (! empty($latest->timestamp_utc)) {
                    try {
                        $timestamp = Carbon::parse($latest->timestamp_utc, 'UTC')
                            ->setTimezone('America/Sao_Paulo')
                            ->format('d/m/Y H:i:s');
                    } catch (\Throwable) {
                        $timestamp = null;
                    }
                }

                $latestRow = [
                    'timestamp' => $timestamp,
                    'sender_ip' => $latest->sender_ip,
                    'sender_port' => $latest->sender_port,
                    'sender_provider' => 'Desconhecido',
                    'type' => $latest->type,
                    'message_id' => $latest->message_id,
                ];
            }

            $cards[] = [
                'recipient' => $recipient,
                'in_agenda' => isset($agendaPhones[$recipient]),
                'total' => (int) $summary->total,
                'latest' => $latestRow,
                'others' => [],
            ];
        }

        $enrichments = count($latestIps) > 0
            ? IpEnrichment::whereIn('ip', array_keys($latestIps))->get()->keyBy('ip')
            : collect();

        foreach ($cards as &$card) {
            $ipBase = $this->extractIpBase(data_get($card, 'latest.sender_ip'));
            $provider = $ipBase ? $this->resolveProviderFromEnrichment($enrichments->get($ipBase)) : null;

            if ($provider !== null) {
                $card['latest']['sender_provider'] = $provider;
            }
        }
        unset($card);

        return $cards;
    }

    protected function resolveProviderFromEnrichment(mixed $enrichment): ?string
    {
        if (! $enrichment) {
            return null;
        }

        $provider = trim((string) (($enrichment?->isp ?? '') ?: ($enrichment?->org ?? '')));
        $provider = preg_replace('/\s+/u', ' ', $provider ?? '') ?? '';

        return $provider !== '' ? $provider : null;
    }

    protected function countBilhetagemCards(AnaliseRun $run): int
    {
        return (int) Bilhetagem::query()
            ->where('analise_run_id', $run->id)
            ->whereNotNull('recipient')
            ->distinct('recipient')
            ->count('recipient');
    }

    protected function buildVinculoRows(AnaliseRun $currentRun): array
    {
        if (! $currentRun->investigation_id) {
            return [];
        }

        $currentParsed = $this->loadParsedPayload($currentRun) ?? [];
        if (! is_array($currentParsed)) {
            return [];
        }

        $currentIps = $this->extractConnectionIpsForVinculo($currentParsed);
        if (count($currentIps) === 0) {
            return [];
        }

        $otherRuns = AnaliseRun::query()
            ->where('investigation_id', $currentRun->investigation_id)
            ->whereKeyNot($currentRun->id)
            ->get();

        if ($otherRuns->isEmpty()) {
            return [];
        }

        $byIp = [];

        foreach ($otherRuns as $run) {
            $parsed = $this->loadParsedPayload($run) ?? [];
            if (! is_array($parsed)) {
                continue;
            }

            $otherIps = $this->extractConnectionIpsForVinculo($parsed);
            if (count($otherIps) === 0) {
                continue;
            }

            $sharedIps = array_intersect(array_keys($currentIps), array_keys($otherIps));
            if (count($sharedIps) === 0) {
                continue;
            }

            $target = $run->target ?: ('Run ' . $run->id);

            foreach ($sharedIps as $ip) {
                $currentMeta = $currentIps[$ip] ?? null;
                $otherMeta = $otherIps[$ip] ?? null;

                if (! is_array($currentMeta) || ! is_array($otherMeta)) {
                    continue;
                }

                $byIp[$ip] ??= [
                    'ip' => $ip,
                    'accesses' => [
                        [
                            'run_id' => $currentRun->id,
                            'target' => (string) ($currentRun->target ?: ('Run ' . $currentRun->id)),
                            'count' => (int) ($currentMeta['occurrences'] ?? 0),
                            'first_seen' => $this->formatCarbonForVinculo($currentMeta['first_seen_at'] ?? null),
                            'last_seen' => $this->formatCarbonForVinculo($currentMeta['last_seen_at'] ?? null),
                            'times' => $currentMeta['times'] ?? [],
                            'status' => $currentRun->status,
                            'progress' => (int) $currentRun->progress,
                            'is_selected' => true,
                        ],
                    ],
                    'targets_count' => 1,
                    'total_occurrences' => (int) ($currentMeta['occurrences'] ?? 0),
                    'last_seen_at' => null,
                ];

                $byIp[$ip]['accesses'][] = [
                    'run_id' => $run->id,
                    'target' => (string) $target,
                    'count' => (int) ($otherMeta['occurrences'] ?? 0),
                    'first_seen' => $this->formatCarbonForVinculo($otherMeta['first_seen_at'] ?? null),
                    'last_seen' => $this->formatCarbonForVinculo($otherMeta['last_seen_at'] ?? null),
                    'times' => $otherMeta['times'] ?? [],
                    'status' => $run->status,
                    'progress' => (int) $run->progress,
                    'is_selected' => false,
                ];
                $byIp[$ip]['targets_count']++;
                $byIp[$ip]['total_occurrences'] += (int) ($otherMeta['occurrences'] ?? 0);

                $lastSeenCandidates = array_filter([
                    $currentMeta['last_seen_at'] ?? null,
                    $otherMeta['last_seen_at'] ?? null,
                ], fn ($value) => $value instanceof Carbon);

                foreach ($lastSeenCandidates as $lastSeenAt) {
                    if (
                        $byIp[$ip]['last_seen_at'] === null ||
                        $lastSeenAt->greaterThan($byIp[$ip]['last_seen_at'])
                    ) {
                        $byIp[$ip]['last_seen_at'] = $lastSeenAt;
                    }
                }
            }
        }

        $sharedIps = array_keys($byIp);
        if (count($sharedIps) === 0) {
            return [];
        }

        $enrichments = IpEnrichment::whereIn('ip', $sharedIps)->get()->keyBy('ip');
        $rows = [];

        foreach ($byIp as $ip => $row) {
            $en = $enrichments->get($ip);
            $provider = trim(($en?->isp ?? '') ?: ($en?->org ?? ''));
            $provider = preg_replace('/\s+/u', ' ', $provider ?? '') ?? '';

            usort($row['accesses'], function (array $left, array $right): int {
                $leftSelected = (bool) ($left['is_selected'] ?? false);
                $rightSelected = (bool) ($right['is_selected'] ?? false);

                if ($leftSelected !== $rightSelected) {
                    return $leftSelected ? -1 : 1;
                }

                return strcmp((string) ($left['target'] ?? ''), (string) ($right['target'] ?? ''));
            });

            $rows[] = [
                'ip' => $ip,
                'targets' => implode(' | ', array_map(
                    fn (array $access): string => (string) ($access['target'] ?? ''),
                    $row['accesses'],
                )),
                'targets_count' => (int) ($row['targets_count'] ?? count($row['accesses'])),
                'total_occurrences' => (int) $row['total_occurrences'],
                'last_seen' => $row['last_seen_at'] ? $row['last_seen_at']->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s') : null,
                'provider' => $provider !== '' ? $provider : 'Desconhecido',
                'city' => $en?->city ?: 'Desconhecida',
                'type' => ($en?->mobile ?? false) ? 'Móvel' : 'Residencial',
                'accesses' => $row['accesses'],
            ];
        }

        usort($rows, fn ($a, $b) => ($b['targets_count'] <=> $a['targets_count'])
            ?: ($b['total_occurrences'] <=> $a['total_occurrences'])
            ?: strcmp((string) $a['ip'], (string) $b['ip']));

        return $rows;
    }

    protected function countVinculoRows(AnaliseRun $currentRun): int
    {
        return count($this->buildVinculoRows($currentRun));
    }

    protected function extractConnectionIpsForVinculo(array $parsed): array
    {
        $ips = [];

        foreach (($parsed['ip_events'] ?? []) as $event) {
            $ip = trim((string) ($event['ip'] ?? ''));
            if ($ip === '') continue;

            $lastSeenAt = $this->parseCarbonUtcForVinculo($event['time_utc'] ?? null);

            $ips[$ip] ??= [
                'occurrences' => 0,
                'first_seen_at' => $lastSeenAt,
                'last_seen_at' => $lastSeenAt,
                'times' => [],
            ];

            $ips[$ip]['occurrences']++;
            if ($lastSeenAt instanceof Carbon) {
                $ips[$ip]['times'][] = $lastSeenAt->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$ip]['first_seen_at'] === null ||
                $lastSeenAt->lessThan($ips[$ip]['first_seen_at'])
            )) {
                $ips[$ip]['first_seen_at'] = $lastSeenAt;
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$ip]['last_seen_at'] === null ||
                $lastSeenAt->greaterThan($ips[$ip]['last_seen_at'])
            )) {
                $ips[$ip]['last_seen_at'] = $lastSeenAt;
            }
        }

        $connectionIp = $this->extractIpBase(data_get($parsed, 'connection_info.last_ip'));
        if ($connectionIp) {
            $lastSeenAt = $this->parseCarbonUtcForVinculo(data_get($parsed, 'connection_info.last_seen_utc'));

            $ips[$connectionIp] ??= [
                'occurrences' => 0,
                'first_seen_at' => $lastSeenAt,
                'last_seen_at' => $lastSeenAt,
                'times' => [],
            ];

            if ($lastSeenAt instanceof Carbon) {
                $ips[$connectionIp]['times'][] = $lastSeenAt->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s');
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$connectionIp]['first_seen_at'] === null ||
                $lastSeenAt->lessThan($ips[$connectionIp]['first_seen_at'])
            )) {
                $ips[$connectionIp]['first_seen_at'] = $lastSeenAt;
            }

            if ($lastSeenAt instanceof Carbon && (
                $ips[$connectionIp]['last_seen_at'] === null ||
                $lastSeenAt->greaterThan($ips[$connectionIp]['last_seen_at'])
            )) {
                $ips[$connectionIp]['last_seen_at'] = $lastSeenAt;
            }
        }

        return $ips;
    }

    protected function formatCarbonForVinculo(mixed $value): ?string
    {
        return $value instanceof Carbon
            ? $value->copy()->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i:s')
            : null;
    }

    protected function parseCarbonUtcForVinculo(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone('UTC');
        }

        if (is_int($value)) {
            return Carbon::createFromTimestamp($value, 'UTC');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value, 'UTC')->setTimezone('UTC');
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
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
            'groups' => count($report['groups_rows'] ?? []),
            'bilhetagem' => (int) data_get($report, '_counts.bilhetagem', count($report['bilhetagem_cards'] ?? [])),
            'vinculo' => (int) data_get($report, '_counts.vinculo', count($report['vinculo_rows'] ?? [])),
        ];

        $heavyKeys = [
            'provider_ip_map',
            'groups_rows',
            'bilhetagem_cards',
            'vinculo_rows',
            'night_events_rows',
            'mobile_events_rows',
        ];

        $keysByTab = [
            'timeline' => ['timeline_rows'],
            'unique_ips' => ['unique_ip_rows'],
            'providers' => ['provider_stats_rows', 'provider_ip_map'],
            'cities' => ['city_stats_rows'],
            'groups' => ['groups_rows'],
            'bilhetagem' => ['bilhetagem_cards'],
            'vinculo' => ['vinculo_rows'],
            'residencial' => ['night_events_rows'],
            'movel' => ['mobile_events_rows'],
        ];

        $keep = $keysByTab[$activeTab] ?? [];

        foreach ($heavyKeys as $key) {
            if (! in_array($key, $keep, true)) {
                $report[$key] = [];
            }
        }

        $report['symmetric_contacts'] = [];
        $report['asymmetric_contacts'] = [];

        $report['timeline_rows'] = array_slice($report['timeline_rows'] ?? [], 0, 200);
        $report['unique_ip_rows'] = array_slice($report['unique_ip_rows'] ?? [], 0, 200);
        $report['provider_stats_rows'] = array_slice($report['provider_stats_rows'] ?? [], 0, 100);
        $report['city_stats_rows'] = array_slice($report['city_stats_rows'] ?? [], 0, 100);
        $report['groups_rows'] = array_slice($report['groups_rows'] ?? [], 0, 100);
        $report['bilhetagem_cards'] = array_slice($report['bilhetagem_cards'] ?? [], 0, 100);
        $report['vinculo_rows'] = array_slice($report['vinculo_rows'] ?? [], 0, 100);
        $report['night_events_rows'] = array_slice($report['night_events_rows'] ?? [], 0, 200);
        $report['mobile_events_rows'] = array_slice($report['mobile_events_rows'] ?? [], 0, 200);

        $providerIpMap = [];
        foreach (array_slice($report['provider_ip_map'] ?? [], 0, 100, true) as $provider => $ips) {
            $providerIpMap[$provider] = array_slice(is_array($ips) ? $ips : [], 0, 100);
        }
        $report['provider_ip_map'] = $providerIpMap;

        $report['_counts'] = $counts;

        return $report;
    }

    protected function loadContactsForType(string $type): array
    {
        $run = $this->loadRunForReport($this->runId);
        if (! $run) {
            return [];
        }

        $parsed = $this->loadParsedPayload($run);
        if (! is_array($parsed)) {
            return [];
        }

        $contacts = $type === 'simetricos'
            ? (array) ($parsed['symmetric_contacts'] ?? [])
            : (array) ($parsed['asymmetric_contacts'] ?? []);

        $contacts = array_values(array_filter(array_map(
            fn ($value): ?string => ($trimmed = trim((string) $value)) !== '' ? $trimmed : null,
            $contacts,
        )));

        return array_slice(array_values(array_unique($contacts)), 0, 1000);
    }

    protected function reportCacheKey(AnaliseRun $run, ?string $tab = null): string
    {
        return 'analise-wpp-report:' . $run->getKey() . ':' . ($tab ?: 'default');
    }

    protected function availableTabs(): array
    {
        return [
            'timeline',
            'unique_ips',
            'providers',
            'cities',
            'residencial',
            'movel',
            'groups',
            'bilhetagem',
            'vinculo',
            'burst',
        ];
    }

    // =========================================================
    // loadExistingRun
    // =========================================================
    protected function loadExistingInvestigation(int $investigationId): void
    {
        $investigation = AnaliseInvestigation::query()
            ->whereKey($investigationId)
            ->first();

        if (! $investigation) {
            Notification::make()->title('Investigação não encontrada')->danger()->send();
            return;
        }

        $this->investigationId = $investigation->id;
        $this->investigation = [
            'id' => $investigation->id,
            'name' => $investigation->name,
        ];

        $runs = AnaliseRun::query()
            ->where('investigation_id', $investigation->id)
            ->orderBy('id')
            ->get(['id', 'investigation_id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at']);

        $this->targetRuns = $this->formatTargetRuns($runs->all());
        $this->running = $runs->contains(fn (AnaliseRun $run) => $run->status === 'running');
        $this->awaitingRunCreationUntil = null;
        $this->awaitingRunCreationBaseCount = null;

        $selected = $runs->first(fn (AnaliseRun $run) => $this->isRunCompleted($run)) ?: $runs->first();
        if (! $selected) {
            return;
        }

        $this->runId = $selected->id;
        $this->selectedTargetRunId = $selected->id;
        $this->progress = (int) $selected->progress;

        if ($this->isRunCompleted($selected)) {
            $this->tab = 'timeline';
            $selectedRun = $this->loadRunForReport($selected->id);
            if ($selectedRun) {
                $this->runWarnings = data_get($selectedRun->report, '_warnings.ignored_bilhetagens', []) ?: [];
                $this->hydrateReportFromRun($selectedRun, 'timeline');
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
        $run = $this->loadRunLight($runId);
        if (! $run) {
            Notification::make()->title('Relatório processado não encontrado')->danger()->send();
            return;
        }

        $this->runId = $run->id;
        $this->selectedTargetRunId = $run->id;
        $this->progress = (int) $run->progress;
        $this->running = in_array((string) $run->status, ['queued', 'running'], true);

        if ($run->investigation_id) {
            $this->investigationId = (int) $run->investigation_id;
            $investigation = AnaliseInvestigation::find($run->investigation_id);
            if ($investigation) {
                $this->investigation = [
                    'id' => $investigation->id,
                    'name' => $investigation->name,
                ];
            }
        }

        $targetRuns = $run->investigation_id
            ? AnaliseRun::where('investigation_id', $run->investigation_id)
                ->orderBy('id')
                ->get(['id', 'investigation_id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at'])
                ->all()
            : [$run];

        $this->targetRuns = $this->formatTargetRuns($targetRuns);
        $this->running = collect($targetRuns)->contains(fn (AnaliseRun $item) => in_array((string) $item->status, ['queued', 'running'], true));
        $this->awaitingRunCreationUntil = null;
        $this->awaitingRunCreationBaseCount = null;

        $this->runWarnings = [];

        if ($this->isRunCompleted($run)) {
            $this->tab = 'timeline';
            $fullRun = $this->loadRunForReport($run->id);
            if ($fullRun) {
                $this->runWarnings = data_get($fullRun->report, '_warnings.ignored_bilhetagens', []) ?: [];
                $this->hydrateReportFromRun($fullRun, 'timeline');
            }
        }
    }

    // =========================================================
    // ✅ GARANTIR RUN IPS PARA BILHETAGEM + ENRICH
    // =========================================================
    protected function ensureRunIpsForBilhetagem(AnaliseRun $run): int
    {
        $added = 0;

        $senderIps = Bilhetagem::query()
            ->where('analise_run_id', $run->id)
            ->whereNotNull('sender_ip')
            ->pluck('sender_ip')
            ->map(fn ($ip) => $this->extractIpBase(is_string($ip) ? $ip : null))
            ->filter(fn ($ip) => is_string($ip) && trim($ip) !== '')
            ->unique()
            ->values()
            ->all();

        if (empty($senderIps)) {
            return 0;
        }

        $existing = AnaliseRunIp::where('analise_run_id', $run->id)
            ->whereIn('ip', $senderIps)
            ->pluck('ip')
            ->all();

        $map = [];
        foreach ($existing as $ip) $map[$ip] = true;

        foreach ($senderIps as $ip) {
            if (isset($map[$ip])) continue;

            AnaliseRunIp::create([
                'analise_run_id' => $run->id,
                'ip' => $ip,
                'occurrences' => 0,
                'last_seen_at' => null,
                'enriched' => false,
            ]);

            $added++;
        }

        if ($added > 0) {
            $totalUniqueIps = AnaliseRunIp::query()
                ->where('analise_run_id', $run->id)
                ->distinct('ip')
                ->count('ip');

            $run->forceFill([
                'total_unique_ips' => $totalUniqueIps,
            ])->save();
        }

        return $added;
    }

    protected function enrichPendingIpsNow(AnaliseRun $run, int $batchSize = 1, int $maxBatches = 1): void
    {
        $pending = AnaliseRunIp::query()
            ->where('analise_run_id', $run->id)
            ->where(function ($query): void {
                $query->where('enriched', false)->orWhereNull('enriched');
            })
            ->count();

        if ($pending <= 0) return;

        $originalStatus = $run->status;
        $originalProgress = $run->progress;

        if ($run->status !== 'running') {
            $run->status = 'running';
            $run->save();
        }

        for ($i = 0; $i < $maxBatches; $i++) {
            $before = AnaliseRunIp::query()
                ->where('analise_run_id', $run->id)
                ->where(function ($query): void {
                    $query->where('enriched', false)->orWhereNull('enriched');
                })
                ->count();

            if ($before === 0) break;

            app(RunStepper::class)->step($run, $batchSize, 0.0);
            $run->refresh();

            $after = AnaliseRunIp::query()
                ->where('analise_run_id', $run->id)
                ->where(function ($query): void {
                    $query->where('enriched', false)->orWhereNull('enriched');
                })
                ->count();

            if ($after >= $before) break;
        }

        if ($originalStatus !== 'running') {
            $run->refresh();
            $run->status = $originalStatus;
            $run->progress = $originalProgress;
            $run->save();
        }
    }

    // =========================================================
    // Helpers: zip/html + ip + alvo
    // =========================================================
    protected function storeParsedPayload(string $runUuid, array $parsed): string
    {
        $path = 'analise-runs/' . $runUuid . '/parsed.json.gz';
        $json = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $json = '{}';
        }

        Storage::disk('local')->put($path, gzencode($json, 6));

        return $path;
    }

    protected function loadParsedPayload(AnaliseRun $run): ?array
    {
        $inline = data_get($run->report, '_parsed');
        if (is_array($inline)) {
            $path = data_get($run->report, '_parsed_path');
            if (! is_string($path) || trim($path) === '') {
                $path = $this->storeParsedPayload((string) ($run->uuid ?: Str::uuid()), $inline);

                $report = is_array($run->report) ? $run->report : [];
                unset($report['_parsed']);
                $report['_parsed_path'] = $path;

                $run->report = $report;
                $run->save();
            }

            return $inline;
        }

        $path = data_get($run->report, '_parsed_path');
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }

        $content = $disk->get($path);
        if (! is_string($content) || $content === '') {
            return null;
        }

        $json = @gzdecode($content);
        if (! is_string($json) || $json === '') {
            $json = $content;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function loadRunLight(int|string|null $runId): ?AnaliseRun
    {
        $runId = (int) $runId;
        if ($runId <= 0) {
            return null;
        }

        return AnaliseRun::query()
            ->whereKey($runId)
            ->first(['id', 'user_id', 'investigation_id', 'target', 'status', 'progress', 'total_unique_ips', 'created_at']);
    }

    protected function loadRunForReport(int|string|null $runId): ?AnaliseRun
    {
        $runId = (int) $runId;
        if ($runId <= 0) {
            return null;
        }

        return AnaliseRun::query()
            ->whereKey($runId)
            ->first([
                'id',
                'user_id',
                'investigation_id',
                'uuid',
                'source',
                'target',
                'total_unique_ips',
                'processed_unique_ips',
                'progress',
                'status',
                'error_message',
                'report',
                'created_at',
                'updated_at',
            ]);
    }

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

                    $lname = strtolower($name);
                    if (str_ends_with($lname, '.html') || str_ends_with($lname, '.htm')) {
                        $htmlContent = $zip->getFromIndex($i);
                        break;
                    }
                }
            }

            $zip->close();

            return is_string($htmlContent) ? $htmlContent : null;
        }

        return $disk->get($storedPath);
    }

    protected function extractIpBase(?string $ipWithPort): ?string
    {
        $ipWithPort = trim((string) $ipWithPort);
        if ($ipWithPort === '') return null;

        if (preg_match('/^\[([0-9a-fA-F:]+)\]:(\d{1,5})$/', $ipWithPort, $m)) return $m[1];
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):(\d{1,5})$/', $ipWithPort, $m)) return $m[1];

        return $ipWithPort;
    }

    protected function normalizePhoneKey(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        $digits = trim($digits);
        return $digits !== '' ? $digits : null;
    }

    private function normalizeTarget(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $digits = trim($digits);

        if ($digits !== '') {
            return strlen($digits) > 10 ? substr($digits, -10) : $digits;
        }

        $v = mb_strtolower($value);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        $v = trim($v);

        return $v !== '' ? $v : null;
    }

    private function normalizeTargetForDuplicateCheck(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $digits = trim($digits);

        if ($digits !== '') {
            return $digits;
        }

        $v = mb_strtolower($value);
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        $v = trim($v);

        return $v !== '' ? $v : null;
    }

    private function targetsMatch(?string $runTargetRaw, ?string $fileTargetRaw): bool
    {
        $a = $this->normalizeTarget($runTargetRaw);
        $b = $this->normalizeTarget($fileTargetRaw);

        if (! $a || ! $b) return false;

        return $a === $b;
    }
}

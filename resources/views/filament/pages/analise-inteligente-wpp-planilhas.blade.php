<x-filament-panels::page>
    <form wire:submit="gerar" class="space-y-6" wire:loading.class="opacity-75" wire:target="gerar">
        @if ($investigationId)
            <x-filament::section heading="Investigação">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs text-gray-500">Enviando arquivos para</div>
                        <div class="font-semibold">{{ $investigation['name'] ?? ('Investigação #' . $investigationId) }}</div>
                    </div>
                    <div class="text-sm text-gray-500">ID {{ $investigationId }}</div>
                </div>
            </x-filament::section>
        @endif

        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="gerar" :disabled="$running">
                <span wire:loading.remove wire:target="gerar">
                    {{ $running ? 'Processando...' : ($investigationId ? 'Enviar arquivos' : 'Criar investigação') }}
                </span>
                <span wire:loading wire:target="gerar">Gerando...</span>
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="limpar" wire:loading.attr="disabled" wire:target="limpar,gerar" :disabled="$running">
                Limpar
            </x-filament::button>
        </div>
    </form>

    @if ($running || $runId || $investigationId)
        <x-filament::section class="mt-6" heading="Progresso">
            <div class="space-y-3"
                @if($running && empty($selectedContactType) && empty($selectedProvider) && empty($vinculoModalIp))
                    wire:poll.1000ms="poll"
                @endif
            >
                <div class="text-sm text-gray-500">
                    @if ($runId)
                        Run ID: <span class="font-mono">{{ $runId }}</span>
                    @else
                        Investigacao ID: <span class="font-mono">{{ $investigationId }}</span>
                    @endif
                </div>

                <div class="w-full bg-gray-200 rounded h-3 overflow-hidden">
                    <div class="bg-primary-600 h-3" style="width: {{ $progress }}%"></div>
                </div>

                <div class="text-sm">
                    {{ $progress }}%
                    @if($running)
                        @if(empty($targetRuns))
                            (preparando alvo e consolidando arquivos...)
                        @else
                            (processando...)
                        @endif
                    @else
                        (finalizado)
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif

    @if (! empty($targetRuns))
        <x-filament::section class="mt-6" heading="Alvos identificados">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($targetRuns as $targetRun)
                    @php
                        $isSelected = (int) ($selectedTargetRunId ?? $runId) === (int) ($targetRun['id'] ?? 0);
                    @endphp

                    <button
                        type="button"
                        wire:click="selectTargetReport({{ (int) ($targetRun['id'] ?? 0) }})"
                        class="rounded-xl border p-4 text-left transition hover:bg-gray-50 {{ $isSelected ? 'border-primary-500 bg-primary-50/50' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs text-gray-500">Alvo</div>
                                <div class="font-semibold break-all">{{ $targetRun['target'] ?? 'Alvo não identificado' }}</div>
                            </div>

                            <div class="text-right text-xs text-gray-500 space-y-1">
                                <x-filament::badge :color="($targetRun['is_completed'] ?? false) ? 'success' : 'warning'">
                                    {{ ($targetRun['is_completed'] ?? false) ? 'Concluído' : ((int) ($targetRun['progress'] ?? 0) . '%') }}
                                </x-filament::badge>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <div class="text-xs text-gray-500">Total de IPs</div>
                                <div class="font-semibold">{{ number_format((int) ($targetRun['total_ips'] ?? 0), 0, ',', '.') }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500">IPs únicos</div>
                                <div class="font-semibold">{{ number_format((int) ($targetRun['unique_ips'] ?? 0), 0, ',', '.') }}</div>
                            </div>
                        </div>

                        <div class="mt-3 h-2 w-full overflow-hidden rounded bg-gray-200">
                            <div class="h-2 bg-primary-600" style="width: {{ (int) ($targetRun['progress'] ?? 0) }}%"></div>
                        </div>
                    </button>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @if ($report)
        <x-filament::section class="mt-6" heading="Resumo da Análise">
            <div class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {{-- 1) Alvo / Total IPs / Dispositivo --}}
                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Alvo</div>
                        <div class="font-semibold break-all">{{ $report['target'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Total de IPs</div>
                        <div class="font-semibold">{{ number_format($report['total_ips'] ?? 0, 0, ',', '.') }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Dispositivo</div>
                        <div class="font-semibold">{{ $report['device'] ?? '-' }}</div>
                    </div>

                    {{-- 2) Emails / Simétricos / Assimétricos --}}
                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Emails de Registro</div>
                        @php $emails = $report['registered_emails'] ?? []; @endphp

                        @if (count($emails))
                            <div class="mt-1 space-y-1">
                                @foreach($emails as $em)
                                    <div class="font-mono text-sm break-all">{{ $em }}</div>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-1 text-sm font-semibold">-</div>
                        @endif
                    </div>

                    <button type="button" class="rounded-xl border p-4 text-left hover:bg-gray-50 transition" wire:click="openContactsModal('simetricos')">
                        <div class="text-sm text-gray-500">Contatos Simétricos</div>
                        <div class="font-semibold">{{ number_format($report['symmetric_contacts_count'] ?? 0, 0, ',', '.') }}</div>
                    </button>

                    <button type="button" class="rounded-xl border p-4 text-left hover:bg-gray-50 transition" wire:click="openContactsModal('assimetricos')">
                        <div class="text-sm text-gray-500">Contatos Assimétricos</div>
                        <div class="font-semibold">{{ number_format($report['asymmetric_contacts_count'] ?? 0, 0, ',', '.') }}</div>
                    </button>

                    {{-- 3) Período (GMT-3) --}}
                    <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-2">
                        <div class="text-sm text-gray-500">Período (GMT-3)</div>
                        <div class="font-semibold">{{ $report['period_label'] ?? '-' }}</div>
                    </div>

                    {{-- 4) Conversão de horário --}}
                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Conversão de Horário</div>
                        <div class="mt-1 text-sm font-semibold text-gray-800">
                            Os horários exibidos foram convertidos de <span class="font-mono">UTC</span> para <span class="font-mono">GMT-3 (Brasília)</span>.
                        </div>
                    </div>

                    {{-- 5) Último: Connection --}}
                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Último IP (Connection)</div>

                        <div class="mt-2 space-y-2">
                            <div class="font-mono text-sm break-all">
                                {{ $report['connection_summary']['last_ip'] ?? '-' }}
                            </div>

                            <div class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Provedor</span><br>
                                <span class="font-semibold">
                                    {{ $report['connection_summary']['last_ip_provider'] ?? '-' }}
                                </span>
                            </div>

                            <div class="text-sm text-gray-700">
                                <span class="text-xs text-gray-500">Última vez visto (GMT-3)</span><br>
                                <span class="font-semibold text-gray-800">
                                    {{ $report['connection_summary']['last_seen'] ?? '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        @php
            $tabs = [
                'timeline' => ['label' => 'Timeline', 'icon' => 'heroicon-o-clock'],
                'unique_ips' => ['label' => 'IPs Únicos', 'icon' => 'heroicon-o-globe-alt'],
                'providers' => ['label' => 'Provedores', 'icon' => 'heroicon-o-building-office-2'],
                'cities' => ['label' => 'Cidades', 'icon' => 'heroicon-o-map-pin'],
                'residencial' => ['label' => 'Noturno', 'icon' => 'heroicon-o-moon'],
                'movel' => ['label' => 'Móvel', 'icon' => 'heroicon-o-device-phone-mobile'],
                'groups' => ['label' => 'Grupos', 'icon' => 'heroicon-o-user-group'],
                'bilhetagem' => ['label' => 'Mensagens', 'icon' => 'heroicon-o-chat-bubble-left-right'],
                'vinculo' => ['label' => 'Vínculo', 'icon' => 'heroicon-o-link'],
            ];

            $counts = [
                'timeline' => (int) data_get($report, '_counts.timeline', count($report['timeline_rows'] ?? [])),
                'unique_ips' => (int) data_get($report, '_counts.unique_ips', count($report['unique_ip_rows'] ?? [])),
                'providers' => (int) data_get($report, '_counts.providers', count($report['provider_stats_rows'] ?? [])),
                'cities' => (int) data_get($report, '_counts.cities', count($report['city_stats_rows'] ?? [])),
                'residencial' => (int) data_get($report, '_counts.residencial', $report['night_total_events'] ?? 0),
                'movel' => (int) data_get($report, '_counts.movel', $report['mobile_total_events'] ?? 0),
                'groups' => (int) data_get($report, '_counts.groups', count($report['groups_rows'] ?? [])),
                'bilhetagem' => (int) data_get($report, '_counts.bilhetagem', count($report['bilhetagem_cards'] ?? [])),
                'vinculo' => (int) data_get($report, '_counts.vinculo', count($report['vinculo_rows'] ?? [])),
            ];
        @endphp

        <x-filament::section class="mt-6" heading="Planilhas">
            <div class="flex flex-wrap gap-2">
                @foreach($tabs as $key => $meta)
                    @php $active = $tab === $key; @endphp

                    <x-filament::button
                        type="button"
                        size="sm"
                        :color="$active ? 'primary' : 'gray'"
                        :outlined="! $active"
                        wire:click="setTab('{{ $key }}')"
                        class="whitespace-nowrap"
                    >
                        <span class="inline-flex items-center gap-2">
                            <x-filament::icon :icon="$meta['icon']" class="h-4 w-4" />
                            <span class="font-semibold">{{ $meta['label'] }}</span>

                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $active ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                                {{ number_format($counts[$key] ?? 0, 0, ',', '.') }}
                            </span>
                        </span>
                    </x-filament::button>
                @endforeach
            </div>
        </x-filament::section>

        <div class="mt-6 space-y-6">
            @if ($tab === 'timeline')
                <x-filament::section heading="Timeline (Eventos)">
                    <livewire:analise-inteligente.timeline-table
                        :rows="$report['timeline_rows'] ?? []"
                        :wire:key="'timeline-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'unique_ips')
                <x-filament::section heading="IPs Únicos (Relevância)">
                    <livewire:analise-inteligente.unique-ips-table
                        :rows="$report['unique_ip_rows'] ?? []"
                        :wire:key="'unique-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'providers')
                <x-filament::section heading="Provedores (Métricas)">
                    <livewire:analise-inteligente.providers-stats-table
                        :rows="$report['provider_stats_rows'] ?? []"
                        :wire:key="'providers-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'cities')
                <x-filament::section heading="Cidades (Concentração)">
                    <livewire:analise-inteligente.cities-stats-table
                        :rows="$report['city_stats_rows'] ?? []"
                        :wire:key="'cities-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'residencial')
                <x-filament::section heading="Noturno">
                    @include('filament.pages.partials.sheet-residencial', ['report' => $report])
                </x-filament::section>
            @endif

            @if ($tab === 'movel')
                <x-filament::section heading="Móvel">
                    @include('filament.pages.partials.sheet-movel', ['report' => $report])
                </x-filament::section>
            @endif

            {{-- ✅ CORRIGIDO AQUI: groups-table -> groups-cards --}}
            @if ($tab === 'groups')
                <x-filament::section heading="Grupos">
                    <livewire:analise-inteligente.groups-cards
                        :rows="$report['groups_rows'] ?? []"
                        :per-page="20"
                        :wire:key="'groups-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'bilhetagem')
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center justify-between gap-3 w-full">
                            <div>Mensagens (por Contato)</div>

                            <x-filament::button
                                type="button"
                                size="sm"
                                icon="heroicon-o-arrow-up-tray"
                                wire:click="mountAction('bilhetagemUpload')"
                            >
                                Upload bilhetagem
                            </x-filament::button>
                        </div>
                    </x-slot>

                    @include('filament.pages.partials.sheet-bilhetagem', [
                        'cards' => $report['bilhetagem_cards'] ?? [],
                    ])
                </x-filament::section>
            @endif

            @if ($tab === 'vinculo')
                <x-filament::section heading="Vínculo por IP entre alvos">
                    @include('filament.pages.partials.sheet-vinculo', [
                        'rows' => $report['vinculo_rows'] ?? [],
                    ])
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>

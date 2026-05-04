<x-filament-panels::page>
    <form wire:submit="gerar" class="space-y-6" wire:loading.class="opacity-75" wire:target="gerar">
        @if ($investigationId)
            <x-filament::section heading="Investigacao">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs text-gray-500">Enviando arquivos para</div>
                        <div class="font-semibold">{{ $investigation['name'] ?? ('Investigacao #' . $investigationId) }}</div>
                    </div>

                    <div class="text-sm text-gray-500">
                        {{ $investigation['platform_label'] ?? ucfirst((string) ($investigation['source'] ?? '')) }} - ID {{ $investigationId }}
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="gerar" :disabled="$running">
                <span wire:loading.remove wire:target="gerar">
                    {{ $running ? 'Processando...' : ($investigationId ? 'Enviar arquivos' : 'Criar investigacao') }}
                </span>
                <span wire:loading wire:target="gerar">Gerando...</span>
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="limpar" wire:loading.attr="disabled" wire:target="limpar,gerar" :disabled="$running">
                Limpar
            </x-filament::button>
        </div>
    </form>

    @if ($runId || $investigationId)
        <x-filament::section class="mt-6" heading="Progresso">
            <div wire:poll.1000ms="poll" class="space-y-3">
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
                        wire:click="selectTargetRun({{ (int) ($targetRun['id'] ?? 0) }})"
                        class="rounded-xl border p-4 text-left transition hover:bg-gray-50 {{ $isSelected ? 'border-primary-500 bg-primary-50/50' : '' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-xs text-gray-500">Alvo</div>
                                <div class="truncate font-semibold">{{ $targetRun['target'] ?? 'Alvo nao identificado' }}</div>
                            </div>

                            <div class="text-right text-xs text-gray-500 space-y-1">
                                <x-filament::badge :color="(int) ($targetRun['progress'] ?? 0) >= 100 ? 'success' : 'warning'">
                                    {{ (int) ($targetRun['progress'] ?? 0) >= 100 ? 'Concluído' : ((int) ($targetRun['progress'] ?? 0) . '%') }}
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

                        <div class="mt-3 h-2 overflow-hidden rounded bg-gray-200">
                            <div class="h-2 bg-primary-600" style="width: {{ (int) ($targetRun['progress'] ?? 0) }}%"></div>
                        </div>
                    </button>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    @if ($report)
        <x-filament::section class="mt-6" heading="Resumo da Analise">
            <div class="mb-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">Alvo selecionado</div>
                    <div class="font-semibold break-all">{{ $report['selected_target'] ?? ($targetRuns[0]['target'] ?? '-') }}</div>
                </div>
            </div>

            @if(!empty($report['subscriber_info']) && is_array($report['subscriber_info']))
                @php $subscriber = $report['subscriber_info']; @endphp

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Conta Google</div>
                        <div class="font-semibold break-all">{{ $subscriber['account_id'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">E-mail principal</div>
                        <div class="font-semibold break-all">{{ $subscriber['email'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Nome</div>
                        <div class="font-semibold">{{ $subscriber['name'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Criada em (GMT-3)</div>
                        <div class="font-semibold">{{ $subscriber['created_on_local'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">IP de criacao/termos</div>
                        <div class="font-mono font-semibold break-all">{{ $subscriber['terms_of_service_ip'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Status</div>
                        <div class="font-semibold">{{ $subscriber['status'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">E-mail de recuperacao</div>
                        <div class="font-semibold break-all">{{ $subscriber['recovery_email'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Telefone de recuperacao</div>
                        <div class="font-semibold">{{ $subscriber['recovery_sms'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Dispositivos informados</div>
                        <div class="font-semibold">{{ $subscriber['device_information'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4 md:col-span-2 xl:col-span-3">
                        <div class="text-sm text-gray-500">Servicos</div>
                        <div class="font-semibold">
                            @if(!empty($subscriber['services']))
                                {{ implode(', ', $subscriber['services']) }}
                            @else
                                -
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Periodo (GMT-3)</div>
                        <div class="font-semibold">{{ $report['period_label'] ?? '-' }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Total de eventos</div>
                        <div class="font-semibold">{{ number_format($report['total_events'] ?? 0, 0, ',', '.') }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">IPs unicos</div>
                        <div class="font-semibold">{{ number_format($report['total_unique_ips'] ?? 0, 0, ',', '.') }}</div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Contas/e-mails</div>
                        <div class="font-semibold break-all">
                            @if(!empty($report['accounts_found']))
                                {{ implode(', ', $report['accounts_found']) }}
                            @elseif(!empty($report['emails_found']))
                                {{ implode(', ', $report['emails_found']) }}
                            @else
                                -
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl border p-4">
                        <div class="text-sm text-gray-500">Plataforma</div>
                        <div class="font-semibold">{{ $report['platform_label'] ?? ($investigation['platform_label'] ?? '-') }}</div>
                    </div>
                </div>
            @endif
        </x-filament::section>

        @php
            $componentPrefix = (($investigation['source'] ?? null) === 'google') ? 'google' : 'generic';
            $isGoogle = (($investigation['source'] ?? null) === 'google');
            $tabs = [
                'timeline' => ['label' => 'Timeline', 'icon' => 'heroicon-o-clock'],
                'unique_ips' => ['label' => 'IPs unicos', 'icon' => 'heroicon-o-globe-alt'],
                'providers' => ['label' => 'Provedores', 'icon' => 'heroicon-o-building-office-2'],
                'cities' => ['label' => 'Cidades', 'icon' => 'heroicon-o-map-pin'],
                'residencial' => ['label' => 'Noturno (23-06)', 'icon' => 'heroicon-o-moon'],
                'movel' => ['label' => 'Movel', 'icon' => 'heroicon-o-device-phone-mobile'],
            ];

            if ($isGoogle) {
                $tabs['maps'] = ['label' => 'Maps', 'icon' => 'heroicon-o-map'];
                $tabs['search'] = ['label' => 'Pesquisa', 'icon' => 'heroicon-o-magnifying-glass'];
                $tabs['user_agents'] = ['label' => 'Dispositivo/UA', 'icon' => 'heroicon-o-device-phone-mobile'];
                $tabs['vinculo'] = ['label' => 'Vinculo', 'icon' => 'heroicon-o-link'];
            }

            $counts = [
                'timeline' => (int) data_get($report, '_counts.timeline', 0),
                'unique_ips' => (int) data_get($report, '_counts.unique_ips', 0),
                'providers' => (int) data_get($report, '_counts.providers', 0),
                'cities' => (int) data_get($report, '_counts.cities', 0),
                'residencial' => (int) data_get($report, '_counts.residencial', $report['night_total_events'] ?? 0),
                'movel' => (int) data_get($report, '_counts.movel', $report['mobile_total_events'] ?? 0),
                'vinculo' => (int) data_get($report, '_counts.vinculo', count($report['vinculo_rows'] ?? [])),
            ];

            if ($isGoogle) {
                $counts['maps'] = (int) data_get($report, '_counts.maps', 0);
                $counts['search'] = (int) data_get($report, '_counts.search', 0);
                $counts['user_agents'] = (int) data_get($report, '_counts.user_agents', 0);
            }
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
                    @livewire('analise-inteligente.' . $componentPrefix . '-timeline-table', ['runId' => $runId], key('platform-timeline-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($tab === 'unique_ips')
                <x-filament::section heading="IPs Unicos (Relevancia)">
                    @livewire('analise-inteligente.' . $componentPrefix . '-unique-ips-table', ['runId' => $runId], key('platform-unique-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($tab === 'providers')
                <x-filament::section heading="Provedores (Metricas)">
                    @livewire('analise-inteligente.' . $componentPrefix . '-providers-table', ['runId' => $runId], key('platform-providers-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($tab === 'cities')
                <x-filament::section heading="Cidades (Concentracao)">
                    @livewire('analise-inteligente.' . $componentPrefix . '-cities-table', ['runId' => $runId], key('platform-cities-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($isGoogle && $tab === 'maps')
                <x-filament::section heading="Maps">
                    @livewire('analise-inteligente.' . $componentPrefix . '-maps-table', ['runId' => $runId], key('platform-maps-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($isGoogle && $tab === 'search')
                <x-filament::section heading="Pesquisa">
                    @livewire('analise-inteligente.' . $componentPrefix . '-search-table', ['runId' => $runId], key('platform-search-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($isGoogle && $tab === 'user_agents')
                @include('filament.pages.partials.sheet-platform-devices', ['report' => $report, 'runId' => $runId, 'componentPrefix' => $componentPrefix])
            @endif

            @if ($tab === 'residencial')
                <x-filament::section heading="Noturno (23-06)">
                    @livewire('analise-inteligente.' . $componentPrefix . '-timeline-table', ['runId' => $runId, 'scope' => 'night'], key('platform-night-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($tab === 'movel')
                <x-filament::section heading="Movel">
                    @livewire('analise-inteligente.' . $componentPrefix . '-timeline-table', ['runId' => $runId, 'scope' => 'mobile'], key('platform-mobile-' . $componentPrefix . '-' . $runId))
                </x-filament::section>
            @endif

            @if ($tab === 'vinculo')
                <x-filament::section heading="Vinculo">
                    @include('filament.pages.partials.sheet-vinculo', [
                        'rows' => $report['vinculo_rows'] ?? [],
                    ])
                </x-filament::section>
            @endif
        </div>

        <x-filament-actions::modals />
    @endif
</x-filament-panels::page>

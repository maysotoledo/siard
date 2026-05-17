<x-filament-panels::page>
    <form wire:submit="gerar" class="space-y-6" wire:loading.class="opacity-75" wire:target="gerar">
        @if ($investigationId)
            <x-filament::section heading="Investigacao">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-xs text-gray-500">Enviando arquivos para</div>
                        <div class="font-semibold">{{ $investigation['name'] ?? ('Investigacao #' . $investigationId) }}</div>
                    </div>
                    <div class="text-sm text-gray-500">Instagram - ID {{ $investigationId }}</div>
                </div>
            </x-filament::section>
        @endif

        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="gerar"
                :disabled="$running"
            >
                <span wire:loading.remove wire:target="gerar">
                    {{ $running ? 'Processando...' : ($investigationId ? 'Enviar arquivos' : 'Criar investigacao') }}
                </span>

                <span wire:loading wire:target="gerar">
                    Gerando...
                </span>
            </x-filament::button>

            <x-filament::button
                type="button"
                color="gray"
                wire:click="limpar"
                wire:loading.attr="disabled"
                wire:target="limpar,gerar"
                :disabled="$running"
            >
                Limpar
            </x-filament::button>
        </div>
    </form>

    @if ($runId || $investigationId)
        <x-filament::section class="mt-6" heading="Progresso">
            <div
                class="space-y-3"
                @if($running && empty($selectedProvider))
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
                            — <span class="text-gray-500">preparando arquivos e enfileirando investigação...</span>
                        @elseif($progress < 20)
                            — <span class="text-gray-500">parsing dos arquivos HTML...</span>
                        @elseif($progress < 50)
                            — <span class="text-gray-500">enriquecendo IPs com geolocalização...</span>
                        @elseif($progress < 85)
                            — <span class="text-gray-500">consolidando eventos e gerando relatório...</span>
                        @else
                            — <span class="text-gray-500">finalizando e salvando resultados...</span>
                        @endif
                    @else
                        — <span class="text-green-600 font-medium">concluído</span>
                    @endif
                </div>
            </div>
        </x-filament::section>
    @endif

    @if (! empty($targetRuns))
        <x-filament::section class="mt-6" heading="Alvos identificados">
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach($targetRuns as $targetRun)
                    @php $isSelected = (int) ($selectedTargetRunId ?? $runId) === (int) ($targetRun['id'] ?? 0); @endphp

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
        <x-filament::section class="mt-6" heading="Resumo da Análise">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        Conta Instagram: {{ $report['vanity_name'] ?? ($report['account_identifier'] ?? '-') }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">
                       Nome: {{ $report['first_name'] ?? '-' }}
                    </div>
                    <div class="text-xs text-gray-400 mt-2">
                        ID: {{ $report['target'] ?? '-' }}
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        Data de registro: {{ $report['registration_date'] ?? '-' }}
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        Telefone de registro: {{ $report['registration_phone_formatted'] ?? '-' }}
                        Verificado em {{ $report['registration_phone_verified_on'] ?? '-' }}
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        IP de registro: {{ $report['registration_ip'] ?? '-' }}
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        Total de IPs {{ number_format($report['total_ips'] ?? 0, 0, ',', '.') }}
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        Gerado em {{ $report['generated_at'] ?? '-' }}
                    </div>
                </div>

                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">
                        UTC convertido para: GMT-3 (Brasilia)
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-xl border p-4 text-left hover:bg-gray-50 transition"
                    wire:click="openRelationshipModal('followers')"
                >
                    <div class="text-sm text-gray-500">Seguidores extraídos do log</div>
                    <div class="mt-1 text-2xl font-semibold">
                        {{ number_format($report['followers_count'] ?? data_get($report, '_counts.followers', count($report['followers'] ?? [])), 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">Clique para ver os nomes</div>
                </button>

                <button
                    type="button"
                    class="rounded-xl border p-4 text-left hover:bg-gray-50 transition"
                    wire:click="openRelationshipModal('following')"
                >
                    <div class="text-sm text-gray-500">Seguindo extraídos do log</div>
                    <div class="mt-1 text-2xl font-semibold">
                        {{ number_format($report['following_count'] ?? data_get($report, '_counts.following', count($report['following'] ?? [])), 0, ',', '.') }}
                    </div>
                    <div class="text-xs text-gray-400 mt-1">Clique para ver os nomes</div>
                </button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 mt-4">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-gray-500">Última localização</div>

                    <div class="font-semibold">
                        {{ $report['last_location_latitude'] ?? '-' }},
                        {{ $report['last_location_longitude'] ?? '-' }}
                    </div>

                    @if(!empty($report['last_location_latitude']) && !empty($report['last_location_longitude']))
                        <div class="mt-3">
                            <x-filament::button
                                  tag="a"
                                  href="https://www.google.com/maps?q={{ $report['last_location_latitude'] }},{{ $report['last_location_longitude'] }}"
                                  target="_blank"
                                  size="sm"
                                  color="primary"
                                  icon="heroicon-o-map-pin"
                                >
                                  Abrir no Maps
                            </x-filament::button>
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border p-4 flex flex-col items-center justify-center">
                    <div class="text-sm text-gray-500 mb-2">QR Code da localização</div>

                    @if(!empty($report['last_location_qr_url']))
                        <img
                            src="{{ $report['last_location_qr_url'] }}"
                            alt="QR Code da localização"
                            class="w-44 h-44 object-contain border rounded-lg"
                        >
                    @else
                        <div class="text-sm text-gray-400">Sem localização disponível</div>
                    @endif
                </div>
            </div>
        </x-filament::section>

        @php
            $tabs = [
                'timeline' => ['label' => 'Timeline', 'icon' => 'heroicon-o-clock'],
                'unique_ips' => ['label' => 'IPs Únicos', 'icon' => 'heroicon-o-globe-alt'],
                'providers' => ['label' => 'Provedores', 'icon' => 'heroicon-o-building-office-2'],
                'cities' => ['label' => 'Cidades', 'icon' => 'heroicon-o-map-pin'],
                'residencial' => ['label' => 'Noturno (23–06)', 'icon' => 'heroicon-o-moon'],
                'movel' => ['label' => 'Móvel', 'icon' => 'heroicon-o-device-phone-mobile'],
                'direct' => ['label' => 'Direct', 'icon' => 'heroicon-o-chat-bubble-left-right'],
                'burst' => ['label' => 'Burst', 'icon' => 'heroicon-o-bolt'],
                'qualidade' => ['label' => 'Qualidade', 'icon' => 'heroicon-o-check-circle'],
            ];

            $counts = [
                'timeline' => (int) data_get($report, '_counts.timeline', count($report['timeline_rows'] ?? [])),
                'unique_ips' => (int) data_get($report, '_counts.unique_ips', count($report['unique_ip_rows'] ?? [])),
                'providers' => (int) data_get($report, '_counts.providers', count($report['provider_stats_rows'] ?? [])),
                'cities' => (int) data_get($report, '_counts.cities', count($report['city_stats_rows'] ?? [])),
                'residencial' => (int) data_get($report, '_counts.residencial', $report['night_total_events'] ?? 0),
                'movel' => (int) data_get($report, '_counts.movel', $report['mobile_total_events'] ?? 0),
                'direct' => (int) data_get($report, '_counts.direct', count($report['direct_threads'] ?? [])),
                'burst' => count($report['hourly_rows'] ?? []),
                'qualidade' => ($report['parse_stats'] ?? null) ? 1 : 0,
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
                    <x-slot name="headerEnd">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportTab('timeline')">CSV</x-filament::button>
                    </x-slot>
                    <livewire:analise-inteligente.timeline-table
                        :rows="$report['timeline_rows'] ?? []"
                        :wire:key="'insta-timeline-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'unique_ips')
                <x-filament::section heading="IPs Únicos (Relevância)">
                    <x-slot name="headerEnd">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportTab('unique_ips')">CSV</x-filament::button>
                    </x-slot>
                    <livewire:analise-inteligente.unique-ips-table
                        :rows="$report['unique_ip_rows'] ?? []"
                        :wire:key="'insta-unique-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'providers')
                <x-filament::section heading="Provedores (Métricas)">
                    <x-slot name="headerEnd">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportTab('providers')">CSV</x-filament::button>
                    </x-slot>
                    <livewire:analise-inteligente.providers-stats-table
                        :rows="$report['provider_stats_rows'] ?? []"
                        :wire:key="'insta-providers-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'cities')
                <x-filament::section heading="Cidades (Concentração)">
                    <x-slot name="headerEnd">
                        <x-filament::button size="sm" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="exportTab('cities')">CSV</x-filament::button>
                    </x-slot>
                    <livewire:analise-inteligente.cities-stats-table
                        :rows="$report['city_stats_rows'] ?? []"
                        :wire:key="'insta-cities-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'residencial')
                <x-filament::section heading="Noturno (23–06)">
                    @include('filament.pages.partials.sheet-residencial', ['report' => $report])
                </x-filament::section>
            @endif

            @if ($tab === 'movel')
                <x-filament::section heading="Móvel">
                    @include('filament.pages.partials.sheet-movel', ['report' => $report])
                </x-filament::section>
            @endif

            @if ($tab === 'direct')
                <x-filament::section heading="Direct (Conversas)">
                    @php $threads = $report['direct_threads'] ?? []; @endphp

                    @if (count($threads) === 0)
                        <div class="text-sm text-gray-500">Nenhuma conversa encontrada em Unified Messages.</div>
                    @else
                        <div
                            x-data="{ search: '' }"
                            class="space-y-4"
                        >
                            <input
                                type="text"
                                x-model="search"
                                placeholder="Buscar por participante..."
                                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                            />

                            <div class="grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($threads as $t)
                                    @php
                                        $name = $t['participant'] ?? '—';
                                        $msgCount = count($t['messages'] ?? []);
                                    @endphp

                                    <button
                                        type="button"
                                        x-show="search === '' || '{{ mb_strtolower($name) }}'.includes(search.toLowerCase())"
                                        class="rounded-xl border p-4 text-left hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                                        wire:click="openDirectModal({{ json_encode($name) }})"
                                    >
                                        <div class="text-sm text-gray-500">Conversou com</div>
                                        <div class="font-semibold break-all">{{ $name }}</div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            {{ number_format($msgCount, 0, ',', '.') }} mensagem(ns)
                                        </div>
                                    </button>
                                @endforeach
                            </div>

                            <div
                                x-show="search !== '' && document.querySelectorAll('[x-show][style*=\'display: none\']').length === {{ count($threads) }}"
                                class="text-sm text-gray-400 text-center py-4"
                            >
                                Nenhuma conversa encontrada para "<span x-text="search"></span>"
                            </div>
                        </div>
                    @endif
                </x-filament::section>
            @endif
            @if ($tab === 'burst')
                <x-filament::section heading="Conexões por hora">
                    <x-slot name="description">Clique em qualquer hora para ver os IPs e horários exatos de acesso.</x-slot>
                    <livewire:analise-inteligente.burst-hours-table
                        :rows="$report['hourly_rows'] ?? []"
                        :wire:key="'burst-insta-' . $runId"
                    />
                </x-filament::section>
            @endif

            @if ($tab === 'qualidade')
                @php $stats = $report['parse_stats'] ?? null; @endphp
                <x-filament::section heading="Qualidade dos dados">
                    <x-slot name="description">Indicadores de completude extraídos do arquivo HTML do Instagram.</x-slot>

                    @if (! $stats)
                        <div class="text-sm text-gray-500">Dados de qualidade não disponíveis (relatório gerado antes desta funcionalidade).</div>
                    @else
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @php
                                $qualItems = [
                                    ['label' => 'Eventos de IP', 'value' => number_format($stats['ip_events_count'], 0, ',', '.'), 'ok' => $stats['ip_events_count'] > 0],
                                    ['label' => 'Conversas Direct', 'value' => number_format($stats['direct_threads_count'], 0, ',', '.'), 'ok' => true],
                                    ['label' => 'Seguidores', 'value' => number_format($stats['followers_count'], 0, ',', '.'), 'ok' => true],
                                    ['label' => 'Seguindo', 'value' => number_format($stats['following_count'], 0, ',', '.'), 'ok' => true],
                                    ['label' => 'Alvo identificado', 'value' => $stats['has_target'] ? 'Sim' : 'Não', 'ok' => $stats['has_target']],
                                    ['label' => 'IP de registro', 'value' => $stats['has_registration_ip'] ? 'Encontrado' : 'Ausente', 'ok' => $stats['has_registration_ip']],
                                    ['label' => 'Telefone de registro', 'value' => $stats['has_registration_phone'] ? 'Encontrado' : 'Ausente', 'ok' => $stats['has_registration_phone']],
                                    ['label' => 'Última localização', 'value' => $stats['has_last_location'] ? 'Encontrada' : 'Ausente', 'ok' => $stats['has_last_location']],
                                    ['label' => 'Intervalo de datas', 'value' => $stats['has_date_range'] ? 'Presente' : 'Ausente', 'ok' => $stats['has_date_range']],
                                    ['label' => 'Erros fatais de parsing', 'value' => $stats['libxml_fatal_errors'], 'ok' => $stats['libxml_fatal_errors'] === 0],
                                ];
                            @endphp

                            @foreach($qualItems as $item)
                                <div class="rounded-xl border p-4 flex items-start gap-3">
                                    <div class="mt-0.5">
                                        @if($item['ok'])
                                            <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-success-500" />
                                        @else
                                            <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-5 w-5 text-warning-500" />
                                        @endif
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500">{{ $item['label'] }}</div>
                                        <div class="font-semibold text-sm">{{ $item['value'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @endif
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>

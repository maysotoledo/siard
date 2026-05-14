<div class="flex max-h-[90vh] flex-col">
    <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $acesso['accessed_at'] }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $acesso['endpoint'] }} · {{ $acesso['localizacao'] }}</p>
        </div>

        <button
            type="button"
            onclick="this.closest('dialog')?.close()"
            class="inline-flex shrink-0 items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:hover:bg-gray-700"
            aria-label="Fechar modal"
        >
            <span>Fechar</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="overflow-y-auto px-5 py-5">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(280px,.85fr)]">
            <div class="space-y-4">
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Conexão</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">IP público</dt>
                            <dd class="mt-1 break-all font-mono text-gray-950 dark:text-white">{{ $acesso['ip'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Porta lógica</dt>
                            <dd class="mt-1 font-mono text-gray-950 dark:text-white">{{ $acesso['porta'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">IP local</dt>
                            <dd class="mt-1 break-all font-mono text-gray-950 dark:text-white">{{ $acesso['ip_local'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">GMT</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['gmt'] }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">ISP</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['isp'] }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Dispositivo</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Idioma</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['idioma'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Plataforma</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['plataforma'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Resolução</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['resolucao'] }}</dd>
                        </div>
                        <div class="sm:col-span-3">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">User-Agent</dt>
                            <dd class="mt-1 break-words font-mono text-xs text-gray-950 dark:text-white">{{ $acesso['user_agent'] }}</dd>
                        </div>
                        <div class="sm:col-span-3">
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Referer</dt>
                            <dd class="mt-1 break-words text-gray-950 dark:text-white">{{ $acesso['referer'] }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Localização</h3>
                    <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Localização por IP</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['localizacao'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">GPS</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">
                                @if ($acesso['gps_url'])
                                    <a href="{{ $acesso['gps_url'] }}" target="_blank" rel="noopener noreferrer" class="font-medium text-primary-600 underline dark:text-primary-400">
                                        {{ $acesso['gps'] }}
                                    </a>
                                @else
                                    <span title="{{ $acesso['gps_error'] }}">{{ $gpsStatus }}</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Precisão GPS</dt>
                            <dd class="mt-1 text-gray-950 dark:text-white">{{ $acesso['gps_accuracy'] }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="space-y-4">
                @if ($acesso['foto_url'])
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Foto capturada</h3>
                            @if ($acesso['foto_contexto'])
                                <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ $acesso['foto_contexto'] }}
                                </span>
                            @endif
                        </div>
                        <a href="{{ $acesso['foto_url'] }}" target="_blank" rel="noopener" class="mt-3 block">
                            <img src="{{ $acesso['foto_url'] }}" alt="Foto capturada" class="max-h-80 w-full rounded-lg object-contain ring-1 ring-gray-200 dark:ring-gray-800">
                        </a>
                        @if (($acesso['foto_contexto'] ?? null) === 'Foto sem vínculo ao acesso')
                            <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">
                                Esta foto foi capturada sem identificador de acesso. Ela foi exibida no acesso mais recente para não ficar oculta.
                            </p>
                        @endif
                    </div>
                @endif

                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Identidade digital</h3>

                    @if (! $temIdentidade)
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Nenhum dado de identidade registrado neste acesso.</p>
                    @else
                        <div class="mt-3 space-y-4">
                            <dl class="grid grid-cols-1 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Nome</dt>
                                    <dd class="mt-1 break-words text-gray-950 dark:text-white">{{ $acesso['identidade_nome'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">E-mail</dt>
                                    <dd class="mt-1 break-words text-gray-950 dark:text-white">{{ $acesso['identidade_email'] ?: '-' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-gray-500 dark:text-gray-400">Telefone</dt>
                                    <dd class="mt-1 break-words text-gray-950 dark:text-white">{{ $acesso['identidade_telefone'] ?: '-' }}</dd>
                                </div>
                            </dl>

                            @if ($redesLogadas->isNotEmpty())
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Contas detectadas</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($redesLogadas as $item)
                                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                                {{ $item['rede'] }}:
                                                {{ $item['usuario'] ?? 'logado' }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($appsInstalados->isNotEmpty())
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Apps detectados</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($appsInstalados as $item)
                                            <span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                                {{ $item['rede'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="sticky bottom-0 flex justify-end border-t border-gray-200 bg-white px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
        <button
            type="button"
            onclick="this.closest('dialog')?.close()"
            class="rounded-md bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
        >
            Fechar
        </button>
    </div>
</div>

